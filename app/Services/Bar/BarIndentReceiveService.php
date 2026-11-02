<?php

namespace App\Services\Bar;

use App\Models\Hotel;
use App\Models\HotelPmsService;
use App\Models\HotelPmsServiceItem;
use App\Models\HotelStoreIndent;
use App\Models\HotelStoreIndentItem;
use App\Models\HotelStoreMasterItem;
use App\Support\DepartmentProcurement;
use InvalidArgumentException;

class BarIndentReceiveService
{
    public function __construct(
        private BarCatalogService $barCatalog,
    ) {}

    /** @return list<int> */
    public function barHotelDepartmentIds(Hotel $hotel): array
    {
        $patterns = DepartmentProcurement::workflow('bar')['department_name_patterns'] ?? [];
        $departments = \App\Models\HotelDepartment::query()
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->get();

        $ids = [];
        foreach ($departments as $department) {
            $name = strtolower(trim($department->name));
            if ($name === '') {
                continue;
            }
            if ($name === 'bar' || str_contains($name, 'bar')) {
                $ids[] = (int) $department->id;

                continue;
            }
            foreach ($patterns as $pattern) {
                $needle = strtolower(str_replace('%', '', (string) $pattern));
                if ($needle !== '' && str_contains($name, $needle)) {
                    $ids[] = (int) $department->id;
                    break;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    public function departmentUsesBarCatalog(Hotel $hotel, int $hotelDepartmentId): bool
    {
        if ($hotelDepartmentId <= 0) {
            return false;
        }

        if (! $this->barCatalog->barServiceForHotel($hotel)) {
            return false;
        }

        return in_array($hotelDepartmentId, $this->barHotelDepartmentIds($hotel), true);
    }

    public function syncIndentToBarCatalog(Hotel $hotel, HotelStoreIndent $indent): void
    {
        if (! $this->departmentUsesBarCatalog($hotel, (int) $indent->hotel_department_id)) {
            return;
        }

        $service = $this->barCatalog->barServiceForHotel($hotel);
        if (! $service) {
            return;
        }

        $indent->loadMissing(['items.masterItem.sellOptions.storePackage', 'items.masterItem.storePackage']);
        $catalogByName = $this->catalogByItemName($service);

        foreach ($indent->items as $line) {
            $this->addLineToBarStock($line, $catalogByName);
        }
    }

    /** @return array<string, HotelPmsServiceItem> */
    private function catalogByItemName(HotelPmsService $service): array
    {
        $map = [];
        foreach ($service->catalogItems()->get() as $item) {
            $key = mb_strtolower(trim($item->item_name));
            if ($key !== '') {
                $map[$key] = $item;
            }
        }

        return $map;
    }

    /** @param  array<string, HotelPmsServiceItem>  $catalogByName */
    private function addLineToBarStock(HotelStoreIndentItem $line, array $catalogByName): void
    {
        $nameKey = mb_strtolower(trim($line->name));
        if ($nameKey === '') {
            return;
        }

        $catalogItem = $catalogByName[$nameKey] ?? null;
        if (! $catalogItem) {
            return;
        }

        $master = $line->masterItem;
        if (! $master && $line->hotel_store_master_item_id) {
            $master = HotelStoreMasterItem::query()->find($line->hotel_store_master_item_id);
        }

        $stockUnits = $this->indentQuantityAsBarStockUnits($master, $line, $catalogItem);
        if ($stockUnits <= 0) {
            return;
        }

        $current = $catalogItem->stock_on_hand !== null ? (float) $catalogItem->stock_on_hand : 0.0;
        $catalogItem->update([
            'stock_on_hand' => round($current + $stockUnits, 2),
        ]);
    }

    private function indentQuantityAsBarStockUnits(
        ?HotelStoreMasterItem $master,
        HotelStoreIndentItem $line,
        HotelPmsServiceItem $catalogItem,
    ): float {
        $qty = (float) $line->quantity;
        if ($qty <= 0) {
            return 0.0;
        }

        if (! $master) {
            return $qty;
        }

        $master->loadMissing(['sellOptions.storePackage', 'storePackage']);
        $lineUnit = trim((string) ($line->unit ?? ''));
        $receiveLabel = $master->storePackage?->unitLabel() ?? trim((string) ($master->unit ?? ''));
        $receivePieces = max(0.01, (float) ($master->pieces_per_receive_unit ?? 1));

        if ($lineUnit !== '' && $receiveLabel !== '' && strcasecmp($lineUnit, $receiveLabel) === 0) {
            return $qty;
        }

        $piecesPerIssue = 1.0;
        foreach ($master->sellOptions as $sellOption) {
            $unit = $sellOption->storePackage?->unitLabel() ?? '';
            if ($lineUnit !== '' && $unit !== '' && strcasecmp($lineUnit, $unit) === 0) {
                $piecesPerIssue = max(0.01, (float) $sellOption->pieces_per_sell_unit);
                break;
            }
        }

        $totalPieces = $qty * $piecesPerIssue;
        $pmsReceivePieces = max(0.01, (float) ($catalogItem->pieces_per_receive_unit ?? $receivePieces));

        return $totalPieces / $pmsReceivePieces;
    }

    public function assertIndentAwaitingBarReceive(Hotel $hotel, HotelStoreIndent $indent): void
    {
        abort_unless((int) $indent->hotel_id === (int) $hotel->id, 404);
        if (! $indent->isIssued()) {
            throw new InvalidArgumentException('Only issued store indents can be received.');
        }
        if (! $this->departmentUsesBarCatalog($hotel, (int) $indent->hotel_department_id)) {
            throw new InvalidArgumentException('This indent is not for the bar department.');
        }
    }
}
