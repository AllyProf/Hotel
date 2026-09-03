<?php

namespace App\Services;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelRatePlan;
use App\Models\HotelRoom;
use App\Models\HotelRoomUnit;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class StayViewService
{
    public const WINDOW_DAYS = 7;

    /** @return array<string, mixed> */
    public function build(Hotel $hotel, Carbon $start): array
    {
        $start = $start->copy()->startOfDay();
        $dates = collect(CarbonPeriod::create($start, $start->copy()->addDays(self::WINDOW_DAYS - 1)))
            ->map(fn (Carbon $date) => $date->copy()->startOfDay())
            ->values();

        $dateKeys = $dates->map(fn (Carbon $d) => $d->format('Y-m-d'))->all();
        $rooms = $hotel->rooms()
            ->where('is_enabled', true)
            ->with(['units' => fn ($q) => $q->orderBy('room_number')->orderBy('id')])
            ->orderBy('rank')
            ->orderBy('id')
            ->get();

        $reservations = CmReservation::query()
            ->where(function ($q) use ($hotel) {
                $q->where('hotel_id', $hotel->id);
            })
            ->where('status', '!=', CmReservation::STATUS_CANCELLED)
            ->whereDate('checkout', '>', $dateKeys[0])
            ->whereDate('checkin', '<=', end($dateKeys))
            ->orderBy('checkin')
            ->get();

        $barRate = $this->defaultBarRate($hotel);
        $roomTypes = [];
        $allUnits = collect();
        $summary = $this->emptySummary($dateKeys, $barRate);

        foreach ($rooms as $room) {
            $units = $this->unitsForRoom($room);
            $unitRows = [];
            $assignedReservationIds = [];

            foreach ($units as $unit) {
                $cells = $this->cellsForUnit($unit, $dates, $reservations, $room, $assignedReservationIds);
                $unitRows[] = [
                    'id' => $unit['id'],
                    'label' => $unit['label'],
                    'status' => $unit['status'],
                    'cells' => $cells,
                ];
                $allUnits->push($unit);
            }

            $roomTypes[] = [
                'id' => $room->id,
                'name' => $room->display_name ?: $room->name,
                'units' => $unitRows,
            ];
        }

        $totalUnits = max(1, $allUnits->count());
        $maintenanceUnits = $allUnits->where('status', HotelRoomUnit::STATUS_MAINTENANCE)->count();

        foreach ($dateKeys as $dateKey) {
            $date = Carbon::parse($dateKey);
            $occupiedUnits = $this->occupiedUnitCount($reservations, $date, $totalUnits, $maintenanceUnits);
            $available = max(0, $totalUnits - $maintenanceUnits - $occupiedUnits);
            $guests = $this->guestCountOn($reservations, $date);
            $arriving = $this->arrivingCount($reservations, $date);
            $checkingOut = $this->checkingOutCount($reservations, $date);
            $occupancy = $totalUnits > 0 ? (int) round(($occupiedUnits / $totalUnits) * 100) : 0;

            $summary[$dateKey] = [
                'available' => $available,
                'occupied' => $occupiedUnits,
                'occupancy' => $occupancy,
                'guests' => $guests,
                'arriving' => $arriving,
                'checking_out' => $checkingOut,
                'bar_rate' => $barRate,
            ];
        }

        $todayKey = now()->format('Y-m-d');
        $todaySummary = $summary[$todayKey] ?? reset($summary);

        return [
            'start' => $start,
            'dates' => $dates,
            'date_keys' => $dateKeys,
            'room_types' => $roomTypes,
            'summary' => $summary,
            'stats' => [
                'guests' => $todaySummary['guests'] ?? 0,
                'occupied' => $todaySummary['occupied'] ?? 0,
                'available' => $todaySummary['available'] ?? $totalUnits,
                'complimentary' => 0,
                'maintenance' => $maintenanceUnits,
            ],
            'bar_rate' => $barRate,
            'total_units' => $totalUnits,
        ];
    }

    /** @return list<array{id: string|int, label: string, status: string}> */
    private function unitsForRoom(HotelRoom $room): array
    {
        if ($room->units->isNotEmpty()) {
            return $room->units->map(fn (HotelRoomUnit $unit) => [
                'id' => $unit->id,
                'label' => $unit->displayLabel(),
                'status' => $unit->status ?: HotelRoomUnit::STATUS_AVAILABLE,
            ])->all();
        }

        $count = max(1, (int) $room->room_count);
        $units = [];

        for ($i = 0; $i < $count; $i++) {
            $units[] = [
                'id' => 'virtual-'.$room->id.'-'.$i,
                'label' => (string) (100 + $i),
                'status' => HotelRoomUnit::STATUS_AVAILABLE,
            ];
        }

        return $units;
    }

    /**
     * @param  array{id: string|int, label: string, status: string}  $unit
     * @param  Collection<int, Carbon>  $dates
     * @param  Collection<int, CmReservation>  $reservations
     * @param  list<int>  $assignedReservationIds
     * @return list<array<string, mixed>>
     */
    private function cellsForUnit(array $unit, Collection $dates, Collection $reservations, HotelRoom $room, array &$assignedReservationIds): array
    {
        if ($unit['status'] === HotelRoomUnit::STATUS_MAINTENANCE) {
            return $dates->map(fn () => [
                'type' => 'maintenance',
                'label' => 'Maintenance',
                'colspan' => 1,
            ])->all();
        }

        $cells = [];
        $index = 0;
        $dateKeys = $dates->map(fn (Carbon $d) => $d->format('Y-m-d'))->all();

        while ($index < count($dateKeys)) {
            $dateKey = $dateKeys[$index];
            $reservation = $this->reservationStartingOn($reservations, $dateKey, $room, $assignedReservationIds);

            if ($reservation !== null) {
                $span = $this->staySpan($reservation, $dateKey, $dateKeys);
                $cells[] = [
                    'type' => 'booking',
                    'colspan' => $span,
                    'guest' => $reservation->guestName(),
                    'channel' => $reservation->channel,
                    'status' => $this->bookingStatusClass($reservation, $dateKey),
                    'status_label' => $this->bookingStatusLabel($reservation, $dateKey),
                    'booking_id' => $reservation->booking_id,
                ];
                $assignedReservationIds[] = $reservation->id;
                $index += $span;

                continue;
            }

            $cells[] = ['type' => 'empty', 'colspan' => 1];
            $index++;
        }

        return $cells;
    }

    /** @param  list<int>  $assignedReservationIds */
    private function reservationStartingOn(Collection $reservations, string $dateKey, HotelRoom $room, array $assignedReservationIds): ?CmReservation
    {
        return $reservations->first(function (CmReservation $reservation) use ($dateKey, $room, $assignedReservationIds) {
            if (in_array($reservation->id, $assignedReservationIds, true)) {
                return false;
            }

            if ($reservation->checkin?->format('Y-m-d') !== $dateKey) {
                return false;
            }

            return $this->reservationMatchesRoom($reservation, $room);
        });
    }

    private function reservationMatchesRoom(CmReservation $reservation, HotelRoom $room): bool
    {
        $roomName = strtolower($room->name.' '.$room->display_name);

        foreach ($reservation->roomLines() as $line) {
            $code = strtolower((string) ($line['roomCode'] ?? $line['room_code'] ?? ''));
            if ($code !== '' && (str_contains($roomName, $code) || str_contains($code, str_replace(' ', '-', strtolower($room->name))))) {
                return true;
            }
        }

        return true;
    }

    /** @param  list<string>  $dateKeys */
    private function staySpan(CmReservation $reservation, string $startDateKey, array $dateKeys): int
    {
        $startIndex = array_search($startDateKey, $dateKeys, true);
        if ($startIndex === false || $reservation->checkout === null) {
            return 1;
        }

        $checkoutKey = $reservation->checkout->format('Y-m-d');
        $endIndex = array_search($checkoutKey, $dateKeys, true);

        if ($endIndex === false) {
            return count($dateKeys) - $startIndex;
        }

        return max(1, $endIndex - $startIndex);
    }

    private function bookingStatusClass(CmReservation $reservation, string $dateKey): string
    {
        $checkin = $reservation->checkin?->format('Y-m-d');
        $checkout = $reservation->checkout?->format('Y-m-d');
        $today = now()->format('Y-m-d');

        if ($checkout === $dateKey && $checkout === $today) {
            return 'checking-out';
        }

        if ($checkin === $dateKey && $checkin === $today) {
            return 'assigned';
        }

        if ($checkin && $checkout && $dateKey >= $checkin && $dateKey < $checkout && $dateKey <= $today) {
            return 'checked-in';
        }

        if ($checkout && $dateKey >= $checkout && $checkout < $today) {
            return 'checked-out';
        }

        return 'assigned';
    }

    private function bookingStatusLabel(CmReservation $reservation, string $dateKey): string
    {
        return match ($this->bookingStatusClass($reservation, $dateKey)) {
            'checking-out' => 'Checking out',
            'checked-in' => 'Checked in',
            'checked-out' => 'Checked out',
            'complimentary' => 'Complimentary',
            default => 'Assigned',
        };
    }

    /** @param  list<string>  $dateKeys */
    private function emptySummary(array $dateKeys, float $barRate): array
    {
        $summary = [];

        foreach ($dateKeys as $dateKey) {
            $summary[$dateKey] = [
                'available' => 0,
                'occupied' => 0,
                'occupancy' => 0,
                'guests' => 0,
                'arriving' => 0,
                'checking_out' => 0,
                'bar_rate' => $barRate,
            ];
        }

        return $summary;
    }

    private function defaultBarRate(Hotel $hotel): float
    {
        $plan = HotelRatePlan::query()
            ->where('hotel_id', $hotel->id)
            ->orderByDesc('is_master')
            ->orderBy('id')
            ->first();

        if ($plan === null) {
            return 50;
        }

        if ($plan->local_base_rate !== null && (float) $plan->local_base_rate > 0) {
            return (float) $plan->local_base_rate;
        }

        return (float) ($plan->base_rate ?: 50);
    }

    /** @param  Collection<int, CmReservation>  $reservations */
    private function occupiedUnitCount(Collection $reservations, Carbon $date, int $totalUnits, int $maintenanceUnits): int
    {
        $dateKey = $date->format('Y-m-d');

        return min(
            $totalUnits - $maintenanceUnits,
            $reservations->filter(function (CmReservation $reservation) use ($dateKey) {
                $checkin = $reservation->checkin?->format('Y-m-d');
                $checkout = $reservation->checkout?->format('Y-m-d');

                return $checkin && $checkout && $dateKey >= $checkin && $dateKey < $checkout;
            })->count()
        );
    }

    /** @param  Collection<int, CmReservation>  $reservations */
    private function guestCountOn(Collection $reservations, Carbon $date): int
    {
        $dateKey = $date->format('Y-m-d');

        return $reservations->filter(function (CmReservation $reservation) use ($dateKey) {
            $checkin = $reservation->checkin?->format('Y-m-d');
            $checkout = $reservation->checkout?->format('Y-m-d');

            return $checkin && $checkout && $dateKey >= $checkin && $dateKey < $checkout;
        })->sum(function (CmReservation $reservation) {
            $lines = $reservation->roomLines();
            if ($lines === []) {
                return 1;
            }

            $adults = 0;
            foreach ($lines as $line) {
                $adults += (int) ($line['occupancy']['adults'] ?? 1);
            }

            return max(1, $adults);
        });
    }

    /** @param  Collection<int, CmReservation>  $reservations */
    private function arrivingCount(Collection $reservations, Carbon $date): int
    {
        return $reservations->where(fn (CmReservation $r) => $r->checkin?->isSameDay($date))->count();
    }

    /** @param  Collection<int, CmReservation>  $reservations */
    private function checkingOutCount(Collection $reservations, Carbon $date): int
    {
        return $reservations->where(fn (CmReservation $r) => $r->checkout?->isSameDay($date))->count();
    }
}
