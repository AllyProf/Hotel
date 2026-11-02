<?php

namespace App\Services\Stores;

use App\Models\Hotel;
use App\Models\HotelPurchaseOrder;
use App\Models\HotelPurchaseOrderItem;
use App\Models\HotelStorePackage;
use App\Services\AccountsDataService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StoreGoodsReceiveService
{
    public function __construct(
        private StoreStockService $storeStock,
        private AccountsDataService $accounts,
    ) {}
    /** @return list<string> */
    public function receiveStatusKeys(): array
    {
        return array_keys(config('hotel_stores.goods_receive_statuses', []));
    }

    public function receiveStatusLabel(?string $status): string
    {
        if ($status === null || $status === '') {
            return '—';
        }

        return (string) (config('hotel_stores.goods_receive_statuses.'.$status) ?? ucfirst($status));
    }

    /** @param  array<int, array<string, mixed>>  $lines  keyed by line id */
    public function saveDraft(Hotel $hotel, HotelPurchaseOrder $order, array $lines, ?string $headerNotes = null): HotelPurchaseOrder
    {
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);

        if ($order->goods_received) {
            throw new InvalidArgumentException('Goods receipt was already finalized.');
        }

        if (! $order->isInvoiced() && ! $order->isPaid()) {
            throw new InvalidArgumentException('This purchase order must be invoiced before saving receipt lines.');
        }

        $allowed = $this->receiveStatusKeys();
        $order->loadMissing('items');

        return DB::transaction(function () use ($order, $lines, $allowed, $headerNotes) {
            foreach ($order->items as $item) {
                $payload = $lines[$item->id] ?? null;
                if (! is_array($payload)) {
                    continue;
                }

                $this->applyLineReceive($item, $payload, $allowed);
            }

            if ($headerNotes !== null) {
                $order->update([
                    'goods_receive_notes' => trim($headerNotes) !== '' ? trim($headerNotes) : null,
                ]);
            }

            return $order->fresh(['vendor', 'items', 'goodsReceivedBy', 'storeDepartment']);
        });
    }

    /** @param  array<int, array<string, mixed>>  $lines  keyed by line id */
    public function finalize(Hotel $hotel, HotelPurchaseOrder $order, array $lines, ?string $headerNotes = null): HotelPurchaseOrder
    {
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);

        if (! in_array($order->status, [HotelPurchaseOrder::STATUS_INVOICED, HotelPurchaseOrder::STATUS_PAID], true)) {
            throw new InvalidArgumentException('Only invoiced or paid orders can be marked as goods received.');
        }

        $allowed = $this->receiveStatusKeys();
        $order->loadMissing(['items', 'hotel']);

        if ($order->items->isEmpty()) {
            throw new InvalidArgumentException('This order has no line items.');
        }

        $hotel = $order->hotel;

        return DB::transaction(function () use ($hotel, $order, $lines, $allowed, $headerNotes) {
            foreach ($order->items as $item) {
                $payload = $lines[$item->id] ?? null;
                if (! is_array($payload)) {
                    throw new InvalidArgumentException('Each line item must be checked before submitting.');
                }

                $received = $this->applyLineReceive($item, $payload, $allowed);
                $this->storeStock->applyReceivedQuantity($hotel, $item->fresh(), $received);
            }

            $order->refresh()->load('items');
            $order->update([
                'pre_tax' => round($order->items->sum(fn ($line) => (float) $line->pre_tax), 2),
                'tax' => round($order->items->sum(fn ($line) => (float) $line->tax), 2),
                'total' => round($order->items->sum(fn ($line) => (float) $line->total), 2),
                'goods_received' => true,
                'goods_received_at' => now(),
                'goods_received_by' => auth()->id(),
                'goods_receive_notes' => $headerNotes !== '' ? $headerNotes : null,
            ]);

            $order = $order->fresh(['vendor', 'items', 'goodsReceivedBy', 'paymentExpense']);
            $order = $this->accounts->postStoreDeskPurchaseOrderBudgetReturn($hotel, $order);

            return $order->fresh(['vendor', 'items', 'goodsReceivedBy', 'paymentExpense', 'budgetReturnExpense']);
        });
    }

    /** @param  array<string, mixed>  $payload */
    private function applyLineReceive(HotelPurchaseOrderItem $item, array $payload, array $allowed): float
    {
        $status = (string) ($payload['receive_status'] ?? '');
        if (! in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Invalid receive status for '.$item->name.'.');
        }

        $ordered = (float) $item->quantity;
        $received = (float) ($payload['received_quantity'] ?? 0);

        if ($status === 'full') {
            if (abs($received - $ordered) > 0.001 && $received <= 0) {
                $received = $ordered;
            }
            if (abs($received - $ordered) > 0.001) {
                throw new InvalidArgumentException($item->name.': received quantity must match ordered quantity when status is all received.');
            }
        } elseif ($status === 'missing') {
            $received = 0.0;
        } elseif ($status === 'partial') {
            if ($received <= 0 || $received >= $ordered) {
                throw new InvalidArgumentException($item->name.': enter how many were received (less than ordered) for partial delivery.');
            }
        }

        $notes = trim((string) ($payload['receive_notes'] ?? ''));

        $unitRateRaw = $payload['received_unit_rate'] ?? null;
        $unitRate = ($unitRateRaw !== null && $unitRateRaw !== '')
            ? max(0, (float) $unitRateRaw)
            : (float) $item->rate;
        if ($status === 'missing') {
            $unitRate = (float) $item->rate;
        }

        $plannedPreTax = (float) $item->pre_tax;
        $plannedTax = (float) $item->tax;
        $taxRatio = $plannedPreTax > 0 ? $plannedTax / $plannedPreTax : 0.0;
        $preTax = round($received * $unitRate, 2);
        $tax = round($preTax * $taxRatio, 2);
        $total = round($preTax + $tax, 2);

        $packageId = ($payload['hotel_store_package_id'] ?? '') !== ''
            ? (int) $payload['hotel_store_package_id']
            : null;
        if ($packageId !== null && $packageId <= 0) {
            $packageId = null;
        }
        if ($packageId !== null) {
            $item->loadMissing('purchaseOrder');
            $exists = HotelStorePackage::query()
                ->whereKey($packageId)
                ->where('hotel_id', $item->purchaseOrder?->hotel_id)
                ->exists();
            if (! $exists) {
                throw new InvalidArgumentException('Select a valid receive package for '.$item->name.'.');
            }
        }

        $item->update([
            'received_quantity' => $received,
            'receive_status' => $status,
            'receive_notes' => $notes !== '' ? $notes : null,
            'hotel_store_package_id' => $packageId,
            'rate' => $unitRate,
            'pre_tax' => $preTax,
            'tax' => $tax,
            'total' => $total,
        ]);

        return $received;
    }
}
