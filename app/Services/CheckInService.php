<?php

namespace App\Services;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\HotelRoomUnit;
use App\Services\ChannelManager\ChannelManagerCodeResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use InvalidArgumentException;

class CheckInService
{
    public function __construct(
        private ChannelManagerCodeResolver $codes,
    ) {}

    /** @return array<string, mixed> */
    public function uiConfig(): array
    {
        return config('hotel_pms.check_in', []);
    }

    /** @return array{date: Carbon, tab: string, stats: array<string, int>, bookings: Collection<int, CmReservation>} */
    public function listForHotel(Hotel $hotel, Carbon $date, string $tab = 'arrivals'): array
    {
        $date = $date->copy()->startOfDay();
        $bookings = match ($tab) {
            'in_house' => $this->inHouseQuery($hotel, $date)->get(),
            'departures' => $this->departuresQuery($hotel, $date)->get(),
            default => $this->arrivalsQuery($hotel, $date)->get(),
        };

        return [
            'date' => $date,
            'tab' => $tab,
            'stats' => [
                'arrivals' => $this->arrivalsQuery($hotel, $date)->count(),
                'in_house' => $this->inHouseQuery($hotel, $date)->count(),
                'departures' => $this->departuresQuery($hotel, $date)->count(),
            ],
            'bookings' => $bookings,
        ];
    }

    /** @return array<string, mixed> */
    public function assignmentPlanForReservation(Hotel $hotel, CmReservation $reservation): array
    {
        $required = max(1, $reservation->roomCount());
        $assignedIds = $reservation->assignedRoomUnitIds();
        $isMulti = $required > 1;
        if (! $isMulti) {
            return [
                'is_multi' => false,
                'required' => 1,
                'assigned_ids' => $assignedIds,
                'assigned_count' => count($assignedIds),
                'units' => $this->availableUnitsForReservation($hotel, $reservation),
            ];
        }
        $rooms = $hotel->rooms()
            ->where('is_enabled', true)
            ->withCount('units')
            ->get();
        $lines = $reservation->roomLines();
        $slots = [];
        $linePlans = [];
        $slotIndex = 0;
        $bookedTypeIds = [];
        foreach ($lines as $lineIndex => $line) {
            $roomType = $this->resolveRoomTypeForLine($hotel, $line, $rooms);
            $count = max(1, (int) ($line['numberOfRooms'] ?? $line['roomCount'] ?? $line['quantity'] ?? 1));
            $lineLabel = trim((string) ($line['roomName'] ?? $line['room_name'] ?? ''))
                ?: ($roomType?->display_name ?: $roomType?->name ?: 'Room');
            $primaryUnits = $roomType !== null
                ? $this->unitsForRoomType($roomType, $reservation)
                : [];
            if ($roomType !== null) {
                $bookedTypeIds[] = (int) $roomType->id;
            }
            $linePlans[] = [
                'index' => $lineIndex,
                'label' => $lineLabel,
                'count' => $count,
                'room_type_id' => $roomType?->id,
                'primary_available' => count($primaryUnits),
            ];
            for ($i = 0; $i < $count; $i++) {
                $slots[] = [
                    'index' => $slotIndex,
                    'line_index' => $lineIndex,
                    'line_label' => $lineLabel,
                    'room_type_id' => $roomType?->id,
                    'assigned_id' => $assignedIds[$slotIndex] ?? null,
                    'primary_units' => $primaryUnits,
                ];
                $slotIndex++;
            }
        }
        while (count($slots) < $required) {
            $roomType = $this->resolveRoomType($hotel, $reservation);
            $primaryUnits = $roomType !== null
                ? $this->unitsForRoomType($roomType, $reservation)
                : [];
            $slots[] = [
                'index' => count($slots),
                'line_index' => null,
                'line_label' => $roomType?->display_name ?: $roomType?->name ?: 'Room',
                'room_type_id' => $roomType?->id,
                'assigned_id' => $assignedIds[count($slots)] ?? null,
                'primary_units' => $primaryUnits,
            ];
        }
        $bookedTypeIds = array_values(array_unique(array_filter($bookedTypeIds)));
        $otherUnits = $this->availableOtherTypeUnits($hotel, $reservation, $bookedTypeIds);
        $primaryUnitIds = [];
        foreach ($slots as $slot) {
            foreach ($slot['primary_units'] as $unit) {
                $primaryUnitIds[(int) $unit['id']] = true;
            }
        }
        $primaryTotal = count($primaryUnitIds);
        $shortfall = max(0, $required - $primaryTotal);

        return [
            'is_multi' => true,
            'required' => $required,
            'assigned_ids' => $assignedIds,
            'assigned_count' => count($assignedIds),
            'lines' => $linePlans,
            'slots' => $slots,
            'other_units' => $otherUnits,
            'primary_total' => $primaryTotal,
            'shortfall' => $shortfall,
            'can_lend_other' => $shortfall > 0 && $otherUnits !== [],
        ];
    }

    /** @return list<array{id: int, label: string, room_type: string}> */
    public function availableUnitsForReservation(Hotel $hotel, CmReservation $reservation): array
    {
        $roomType = $this->resolveRoomType($hotel, $reservation);
        if ($roomType === null) {
            return [];
        }

        return $this->unitsForRoomType($roomType, $reservation);
    }

    public function assignRoom(Hotel $hotel, CmReservation $reservation, int $roomUnitId): CmReservation
    {
        return $this->assignRooms($hotel, $reservation, [$roomUnitId], allowOtherTypes: false);
    }

    /** @param  list<int>  $roomUnitIds */
    public function assignRooms(
        Hotel $hotel,
        CmReservation $reservation,
        array $roomUnitIds,
        bool $allowOtherTypes = false,
    ): CmReservation {
        $this->assertEditable($reservation);
        $roomUnitIds = array_values(array_unique(array_filter(array_map('intval', $roomUnitIds))));
        if ($roomUnitIds === []) {
            throw new InvalidArgumentException('Select at least one room.');
        }
        $required = max(1, $reservation->roomCount());
        if (count($roomUnitIds) > $required) {
            throw new InvalidArgumentException("This booking requires {$required} room(s), not ".count($roomUnitIds).'.');
        }
        $plan = $this->assignmentPlanForReservation($hotel, $reservation);
        $bookedTypeIds = collect($plan['lines'] ?? [])
            ->pluck('room_type_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $units = HotelRoomUnit::query()
            ->where('hotel_id', $hotel->id)
            ->whereIn('id', $roomUnitIds)
            ->get()
            ->keyBy('id');
        if ($units->count() !== count($roomUnitIds)) {
            throw new InvalidArgumentException('One or more selected rooms are invalid.');
        }
        foreach ($roomUnitIds as $unitId) {
            $unit = $units->get($unitId);
            if ($unit === null) {
                continue;
            }
            if (! $allowOtherTypes
                && $bookedTypeIds !== []
                && ! in_array((int) $unit->hotel_room_id, $bookedTypeIds, true)) {
                throw new InvalidArgumentException('Selected room does not match the booked room type.');
            }
            if (! $this->isUnitAvailable($unit, $reservation, $reservation->id, $roomUnitIds)) {
                throw new InvalidArgumentException('Room '.$unit->displayLabel().' is not available for these dates.');
            }
        }
        $payload = is_array($reservation->payload) ? $reservation->payload : [];
        $payload['room_unit_ids'] = $roomUnitIds;
        $payload['room_unit_id'] = $roomUnitIds[0];
        $payload['allow_other_room_types'] = $allowOtherTypes ?: null;
        $reservation->update([
            'room_unit_id' => $roomUnitIds[0],
            'pms_status' => count($roomUnitIds) >= $required
                ? CmReservation::PMS_ASSIGNED
                : CmReservation::PMS_RESERVED,
            'payload' => $payload,
        ]);

        return $reservation->fresh(['roomUnit']);
    }

    public function checkIn(Hotel $hotel, CmReservation $reservation, ?int $roomUnitId = null, ?array $roomUnitIds = null): CmReservation
    {
        $this->assertEditable($reservation);
        if ($roomUnitIds !== null && $roomUnitIds !== []) {
            $this->assignRooms(
                $hotel,
                $reservation->fresh(),
                $roomUnitIds,
                allowOtherTypes: ! empty($reservation->payload['allow_other_room_types']),
            );
            $reservation = $reservation->fresh(['roomUnit']);
        } elseif ($roomUnitId !== null) {
            $this->assignRoom($hotel, $reservation->fresh(), $roomUnitId);
            $reservation = $reservation->fresh(['roomUnit']);
        }
        $required = max(1, $reservation->roomCount());
        if ($reservation->assignedRoomCount() < $required) {
            throw new InvalidArgumentException("Assign all {$required} rooms before checking in.");
        }
        $reservation->update([
            'pms_status' => CmReservation::PMS_CHECKED_IN,
            'checked_in_at' => now(),
        ]);

        return $reservation->fresh(['roomUnit']);
    }

    /** @param  list<int>  $selectedUnitIds */
    public function isUnitAvailable(
        HotelRoomUnit $unit,
        CmReservation $reservation,
        ?int $excludeReservationId = null,
        array $selectedUnitIds = [],
    ): bool {
        if ($unit->status === HotelRoomUnit::STATUS_MAINTENANCE
            || $unit->status === HotelRoomUnit::STATUS_INACTIVE
            || $unit->status === HotelRoomUnit::STATUS_DIRTY) {
            return false;
        }
        if ($reservation->checkin === null || $reservation->checkout === null) {
            return false;
        }
        $checkin = $reservation->checkin->format('Y-m-d');
        $checkout = $reservation->checkout->format('Y-m-d');
        $overlapping = CmReservation::query()
            ->where('hotel_id', $reservation->hotel_id)
            ->where('status', '!=', CmReservation::STATUS_CANCELLED)
            ->whereIn('pms_status', [
                CmReservation::PMS_ASSIGNED,
                CmReservation::PMS_CHECKED_IN,
            ])
            ->whereDate('checkout', '>', $checkin)
            ->whereDate('checkin', '<', $checkout)
            ->when($excludeReservationId !== null, fn ($query) => $query->where('id', '!=', $excludeReservationId))
            ->get(['id', 'room_unit_id', 'payload']);
        foreach ($overlapping as $other) {
            foreach ($other->assignedRoomUnitIds() as $assignedId) {
                if ((int) $assignedId === (int) $unit->id
                    && ! in_array((int) $unit->id, array_map('intval', $selectedUnitIds), true)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function resolveRoomType(Hotel $hotel, CmReservation $reservation): ?HotelRoom
    {
        $rooms = $hotel->rooms()
            ->where('is_enabled', true)
            ->withCount('units')
            ->get();
        foreach ($reservation->roomLines() as $line) {
            $room = $this->resolveRoomTypeForLine($hotel, $line, $rooms);
            if ($room !== null) {
                return $room;
            }
        }
        if ($rooms->count() === 1) {
            return $rooms->first();
        }

        return null;
    }

    /** @return SupportCollection<int, HotelRoomUnit> */
    public function assignedUnitsMap(Hotel $hotel, SupportCollection $reservations): SupportCollection
    {
        $unitIds = $reservations
            ->flatMap(fn (CmReservation $reservation) => $reservation->assignedRoomUnitIds())
            ->unique()
            ->values();
        if ($unitIds->isEmpty()) {
            return collect();
        }

        return HotelRoomUnit::query()
            ->where('hotel_id', $hotel->id)
            ->whereIn('id', $unitIds)
            ->get()
            ->keyBy('id');
    }

    /** @return array<int, CmReservation> */
    public function reservationsByUnitId(SupportCollection $reservations): array
    {
        $map = [];
        foreach ($reservations as $reservation) {
            foreach ($reservation->assignedRoomUnitIds() as $unitId) {
                $map[(int) $unitId] = $reservation;
            }
        }

        return $map;
    }

    /** @return list<array{id: int, label: string, room_type: string}> */
    private function unitsForRoomType(HotelRoom $roomType, CmReservation $reservation): array
    {
        return $this->mapAvailableUnits(
            $roomType->units()
                ->where('status', HotelRoomUnit::STATUS_AVAILABLE)
                ->orderBy('room_number')
                ->orderBy('id')
                ->get(),
            $reservation,
            $roomType->display_name ?: $roomType->name,
        );
    }

    /** @param  list<int>  $excludeRoomTypeIds @return list<array{id: int, label: string, room_type: string}> */
    private function availableOtherTypeUnits(Hotel $hotel, CmReservation $reservation, array $excludeRoomTypeIds): array
    {
        return $this->mapAvailableUnits(
            HotelRoomUnit::query()
                ->where('hotel_id', $hotel->id)
                ->where('status', HotelRoomUnit::STATUS_AVAILABLE)
                ->when($excludeRoomTypeIds !== [], fn ($query) => $query->whereNotIn('hotel_room_id', $excludeRoomTypeIds))
                ->with('roomType')
                ->orderBy('room_number')
                ->orderBy('id')
                ->get(),
            $reservation,
        );
    }

    /** @param  Collection<int, HotelRoom>  $rooms @param  array<string, mixed>  $line */
    private function resolveRoomTypeForLine(Hotel $hotel, array $line, Collection $rooms): ?HotelRoom
    {
        $code = trim((string) ($line['roomCode'] ?? $line['room_code'] ?? ''));
        if ($code !== '') {
            $room = $this->codes->resolveRoomByCode($hotel, $code);
            if ($room !== null) {
                return $this->preferRoomWithUnits($room, $rooms, $line);
            }
        }
        $name = trim((string) ($line['roomName'] ?? $line['room_name'] ?? ''));
        if ($name !== '') {
            return $this->matchRoomByName($rooms, $name);
        }

        return null;
    }

    private function arrivalsQuery(Hotel $hotel, Carbon $date)
    {
        return CmReservation::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', '!=', CmReservation::STATUS_CANCELLED)
            ->whereDate('checkin', $date)
            ->whereNotIn('pms_status', [CmReservation::PMS_CHECKED_IN, CmReservation::PMS_CHECKED_OUT])
            ->with(['roomUnit'])
            ->orderBy('guest_first_name')
            ->orderBy('guest_last_name');
    }

    private function inHouseQuery(Hotel $hotel, Carbon $date)
    {
        return CmReservation::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', '!=', CmReservation::STATUS_CANCELLED)
            ->where('pms_status', CmReservation::PMS_CHECKED_IN)
            ->whereDate('checkin', '<=', $date)
            ->whereDate('checkout', '>', $date)
            ->with(['roomUnit'])
            ->orderBy('room_unit_id')
            ->orderBy('guest_first_name');
    }

    private function departuresQuery(Hotel $hotel, Carbon $date)
    {
        return CmReservation::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', '!=', CmReservation::STATUS_CANCELLED)
            ->whereDate('checkout', $date)
            ->whereIn('pms_status', [CmReservation::PMS_CHECKED_IN, CmReservation::PMS_ASSIGNED])
            ->with(['roomUnit'])
            ->orderBy('guest_first_name');
    }

    private function assertEditable(CmReservation $reservation): void
    {
        if ($reservation->isCancelled()) {
            throw new InvalidArgumentException('Cancelled bookings cannot be updated.');
        }
        if ($reservation->isCheckedOut()) {
            throw new InvalidArgumentException('Checked-out bookings cannot be updated.');
        }
    }

    /** @param  array<string, mixed>  $line */
    private function preferRoomWithUnits(HotelRoom $resolved, Collection $rooms, array $line): HotelRoom
    {
        if ($resolved->units()->exists()) {
            return $resolved;
        }
        $name = trim((string) ($line['roomName'] ?? $line['room_name'] ?? ''));
        if ($name !== '') {
            $byName = $this->matchRoomByName($rooms, $name, requireUnits: true);
            if ($byName !== null) {
                return $byName;
            }
        }
        $bySimilar = $this->matchRoomByName(
            $rooms,
            $resolved->display_name ?: $resolved->name,
            requireUnits: true,
        );
        if ($bySimilar !== null && (int) $bySimilar->id !== (int) $resolved->id) {
            return $bySimilar;
        }

        return $resolved;
    }

    /** @param  Collection<int, HotelRoom>  $rooms */
    private function matchRoomByName(Collection $rooms, string $name, bool $requireUnits = false): ?HotelRoom
    {
        $needle = strtolower(trim($name));
        if ($needle === '') {
            return null;
        }
        $exact = $rooms->first(function (HotelRoom $room) use ($needle, $requireUnits) {
            if ($requireUnits && (int) $room->units_count === 0) {
                return false;
            }
            foreach ([$room->name, $room->display_name] as $candidate) {
                if ($candidate !== null && strtolower(trim($candidate)) === $needle) {
                    return true;
                }
            }

            return false;
        });
        if ($exact !== null) {
            return $exact;
        }

        return $rooms
            ->filter(function (HotelRoom $room) use ($needle, $requireUnits) {
                if ($requireUnits && (int) $room->units_count === 0) {
                    return false;
                }
                foreach ([$room->name, $room->display_name] as $candidate) {
                    $candidate = strtolower(trim((string) $candidate));
                    if ($candidate === '') {
                        continue;
                    }
                    if (str_contains($needle, $candidate) || str_contains($candidate, $needle)) {
                        return true;
                    }
                }

                return false;
            })
            ->sortByDesc(fn (HotelRoom $room) => strlen(trim((string) ($room->display_name ?: $room->name))))
            ->first();
    }

    /**
     * @param  Collection<int, HotelRoomUnit>|SupportCollection<int, HotelRoomUnit>  $units
     * @return list<array{id: int, label: string, room_type: string}>
     */
    private function mapAvailableUnits(
        Collection|SupportCollection $units,
        CmReservation $reservation,
        ?string $defaultRoomType = null,
    ): array {
        return $units
            ->filter(fn (HotelRoomUnit $unit) => $this->isUnitAvailable($unit, $reservation, $reservation->id))
            ->map(function (HotelRoomUnit $unit) use ($defaultRoomType) {
                return [
                    'id' => $unit->id,
                    'label' => $unit->displayLabel(),
                    'room_type' => $defaultRoomType
                        ?: ($unit->roomType?->display_name ?: $unit->roomType?->name ?: '—'),
                ];
            })
            ->values()
            ->all();
    }
}
