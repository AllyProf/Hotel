<?php

namespace App\Services\Stores;

use App\Models\Hotel;
use App\Models\HotelPurchaseOrder;
use App\Models\HotelPurchaseOrderItem;
use App\Models\HotelStoreMasterItem;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class StoreStockService
{
    /** Apply stock for invoice lines received before stock sync existed. */
    public function syncPendingReceiptLines(Hotel $hotel): void
    {
        HotelPurchaseOrderItem::query()
            ->whereNull('stock_applied_at')
            ->whereNotNull('received_quantity')
            ->where('received_quantity', '>', 0)
            ->whereHas('purchaseOrder', function (Builder $q) use ($hotel) {
                $q->where('hotel_id', $hotel->id)
                    ->where('goods_received', true)
                    ->where('status', HotelPurchaseOrder::STATUS_INVOICED);
            })
            ->orderBy('id')
            ->each(function (HotelPurchaseOrderItem $line) use ($hotel) {
                $this->applyReceivedQuantity($hotel, $line->fresh(), (float) $line->received_quantity);
            });
    }

    /** @return array{categories: \Illuminate\Support\Collection, items: \Illuminate\Support\Collection, total_value: float} */
    public function stockIndex(Hotel $hotel, array $filters): array
    {
        $this->syncPendingReceiptLines($hotel);

        $categoryId = (int) ($filters['category_id'] ?? 0);
        $search = trim((string) ($filters['search'] ?? ''));

        $categories = \App\Models\HotelStoreCategory::query()
            ->where('hotel_id', $hotel->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $itemsQuery = HotelStoreMasterItem::query()
            ->where('hotel_id', $hotel->id)
            ->where('inventoried', true)
            ->with(['category', 'storePackage', 'vendor']);

        if ($categoryId > 0) {
            $itemsQuery->where('hotel_store_category_id', $categoryId);
        }

        if ($search !== '') {
            $needle = '%'.$search.'%';
            $itemsQuery->where(function (Builder $q) use ($needle) {
                $q->where('name', 'like', $needle)
                    ->orWhere('unit', 'like', $needle);
            });
        }

        $items = $itemsQuery->orderBy('name')->get();
        $totalValue = round($items->sum(fn (HotelStoreMasterItem $item) => $item->lineValue()), 2);

        return [
            'categories' => $categories,
            'items' => $items,
            'total_value' => $totalValue,
        ];
    }

    public function applyReceivedQuantity(Hotel $hotel, HotelPurchaseOrderItem $line, float $receivedQuantity): void
    {
        if ($receivedQuantity <= 0 || $line->stock_applied_at !== null) {
            if ($receivedQuantity <= 0 && $line->stock_applied_at === null) {
                $line->update(['stock_applied_at' => now()]);
            }

            return;
        }

        $master = $this->resolveMasterItem($hotel, $line);
        if ($master === null) {
            throw new InvalidArgumentException(
                'No master item found for "'.$line->name.'". Add it under Settings → Master Items (inventoried) or recreate the PO line from the catalog.'
            );
        }

        if (! $master->inventoried) {
            throw new InvalidArgumentException(
                '"'.$master->name.'" is not marked inventoried. Enable Inventoried on the master item to receive stock.'
            );
        }

        $master->update([
            'stock' => round((float) $master->stock + $receivedQuantity, 2),
        ]);

        $line->update([
            'hotel_store_master_item_id' => $master->id,
            'stock_applied_at' => now(),
        ]);
    }

    private function resolveMasterItem(Hotel $hotel, HotelPurchaseOrderItem $line): ?HotelStoreMasterItem
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
            ->orderBy('id')
            ->first();
    }
}
