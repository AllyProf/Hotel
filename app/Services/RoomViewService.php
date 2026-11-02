<?php

namespace App\Services;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\HotelRoomUnit;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RoomViewService
{
    /** @return array<string, mixed> */
    public function uiConfig(): array
    {
        return config('hotel_pms.room_view', []);
    }

    /** @return array<string, mixed> */
    public function build(Hotel $hotel, Carbon $date): array
    {
        $date = $date->copy()->startOfDay();
        $dateKey = $date->format('Y-m-d');

        $rooms = $hotel->rooms()
            ->where('is_enabled', true)
            ->with(['units' => fn ($q) => $q->orderBy('room_number')->orderBy('id')])
            ->orderBy('rank')
            ->orderBy('id')
            ->get();

        $reservations = CmReservation::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', '!=', CmReservation::STATUS_CANCELLED)
            ->whereDate('checkout', '>', $dateKey)
            ->whereDate('checkin', '<=', $dateKey)
            ->with('roomUnit')
            ->orderBy('checkin')
            ->get();

        $assignedByUnit = app(CheckInService::class)->reservationsByUnitId($reservations);
        $preassignedIds = $reservations->whereNotNull('room_unit_id')->pluck('id')->all();

        $roomTypes = [];
        $globalStats = $this->emptyStats();

        foreach ($rooms as $room) {
            $typeReservations = $reservations->filter(
                fn (CmReservation $reservation) => $this->reservationMatchesRoom($reservation, $room)
            )->values();

            $assignedReservationIds = $preassignedIds;
            $units = [];
            $typeStats = $this->emptyStats();

            foreach ($this->unitsForRoom($room) as $unit) {
                $card = $this->cardForUnit($unit, $room, $dateKey, $typeReservations, $assignedReservationIds, $assignedByUnit);
                $units[] = $card;
                $this->incrementStat($typeStats, $card['stat_key']);
                $typeStats['guests'] += (int) ($card['guest_count'] ?? 0);
            }

            $roomTypes[] = [
                'id' => $room->id,
                'name' => $room->display_name ?: $room->name,
                'units' => $units,
                'stats' => $typeStats,
            ];

            foreach ($typeStats as $key => $value) {
                $globalStats[$key] += $value;
            }
        }

        return [
            'date' => $date,
            'date_key' => $dateKey,
            'room_types' => $roomTypes,
            'stats' => $globalStats,
        ];
    }

    /** @return array{guests: int, occupied: int, available: int, dirty: int, cleaned: int, complimentary: int, maintenance: int} */
    private function emptyStats(): array
    {
        return [
            'guests' => 0,
            'occupied' => 0,
            'available' => 0,
            'dirty' => 0,
            'cleaned' => 0,
            'complimentary' => 0,
            'maintenance' => 0,
        ];
    }

    /** @param array{guests: int, occupied: int, available: int, dirty: int, cleaned: int, complimentary: int, maintenance: int} $stats */
    private function incrementStat(array &$stats, string $key): void
    {
        if (! array_key_exists($key, $stats)) {
            return;
        }

        $stats[$key]++;
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
                'label' => trim(($room->display_name ?: $room->name).' '.($i + 1)),
                'status' => HotelRoomUnit::STATUS_AVAILABLE,
            ];
        }

        return $units;
    }

    /**
     * @param  array{id: string|int, label: string, status: string}  $unit
     * @param  Collection<int, CmReservation>  $reservations
     * @param  list<int>  $assignedReservationIds
     * @param  array<int, CmReservation>  $assignedByUnit
     * @return array<string, mixed>
     */
    private function cardForUnit(
        array $unit,
        HotelRoom $room,
        string $dateKey,
        Collection $reservations,
        array &$assignedReservationIds,
        array $assignedByUnit,
    ): array {
        if ($unit['status'] === HotelRoomUnit::STATUS_MAINTENANCE) {
            return $this->cardPayload($unit, 'maintenance', 'maintenance');
        }

        $pinnedReservation = is_int($unit['id']) ? ($assignedByUnit[$unit['id']] ?? null) : null;
        $reservation = $pinnedReservation;

        if ($reservation === null) {
            $reservation = $this->reservationForUnitOnDate($reservations, $dateKey, $assignedReservationIds);
        } else {
            $checkin = $reservation->checkin?->format('Y-m-d');
            $checkout = $reservation->checkout?->format('Y-m-d');

            if (! ($checkin && $checkout && $dateKey >= $checkin && $dateKey < $checkout)) {
                $reservation = null;
            }
        }

        if ($reservation !== null && $reservation->isCheckedOut()) {
            $reservation = null;
        }

        if ($unit['status'] === HotelRoomUnit::STATUS_DIRTY && $reservation === null) {
            return $this->cardPayload($unit, 'dirty', 'dirty');
        }

        if ($unit['status'] === HotelRoomUnit::STATUS_CLEANED && $reservation === null) {
            return $this->cardPayload($unit, 'cleaned', 'cleaned');
        }

        if ($reservation !== null) {
            if ($pinnedReservation === null) {
                $assignedReservationIds[] = $reservation->id;
            }

            $state = $this->bookingStatusClass($reservation, $dateKey);
            $statKey = $state === 'complimentary' ? 'complimentary' : 'occupied';

            return $this->cardPayload($unit, $state, $statKey, $reservation);
        }

        return $this->cardPayload($unit, 'available', 'available');
    }

    /** @param  array{id: string|int, label: string, status: string}  $unit */
    private function cardPayload(array $unit, string $state, string $statKey, ?CmReservation $reservation = null): array
    {
        $icons = config('hotel_pms.room_view.card_icons', []);
        $labels = config('hotel_pms.room_view.status_labels', []);

        return [
            'id' => $unit['id'],
            'label' => $unit['label'],
            'state' => $state,
            'stat_key' => $statKey,
            'guest' => $reservation?->guestName(),
            'channel' => $reservation?->channel,
            'booking_id' => $reservation?->booking_id,
            'status_label' => $labels[$state] ?? ucfirst(str_replace('-', ' ', $state)),
            'guest_count' => $reservation ? $this->guestCountForReservation($reservation) : 0,
            'icon' => $icons[$state] ?? 'fa-star-o',
        ];
    }

    /** @param  Collection<int, CmReservation>  $reservations @param  list<int>  $assignedReservationIds */
    private function reservationForUnitOnDate(Collection $reservations, string $dateKey, array $assignedReservationIds): ?CmReservation
    {
        return $reservations->first(function (CmReservation $reservation) use ($dateKey, $assignedReservationIds) {
            if ($reservation->room_unit_id) {
                return false;
            }

            if (in_array($reservation->id, $assignedReservationIds, true)) {
                return false;
            }

            $checkin = $reservation->checkin?->format('Y-m-d');
            $checkout = $reservation->checkout?->format('Y-m-d');

            return $checkin && $checkout && $dateKey >= $checkin && $dateKey < $checkout;
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

        return count($reservation->roomLines()) <= 1;
    }

    private function bookingStatusClass(CmReservation $reservation, string $dateKey): string
    {
        $checkin = $reservation->checkin?->format('Y-m-d');
        $checkout = $reservation->checkout?->format('Y-m-d');
        $today = now()->format('Y-m-d');

        if ($reservation->isComplimentary()) {
            if ($reservation->pms_status === CmReservation::PMS_CHECKED_OUT) {
                return 'checked-out';
            }

            if ($checkin && $checkout && $dateKey >= $checkin && $dateKey < $checkout) {
                return 'complimentary';
            }
        }

        if ($reservation->pms_status === CmReservation::PMS_CHECKED_OUT) {
            return 'checked-out';
        }

        if ($reservation->pms_status === CmReservation::PMS_CHECKED_IN) {
            if ($checkout === $dateKey && $checkout === $today) {
                return 'checking-out';
            }

            return 'checked-in';
        }

        if ($reservation->pms_status === CmReservation::PMS_ASSIGNED) {
            if ($checkin === $dateKey && $checkin >= $today) {
                return 'assigned';
            }

            if ($checkin === $dateKey) {
                return 'assigned';
            }
        }

        if ($checkout === $dateKey && $checkout === $today) {
            return 'checking-out';
        }

        if ($checkin === $dateKey && $checkin >= $today) {
            return 'assigned';
        }

        if ($checkin && $checkout && $dateKey >= $checkin && $dateKey < $checkout && $dateKey <= $today) {
            return 'checked-in';
        }

        if ($checkin === $dateKey) {
            return 'assigned';
        }

        return 'assigned';
    }

    private function guestCountForReservation(CmReservation $reservation): int
    {
        $lines = $reservation->roomLines();
        if ($lines === []) {
            return 1;
        }

        $adults = 0;
        foreach ($lines as $line) {
            $adults += (int) ($line['occupancy']['adults'] ?? 1);
        }

        return max(1, $adults);
    }

    public function markUnitClean(Hotel $hotel, int $unitId): void
    {
        $this->markUnitCleaned($hotel, $unitId);
    }

    public function markUnitCleaned(Hotel $hotel, int $unitId): void
    {
        $updated = HotelRoomUnit::query()
            ->where('hotel_id', $hotel->id)
            ->whereKey($unitId)
            ->where('status', HotelRoomUnit::STATUS_DIRTY)
            ->update(['status' => HotelRoomUnit::STATUS_CLEANED]);

        if ($updated === 0) {
            throw new \InvalidArgumentException('Room is not marked as dirty.');
        }
    }

    public function markUnitAvailable(Hotel $hotel, int $unitId): void
    {
        $updated = HotelRoomUnit::query()
            ->where('hotel_id', $hotel->id)
            ->whereKey($unitId)
            ->where('status', HotelRoomUnit::STATUS_CLEANED)
            ->update(['status' => HotelRoomUnit::STATUS_AVAILABLE]);

        if ($updated === 0) {
            throw new \InvalidArgumentException('Room must be in cleaned status before it can be marked available.');
        }
    }

    /** @param  list<int>  $unitIds */
    public function markUnitsDirty(Hotel $hotel, array $unitIds): void
    {
        $unitIds = array_values(array_filter(array_map('intval', $unitIds)));
        if ($unitIds === []) {
            return;
        }

        HotelRoomUnit::query()
            ->where('hotel_id', $hotel->id)
            ->whereIn('id', $unitIds)
            ->update(['status' => HotelRoomUnit::STATUS_DIRTY]);
    }
}
