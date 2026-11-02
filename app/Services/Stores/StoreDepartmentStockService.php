<?php

namespace App\Services\Stores;

use App\Models\Hotel;
use App\Services\Bar\BarIndentReceiveService;
use App\Models\HotelStoreDepartmentStock;
use App\Models\HotelStoreIndent;
use App\Models\HotelStoreIndentItem;
use App\Models\HotelStoreMasterItem;
use Illuminate\Database\Eloquent\Builder;

class StoreDepartmentStockService
{
    /** @param  array<string, mixed>  $filters */
    public function consumptionIndex(Hotel $hotel, array $filters): array
    {
        $departmentId = (int) ($filters['department_id'] ?? 0);
        $search = trim((string) ($filters['search'] ?? ''));

        $departmentIds = $filters['department_ids'] ?? null;
        $departmentsQuery = \App\Models\HotelDepartment::query()
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->orderBy('name');

        if (is_array($departmentIds) && $departmentIds !== []) {
            $departmentsQuery->whereIn('id', $departmentIds);
        }

        $departments = $departmentsQuery->get();

        $query = HotelStoreDepartmentStock::query()
            ->where('hotel_id', $hotel->id)
            ->where('quantity', '>', 0)
            ->with(['department', 'masterItem.storePackage', 'masterItem.category']);

        if (is_array($departmentIds) && $departmentIds !== []) {
            $query->whereIn('hotel_department_id', $departmentIds);
        }

        if ($departmentId > 0) {
            $query->where('hotel_department_id', $departmentId);
        }

        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(function (Builder $q) use ($needle) {
                $q->whereHas('masterItem', fn (Builder $m) => $m->where('name', 'like', $needle))
                    ->orWhereHas('department', fn (Builder $d) => $d->where('name', 'like', $needle));
            });
        }

        $rows = $query->orderBy('hotel_department_id')->orderBy('hotel_store_master_item_id')->get();

        return [
            'departments' => $departments,
            'rows' => $rows,
        ];
    }

    public function receiveIndentIntoDepartment(Hotel $hotel, HotelStoreIndent $indent): void
    {
        $indent->loadMissing(['items', 'department']);
        abort_unless((int) $indent->hotel_id === (int) $hotel->id, 404);

        $departmentId = (int) $indent->hotel_department_id;

        foreach ($indent->items as $line) {
            $this->addLineToDepartment($hotel, $departmentId, $line);
        }

        app(BarIndentReceiveService::class)->syncIndentToBarCatalog($hotel, $indent);
    }

    private function addLineToDepartment(Hotel $hotel, int $departmentId, HotelStoreIndentItem $line): void
    {
        $qty = (float) $line->quantity;
        if ($qty <= 0) {
            return;
        }

        $masterId = (int) ($line->hotel_store_master_item_id ?? 0);
        if ($masterId <= 0) {
            $master = HotelStoreMasterItem::query()
                ->where('hotel_id', $hotel->id)
                ->where('name', $line->name)
                ->orderByDesc('inventoried')
                ->first();
            $masterId = (int) ($master?->id ?? 0);
        }

        if ($masterId <= 0) {
            return;
        }

        $row = HotelStoreDepartmentStock::query()->firstOrCreate(
            [
                'hotel_department_id' => $departmentId,
                'hotel_store_master_item_id' => $masterId,
            ],
            [
                'hotel_id' => $hotel->id,
                'quantity' => 0,
            ]
        );

        $row->update([
            'quantity' => round((float) $row->quantity + $qty, 2),
        ]);
    }
}
