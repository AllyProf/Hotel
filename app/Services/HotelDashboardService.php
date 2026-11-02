<?php

namespace App\Services;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Services\Erp\HousekeepingMaintenanceService;
use App\Services\LostFoundNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class HotelDashboardService
{
    public function __construct(
        private CheckInService $checkIn,
        private CheckOutService $checkOut,
        private RoomViewService $roomView,
        private HousekeepingMaintenanceService $erpHk,
        private LostFoundNotificationService $lostFoundNotifications,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(Hotel $hotel): array
    {
        $today = Carbon::today();
        $checkInData = $this->checkIn->listForHotel($hotel, $today, 'arrivals');
        $checkOutData = $this->checkOut->listForHotel($hotel, $today, 'departures');
        $roomSnapshot = $this->roomView->build($hotel, $today);
        $stats = $roomSnapshot['stats'] ?? [];

        $occupied = (int) ($stats['occupied'] ?? 0);
        $available = (int) ($stats['available'] ?? 0);
        $maintenance = (int) ($stats['maintenance'] ?? 0);
        $totalRooms = $occupied + $available + $maintenance + (int) ($stats['complimentary'] ?? 0);

        $occupancyPct = $totalRooms > 0 ? round(($occupied / $totalRooms) * 100) : 0;

        return [
            'date' => $today,
            'stats' => [
                'arrivals' => (int) ($checkInData['stats']['arrivals'] ?? 0),
                'departures' => (int) ($checkOutData['stats']['departures'] ?? 0),
                'in_house' => (int) ($checkInData['stats']['in_house'] ?? 0),
                'occupied' => $occupied,
                'available' => $available,
                'total_rooms' => $totalRooms,
                'occupancy_pct' => $occupancyPct,
                'lost_found_open' => $this->erpHk->lostFoundOpenCount($hotel),
                'lost_found_today' => $this->lostFoundNotifications->loggedTodayCount($hotel),
            ],
            'arrivals' => $this->mapBookings($checkInData['bookings']->take(8)),
            'departures' => $this->mapBookings($checkOutData['bookings']->take(8)),
        ];
    }

    /** @param  Collection<int, CmReservation>  $bookings
     * @return list<array<string, string>>
     */
    private function mapBookings(Collection $bookings): array
    {
        return $bookings->map(fn (CmReservation $booking) => [
            'booking_id' => $booking->booking_id,
            'guest' => $booking->guestName(),
            'room' => $booking->isRoomAssigned() ? $booking->assignedRoomLabel() : ($booking->roomLabel() !== '—' ? $booking->roomLabel() : '—'),
            'source' => $booking->sourceDisplayLabel(),
            'status' => $booking->pmsStatusLabel(),
        ])->values()->all();
    }
}
