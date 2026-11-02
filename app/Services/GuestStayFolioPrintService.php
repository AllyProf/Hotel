<?php

namespace App\Services;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelLaundryOrder;
use Carbon\Carbon;
class GuestStayFolioPrintService
{
    /** @return array<string, mixed> */
    public function build(Hotel $hotel, CmReservation $reservation, ?Carbon $asOf = null): array
    {
        $asOf = ($asOf ?? now())->copy()->startOfDay();
        $currency = strtoupper((string) ($reservation->currency ?? $hotel->currency ?? 'TZS'));
        $payload = is_array($reservation->payload) ? $reservation->payload : [];

        $manualCharges = collect(is_array($payload['folio_service_charges'] ?? null) ? $payload['folio_service_charges'] : [])
            ->filter(fn ($row) => is_array($row))
            ->values();

        $laundryOrders = HotelLaundryOrder::query()
            ->where('hotel_id', $hotel->id)
            ->where('cm_reservation_id', $reservation->id)
            ->with(['lines.category', 'creator', 'completer'])
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();

        $manualTotal = $manualCharges->sum(fn (array $c) => (float) ($c['amount'] ?? 0));
        $postedLaundryTotal = $laundryOrders
            ->filter(fn (HotelLaundryOrder $o) => $o->posted_to_folio)
            ->sum(fn (HotelLaundryOrder $o) => (float) $o->total);
        $unpostedLaundryTotal = $laundryOrders
            ->reject(fn (HotelLaundryOrder $o) => $o->posted_to_folio)
            ->sum(fn (HotelLaundryOrder $o) => (float) $o->total);

        $lateFee = max(0, (float) ($reservation->late_checkout_fee ?? 0));
        $folioTotal = max(0, (float) ($reservation->amount_after_tax ?? 0));
        $roomBase = round(max(0, $folioTotal - $manualTotal - $postedLaundryTotal - $lateFee), 2);

        $folioLines = [];

        if ($roomBase > 0.009) {
            $folioLines[] = [
                'label' => 'Room accommodation',
                'detail' => $this->roomLineDetail($reservation),
                'amount' => $roomBase,
                'currency' => $currency,
            ];
        }

        foreach ($manualCharges as $charge) {
            $amount = (float) ($charge['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $chargeCurrency = strtoupper((string) ($charge['currency'] ?? $currency));
            $folioLines[] = [
                'label' => trim((string) ($charge['service_name'] ?? 'Service')),
                'detail' => trim((string) ($charge['description'] ?? '')) ?: null,
                'amount' => $amount,
                'currency' => $chargeCurrency,
            ];
        }

        foreach ($laundryOrders->filter(fn (HotelLaundryOrder $o) => $o->posted_to_folio) as $order) {
            $folioLines[] = [
                'label' => 'Laundry — '.$order->ticket_no,
                'detail' => $order->is_express ? 'Express' : null,
                'amount' => (float) $order->total,
                'currency' => $order->displayCurrency($hotel),
            ];
        }

        if ($lateFee > 0.009) {
            $folioLines[] = [
                'label' => 'Late checkout fee',
                'detail' => null,
                'amount' => $lateFee,
                'currency' => $currency,
            ];
        }

        return [
            'as_of' => $asOf,
            'currency' => $currency,
            'guest' => $reservation->guestName(),
            'booking_id' => $reservation->booking_id,
            'room' => $reservation->isRoomAssigned() ? $reservation->assignedRoomLabel() : $reservation->roomLabel(),
            'checkin' => $reservation->checkinLabel(),
            'checkout' => $reservation->checkoutLabel(),
            'pms_status' => $reservation->pmsStatusLabel(),
            'folio_lines' => $folioLines,
            'folio_total' => $folioTotal,
            'amount_paid' => $reservation->effectiveAmountPaid(),
            'balance_due' => $reservation->balanceDue(),
            'unposted_laundry_total' => round($unpostedLaundryTotal, 2),
            'laundry_orders' => $laundryOrders,
            'manual_charges' => $manualCharges,
        ];
    }

    private function roomLineDetail(CmReservation $reservation): ?string
    {
        $parts = array_filter([
            $reservation->roomLabel() !== '—' ? $reservation->roomLabel() : null,
            $reservation->roomNightCount() ? $reservation->roomNightCount().' night(s)' : null,
        ]);

        return $parts !== [] ? implode(' · ', $parts) : null;
    }

    public function formatMoney(float $amount, string $currency, Hotel $hotel): string
    {
        $decimals = $this->currencyDecimals($currency, $hotel);

        return number_format($amount, $decimals).' '.strtoupper($currency);
    }

    private function currencyDecimals(string $currency, Hotel $hotel): int
    {
        $hotelCurrency = strtoupper((string) ($hotel->currency ?? ''));

        if ($currency === $hotelCurrency && in_array($currency, ['TZS', 'UGX', 'RWF', 'BIF'], true)) {
            return 0;
        }

        return 2;
    }
}
