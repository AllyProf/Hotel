<?php

namespace App\Services;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelLaundryOrder;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GuestStaySummaryService
{
    /**
     * @param  Collection<int, CmReservation>  $bookings
     * @return array<int, array<string, mixed>>
     */
    public function summariesForBookings(Hotel $hotel, Collection $bookings, Carbon $asOf): array
    {
        if ($bookings->isEmpty()) {
            return [];
        }

        $asOf = $asOf->copy()->startOfDay();
        $reservationIds = $bookings->pluck('id')->all();

        $laundryOrders = HotelLaundryOrder::query()
            ->where('hotel_id', $hotel->id)
            ->whereIn('cm_reservation_id', $reservationIds)
            ->withCount('lines')
            ->with(['creator', 'completer'])
            ->orderBy('received_at')
            ->get()
            ->groupBy('cm_reservation_id');

        $summaries = [];

        foreach ($bookings as $booking) {
            $orders = $laundryOrders->get($booking->id, collect());
            $summaries[$booking->id] = $this->buildSummary($hotel, $booking, $asOf, $orders);
        }

        return $summaries;
    }

    /** @return array<string, mixed> */
    public function summaryForBooking(Hotel $hotel, CmReservation $booking, ?Carbon $asOf = null): array
    {
        $asOf = ($asOf ?? now())->copy()->startOfDay();

        $orders = HotelLaundryOrder::query()
            ->where('hotel_id', $hotel->id)
            ->where('cm_reservation_id', $booking->id)
            ->withCount('lines')
            ->with(['creator', 'completer'])
            ->orderBy('received_at')
            ->get();

        return $this->buildSummary($hotel, $booking, $asOf, $orders);
    }

    /** @param  Collection<int, HotelLaundryOrder>  $laundryOrders */
    private function buildSummary(Hotel $hotel, CmReservation $booking, Carbon $asOf, Collection $laundryOrders): array
    {
        $payload = is_array($booking->payload) ? $booking->payload : [];
        $nightsBooked = $booking->roomNightCount();
        $nightsRemaining = $booking->nightsRemaining($asOf);
        $checkoutDay = $booking->checkout?->copy()->startOfDay();
        $isCheckoutDay = $checkoutDay !== null && $checkoutDay->equalTo($asOf);

        $nightsStayed = null;
        if ($booking->checkin !== null) {
            $checkinDay = $booking->checkin->copy()->startOfDay();
            if ($asOf->gte($checkinDay)) {
                $nightsStayed = (int) $checkinDay->diffInDays($asOf);
                if ($nightsBooked !== null) {
                    $nightsStayed = min($nightsStayed, $nightsBooked);
                }
            } else {
                $nightsStayed = 0;
            }
        }

        $laundryTickets = $laundryOrders->map(fn (HotelLaundryOrder $order) => [
            'ticket_no' => $order->ticket_no,
            'status' => $order->statusLabel(),
            'total' => $order->formattedTotal($hotel),
            'items' => (int) $order->lines_count,
            'express' => $order->is_express,
            'received_at' => $order->received_at?->format('d/m/Y H:i'),
            'created_by' => $order->creator?->name,
            'completed_by' => $order->completer?->name,
            'completed_at' => $order->delivered_at?->format('d/m/Y H:i'),
            'posted_to_folio' => $order->posted_to_folio,
            'url' => route('hotel.laundry.show', $order),
            'print_url' => route('hotel.laundry.print', $order),
        ])->values()->all();

        $folioLaundry = collect(is_array($payload['laundry_charges'] ?? null) ? $payload['laundry_charges'] : [])
            ->map(fn (array $charge) => [
                'ticket_no' => $charge['ticket_no'] ?? '—',
                'amount' => isset($charge['amount'], $charge['currency'])
                    ? number_format((float) $charge['amount'], 2).' '.strtoupper((string) $charge['currency'])
                    : (isset($charge['amount']) ? number_format((float) $charge['amount'], 2) : '—'),
                'posted_at' => ! empty($charge['posted_at'])
                    ? Carbon::parse($charge['posted_at'])->format('d/m/Y H:i')
                    : null,
            ])
            ->values()
            ->all();

        $manualCharges = collect(is_array($payload['folio_service_charges'] ?? null) ? $payload['folio_service_charges'] : [])
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $charge) => [
                'service' => trim((string) ($charge['service_name'] ?? 'Service')),
                'description' => trim((string) ($charge['description'] ?? '')) ?: null,
                'amount' => isset($charge['amount'], $charge['currency'])
                    ? number_format((float) $charge['amount'], 2).' '.strtoupper((string) $charge['currency'])
                    : (isset($charge['amount']) ? number_format((float) $charge['amount'], 2) : '—'),
                'posted_at' => ! empty($charge['posted_at'])
                    ? Carbon::parse($charge['posted_at'])->format('d/m/Y H:i')
                    : null,
                'posted_by' => $charge['posted_by_name'] ?? null,
            ])
            ->values()
            ->all();

        $specialRequest = trim((string) ($payload['special_request'] ?? $payload['specialRequest'] ?? $payload['comments'] ?? ''));

        return [
            'booking_id' => $booking->booking_id,
            'guest' => $booking->guestName(),
            'reservation_url' => route('hotel.reservations.show', $booking),
            'pms_status' => $booking->pmsStatusLabel(),
            'source' => $booking->sourceDisplayLabel(),
            'room_type' => $booking->roomLabel(),
            'room_no' => $booking->isRoomAssigned() ? $booking->assignedRoomLabel() : null,
            'checkin' => $booking->checkinLabel(),
            'checkout' => $booking->checkoutLabel(),
            'checked_in_at' => $booking->checked_in_at?->format('d/m/Y g:i A'),
            'checked_out_at' => $booking->checked_out_at?->format('d/m/Y g:i A'),
            'nights_booked' => $nightsBooked,
            'nights_stayed' => $nightsStayed,
            'nights_remaining' => $nightsRemaining,
            'is_checkout_day' => $isCheckoutDay,
            'guest_count' => $booking->guestCount(),
            'meal_plan' => $booking->mealPlanLabel() !== '—' ? $booking->mealPlanLabel() : null,
            'payment_type' => $booking->paymentLabel(),
            'room_rate' => $booking->priceLabel() !== '—' ? $booking->priceLabel() : null,
            'total_due' => number_format($booking->totalDue(), 2).' '.($booking->currency ?? ''),
            'amount_paid' => $booking->effectiveAmountPaid() > 0
                ? number_format($booking->effectiveAmountPaid(), 2).' '.($booking->currency ?? '')
                : null,
            'balance_due' => $booking->balanceDue() > 0.009 ? $booking->balanceLabel() : null,
            'late_checkout_fee' => $booking->late_checkout_fee > 0
                ? number_format((float) $booking->late_checkout_fee, 2).' '.($booking->currency ?? '')
                : null,
            'laundry_tickets' => $laundryTickets,
            'folio_laundry_charges' => $folioLaundry,
            'folio_service_charges' => $manualCharges,
            'special_request' => $specialRequest !== '' ? $specialRequest : null,
        ];
    }
}
