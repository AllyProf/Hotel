<?php

namespace App\Services\Bar;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelBarSale;
use App\Models\HotelPmsService;
use App\Models\HotelPmsServiceItem;
use App\Models\HotelPmsServiceItemSellOption;
use App\Models\User;
use App\Services\FrontDeskPaymentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BarSaleService
{
    public function __construct(
        private FrontDeskPaymentService $deskPayments,
        private BarPurchaseListService $purchaseLists,
    ) {}

    /** @return Collection<int, CmReservation> */
    public function inHouseReservations(Hotel $hotel): Collection
    {
        return CmReservation::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', '!=', CmReservation::STATUS_CANCELLED)
            ->where('pms_status', CmReservation::PMS_CHECKED_IN)
            ->with(['roomUnit'])
            ->orderBy('room_unit_id')
            ->get();
    }

    public function nextSaleNo(Hotel $hotel): string
    {
        $date = now()->format('Ymd');
        $prefix = 'BAR-SL-'.$date.'-';
        $last = HotelBarSale::query()
            ->where('hotel_id', $hotel->id)
            ->where('sale_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('sale_no');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  Collection<int, HotelPmsServiceItem>  $catalogItems
     * @return list<array<string, mixed>>
     */
    public function sellCatalogFromItems(Collection $catalogItems): array
    {
        $out = [];

        foreach ($catalogItems as $item) {
            if (! $item->is_active) {
                continue;
            }

            $item->loadMissing(['sellOptions.package', 'sellingPackage', 'package']);
            $methods = [];

            if ($item->sellOptions->isNotEmpty()) {
                foreach ($item->sellOptions->sortBy('sort_order') as $opt) {
                    $price = $opt->unit_price !== null ? (float) $opt->unit_price : (float) ($item->unit_price ?? 0);
                    if ($price <= 0) {
                        continue;
                    }
                    $methods[] = [
                        'package_id' => (int) $opt->hotel_pms_service_package_id,
                        'label' => $opt->package?->abbreviation ?: $opt->package?->name ?: 'Unit',
                        'unit_price' => $price,
                        'pieces_per_sell_unit' => $opt->pieces_per_sell_unit !== null ? (float) $opt->pieces_per_sell_unit : 1.0,
                    ];
                }
            } else {
                $price = (float) ($item->unit_price ?? 0);
                if ($price > 0) {
                    $pkgId = (int) ($item->selling_hotel_pms_service_package_id ?: $item->hotel_pms_service_package_id ?: 0);
                    $methods[] = [
                        'package_id' => $pkgId,
                        'label' => $item->sellingPackage?->abbreviation ?: $item->package?->abbreviation ?: 'Unit',
                        'unit_price' => $price,
                        'pieces_per_sell_unit' => 1.0,
                    ];
                }
            }

            if ($methods === []) {
                continue;
            }

            $out[] = [
                'id' => $item->id,
                'name' => $item->item_name,
                'category' => $item->category?->name,
                'stock_pieces' => $item->barPiecesAvailable(),
                'methods' => $methods,
            ];
        }

        return $out;
    }

    /** @return Builder<HotelBarSale> */
    public function salesQuery(Hotel $hotel): Builder
    {
        return HotelBarSale::query()
            ->where('hotel_id', $hotel->id)
            ->with(['lines', 'reservation.roomUnit', 'creator'])
            ->orderByDesc('sold_at')
            ->orderByDesc('id');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lines
     */
    public function createSale(
        Hotel $hotel,
        HotelPmsService $service,
        array $data,
        array $lines,
        ?User $user = null,
    ): HotelBarSale {
        if ($lines === []) {
            throw new InvalidArgumentException('Add at least one item to the sale.');
        }

        $settings = $service->barSettings();
        $reservation = null;

        if (! empty($data['cm_reservation_id'])) {
            $reservation = CmReservation::query()
                ->where('hotel_id', $hotel->id)
                ->whereKey((int) $data['cm_reservation_id'])
                ->firstOrFail();
        } elseif (empty($settings['allow_walk_in'])) {
            throw new InvalidArgumentException('Select an in-house guest or enable walk-in sales in bar service settings.');
        }

        $roomNo = trim((string) ($data['room_no'] ?? ''));
        if ($roomNo === '' && $reservation?->roomUnit) {
            $roomNo = (string) $reservation->roomUnit->room_number;
        }

        $guestName = trim((string) ($data['guest_name'] ?? ''));
        if ($guestName === '' && $reservation) {
            $guestName = $reservation->guestName();
        }

        $currency = strtoupper($this->purchaseLists->budgetCurrencyForHotel($hotel));
        $pricedLines = $this->buildPricedLines($service, $lines, $currency);
        $subtotal = round(collect($pricedLines)->sum('line_total'), 2);
        $total = $subtotal;
        $walkIn = $reservation === null;
        $payment = $this->deskPayments->parsePayment($hotel, $data, $total, $walkIn);

        return DB::transaction(function () use (
            $hotel,
            $service,
            $data,
            $user,
            $reservation,
            $roomNo,
            $guestName,
            $currency,
            $pricedLines,
            $subtotal,
            $total,
            $payment,
            $settings,
        ) {
            foreach ($pricedLines as $line) {
                $this->deductStockForLine(
                    $line['catalog_item'],
                    $line['quantity'],
                    $line['pieces_per_sell_unit'],
                    $line['sell_unit_label'] ?? null,
                    isset($line['hotel_pms_service_package_id']) ? (int) $line['hotel_pms_service_package_id'] : null,
                );
            }

            $sale = HotelBarSale::query()->create([
                'hotel_id' => $hotel->id,
                'hotel_pms_service_id' => $service->id,
                'cm_reservation_id' => $reservation?->id,
                'sale_no' => $this->nextSaleNo($hotel),
                'room_no' => $roomNo !== '' ? $roomNo : null,
                'guest_name' => $guestName !== '' && $guestName !== '—' ? $guestName : null,
                'subtotal' => $subtotal,
                'total' => $total,
                'currency' => $currency,
                'payment_mode' => $payment['payment_mode'],
                'payment_provider' => $payment['payment_provider'],
                'amount_paid' => $payment['amount_paid'],
                'payment_notes' => $payment['payment_notes'],
                'notes' => ! empty($data['notes']) ? trim((string) $data['notes']) : null,
                'sold_at' => now(),
                'created_by' => $user?->id,
            ]);

            foreach ($pricedLines as $line) {
                unset($line['catalog_item']);
                $sale->lines()->create($line);
            }

            if ($payment['amount_paid'] > 0 && $reservation) {
                $this->deskPayments->recordOnReservation(
                    $reservation,
                    $payment,
                    'bar_sale',
                    $sale->id,
                    $sale->sale_no,
                );
            }

            if (! empty($settings['post_to_folio']) && $reservation && $total > 0) {
                $this->postToFolio($sale, $reservation);
            }

            return $sale->fresh(['lines', 'reservation.roomUnit', 'creator']);
        });
    }

    public function postToFolio(HotelBarSale $sale, ?CmReservation $reservation = null): void
    {
        if ($sale->posted_to_folio) {
            return;
        }

        $reservation ??= $sale->reservation;
        if (! $reservation || $sale->total <= 0) {
            return;
        }

        $payload = is_array($reservation->payload) ? $reservation->payload : [];
        $charges = is_array($payload['bar_charges'] ?? null) ? $payload['bar_charges'] : [];

        $charges[] = [
            'sale_id' => $sale->id,
            'sale_no' => $sale->sale_no,
            'amount' => (float) $sale->total,
            'currency' => $sale->currency,
            'posted_at' => now()->toIso8601String(),
        ];
        $payload['bar_charges'] = $charges;

        $reservation->update([
            'amount_after_tax' => round((float) ($reservation->amount_after_tax ?? 0) + (float) $sale->total, 2),
            'payload' => $payload,
        ]);

        $sale->update(['posted_to_folio' => true]);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function buildPricedLines(HotelPmsService $service, array $lines, string $currency): array
    {
        $out = [];

        foreach ($lines as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            $qty = max(0.01, (float) ($row['quantity'] ?? 1));
            $packageId = (int) ($row['sell_package_id'] ?? 0);

            $item = HotelPmsServiceItem::query()
                ->where('hotel_pms_service_id', $service->id)
                ->whereKey($itemId)
                ->where('is_active', true)
                ->with(['sellOptions.package', 'package', 'sellingPackage'])
                ->first();

            if (! $item) {
                throw new InvalidArgumentException('Invalid bar item on this sale.');
            }

            $sellOption = $this->resolveSellOption($item, $packageId);
            $unitPrice = $sellOption['unit_price'];
            $lineTotal = round($qty * $unitPrice, 2);

            $out[] = [
                'hotel_pms_service_item_id' => $item->id,
                'item_name' => $item->item_name,
                'hotel_pms_service_package_id' => $sellOption['package_id'] ?: null,
                'sell_unit_label' => $sellOption['unit_label'],
                'quantity' => $qty,
                'pieces_per_sell_unit' => $sellOption['pieces_per_sell_unit'],
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'catalog_item' => $item,
            ];
        }

        return $out;
    }

    /** @return array{package_id: int, unit_label: string, pieces_per_sell_unit: float, unit_price: float} */
    private function resolveSellOption(HotelPmsServiceItem $item, int $packageId): array
    {
        $item->loadMissing('sellOptions.package');

        if ($item->sellOptions->isNotEmpty()) {
            $opt = $packageId > 0
                ? $item->sellOptions->firstWhere('hotel_pms_service_package_id', $packageId)
                : $item->sellOptions->sortBy('sort_order')->first();

            if (! $opt instanceof HotelPmsServiceItemSellOption) {
                throw new InvalidArgumentException('Select a valid sell unit for '.$item->item_name.'.');
            }

            $price = $opt->unit_price !== null ? (float) $opt->unit_price : (float) ($item->unit_price ?? 0);
            if ($price <= 0) {
                throw new InvalidArgumentException('Set a sell price for '.$item->item_name.' ('.($opt->package?->abbreviation ?: $opt->package?->name).').');
            }

            return [
                'package_id' => (int) $opt->hotel_pms_service_package_id,
                'unit_label' => $opt->package?->abbreviation ?: $opt->package?->name ?: 'Unit',
                'pieces_per_sell_unit' => $opt->pieces_per_sell_unit !== null ? (float) $opt->pieces_per_sell_unit : 1.0,
                'unit_price' => $price,
            ];
        }

        $price = (float) ($item->unit_price ?? 0);
        if ($price <= 0) {
            throw new InvalidArgumentException('Set a sell price for '.$item->item_name.'.');
        }

        $pkgId = (int) ($item->selling_hotel_pms_service_package_id ?: $item->hotel_pms_service_package_id ?: 0);

        return [
            'package_id' => $pkgId,
            'unit_label' => $item->sellingPackage?->abbreviation ?: $item->package?->abbreviation ?: 'Unit',
            'pieces_per_sell_unit' => 1.0,
            'unit_price' => $price,
        ];
    }

    public function deductStockForPosLine(
        HotelPmsServiceItem $item,
        float $qty,
        float $piecesPerSellUnit,
        ?string $sellUnitLabel = null,
        ?int $sellPackageId = null,
    ): void {
        $this->deductStockForLine($item, $qty, $piecesPerSellUnit, $sellUnitLabel, $sellPackageId);
    }

    private function deductStockForLine(
        HotelPmsServiceItem $item,
        float $qty,
        float $piecesPerSellUnit,
        ?string $sellUnitLabel = null,
        ?int $sellPackageId = null,
    ): void {
        $piecesPerSellUnit = $this->effectivePiecesPerSellUnit($item, $piecesPerSellUnit, $sellPackageId);
        $piecesSold = $qty * $piecesPerSellUnit;
        $receivePieces = $item->pieces_per_receive_unit !== null ? (float) $item->pieces_per_receive_unit : 1.0;
        if ($receivePieces <= 0) {
            $receivePieces = 1.0;
        }

        $stockUnits = $item->stock_on_hand !== null ? (float) $item->stock_on_hand : 0.0;
        $piecesAvailable = $stockUnits * $receivePieces;

        if ($piecesSold > $piecesAvailable + 0.0001) {
            $unit = $sellUnitLabel ?: 'sale unit';
            $maxQty = $piecesPerSellUnit > 0
                ? floor(($piecesAvailable / $piecesPerSellUnit) * 100) / 100
                : 0;
            $qtyLabel = $this->formatQuantityLabel($qty);
            $maxLabel = $this->formatQuantityLabel($maxQty);

            throw new InvalidArgumentException(
                'Not enough bar stock for '.$item->item_name.': '
                .$qtyLabel.' '.$unit.' not available (on hand ~'.$maxLabel.' '.$unit.').'
            );
        }

        $piecesRemaining = max(0, $piecesAvailable - $piecesSold);
        $newStockUnits = round($piecesRemaining / $receivePieces, 4);

        $item->update(['stock_on_hand' => $newStockUnits]);
    }

    /**
     * When "pieces per sale unit" was set to the same as "pieces per receive unit" (e.g. 12 glasses
     * per bottle entered on both), treat one sale unit as one piece for stock deduction.
     */
    private function effectivePiecesPerSellUnit(
        HotelPmsServiceItem $item,
        float $piecesPerSellUnit,
        ?int $sellPackageId = null,
    ): float {
        if ($piecesPerSellUnit <= 0) {
            return 1.0;
        }

        $receivePieces = $item->pieces_per_receive_unit !== null ? (float) $item->pieces_per_receive_unit : 1.0;
        if ($receivePieces <= 1) {
            return $piecesPerSellUnit;
        }

        $receivePackageId = (int) ($item->hotel_pms_service_package_id ?? 0);
        if ($sellPackageId && $receivePackageId && $sellPackageId === $receivePackageId) {
            return $piecesPerSellUnit;
        }

        if ($piecesPerSellUnit >= $receivePieces - 0.0001) {
            $normalized = round($piecesPerSellUnit / $receivePieces, 4);

            return max(0.0001, $normalized);
        }

        return $piecesPerSellUnit;
    }

    private function formatQuantityLabel(float $qty): string
    {
        $rounded = round($qty, 2);

        return rtrim(rtrim(number_format($rounded, 2, '.', ''), '0'), '.');
    }
}
