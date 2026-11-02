<?php

namespace App\Services\Erp;

use App\Models\Hotel;
use App\Models\HotelHousekeepingTask;
use App\Models\HotelLostFoundItem;
use App\Models\HotelMaintenanceOrder;
use App\Models\HotelRoomUnit;
use App\Models\User;
use App\Services\HotelLogService;
use App\Services\RoomViewService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HousekeepingMaintenanceService
{
    public function __construct(
        private RoomViewService $roomView,
        private HotelLogService $logs,
    ) {}

    /** @return array<string, mixed> */
    public function uiConfig(): array
    {
        return config('hotel_erp_hk', []);
    }

    /** @return list<array{value: int, label: string}> */
    public function staffOptions(Hotel $hotel): array
    {
        return User::query()
            ->where('hotel_id', $hotel->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => ['value' => $user->id, 'label' => $user->name ?: 'User #'.$user->id])
            ->all();
    }

    /** @return list<array{value: int, label: string, room_type: string}> */
    public function roomUnitOptions(Hotel $hotel): array
    {
        return HotelRoomUnit::query()
            ->where('hotel_id', $hotel->id)
            ->with('roomType')
            ->orderBy('room_number')
            ->get()
            ->map(fn (HotelRoomUnit $unit) => [
                'value' => $unit->id,
                'label' => $unit->displayLabel(),
                'consumption_label' => $unit->consumptionLabel(),
                'room_type' => $unit->roomType?->display_name ?: $unit->roomType?->name ?: '—',
            ])
            ->all();
    }

    public function syncDirtyRoomTasks(Hotel $hotel): void
    {
        $dirtyUnits = HotelRoomUnit::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', HotelRoomUnit::STATUS_DIRTY)
            ->with('roomType')
            ->get();

        foreach ($dirtyUnits as $unit) {
            $hasOpen = HotelHousekeepingTask::query()
                ->where('hotel_id', $hotel->id)
                ->where('hotel_room_unit_id', $unit->id)
                ->where('status', '!=', HotelHousekeepingTask::STATUS_COMPLETED)
                ->exists();

            if ($hasOpen) {
                continue;
            }

            HotelHousekeepingTask::query()->create([
                'hotel_id' => $hotel->id,
                'hotel_room_unit_id' => $unit->id,
                'room_no' => $unit->displayLabel(),
                'room_type_name' => $unit->roomType?->display_name ?: $unit->roomType?->name,
                'task_type' => 'checkout_clean',
                'status' => HotelHousekeepingTask::STATUS_PENDING,
                'priority' => 'normal',
                'due_at' => now(),
                'created_by' => auth()->id(),
            ]);
        }
    }

    /** @return array{stats: array<string, int>, tasks: Collection<int, HotelHousekeepingTask>} */
    public function housekeepingDashboard(Hotel $hotel, string $filter): array
    {
        $this->syncDirtyRoomTasks($hotel);

        $query = HotelHousekeepingTask::query()
            ->where('hotel_id', $hotel->id)
            ->with(['assignee', 'roomUnit.roomType'])
            ->orderByDesc('updated_at')
            ->orderBy('due_at')
            ->orderBy('id');

        if ($filter === 'open') {
            $query->where('status', '!=', HotelHousekeepingTask::STATUS_COMPLETED);
        } elseif ($filter === 'done') {
            $query->where('status', HotelHousekeepingTask::STATUS_COMPLETED);
        }

        $tasks = $query->limit(200)->get();

        $stats = [
            'pending' => HotelHousekeepingTask::query()
                ->where('hotel_id', $hotel->id)
                ->where('status', HotelHousekeepingTask::STATUS_PENDING)
                ->count(),
            'in_progress' => HotelHousekeepingTask::query()
                ->where('hotel_id', $hotel->id)
                ->where('status', HotelHousekeepingTask::STATUS_IN_PROGRESS)
                ->count(),
            'dirty_rooms' => HotelRoomUnit::query()
                ->where('hotel_id', $hotel->id)
                ->where('status', HotelRoomUnit::STATUS_DIRTY)
                ->count(),
            'cleaned_rooms' => HotelRoomUnit::query()
                ->where('hotel_id', $hotel->id)
                ->where('status', HotelRoomUnit::STATUS_CLEANED)
                ->count(),
        ];

        return ['stats' => $stats, 'tasks' => $tasks];
    }

    public function startHousekeepingTask(Hotel $hotel, HotelHousekeepingTask $task, ?int $assignedTo): void
    {
        $this->assertHotelOwns($hotel, $task->hotel_id);

        if ($task->status === HotelHousekeepingTask::STATUS_COMPLETED) {
            throw new \InvalidArgumentException('Task is already completed.');
        }

        $task->update([
            'status' => HotelHousekeepingTask::STATUS_IN_PROGRESS,
            'assigned_to' => $assignedTo ?: $task->assigned_to ?: auth()->id(),
            'started_at' => $task->started_at ?? now(),
        ]);

        $this->logs->record($hotel, [
            'action_type' => 'HK task started',
            'room_no' => $task->room_no,
            'details' => $task->taskTypeLabel().' — '.$task->room_no,
        ]);
    }

    public function completeHousekeepingTask(Hotel $hotel, HotelHousekeepingTask $task): void
    {
        $this->assertHotelOwns($hotel, $task->hotel_id);

        DB::transaction(function () use ($hotel, $task) {
            $task->update([
                'status' => HotelHousekeepingTask::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            $unit = $task->roomUnit;
            if ($unit && $unit->status === HotelRoomUnit::STATUS_DIRTY) {
                $this->roomView->markUnitCleaned($hotel, $unit->id);
            }
        });

        $this->logs->record($hotel, [
            'action_type' => 'HK task completed',
            'room_no' => $task->room_no,
            'details' => 'Room marked cleaned — '.$task->room_no,
        ]);
    }

    /** @return array{stats: array<string, int>, orders: Collection<int, HotelMaintenanceOrder>} */
    public function maintenanceDashboard(Hotel $hotel, string $filter): array
    {
        $query = HotelMaintenanceOrder::query()
            ->where('hotel_id', $hotel->id)
            ->with(['assignee', 'roomUnit.roomType'])
            ->orderByDesc('updated_at')
            ->orderByDesc('opened_at');

        if ($filter === 'open') {
            $query->whereNotIn('status', [
                HotelMaintenanceOrder::STATUS_COMPLETED,
                HotelMaintenanceOrder::STATUS_CANCELLED,
            ]);
        } elseif ($filter === 'done') {
            $query->whereIn('status', [
                HotelMaintenanceOrder::STATUS_COMPLETED,
                HotelMaintenanceOrder::STATUS_CANCELLED,
            ]);
        }

        $orders = $query->limit(200)->get();

        $stats = [
            'open' => HotelMaintenanceOrder::query()
                ->where('hotel_id', $hotel->id)
                ->whereNotIn('status', [
                    HotelMaintenanceOrder::STATUS_COMPLETED,
                    HotelMaintenanceOrder::STATUS_CANCELLED,
                ])
                ->count(),
            'blocked_rooms' => HotelRoomUnit::query()
                ->where('hotel_id', $hotel->id)
                ->where('status', HotelRoomUnit::STATUS_MAINTENANCE)
                ->count(),
        ];

        return ['stats' => $stats, 'orders' => $orders];
    }

    /** @param  array<string, mixed>  $data */
    public function createMaintenanceOrder(Hotel $hotel, array $data): HotelMaintenanceOrder
    {
        $unitId = ! empty($data['hotel_room_unit_id']) ? (int) $data['hotel_room_unit_id'] : null;
        $unit = $unitId
            ? HotelRoomUnit::query()->where('hotel_id', $hotel->id)->findOrFail($unitId)
            : null;

        $blocksRoom = ! empty($data['blocks_room']);

        return DB::transaction(function () use ($hotel, $data, $unit, $blocksRoom) {
            $order = HotelMaintenanceOrder::query()->create([
                'hotel_id' => $hotel->id,
                'hotel_room_unit_id' => $unit?->id,
                'room_no' => $unit?->displayLabel() ?: trim((string) ($data['room_no'] ?? '')),
                'title' => trim((string) $data['title']),
                'description' => trim((string) ($data['description'] ?? '')) ?: null,
                'category' => $data['category'] ?? 'general',
                'status' => HotelMaintenanceOrder::STATUS_OPEN,
                'priority' => $data['priority'] ?? 'normal',
                'blocks_room' => $blocksRoom,
                'assigned_to' => ! empty($data['assigned_to']) ? (int) $data['assigned_to'] : null,
                'opened_at' => now(),
                'created_by' => auth()->id(),
            ]);

            if ($blocksRoom && $unit) {
                $unit->update(['status' => HotelRoomUnit::STATUS_MAINTENANCE]);
            }

            $this->logs->record($hotel, [
                'action_type' => 'Maintenance work order',
                'category' => \App\Models\HotelLog::CATEGORY_OUT_OF_ORDER,
                'room_no' => $order->room_no,
                'hotel_room_id' => $unit?->hotel_room_id,
                'details' => $order->title,
            ]);

            return $order;
        });
    }

    public function updateMaintenanceStatus(Hotel $hotel, HotelMaintenanceOrder $order, string $status): void
    {
        $this->assertHotelOwns($hotel, $order->hotel_id);

        $allowed = array_keys(config('hotel_erp_hk.maintenance.statuses', []));
        if (! in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid status.');
        }

        $order->update(['status' => $status]);

        if (in_array($status, [HotelMaintenanceOrder::STATUS_COMPLETED, HotelMaintenanceOrder::STATUS_CANCELLED], true)) {
            $this->releaseRoomIfBlocked($hotel, $order, $status);
        }
    }

    public function completeMaintenanceOrder(Hotel $hotel, HotelMaintenanceOrder $order): void
    {
        $this->updateMaintenanceStatus($hotel, $order, HotelMaintenanceOrder::STATUS_COMPLETED);
    }

    /** @return array{stats: array<string, int>, groups: list<array<string, mixed>>} */
    public function roomBoard(Hotel $hotel): array
    {
        $units = HotelRoomUnit::query()
            ->where('hotel_id', $hotel->id)
            ->with('roomType')
            ->orderBy('room_number')
            ->get();

        $stats = [
            'available' => 0,
            'dirty' => 0,
            'cleaned' => 0,
            'maintenance' => 0,
            'inactive' => 0,
        ];

        foreach ($units as $unit) {
            $key = $unit->status ?: HotelRoomUnit::STATUS_AVAILABLE;
            if (isset($stats[$key])) {
                $stats[$key]++;
            }
        }

        $grouped = $units->groupBy(fn (HotelRoomUnit $unit) => $unit->roomType?->display_name ?: $unit->roomType?->name ?: 'Other');

        $groups = [];
        foreach ($grouped as $typeName => $typeUnits) {
            $groups[] = [
                'name' => $typeName,
                'units' => $typeUnits->map(fn (HotelRoomUnit $unit) => [
                    'id' => $unit->id,
                    'label' => $unit->displayLabel(),
                    'status' => $unit->status ?: HotelRoomUnit::STATUS_AVAILABLE,
                    'status_label' => config('hotel_erp_hk.room_status_labels.'.$unit->status, ucfirst($unit->status)),
                ])->values()->all(),
            ];
        }

        return ['stats' => $stats, 'groups' => $groups];
    }

    public function setRoomStatus(Hotel $hotel, int $unitId, string $status): void
    {
        $allowed = [
            HotelRoomUnit::STATUS_AVAILABLE,
            HotelRoomUnit::STATUS_DIRTY,
            HotelRoomUnit::STATUS_CLEANED,
            HotelRoomUnit::STATUS_MAINTENANCE,
        ];

        if (! in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid room status.');
        }

        $unit = HotelRoomUnit::query()
            ->where('hotel_id', $hotel->id)
            ->findOrFail($unitId);

        $unit->update(['status' => $status]);

        if ($status === HotelRoomUnit::STATUS_DIRTY) {
            $this->syncDirtyRoomTasks($hotel);
        }

        $this->logs->record($hotel, [
            'action_type' => 'Room status updated',
            'room_no' => $unit->displayLabel(),
            'details' => 'Status set to '.config('hotel_erp_hk.room_status_labels.'.$status, $status),
        ]);
    }

    private function releaseRoomIfBlocked(Hotel $hotel, HotelMaintenanceOrder $order, string $finalStatus): void
    {
        if (! $order->blocks_room || ! $order->hotel_room_unit_id) {
            $order->update(['completed_at' => now()]);

            return;
        }

        DB::transaction(function () use ($hotel, $order) {
            $unit = HotelRoomUnit::query()
                ->where('hotel_id', $hotel->id)
                ->find($order->hotel_room_unit_id);

            if ($unit && $unit->status === HotelRoomUnit::STATUS_MAINTENANCE) {
                $unit->update(['status' => HotelRoomUnit::STATUS_AVAILABLE]);
            }

            $order->update(['completed_at' => now()]);
        });

        $this->logs->record($hotel, [
            'action_type' => 'Maintenance closed',
            'room_no' => $order->room_no,
            'details' => $order->title.' — '.$finalStatus,
        ]);
    }

    /** @return Collection<int, HotelLostFoundItem> */
    public function lostFoundItems(Hotel $hotel, string $filter): Collection
    {
        $query = HotelLostFoundItem::query()
            ->where('hotel_id', $hotel->id)
            ->with(['roomUnit.roomType', 'creator'])
            ->orderByDesc('logged_at')
            ->orderByDesc('id');

        if ($filter === 'open') {
            $query->where('status', HotelLostFoundItem::STATUS_STORED);
        } elseif ($filter === 'done') {
            $query->whereIn('status', [
                HotelLostFoundItem::STATUS_CLAIMED,
                HotelLostFoundItem::STATUS_DISPOSED,
            ]);
        }

        return $query->limit(300)->get();
    }

    public function lostFoundOpenCount(Hotel $hotel): int
    {
        return HotelLostFoundItem::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', HotelLostFoundItem::STATUS_STORED)
            ->count();
    }

    /** @param  array<string, mixed>  $data */
    public function createLostFoundItem(Hotel $hotel, array $data): HotelLostFoundItem
    {
        $unitId = ! empty($data['hotel_room_unit_id']) ? (int) $data['hotel_room_unit_id'] : null;
        $unit = $unitId
            ? HotelRoomUnit::query()->where('hotel_id', $hotel->id)->find($unitId)
            : null;

        $item = HotelLostFoundItem::query()->create([
            'hotel_id' => $hotel->id,
            'reference_no' => $this->nextLostFoundReference($hotel),
            'entry_type' => $data['entry_type'] ?? HotelLostFoundItem::TYPE_FOUND,
            'item_name' => trim((string) $data['item_name']),
            'category' => $data['category'] ?? 'other',
            'hotel_room_unit_id' => $unit?->id,
            'room_no' => $unit?->displayLabel() ?: trim((string) ($data['room_no'] ?? '')) ?: null,
            'location' => trim((string) ($data['location'] ?? '')) ?: null,
            'storage_location' => trim((string) ($data['storage_location'] ?? '')) ?: null,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'photo_path' => $data['photo_path'] ?? null,
            'status' => HotelLostFoundItem::STATUS_STORED,
            'logged_at' => now(),
            'created_by' => auth()->id(),
        ]);

        $this->logs->record($hotel, [
            'action_type' => 'Lost & Found logged',
            'room_no' => $item->room_no,
            'details' => $item->reference_no.' — '.$item->item_name,
        ]);

        return $item;
    }

    public function updateLostFoundStatus(Hotel $hotel, HotelLostFoundItem $item, string $status, array $extra = []): void
    {
        $this->assertHotelOwns($hotel, $item->hotel_id);

        $allowed = array_keys(config('hotel_erp_hk.lost_found.statuses', []));
        if (! in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid status.');
        }

        $updates = ['status' => $status];

        if (in_array($status, [HotelLostFoundItem::STATUS_CLAIMED, HotelLostFoundItem::STATUS_DISPOSED], true)) {
            $updates['claimed_at'] = now();
            $updates['claimed_by_name'] = trim((string) ($extra['claimed_by_name'] ?? '')) ?: null;
            $updates['closure_notes'] = trim((string) ($extra['closure_notes'] ?? '')) ?: null;
        }

        $item->update($updates);

        $this->logs->record($hotel, [
            'action_type' => 'Lost & Found updated',
            'room_no' => $item->room_no,
            'details' => $item->reference_no.' — '.$item->statusLabel(),
        ]);
    }

    private function nextLostFoundReference(Hotel $hotel): string
    {
        $prefix = 'LF-'.now()->format('ymd').'-';
        $last = HotelLostFoundItem::query()
            ->where('hotel_id', $hotel->id)
            ->where('reference_no', 'like', $prefix.'%')
            ->orderByDesc('reference_no')
            ->value('reference_no');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $matches)) {
            $seq = (int) $matches[1] + 1;
        }

        return $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    private function assertHotelOwns(Hotel $hotel, int $ownerHotelId): void
    {
        if ($hotel->id !== $ownerHotelId) {
            abort(404);
        }
    }
}
