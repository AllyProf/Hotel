<?php

namespace App\Services;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelLostFoundItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LostFoundGuestMatchService
{
    /** @return array<int, list<array<string, mixed>>> */
    public function hintsByItems(Hotel $hotel, Collection $items): array
    {
        $hints = [];

        foreach ($items as $item) {
            if (! $item instanceof HotelLostFoundItem || ! $item->hotel_room_unit_id) {
                continue;
            }

            $hints[$item->id] = $this->candidatesForRoom(
                $hotel,
                (int) $item->hotel_room_unit_id,
                $item->logged_at,
            );
        }

        return $hints;
    }

    /**
     * Guests whose stay overlapped this room around the time the item was logged.
     *
     * @return list<array<string, mixed>>
     */
    public function candidatesForRoom(Hotel $hotel, int $roomUnitId, ?Carbon $loggedAt = null, int $lookbackDays = 21): array
    {
        $anchor = ($loggedAt ?? now())->copy()->startOfDay();
        $stayFrom = $anchor->copy()->subDays($lookbackDays);
        $stayTo = $anchor->copy()->addDay();

        $reservations = CmReservation::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', '!=', CmReservation::STATUS_CANCELLED)
            ->whereNotNull('checkin')
            ->whereNotNull('checkout')
            ->whereDate('checkout', '>=', $stayFrom)
            ->whereDate('checkin', '<=', $stayTo)
            ->where(function ($query) use ($roomUnitId) {
                $query->where('room_unit_id', $roomUnitId)
                    ->orWhereJsonContains('payload->room_unit_ids', $roomUnitId);
            })
            ->orderByDesc('checkout')
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->filter(fn (CmReservation $reservation) => in_array($roomUnitId, $reservation->assignedRoomUnitIds(), true));

        return $reservations
            ->take(8)
            ->map(fn (CmReservation $reservation) => $this->formatCandidate($reservation, $anchor))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function formatCandidate(CmReservation $reservation, Carbon $anchor): array
    {
        $checkin = $reservation->checkin?->format('d/m/Y') ?? '—';
        $checkout = $reservation->checkout?->format('d/m/Y') ?? '—';
        $inHouse = $reservation->isCheckedIn()
            || ($reservation->checkin && $reservation->checkout
                && $anchor->between($reservation->checkin->copy()->startOfDay(), $reservation->checkout->copy()->startOfDay()));

        return [
            'reservation_id' => $reservation->id,
            'booking_id' => $reservation->booking_id,
            'guest_name' => $reservation->guestName(),
            'phone' => $reservation->guestPhone(),
            'email' => $reservation->guestEmail(),
            'checkin' => $checkin,
            'checkout' => $checkout,
            'pms_status' => $reservation->pmsStatusLabel(),
            'pms_badge' => $reservation->pmsStatusBadgeClass(),
            'in_house' => $inHouse,
            'show_url' => route('hotel.reservations.show', $reservation),
        ];
    }
}
