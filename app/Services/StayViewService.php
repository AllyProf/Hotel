<?php

namespace App\Services;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelRatePlan;
use App\Models\HotelRoom;
use App\Models\HotelRoomUnit;
use App\Services\ChannelManager\ChannelManagerCodeResolver;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class StayViewService
{
    public const WINDOW_DAYS = 7;

    public const SAMPLE_BOOKING_PREFIX = 'STAYVIEW-DEMO-';

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
            ->with('roomUnit')
            ->orderBy('checkin')
            ->get();

        $reservations = $this->mergeDemoReservations($hotel, $start, $rooms, $reservations);

        $preassignedIds = $reservations->whereNotNull('room_unit_id')->pluck('id')->all();

        $barRate = $this->defaultBarRate($hotel);
        $roomTypes = [];
        $allUnits = collect();
        $summary = $this->emptySummary($dateKeys, $barRate);

        foreach ($rooms as $room) {
            $units = $this->unitsForRoom($room);
            $unitRows = [];
            $assignedReservationIds = $preassignedIds;

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
                'date_counts' => $this->dateCountsForRoomType($room, $unitRows, $dateKeys, $reservations),
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

        $dateHeaderCounts = [];
        foreach ($dateKeys as $dateKey) {
            $dateHeaderCounts[$dateKey] = (int) ($summary[$dateKey]['arriving'] ?? 0);
        }

        $isDemoGrid = $reservations->contains(fn (CmReservation $r) => $this->isSampleBooking($r));

        $hasSampleBookings = CmReservation::query()
            ->where('hotel_id', $hotel->id)
            ->where('booking_id', 'like', self::SAMPLE_BOOKING_PREFIX.'%')
            ->exists();

        return [
            'start' => $start,
            'dates' => $dates,
            'date_keys' => $dateKeys,
            'date_header_counts' => $dateHeaderCounts,
            'is_demo' => $isDemoGrid,
            'has_sample_bookings' => $hasSampleBookings,
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
        $dayCount = $dates->count();

        if ($unit['status'] === HotelRoomUnit::STATUS_MAINTENANCE) {
            return [[
                'type' => 'maintenance',
                'label' => 'Out of Order',
                'colspan' => max(1, $dayCount),
            ]];
        }

        $cells = [];
        $index = 0;
        $dateKeys = $dates->map(fn (Carbon $d) => $d->format('Y-m-d'))->all();
        $usedOnUnit = [];

        while ($index < count($dateKeys)) {
            $dateKey = $dateKeys[$index];
            $reservation = $this->reservationForUnitStartingAt(
                $unit,
                $dateKey,
                $dateKeys,
                $index,
                $reservations,
                $usedOnUnit,
                $room,
                $assignedReservationIds,
            );

            if ($reservation !== null) {
                $span = $this->staySpan($reservation, $dateKey, $dateKeys);
                $barStatus = $this->stayBarStatusClass($reservation);
                $cells[] = [
                    'type' => 'booking',
                    'colspan' => $span,
                    'guest' => $reservation->guestName(),
                    'channel' => $reservation->channel,
                    'status' => $barStatus,
                    'status_label' => $this->bookingStatusLabel($reservation, $dateKey),
                    'booking_id' => $reservation->booking_id,
                ];

                $usedOnUnit[] = $reservation->id;

                if (! $reservation->room_unit_id) {
                    $assignedReservationIds[] = $reservation->id;
                }

                $index += $span;

                $checkoutKey = $reservation->checkout?->format('Y-m-d');
                if ($checkoutKey
                    && ($dateKeys[$index] ?? null) === $checkoutKey
                    && $reservation->pms_status !== CmReservation::PMS_CHECKED_OUT) {
                    $cells[] = [
                        'type' => 'booking',
                        'colspan' => 1,
                        'guest' => $reservation->guestName(),
                        'channel' => $reservation->channel,
                        'status' => 'checking-out',
                        'status_label' => 'Checking out',
                        'booking_id' => $reservation->booking_id,
                    ];
                    $index++;
                }

                continue;
            }

            $cells[] = ['type' => 'empty', 'colspan' => 1];
            $index++;
        }

        return $cells;
    }

    /**
     * @param  list<int|string>  $usedOnUnit
     * @param  list<int>  $assignedReservationIds
     */
    private function reservationForUnitStartingAt(
        array $unit,
        string $dateKey,
        array $dateKeys,
        int $index,
        Collection $reservations,
        array $usedOnUnit,
        HotelRoom $room,
        array $assignedReservationIds,
    ): ?CmReservation {
        $unitId = $unit['id'] ?? null;

        if ($unitId !== null) {
            $assigned = $reservations->first(function (CmReservation $reservation) use ($unitId, $dateKey, $dateKeys, $index, $usedOnUnit) {
                if (in_array($reservation->id, $usedOnUnit, true)) {
                    return false;
                }

                if (! in_array((int) $unitId, $reservation->assignedRoomUnitIds(), true)) {
                    return false;
                }

                return $this->stayStartsOnGridDate($reservation, $dateKey, $dateKeys, $index);
            });

            if ($assigned !== null) {
                return $assigned;
            }
        }

        return $this->reservationStartingOn($reservations, $dateKey, $room, $assignedReservationIds);
    }

    /** @param  list<string>  $dateKeys */
    private function stayStartsOnGridDate(CmReservation $reservation, string $dateKey, array $dateKeys, int $index): bool
    {
        $checkin = $reservation->checkin?->format('Y-m-d');
        $checkout = $reservation->checkout?->format('Y-m-d');

        if (! $checkin || ! $checkout || $dateKey < $checkin || $dateKey >= $checkout) {
            return false;
        }

        return $dateKey === $checkin || ($index === 0 && $checkin < $dateKeys[0]);
    }

    private function stayBarStatusClass(CmReservation $reservation): string
    {
        if ($reservation->isComplimentary()) {
            return $reservation->pms_status === CmReservation::PMS_CHECKED_OUT
                ? 'checked-out'
                : 'complimentary';
        }

        return match ($reservation->pms_status) {
            CmReservation::PMS_CHECKED_OUT => 'checked-out',
            CmReservation::PMS_CHECKED_IN => 'checked-in',
            default => 'assigned',
        };
    }

    /** @param  list<int>  $assignedReservationIds */
    private function reservationStartingOn(Collection $reservations, string $dateKey, HotelRoom $room, array $assignedReservationIds): ?CmReservation
    {
        return $reservations->first(function (CmReservation $reservation) use ($dateKey, $room, $assignedReservationIds) {
            if ($reservation->room_unit_id) {
                return false;
            }

            if (in_array($reservation->id, $assignedReservationIds, true)) {
                return false;
            }

            if ($reservation->checkin?->format('Y-m-d') !== $dateKey) {
                return false;
            }

            return $this->reservationMatchesRoom($reservation, $room);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $unitRows
     * @param  list<string>  $dateKeys
     * @return array<string, int>
     */
    private function dateCountsForRoomType(HotelRoom $room, array $unitRows, array $dateKeys, Collection $reservations): array
    {
        $unitIds = collect($unitRows)
            ->pluck('id')
            ->filter(fn ($id) => is_int($id))
            ->map(fn ($id) => (int) $id)
            ->all();

        $counts = [];
        foreach ($dateKeys as $dateKey) {
            $counts[$dateKey] = $reservations->filter(function (CmReservation $reservation) use ($dateKey, $room, $unitIds) {
                $checkin = $reservation->checkin?->format('Y-m-d');
                $checkout = $reservation->checkout?->format('Y-m-d');

                if (! $checkin || ! $checkout || $dateKey < $checkin || $dateKey >= $checkout) {
                    return false;
                }

                foreach ($reservation->assignedRoomUnitIds() as $unitId) {
                    if (in_array((int) $unitId, $unitIds, true)) {
                        return true;
                    }
                }

                if (count($reservation->assignedRoomUnitIds()) === 0 && $this->reservationMatchesRoom($reservation, $room)) {
                    return true;
                }

                return false;
            })->count();
        }

        return $counts;
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
            if ($checkin === $dateKey && $checkin === $today) {
                return 'assigned';
            }

            if ($checkin && $checkout && $dateKey >= $checkin && $dateKey < $checkout && $dateKey <= $today) {
                return 'assigned';
            }
        }

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

    /** @return array{created: int, message: string} */
    public function seedSampleBookings(Hotel $hotel, Carbon $start): array
    {
        if (! $this->sampleBookingsAllowed()) {
            throw new InvalidArgumentException('Sample bookings are only available in local/testing environments.');
        }

        $rooms = $hotel->rooms()
            ->where('is_enabled', true)
            ->with(['units' => fn ($q) => $q->orderBy('room_number')->orderBy('id')])
            ->orderBy('rank')
            ->orderBy('id')
            ->get();

        if ($rooms->isEmpty()) {
            throw new InvalidArgumentException('Add rooms under Settings → Rooms before generating sample bookings.');
        }

        $this->clearSampleBookings($hotel);

        $units = $this->collectPhysicalUnits($rooms);
        $codes = app(ChannelManagerCodeResolver::class);
        $hotelCode = $codes->hotelCode($hotel);
        $currency = $hotel->currency ?? 'TZS';
        $start = $start->copy()->startOfDay();

        $created = 0;
        $seq = 0;

        foreach ($this->sampleBookingScenarios() as $scenario) {
            $seq++;
            $checkin = $start->copy()->addDays((int) $scenario['offset']);
            $checkout = $checkin->copy()->addDays((int) $scenario['nights']);

            $room = $rooms->first();
            $unitId = null;

            if ($units !== []) {
                $slot = $units[$scenario['unit'] % count($units)];
                $room = $slot['room'];
                $unitId = (int) $slot['unit']['id'];
            }

            $roomCode = $codes->roomCode($hotel, $room);
            $amount = 120.0 * (int) $scenario['nights'];

            CmReservation::query()->create([
                'hotel_id' => $hotel->id,
                'hotel_code' => $hotelCode,
                'booking_id' => self::SAMPLE_BOOKING_PREFIX.$hotel->id.'-'.$seq,
                'channel' => 'Direct',
                'action' => 'book',
                'status' => CmReservation::STATUS_CONFIRMED,
                'pms_status' => $scenario['pms'],
                'room_unit_id' => $unitId,
                'checkin' => $checkin,
                'checkout' => $checkout,
                'guest_first_name' => $scenario['first'],
                'guest_last_name' => $scenario['last'],
                'amount_after_tax' => $amount,
                'amount_paid' => 0,
                'amount_before_tax' => round($amount * 0.85, 2),
                'tax' => round($amount * 0.15, 2),
                'currency' => $currency,
                'rooms' => [[
                    'roomCode' => $roomCode,
                    'roomName' => $room->display_name ?: $room->name,
                    'rateplanCode' => $roomCode.'-s-ep',
                    'mealPlan' => 'EP',
                    'numberOfRooms' => 1,
                    'occupancy' => ['adults' => 2, 'children' => 0],
                ]],
                'payload' => [
                    'source' => 'stay_view_sample',
                    'room_unit_ids' => $unitId ? [$unitId] : [],
                ],
            ]);

            $created++;
        }

        return [
            'created' => $created,
            'message' => "Generated {$created} sample room bookings for this week.",
        ];
    }

    public function clearSampleBookings(Hotel $hotel): int
    {
        return (int) CmReservation::query()
            ->where('hotel_id', $hotel->id)
            ->where('booking_id', 'like', self::SAMPLE_BOOKING_PREFIX.'%')
            ->delete();
    }

    private function sampleBookingsAllowed(): bool
    {
        if (! config('hotel_pms.stay_view.allow_sample_bookings', true)) {
            return false;
        }

        return app()->environment('local', 'testing');
    }

    private function isSampleBooking(CmReservation $reservation): bool
    {
        $bookingId = (string) $reservation->booking_id;

        return str_starts_with($bookingId, self::SAMPLE_BOOKING_PREFIX)
            || str_starts_with($bookingId, 'DEMO-');
    }

    /** @param  Collection<int, HotelRoom>  $rooms
     * @return list<array{room: HotelRoom, unit: array{id: int, label: string, status: string}}>
     */
    private function collectPhysicalUnits(Collection $rooms): array
    {
        $units = [];

        foreach ($rooms as $room) {
            foreach ($this->unitsForRoom($room) as $unit) {
                if (is_int($unit['id'])) {
                    $units[] = ['room' => $room, 'unit' => $unit];
                }
            }
        }

        return $units;
    }

    /** @return list<array{unit: int, offset: int, nights: int, pms: string, first: string, last: string}> */
    private function sampleBookingScenarios(): array
    {
        return [
            ['unit' => 0, 'offset' => 0, 'nights' => 3, 'pms' => CmReservation::PMS_CHECKED_IN, 'first' => 'Thanyaphat', 'last' => 'S'],
            ['unit' => 1, 'offset' => 0, 'nights' => 2, 'pms' => CmReservation::PMS_CHECKED_IN, 'first' => 'Rahul', 'last' => 'Luthra'],
            ['unit' => 1, 'offset' => 2, 'nights' => 3, 'pms' => CmReservation::PMS_CHECKED_IN, 'first' => 'P', 'last' => 'Subhash'],
            ['unit' => 2, 'offset' => 0, 'nights' => 4, 'pms' => CmReservation::PMS_CHECKED_IN, 'first' => 'Maria', 'last' => 'Garcia'],
            ['unit' => 3, 'offset' => 1, 'nights' => 2, 'pms' => CmReservation::PMS_ASSIGNED, 'first' => 'James', 'last' => 'Chen'],
            ['unit' => 4, 'offset' => 0, 'nights' => 5, 'pms' => CmReservation::PMS_CHECKED_IN, 'first' => 'Asha', 'last' => 'Patel'],
            ['unit' => 5, 'offset' => 2, 'nights' => 4, 'pms' => CmReservation::PMS_CHECKED_IN, 'first' => 'David', 'last' => 'Kim'],
            ['unit' => 6, 'offset' => 0, 'nights' => 2, 'pms' => CmReservation::PMS_ASSIGNED, 'first' => 'Sarah', 'last' => 'Nguyen'],
            ['unit' => 7, 'offset' => 3, 'nights' => 3, 'pms' => CmReservation::PMS_ASSIGNED, 'first' => 'Omar', 'last' => 'Hassan'],
            ['unit' => 8, 'offset' => 1, 'nights' => 3, 'pms' => CmReservation::PMS_CHECKED_IN, 'first' => 'Lin', 'last' => 'Wei'],
            ['unit' => 9, 'offset' => 4, 'nights' => 2, 'pms' => CmReservation::PMS_CHECKED_IN, 'first' => 'Emma', 'last' => 'Brown'],
        ];
    }

    /** @param  Collection<int, HotelRoom>  $rooms
     * @param  Collection<int, CmReservation>  $existing
     * @return Collection<int, CmReservation>
     */
    private function mergeDemoReservations(Hotel $hotel, Carbon $start, Collection $rooms, Collection $existing): Collection
    {
        if (! config('hotel_pms.stay_view.demo_on_empty', false) || ! app()->environment('local')) {
            return $existing;
        }

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        if ($rooms->isEmpty()) {
            return $existing;
        }

        $units = $this->collectPhysicalUnits($rooms);

        if ($units === []) {
            return $existing;
        }

        $demo = collect();
        $demoId = -1000;

        foreach ($this->sampleBookingScenarios() as $scenario) {
            $slot = $units[$scenario['unit'] % count($units)] ?? null;
            if ($slot === null) {
                continue;
            }

            $checkin = $start->copy()->addDays((int) $scenario['offset']);
            $checkout = $checkin->copy()->addDays((int) $scenario['nights']);
            $unitId = (int) $slot['unit']['id'];

            $reservation = new CmReservation([
                'hotel_id' => $hotel->id,
                'booking_id' => 'DEMO-'.abs($demoId),
                'channel' => 'Direct',
                'status' => CmReservation::STATUS_CONFIRMED,
                'pms_status' => $scenario['pms'],
                'room_unit_id' => $unitId,
                'checkin' => $checkin,
                'checkout' => $checkout,
                'guest_first_name' => $scenario['first'],
                'guest_last_name' => $scenario['last'],
                'payload' => ['room_unit_ids' => [$unitId]],
            ]);
            $reservation->id = $demoId;
            $demoId--;

            $demo->push($reservation);
        }

        return $demo->concat($existing);
    }
}
