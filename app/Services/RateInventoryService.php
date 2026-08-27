<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelRateInventory;
use App\Models\HotelRatePlan;
use Carbon\Carbon;

class RateInventoryService
{
    public const WINDOW_DAYS = 14;

    public function __construct(private RoomInventoryService $inventoryService) {}

    /** @return array<string, mixed> */
    public function calendarGrid(Hotel $hotel, Carbon $start): array
    {
        $rateGrid = $this->grid($hotel, $start);
        $inventoryGrid = $this->inventoryService->grid($hotel, $start);
        $dynamicRates = $hotel->settings?->rateplan['dynamic_rates'] ?? [];

        $inventoryRooms = collect($inventoryGrid['rooms'])->keyBy('id');
        $groupedRooms = [];

        foreach ($rateGrid['ratePlans'] as $plan) {
            $roomId = (int) $plan['hotel_room_id'];
            $roomRow = $inventoryRooms->get($roomId);

            if (! isset($groupedRooms[$roomId])) {
                $groupedRooms[$roomId] = [
                    'id' => $roomId,
                    'name' => $plan['room_name'],
                    'counts' => $roomRow['counts'] ?? array_fill_keys($rateGrid['dateKeys'], 0),
                    'rate_plans' => [],
                ];
            }

            $letter = $this->occupancyLetter((string) $plan['occupancy']);

            $displayRates = [];
            foreach ($rateGrid['dateKeys'] as $dateKey) {
                $cell = $plan['rates'][$dateKey];
                $displayRates[$dateKey] = $this->displayRate($cell, $plan['pricing_mode']);
            }

            $groupedRooms[$roomId]['rate_plans'][] = array_merge($plan, [
                'plan_label' => $plan['room_name'].' '.$plan['meal_plan'].', '.$letter,
                'occupancy_letter' => $letter,
                'display_rates' => $displayRates,
            ]);
        }

        $dynamic = [];
        foreach ($rateGrid['dateKeys'] as $dateKey) {
            $dynamic[$dateKey] = (bool) ($dynamicRates[$dateKey] ?? false);
        }

        $availabilityMeta = [];
        foreach ($rateGrid['dateKeys'] as $dateKey) {
            $otaStatus = $inventoryGrid['otaAvailability'][$dateKey] ?? [];
            $availabilityMeta[$dateKey] = [
                'state' => $inventoryGrid['isOpen'][$dateKey] ?? 'open',
                'stopped_channels' => $this->inventoryService->stoppedChannelNames($hotel, $otaStatus),
                'ota_status' => $otaStatus,
            ];
        }

        return array_merge($rateGrid, [
            'inventory' => $inventoryGrid,
            'roomGroups' => array_values($groupedRooms),
            'dynamicRates' => $dynamic,
            'availabilityMeta' => $availabilityMeta,
        ]);
    }

    /** @return array<string, mixed> */
    public function monthCalendar(Hotel $hotel, Carbon $month, ?int $roomId = null): array
    {
        $month = $month->copy()->startOfMonth();
        $gridStart = $month->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $rooms = $hotel->rooms()->where('is_enabled', true)->orderBy('rank')->orderBy('id')->get();
        $plans = $hotel->ratePlans()->with('room')->get()->filter(fn ($p) => $p->room !== null);

        if ($roomId !== null) {
            $plans = $plans->where('hotel_room_id', $roomId)->values();
        }

        $primaryPlan = $plans->first();
        $days = [];
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $dateKey = $cursor->format('Y-m-d');
            $dayStart = $cursor->copy()->startOfDay();

            $available = $this->availableCountForDate($hotel, $dayStart, $roomId);
            $price = $primaryPlan ? $this->cmRateForPlan($primaryPlan, $dateKey) : 0;

            if ($price <= 0 && $plans->count() > 1) {
                foreach ($plans as $plan) {
                    $candidate = $this->cmRateForPlan($plan, $dateKey);
                    if ($candidate > 0) {
                        $price = $candidate;
                        break;
                    }
                }
            }

            $days[] = [
                'date' => $dateKey,
                'day' => (int) $cursor->format('j'),
                'inMonth' => $cursor->month === $month->month,
                'isToday' => $cursor->isToday(),
                'price' => $price > 0 ? $price : null,
                'available' => $available,
                'occupancy' => 0,
            ];

            $cursor->addDay();
        }

        $weeks = array_chunk($days, 7);
        $dayDetails = $this->buildMonthDayDetails($hotel, $plans, $rooms, $gridStart, $gridEnd, $roomId);

        return [
            'month' => $month->format('Y-m'),
            'monthLabel' => $month->format('F Y'),
            'weeks' => $weeks,
            'rooms' => $rooms->map(fn ($room) => ['id' => $room->id, 'name' => $room->name])->values()->all(),
            'selectedRoomId' => $roomId,
            'dayDetails' => $dayDetails,
        ];
    }

    /** @param \Illuminate\Support\Collection<int, HotelRatePlan> $plans @param \Illuminate\Support\Collection<int, \App\Models\HotelRoom> $rooms @return array<string, array<string, mixed>> */
    private function buildMonthDayDetails(Hotel $hotel, $plans, $rooms, Carbon $gridStart, Carbon $gridEnd, ?int $roomId): array
    {
        $rateplan = is_array($hotel->settings?->rateplan) ? $hotel->settings->rateplan : [];
        $dynamicRates = $rateplan['dynamic_rates'] ?? [];
        $inventoryReallocation = $rateplan['inventory_reallocation'] ?? [];

        $details = [];
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $dateKey = $cursor->format('Y-m-d');
            $rateRows = [];

            foreach ($plans as $plan) {
                if (! $plan->room) {
                    continue;
                }

                $rates = $this->rateForDate($plan, $dateKey);
                $display = $this->displayRate($rates, $plan->pricing_mode ?? HotelRatePlan::PRICING_BOTH);

                $rateRows[] = [
                    'room' => $plan->room->name,
                    'mealplan' => $plan->meal_plan ?: 'EP',
                    'occupancy' => $this->occupancyLetter((string) ($plan->occupancy ?? 'Standard')),
                    'rate' => $display !== null ? (int) round($display) : 0,
                ];
            }

            $inventoryRows = [];
            $roomsForDay = $roomId !== null
                ? $rooms->where('id', $roomId)
                : $rooms;

            foreach ($roomsForDay as $room) {
                $inventoryRows[] = [
                    'room' => $room->name,
                    'inventory' => $this->availableCountForRoomOnDate($room, $dateKey),
                ];
            }

            $details[$dateKey] = [
                'label' => $cursor->format('j F Y'),
                'dynamic_rates' => (bool) ($dynamicRates[$dateKey] ?? false),
                'inventory_reallocation' => (bool) ($inventoryReallocation[$dateKey] ?? false),
                'rates' => $rateRows,
                'inventory' => $inventoryRows,
            ];

            $cursor->addDay();
        }

        return $details;
    }

    private function availableCountForRoomOnDate($room, string $dateKey): int
    {
        $row = \App\Models\HotelRoomInventory::query()
            ->where('hotel_room_id', $room->id)
            ->where('date', $dateKey)
            ->first();

        return $row ? (int) $row->available_count : (int) $room->room_count;
    }

    private function availableCountForDate(Hotel $hotel, Carbon $date, ?int $roomId): int
    {
        $dateKey = $date->format('Y-m-d');

        if ($roomId !== null) {
            $room = $hotel->rooms()->find($roomId);
            if (! $room) {
                return 0;
            }

            $row = \App\Models\HotelRoomInventory::query()
                ->where('hotel_room_id', $roomId)
                ->where('date', $dateKey)
                ->first();

            return $row ? (int) $row->available_count : (int) $room->room_count;
        }

        return (int) $hotel->rooms()
            ->where('is_enabled', true)
            ->get()
            ->sum(function ($room) use ($dateKey) {
                $row = \App\Models\HotelRoomInventory::query()
                    ->where('hotel_room_id', $room->id)
                    ->where('date', $dateKey)
                    ->first();

                return $row ? (int) $row->available_count : (int) $room->room_count;
            });
    }

    /** @return array<string, mixed> */
    public function grid(Hotel $hotel, Carbon $start): array
    {
        $dates = $this->inventoryService->dateRange($start);
        $dateKeys = $dates->map(fn (Carbon $d) => $d->format('Y-m-d'))->all();

        $plans = $hotel->ratePlans()
            ->with('room')
            ->get()
            ->filter(fn (HotelRatePlan $plan) => $plan->room !== null)
            ->values();

        $overrides = HotelRateInventory::query()
            ->where('hotel_id', $hotel->id)
            ->whereIn('hotel_rate_plan_id', $plans->pluck('id'))
            ->whereBetween('date', [$dateKeys[0], end($dateKeys)])
            ->get()
            ->groupBy('hotel_rate_plan_id');

        $rows = [];

        foreach ($plans as $plan) {
            $defaults = $this->defaultRates($plan);
            $planOverrides = $overrides->get($plan->id, collect())
                ->keyBy(fn (HotelRateInventory $row) => $row->date->format('Y-m-d'));

            $rates = [];
            foreach ($dateKeys as $dateKey) {
                $override = $planOverrides[$dateKey] ?? null;
                $rates[$dateKey] = [
                    'local' => $override?->local_rate !== null ? (float) $override->local_rate : $defaults['local'],
                    'international' => $override?->international_rate !== null ? (float) $override->international_rate : $defaults['international'],
                ];
            }

            $rows[] = [
                'id' => $plan->id,
                'hotel_room_id' => $plan->hotel_room_id,
                'room_name' => $plan->room?->name ?? 'Room',
                'meal_plan' => $plan->meal_plan,
                'occupancy' => $plan->occupancy ?? 'Standard',
                'label' => ($plan->room?->name ?? 'Room').' · '.$plan->meal_plan,
                'pricing_mode' => $plan->pricing_mode ?? HotelRatePlan::PRICING_BOTH,
                'local_currency' => $plan->local_currency ?? $hotel->currency ?? 'TZS',
                'international_currency' => $plan->international_currency ?? 'USD',
                'defaults' => $defaults,
                'rates' => $rates,
            ];
        }

        return [
            'dates' => $dates,
            'dateKeys' => $dateKeys,
            'ratePlans' => $rows,
        ];
    }

    /** @param array<int, array<string, array<string, mixed>>> $submitted */
    public function save(Hotel $hotel, Carbon $start, array $submitted): void
    {
        $dateKeys = $this->inventoryService->dateRange($start)
            ->map(fn (Carbon $d) => $d->format('Y-m-d'))
            ->all();

        $validPlanIds = array_flip(
            $hotel->ratePlans()->pluck('id')->map(fn ($id) => (int) $id)->all()
        );

        foreach ($submitted as $planId => $dates) {
            if (! isset($validPlanIds[(int) $planId]) || ! is_array($dates)) {
                continue;
            }

            $plan = $hotel->ratePlans()->with('room')->find($planId);
            if (! $plan) {
                continue;
            }

            $defaults = $this->defaultRates($plan);

            foreach ($dateKeys as $dateKey) {
                if (! array_key_exists($dateKey, $dates)) {
                    continue;
                }

                $row = $dates[$dateKey];
                [$local, $international] = $this->resolveSavedRates($plan, $row);

                $sameAsDefault = $this->ratesMatch($local, $defaults['local'])
                    && $this->ratesMatch($international, $defaults['international']);

                if ($sameAsDefault) {
                    HotelRateInventory::query()
                        ->where('hotel_rate_plan_id', (int) $planId)
                        ->where('date', $dateKey)
                        ->delete();

                    continue;
                }

                HotelRateInventory::query()->updateOrCreate(
                    [
                        'hotel_rate_plan_id' => (int) $planId,
                        'date' => $dateKey,
                    ],
                    [
                        'hotel_id' => $hotel->id,
                        'local_rate' => $local,
                        'international_rate' => $international,
                    ]
                );
            }
        }
    }

    /** @return array{local: ?float, international: ?float} */
    public function rateForDate(HotelRatePlan $plan, string $dateKey): array
    {
        $override = HotelRateInventory::query()
            ->where('hotel_rate_plan_id', $plan->id)
            ->where('date', $dateKey)
            ->first();

        $defaults = $this->defaultRates($plan);

        return [
            'local' => $override?->local_rate !== null ? (float) $override->local_rate : $defaults['local'],
            'international' => $override?->international_rate !== null ? (float) $override->international_rate : $defaults['international'],
        ];
    }

    /** @return array{local: ?float, international: ?float} */
    public function defaultRates(HotelRatePlan $plan): array
    {
        $local = $plan->pricing_mode === HotelRatePlan::PRICING_INTERNATIONAL
            ? null
            : (($plan->local_base_rate !== null && (float) $plan->local_base_rate > 0)
                ? (float) $plan->local_base_rate
                : null);

        $international = $plan->pricing_mode === HotelRatePlan::PRICING_LOCAL
            ? null
            : ((float) $plan->base_rate > 0 ? (float) $plan->base_rate : null);

        return [
            'local' => $local,
            'international' => $international,
        ];
    }

    public function cmRateForPlan(HotelRatePlan $plan, string $dateKey): float
    {
        $rates = $this->rateForDate($plan, $dateKey);

        if ($rates['international'] !== null && $rates['international'] > 0) {
            return $rates['international'];
        }

        return (float) ($rates['local'] ?? 0);
    }

    private function normalizeRate(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $amount = max(0, (float) $value);

        return $amount > 0 ? $amount : null;
    }

    private function ratesMatch(?float $a, ?float $b): bool
    {
        if ($a === null && $b === null) {
            return true;
        }

        if ($a === null || $b === null) {
            return false;
        }

        return abs($a - $b) < 0.001;
    }

    /** @param array<string, mixed> $row @return array{0: ?float, 1: ?float} */
    private function resolveSavedRates(HotelRatePlan $plan, array $row): array
    {
        if (array_key_exists('amount', $row)) {
            $amount = $this->normalizeRate($row['amount']);

            return match ($plan->pricing_mode ?? HotelRatePlan::PRICING_BOTH) {
                HotelRatePlan::PRICING_LOCAL => [$amount, null],
                HotelRatePlan::PRICING_INTERNATIONAL => [null, $amount],
                default => [$amount, $amount],
            };
        }

        return [
            $this->normalizeRate($row['local'] ?? null),
            $this->normalizeRate($row['international'] ?? null),
        ];
    }

    /** @param array{local: ?float, international: ?float} $cell */
    private function displayRate(array $cell, string $pricingMode): ?float
    {
        if ($pricingMode === HotelRatePlan::PRICING_LOCAL) {
            return $cell['local'];
        }

        if ($pricingMode === HotelRatePlan::PRICING_INTERNATIONAL) {
            return $cell['international'];
        }

        if ($cell['international'] !== null && $cell['international'] > 0) {
            return $cell['international'];
        }

        return $cell['local'];
    }

    private function occupancyLetter(string $occupancy): string
    {
        if (preg_match('/\(([sdtq])\)/i', $occupancy, $matches)) {
            return strtoupper($matches[1]);
        }

        $lower = strtolower($occupancy);

        if (str_contains($lower, 'quad')) {
            return 'Q';
        }

        if (str_contains($lower, 'triple')) {
            return 'T';
        }

        if (str_contains($lower, 'double')) {
            return 'D';
        }

        return 'S';
    }
}
