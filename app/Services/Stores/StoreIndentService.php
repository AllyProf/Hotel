<?php

namespace App\Services\Stores;

use App\Models\Hotel;
use App\Models\HotelStoreIndent;
use App\Models\HotelStoreIndentItem;
use App\Models\HotelStoreMasterItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StoreIndentService
{
    public function __construct(
        private StoreDepartmentStockService $departmentStock,
    ) {}
    /** @param  array<string, mixed>  $filters */
    public function indentsQuery(int $hotelId, array $filters): Builder
    {
        $query = HotelStoreIndent::query()
            ->where('hotel_id', $hotelId)
            ->with(['department', 'items'])
            ->orderByDesc('created_at');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(function (Builder $q) use ($needle) {
                $q->where('indent_number', 'like', $needle)
                    ->orWhereHas('department', fn (Builder $d) => $d->where('name', 'like', $needle))
                    ->orWhereHas('storeCategory', fn (Builder $c) => $c->where('name', 'like', $needle));
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

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $departmentIds = $filters['department_ids'] ?? null;
        if (is_array($departmentIds) && $departmentIds !== []) {
            $query->whereIn('hotel_department_id', $departmentIds);
        } elseif (isset($filters['department_id']) && (int) $filters['department_id'] > 0) {
            $query->where('hotel_department_id', (int) $filters['department_id']);
        }

        return $query;
    }

    public function pendingApprovalCount(int $hotelId): int
    {
        return HotelStoreIndent::query()
            ->where('hotel_id', $hotelId)
            ->where('status', HotelStoreIndent::STATUS_APPLIED)
            ->count();
    }

    /** @param  array<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function linesFromRequestRows(Hotel $hotel, array $rows): array
    {
        return collect($rows)->map(function (array $item) use ($hotel) {
            $masterId = (int) ($item['master_item_id'] ?? 0);
            $quantity = (float) $item['quantity'];
            $preTax = 0.0;

            if ($masterId > 0) {
                $master = HotelStoreMasterItem::query()
                    ->where('hotel_id', $hotel->id)
                    ->whereKey($masterId)
                    ->first();
                if ($master) {
                    $preTax = round($quantity * (float) $master->rate, 2);
                }
            }

            return [
                'hotel_store_master_item_id' => $masterId > 0 ? $masterId : null,
                'name' => $item['name'],
                'quantity' => $quantity,
                'unit' => trim((string) ($item['unit'] ?? '')) ?: null,
                'pre_tax' => $preTax,
                'comments' => trim((string) ($item['comments'] ?? '')) ?: null,
            ];
        })->all();
    }

    /** @param  list<array<string, mixed>>  $lines */
    public function createIndent(Hotel $hotel, int $departmentId, array $lines, ?int $storeCategoryId = null): HotelStoreIndent
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Add at least one item to the indent.');
        }

        $this->assertSufficientStock($hotel, $lines);

        return DB::transaction(function () use ($hotel, $departmentId, $storeCategoryId, $lines) {
            $start = (int) config('hotel_stores.indent_number_start', 10400);
            $maxNumeric = HotelStoreIndent::query()
                ->where('hotel_id', $hotel->id)
                ->pluck('indent_number')
                ->map(fn ($value) => preg_match('/^IND-(\d+)$/', (string) $value, $m) ? (int) $m[1] : 0)
                ->max();
            $indentNumber = 'IND-'.max($start, (int) $maxNumeric + 1);

            $indent = HotelStoreIndent::query()->create([
                'hotel_id' => $hotel->id,
                'hotel_department_id' => $departmentId,
                'hotel_store_category_id' => $storeCategoryId > 0 ? $storeCategoryId : null,
                'created_by' => auth()->id(),
                'indent_number' => $indentNumber,
                'pre_tax' => collect($lines)->sum('pre_tax'),
                'status' => HotelStoreIndent::STATUS_APPLIED,
            ]);

            foreach ($lines as $line) {
                HotelStoreIndentItem::query()->create([
                    'hotel_store_indent_id' => $indent->id,
                    ...$line,
                ]);
            }

            return $indent->fresh(['department', 'items']);
        });
    }

    /** @param  list<array<string, mixed>>  $lines */
    public function updateIndent(Hotel $hotel, HotelStoreIndent $indent, int $departmentId, array $lines, ?int $storeCategoryId = null): HotelStoreIndent
    {
        abort_unless((int) $indent->hotel_id === (int) $hotel->id, 404);

        if (! $indent->isApplied()) {
            throw new InvalidArgumentException('Only applied indents can be edited.');
        }

        if ($lines === []) {
            throw new InvalidArgumentException('Add at least one item to the indent.');
        }

        $this->assertSufficientStock($hotel, $lines);

        return DB::transaction(function () use ($indent, $departmentId, $storeCategoryId, $lines) {
            $indent->update([
                'hotel_department_id' => $departmentId,
                'hotel_store_category_id' => $storeCategoryId > 0 ? $storeCategoryId : null,
                'pre_tax' => collect($lines)->sum('pre_tax'),
            ]);

            $indent->items()->delete();

            foreach ($lines as $line) {
                HotelStoreIndentItem::query()->create([
                    'hotel_store_indent_id' => $indent->id,
                    ...$line,
                ]);
            }

            return $indent->fresh(['department', 'items']);
        });
    }

    public function deleteIndent(Hotel $hotel, HotelStoreIndent $indent): void
    {
        abort_unless((int) $indent->hotel_id === (int) $hotel->id, 404);

        if (! $indent->isApplied()) {
            throw new InvalidArgumentException('Only applied indents can be deleted.');
        }

        $indent->items()->delete();
        $indent->delete();
    }

    public function approveIndent(Hotel $hotel, HotelStoreIndent $indent): HotelStoreIndent
    {
        abort_unless((int) $indent->hotel_id === (int) $hotel->id, 404);

        if (! $indent->isApplied()) {
            throw new InvalidArgumentException('Only applied indents can be approved.');
        }

        $indent->loadMissing('items');

        return DB::transaction(function () use ($hotel, $indent) {
            foreach ($indent->items as $line) {
                $this->issueStockForLine($hotel, $line);
            }

            $indent->update([
                'status' => HotelStoreIndent::STATUS_ISSUED,
                'received_at' => null,
                'received_by' => null,
            ]);

            return $indent->fresh(['department', 'items']);
        });
    }

    public function receiveIndent(Hotel $hotel, HotelStoreIndent $indent): HotelStoreIndent
    {
        abort_unless((int) $indent->hotel_id === (int) $hotel->id, 404);

        if (! $indent->isIssued()) {
            throw new InvalidArgumentException('Only issued indents can be received into department stock.');
        }

        $indent->loadMissing('items');

        return DB::transaction(function () use ($hotel, $indent) {
            $this->departmentStock->receiveIndentIntoDepartment($hotel, $indent);

            $indent->update([
                'status' => HotelStoreIndent::STATUS_RECEIVED,
                'received_at' => now(),
                'received_by' => auth()->id(),
            ]);

            return $indent->fresh(['department', 'items', 'receivedByUser']);
        });
    }

    public function pendingDepartmentReceiveCount(int $hotelId, array $departmentIds = []): int
    {
        $query = HotelStoreIndent::query()
            ->where('hotel_id', $hotelId)
            ->where('status', HotelStoreIndent::STATUS_ISSUED);

        if ($departmentIds !== []) {
            $query->whereIn('hotel_department_id', $departmentIds);
        }

        return $query->count();
    }

    private function issueStockForLine(Hotel $hotel, HotelStoreIndentItem $line): void
    {
        $qty = (float) $line->quantity;
        if ($qty <= 0) {
            return;
        }

        $master = $this->resolveMasterItem($hotel, $line);
        if ($master === null || ! $master->inventoried) {
            return;
        }

        $available = (float) $master->stock;
        if ($available < $qty) {
            throw new InvalidArgumentException(
                'Insufficient stock for "'.$master->name.'". Available: '.$available.', requested: '.$qty.'.'
            );
        }

        $master->update([
            'stock' => round($available - $qty, 2),
        ]);

        if (! $line->hotel_store_master_item_id) {
            $line->update(['hotel_store_master_item_id' => $master->id]);
        }
    }

    private function resolveMasterItem(Hotel $hotel, HotelStoreIndentItem $line): ?HotelStoreMasterItem
    {
        if ($line->hotel_store_master_item_id) {
            return HotelStoreMasterItem::query()
                ->where('hotel_id', $hotel->id)
                ->whereKey($line->hotel_store_master_item_id)
                ->first();
        }

        return HotelStoreMasterItem::query()
            ->where('hotel_id', $hotel->id)
            ->where('name', $line->name)
            ->orderByDesc('inventoried')
            ->first();
    }

    /** @param  list<array<string, mixed>>  $lines */
    private function assertSufficientStock(Hotel $hotel, array $lines): void
    {
        /** @var array<int, float> $demand */
        $demand = [];
        foreach ($lines as $line) {
            $masterId = (int) ($line['hotel_store_master_item_id'] ?? 0);
            if ($masterId <= 0) {
                continue;
            }
            $demand[$masterId] = ($demand[$masterId] ?? 0) + (float) ($line['quantity'] ?? 0);
        }

        foreach ($demand as $masterId => $requested) {
            $master = HotelStoreMasterItem::query()
                ->where('hotel_id', $hotel->id)
                ->with('storePackage')
                ->whereKey($masterId)
                ->first();

            if ($master === null || ! $master->inventoried) {
                continue;
            }

            $available = (float) $master->stock;
            if ($requested > $available) {
                $unit = $master->storePackage?->name ?: 'units';
                throw new InvalidArgumentException(
                    'Insufficient stock for "'.$master->name.'". Available: '.$available.' '.$unit.', requested: '.$requested.'.'
                );
            }
        }
    }
}
