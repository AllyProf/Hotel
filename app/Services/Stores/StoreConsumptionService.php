<?php

namespace App\Services\Stores;

use App\Models\Hotel;
use App\Models\HotelStoreConsumption;
use App\Models\HotelStoreConsumptionItem;
use App\Models\HotelStoreDepartmentStock;
use App\Models\HotelRoomUnit;
use App\Models\HotelStoreMasterItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StoreConsumptionService
{
    /** @param  array<string, mixed>  $filters */
    public function consumptionsQuery(int $hotelId, array $filters): Builder
    {
        $query = HotelStoreConsumption::query()
            ->where('hotel_id', $hotelId)
            ->with(['department', 'items'])
            ->orderByDesc('created_at');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(function (Builder $q) use ($needle) {
                $q->where('consumption_number', 'like', $needle)
                    ->orWhereHas('department', fn (Builder $d) => $d->where('name', 'like', $needle));
            });
        }

        $from = trim((string) ($filters['from_date'] ?? ''));
        $to = trim((string) ($filters['to_date'] ?? ''));
        if ($from !== '') {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to !== '') {
            $query->whereDate('created_at', '<=', $to);
        }

        $departmentId = (int) ($filters['department_id'] ?? 0);
        $departmentIds = $filters['department_ids'] ?? null;
        if (is_array($departmentIds) && $departmentIds !== []) {
            $query->whereIn('hotel_department_id', $departmentIds);
        }
        if ($departmentId > 0) {
            $query->where('hotel_department_id', $departmentId);
        }

        return $query;
    }

    /** @return list<array<string, mixed>> */
    public function departmentStockCatalog(Hotel $hotel, int $departmentId): array
    {
        if ($departmentId <= 0) {
            return [];
        }

        return HotelStoreDepartmentStock::query()
            ->where('hotel_id', $hotel->id)
            ->where('hotel_department_id', $departmentId)
            ->where('quantity', '>', 0)
            ->with(['masterItem.storePackage', 'masterItem.sellOptions.storePackage', 'masterItem.category'])
            ->get()
            ->map(function (HotelStoreDepartmentStock $row) {
                $item = $row->masterItem;

                return [
                    'id' => (int) $item?->id,
                    'name' => $item?->name ?? '—',
                    'stock' => (float) $row->quantity,
                    'unit' => $item ? $item->consumptionUnitLabel() : 'units',
                    'category_id' => (int) ($item?->hotel_store_category_id ?? 0),
                ];
            })
            ->filter(fn (array $row) => $row['id'] > 0)
            ->values()
            ->all();
    }

    /** @param  list<array<string, mixed>>  $lines */
    public function createConsumption(Hotel $hotel, int $departmentId, array $lines): HotelStoreConsumption
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Add at least one item to the consumption.');
        }

        $this->assertDepartmentStock($hotel, $departmentId, $lines);

        return DB::transaction(function () use ($hotel, $departmentId, $lines) {
            $start = (int) config('hotel_stores.consumption_number_start', 10500);
            $maxNumeric = HotelStoreConsumption::query()
                ->where('hotel_id', $hotel->id)
                ->pluck('consumption_number')
                ->map(fn ($value) => preg_match('/^CON-(\d+)$/', (string) $value, $m) ? (int) $m[1] : 0)
                ->max();
            $consumptionNumber = 'CON-'.max($start, (int) $maxNumeric + 1);

            $consumption = HotelStoreConsumption::query()->create([
                'hotel_id' => $hotel->id,
                'hotel_department_id' => $departmentId,
                'created_by' => auth()->id(),
                'consumption_number' => $consumptionNumber,
            ]);

            foreach ($lines as $line) {
                HotelStoreConsumptionItem::query()->create([
                    'hotel_store_consumption_id' => $consumption->id,
                    ...$this->normalizeConsumptionLine($hotel, $line),
                ]);

                $this->deductDepartmentStock(
                    $hotel,
                    $departmentId,
                    (int) $line['hotel_store_master_item_id'],
                    (float) $line['quantity']
                );
            }

            return $consumption->fresh(['department', 'items']);
        });
    }

    /** @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function normalizeConsumptionLine(Hotel $hotel, array $line): array
    {
        $distribution = $line['room_distribution'] ?? null;
        if (! is_array($distribution)) {
            $all = filter_var($line['room_all'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $unitIds = $line['room_unit_ids'] ?? [];
            if (! is_array($unitIds)) {
                $unitIds = [];
            }
            $distribution = [
                'all' => $all,
                'unit_ids' => array_values(array_filter(array_map('intval', $unitIds))),
            ];
        }

        $allRooms = ! empty($distribution['all']);
        $unitIds = array_values(array_unique(array_filter(array_map(
            'intval',
            is_array($distribution['unit_ids'] ?? null) ? $distribution['unit_ids'] : []
        ))));

        if ($allRooms) {
            $unitIds = HotelRoomUnit::query()
                ->where('hotel_id', $hotel->id)
                ->where('status', '!=', HotelRoomUnit::STATUS_INACTIVE)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $line['room_distribution'] = ['all' => true, 'unit_ids' => $unitIds];
            $line['hotel_room_unit_id'] = null;
            $line['room_no'] = null;

            return $line;
        }

        if ($unitIds !== []) {
            $validIds = HotelRoomUnit::query()
                ->where('hotel_id', $hotel->id)
                ->whereIn('id', $unitIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $line['room_distribution'] = ['all' => false, 'unit_ids' => $validIds];
            $first = $validIds[0] ?? null;
            if ($first) {
                $unit = HotelRoomUnit::query()->whereKey($first)->first();
                $line['hotel_room_unit_id'] = $first;
                $line['room_no'] = $unit?->room_number;
            } else {
                $line['hotel_room_unit_id'] = null;
                $line['room_no'] = null;
            }

            return $line;
        }

        $roomUnitId = (int) ($line['hotel_room_unit_id'] ?? 0);
        if ($roomUnitId > 0) {
            $unit = HotelRoomUnit::query()
                ->where('hotel_id', $hotel->id)
                ->whereKey($roomUnitId)
                ->first();
            if ($unit) {
                $line['room_distribution'] = ['all' => false, 'unit_ids' => [$unit->id]];
                $line['hotel_room_unit_id'] = $unit->id;
                $line['room_no'] = $unit->room_number;

                return $line;
            }
        }

        $line['room_distribution'] = null;
        $line['hotel_room_unit_id'] = null;
        $line['room_no'] = trim((string) ($line['room_no'] ?? '')) ?: null;

        return $line;
    }

    /** @param  list<array<string, mixed>>  $lines */
    private function assertDepartmentStock(Hotel $hotel, int $departmentId, array $lines): void
    {
        /** @var array<int, float> $demand */
        $demand = [];
        foreach ($lines as $line) {
            $masterId = (int) ($line['hotel_store_master_item_id'] ?? 0);
            if ($masterId <= 0) {
                throw new InvalidArgumentException('Each line must be linked to a store item.');
            }
            $demand[$masterId] = ($demand[$masterId] ?? 0) + (float) ($line['quantity'] ?? 0);
        }

        foreach ($demand as $masterId => $requested) {
            $stockRow = HotelStoreDepartmentStock::query()
                ->where('hotel_id', $hotel->id)
                ->where('hotel_department_id', $departmentId)
                ->where('hotel_store_master_item_id', $masterId)
                ->first();

            $available = (float) ($stockRow?->quantity ?? 0);
            if ($requested > $available) {
                $master = HotelStoreMasterItem::query()->whereKey($masterId)->first();
                $name = $master?->name ?? 'Item';
                throw new InvalidArgumentException(
                    'Insufficient department stock for "'.$name.'". Available: '.$available.', requested: '.$requested.'.'
                );
            }
        }
    }

    private function deductDepartmentStock(Hotel $hotel, int $departmentId, int $masterId, float $qty): void
    {
        $row = HotelStoreDepartmentStock::query()
            ->where('hotel_id', $hotel->id)
            ->where('hotel_department_id', $departmentId)
            ->where('hotel_store_master_item_id', $masterId)
            ->first();

        if ($row === null) {
            return;
        }

        $next = round((float) $row->quantity - $qty, 2);
        if ($next <= 0) {
            $row->delete();
        } else {
            $row->update(['quantity' => $next]);
        }
    }
}
