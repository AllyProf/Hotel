<?php

namespace App\Services\Laundry;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelLaundryOrder;
use App\Models\HotelPmsLaundryPrice;
use App\Models\HotelPmsService;
use App\Models\User;
use App\Services\FrontDeskPaymentService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class LaundryOrderService
{
    public function __construct(
        private FrontDeskPaymentService $deskPayments,
    ) {}

    /** @return array<string, mixed> */
    public function uiConfig(): array
    {
        return config('hotel_pms.laundry', []);
    }

    public function laundryServiceForHotel(Hotel $hotel): ?HotelPmsService
    {
        return $hotel->pmsServices()
            ->where('template_key', 'laundry')
            ->where('is_active', true)
            ->first();
    }

    public function nextTicketNo(Hotel $hotel): string
    {
        $year = now()->format('Y');
        $prefix = 'LAU-'.$year.'-';
        $last = HotelLaundryOrder::query()
            ->where('hotel_id', $hotel->id)
            ->where('ticket_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('ticket_no');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    /** @return Builder<HotelLaundryOrder> */
    public function ordersQuery(Hotel $hotel, ?string $status = null): Builder
    {
        $query = HotelLaundryOrder::query()
            ->where('hotel_id', $hotel->id)
            ->with(['lines.category', 'reservation.roomUnit', 'service', 'creator', 'completer'])
            ->orderByDesc('id');

        if ($status === 'active' || $status === 'progressing') {
            $query->whereIn('status', [
                HotelLaundryOrder::STATUS_RECEIVED,
                HotelLaundryOrder::STATUS_IN_PROGRESS,
                HotelLaundryOrder::STATUS_READY,
            ]);
        } elseif ($status === 'done') {
            $query->where('status', HotelLaundryOrder::STATUS_DELIVERED);
        } elseif ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        return $query;
    }

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

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lines
     */
    public function createOrder(
        Hotel $hotel,
        HotelPmsService $service,
        array $data,
        array $lines,
        ?User $user = null,
    ): HotelLaundryOrder {
        if ($lines === []) {
            throw new InvalidArgumentException('Add at least one laundry item.');
        }

        $settings = $service->laundrySettings();
        $reservation = null;

        if (! empty($data['cm_reservation_id'])) {
            $reservation = CmReservation::query()
                ->where('hotel_id', $hotel->id)
                ->whereKey((int) $data['cm_reservation_id'])
                ->firstOrFail();
        }

        $roomNoInput = trim((string) ($data['room_no'] ?? ''));
        if (! empty($settings['require_room_number']) && $reservation !== null) {
            if ($roomNoInput === '' && ! $reservation->roomUnit) {
                throw new InvalidArgumentException('Room number is required for in-house guests.');
            }
        }

        $roomNo = $roomNoInput;
        if ($roomNo === '' && $reservation?->roomUnit) {
            $roomNo = (string) $reservation->roomUnit->room_number;
        }

        $guestName = trim((string) ($data['guest_name'] ?? ''));
        if ($guestName === '' && $reservation) {
            $guestName = $reservation->guestName();
        }

        $isExpress = ! empty($data['is_express']);
        $currency = $this->resolveOrderCurrency($hotel, $service, $lines);

        $pricedLines = $this->buildPricedLines($service, $lines, $currency);
        $complimentaryAllowance = 0;

        if ($reservation && ($settings['complimentary_pieces'] ?? 0) > 0) {
            $complimentaryAllowance = (int) $settings['complimentary_pieces'];
        }

        $this->applyComplimentaryPieces($pricedLines, $complimentaryAllowance);

        $subtotal = round(collect($pricedLines)->sum('line_total'), 2);
        $surchargePercent = $isExpress ? (int) ($settings['express_surcharge_percent'] ?? 0) : 0;
        $surcharge = round($subtotal * ($surchargePercent / 100), 2);
        $total = round($subtotal + $surcharge, 2);
        $payment = $this->deskPayments->parsePayment($hotel, $data, $total, $reservation === null);

        $order = HotelLaundryOrder::query()->create([
            'hotel_id' => $hotel->id,
            'hotel_pms_service_id' => $service->id,
            'cm_reservation_id' => $reservation?->id,
            'ticket_no' => $this->nextTicketNo($hotel),
            'room_no' => $roomNo !== '' ? $roomNo : null,
            'guest_name' => $guestName !== '' && $guestName !== '—' ? $guestName : null,
            'status' => HotelLaundryOrder::STATUS_IN_PROGRESS,
            'is_express' => $isExpress,
            'complimentary_pieces' => $complimentaryAllowance,
            'subtotal' => $subtotal,
            'surcharge_amount' => $surcharge,
            'total' => $total,
            'currency' => $currency,
            'payment_mode' => $payment['payment_mode'],
            'payment_provider' => $payment['payment_provider'],
            'amount_paid' => $payment['amount_paid'],
            'payment_notes' => $payment['payment_notes'],
            'notes' => ! empty($data['notes']) ? trim((string) $data['notes']) : null,
            'received_at' => now(),
            'created_by' => $user?->id,
        ]);

        foreach ($pricedLines as $line) {
            $order->lines()->create($line);
        }

        if (! empty($settings['post_to_folio']) && $reservation && $total > 0) {
            $this->postToFolio($order, $reservation);
        }

        return $order->fresh(['lines', 'reservation.roomUnit']);
    }

    public function updateStatus(HotelLaundryOrder $order, string $status, ?User $user = null): HotelLaundryOrder
    {
        if ($status !== HotelLaundryOrder::STATUS_DELIVERED) {
            throw new InvalidArgumentException('Invalid status.');
        }

        if (! $order->isOpen()) {
            throw new InvalidArgumentException('This ticket is already done.');
        }

        $order->update([
            'status' => HotelLaundryOrder::STATUS_DELIVERED,
            'delivered_at' => now(),
            'completed_by' => $user?->id,
        ]);

        return $order->fresh(['lines', 'reservation.roomUnit', 'creator', 'completer']);
    }

    public function postToFolio(HotelLaundryOrder $order, ?CmReservation $reservation = null): void
    {
        if ($order->posted_to_folio) {
            return;
        }

        $reservation ??= $order->reservation;
        if (! $reservation || $order->total <= 0) {
            return;
        }

        $payload = is_array($reservation->payload) ? $reservation->payload : [];
        $charges = is_array($payload['laundry_charges'] ?? null) ? $payload['laundry_charges'] : [];

        $charges[] = [
            'order_id' => $order->id,
            'ticket_no' => $order->ticket_no,
            'amount' => (float) $order->total,
            'currency' => $order->displayCurrency($order->hotel),
            'posted_at' => now()->toIso8601String(),
        ];
        $payload['laundry_charges'] = $charges;

        $reservation->update([
            'amount_after_tax' => round((float) ($reservation->amount_after_tax ?? 0) + (float) $order->total, 2),
            'payload' => $payload,
        ]);

        $order->update(['posted_to_folio' => true]);
    }

    /** @param  list<array<string, mixed>>  $lines */
    private function resolveOrderCurrency(Hotel $hotel, HotelPmsService $service, array $lines): string
    {
        foreach ($lines as $line) {
            $priceId = ! empty($line['price_id']) ? (int) $line['price_id'] : null;
            if (! $priceId) {
                continue;
            }

            $priceCurrency = HotelPmsLaundryPrice::query()
                ->where('hotel_pms_service_id', $service->id)
                ->whereKey($priceId)
                ->value('currency');

            if ($priceCurrency) {
                return strtoupper(trim((string) $priceCurrency));
            }
        }

        $catalogCurrency = $service->laundryPrices()
            ->whereNotNull('currency')
            ->where('currency', '!=', '')
            ->value('currency');

        if ($catalogCurrency) {
            return strtoupper(trim((string) $catalogCurrency));
        }

        return $service->displayCurrency($hotel);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function buildPricedLines(HotelPmsService $service, array $lines, string $currency): array
    {
        $priced = [];

        foreach ($lines as $line) {
            $qty = max(0.01, (float) ($line['quantity'] ?? 1));
            $priceId = ! empty($line['price_id']) ? (int) $line['price_id'] : null;
            $price = $priceId
                ? HotelPmsLaundryPrice::query()->where('hotel_pms_service_id', $service->id)->whereKey($priceId)->first()
                : null;

            $itemName = trim((string) ($line['item_name'] ?? $price?->item_name ?? ''));
            if ($itemName === '') {
                continue;
            }

            $unitPrice = $price ? (float) $price->unit_price : max(0, (float) ($line['unit_price'] ?? 0));
            $lineTotal = round($unitPrice * $qty, 2);

            $priced[] = [
                'hotel_pms_service_category_id' => $price?->hotel_pms_service_category_id
                    ?? (! empty($line['category_id']) ? (int) $line['category_id'] : null),
                'item_name' => $itemName,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'is_complimentary' => false,
                'item_notes' => ! empty($line['item_notes']) ? trim((string) $line['item_notes']) : null,
            ];
        }

        return $priced;
    }

    /** @param  list<array<string, mixed>>  $lines */
    private function applyComplimentaryPieces(array &$lines, int $allowance): void
    {
        if ($allowance <= 0) {
            return;
        }

        $remaining = $allowance;

        foreach ($lines as &$line) {
            if ($remaining <= 0) {
                break;
            }

            $qty = (float) $line['quantity'];
            $freeQty = min($remaining, (int) floor($qty));
            if ($freeQty <= 0) {
                continue;
            }

            $unitPrice = (float) $line['unit_price'];
            $freeValue = round($unitPrice * $freeQty, 2);
            $line['line_total'] = max(0, round((float) $line['line_total'] - $freeValue, 2));

            if ($freeQty >= $qty) {
                $line['is_complimentary'] = true;
            }

            $remaining -= $freeQty;
        }
        unset($line);
    }

    public function expectedReadyAt(HotelPmsService $service, Carbon $receivedAt): Carbon
    {
        $hours = (int) ($service->laundrySettings()['turnaround_hours'] ?? 24);

        return $receivedAt->copy()->addHours(max(1, $hours));
    }
}
