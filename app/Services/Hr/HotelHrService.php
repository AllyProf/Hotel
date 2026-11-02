<?php

namespace App\Services\Hr;

use App\Models\Hotel;
use App\Models\HotelAttendanceEntry;
use App\Models\HotelDepartment;
use App\Models\HotelPmsService;
use App\Models\User;
use App\Services\HotelRoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class HotelHrService
{
    public function __construct(
        private HotelRoleService $roles,
    ) {}

    /** @return array<string, mixed> */
    public function uiConfig(): array
    {
        return config('hotel_hr', []);
    }

    public function ensureDefaults(Hotel $hotel): void
    {
        $this->roles->ensureDefaults($hotel);

        if (HotelDepartment::query()->where('hotel_id', $hotel->id)->exists()) {
            return;
        }

        foreach (config('hotel_hr.default_departments', []) as $name) {
            HotelDepartment::query()->create([
                'hotel_id' => $hotel->id,
                'name' => $name,
                'is_active' => true,
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function filtersFromRequest(Request $request): array
    {
        $tabs = array_keys($this->uiConfig()['tabs'] ?? []);
        $tab = (string) $request->input('tab', 'team');
        if (! in_array($tab, $tabs, true)) {
            $tab = 'team';
        }

        $workDate = $request->input('work_date', now()->format('Y-m-d'));

        return [
            'tab' => $tab,
            'search' => trim((string) $request->input('search', '')),
            'work_date' => $workDate,
        ];
    }

    /** @return array{active: int, inactive: int, departments: int} */
    public function teamStats(Hotel $hotel): array
    {
        $base = User::query()
            ->where('hotel_id', $hotel->id)
            ->where('role', User::ROLE_HOTEL_STAFF);

        return [
            'active' => (clone $base)->where('is_active', true)->count(),
            'inactive' => (clone $base)->where('is_active', false)->count(),
            'departments' => HotelDepartment::query()->where('hotel_id', $hotel->id)->where('is_active', true)->count(),
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function staffQuery(Hotel $hotel, array $filters)
    {
        $query = User::query()
            ->where('hotel_id', $hotel->id)
            ->where('role', User::ROLE_HOTEL_STAFF)
            ->with(['hotelRole', 'branch', 'department'])
            ->orderBy('name');

        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            });
        }

        return $query;
    }

    /** @return Collection<int, HotelDepartment> */
    public function departments(Hotel $hotel): Collection
    {
        return HotelDepartment::query()
            ->where('hotel_id', $hotel->id)
            ->withCount('staff')
            ->orderBy('name')
            ->get();
    }

    public function createDepartment(Hotel $hotel, string $name): HotelDepartment
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Department name is required.');
        }

        return HotelDepartment::query()->create([
            'hotel_id' => $hotel->id,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    public function updateDepartment(Hotel $hotel, HotelDepartment $department, array $data): void
    {
        $this->assertOwns($hotel, (int) $department->hotel_id);

        $showInPos = ! empty($data['show_in_pos']);
        $serviceId = $showInPos ? (int) ($data['hotel_pms_service_id'] ?? 0) : 0;
        if ($showInPos) {
            if ($serviceId <= 0) {
                throw new InvalidArgumentException('Select a PMS catalog service for POS.');
            }
            $service = HotelPmsService::query()
                ->where('hotel_id', $hotel->id)
                ->whereKey($serviceId)
                ->first();
            if (! $service || ! $service->is_active || ! $service->showsCatalog()) {
                throw new InvalidArgumentException('Choose an active PMS service with a sellable item catalog.');
            }
        } else {
            $serviceId = null;
        }

        $department->update([
            'name' => trim((string) ($data['name'] ?? $department->name)),
            'is_active' => ! empty($data['is_active']),
            'show_in_pos' => $showInPos,
            'hotel_pms_service_id' => $serviceId,
        ]);
    }

    public function deleteDepartment(Hotel $hotel, HotelDepartment $department): void
    {
        $this->assertOwns($hotel, (int) $department->hotel_id);

        if ($department->staff()->exists()) {
            throw new InvalidArgumentException('Move staff to another department before deleting.');
        }

        $department->delete();
    }

    /** @return list<array{user: User, entry: ?HotelAttendanceEntry}> */
    public function attendanceSheet(Hotel $hotel, string $workDate): array
    {
        $staff = User::query()
            ->where('hotel_id', $hotel->id)
            ->where('role', User::ROLE_HOTEL_STAFF)
            ->where('is_active', true)
            ->with('department')
            ->orderBy('name')
            ->get();

        $entries = HotelAttendanceEntry::query()
            ->where('hotel_id', $hotel->id)
            ->whereDate('work_date', $workDate)
            ->get()
            ->keyBy('user_id');

        return $staff->map(fn (User $user) => [
            'user' => $user,
            'entry' => $entries->get($user->id),
        ])->all();
    }

    public function saveAttendance(Hotel $hotel, int $userId, string $workDate, string $status, ?string $notes = null): void
    {
        $allowed = array_keys(config('hotel_hr.attendance_statuses', []));
        if (! in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Invalid attendance status.');
        }

        $staff = User::query()
            ->where('hotel_id', $hotel->id)
            ->where('role', User::ROLE_HOTEL_STAFF)
            ->findOrFail($userId);

        HotelAttendanceEntry::query()->updateOrCreate(
            [
                'hotel_id' => $hotel->id,
                'user_id' => $staff->id,
                'work_date' => $workDate,
            ],
            [
                'status' => $status,
                'notes' => trim((string) $notes) ?: null,
                'marked_by' => auth()->id(),
            ]
        );
    }

    private function assertOwns(Hotel $hotel, int $ownerHotelId): void
    {
        if ((int) $hotel->id !== $ownerHotelId) {
            throw new InvalidArgumentException('Record not found.');
        }
    }
}
