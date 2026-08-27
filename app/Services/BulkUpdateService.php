<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelRateInventory;
use App\Models\HotelRatePlan;
use App\Models\HotelRoomInventory;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class BulkUpdateService
{
    public function __construct(
        private RateInventoryService $rates,
        private RoomInventoryService $inventory,
        private OtaConnectionService $otaConnections,
        private HotelSettingsService $settingsService,
    ) {}

    /** @param list<string> $weekdays @return list<string> */
    public function matchingDates(Carbon $from, Carbon $to, array $weekdays): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $dayKeys = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
        $allowed = in_array('all', $weekdays, true)
            ? $dayKeys
            : array_values(array_intersect($dayKeys, $weekdays));

        $dates = [];
        foreach (CarbonPeriod::create($from, $to) as $date) {
            $key = strtolower($date->format('D'));
            if (in_array($key, $allowed, true)) {
                $dates[] = $date->format('Y-m-d');
            }
        }

        return $dates;
    }

    /** @param array<int, int|string> $rooms */
    public function applyInventory(Hotel $hotel, array $dates, array $rooms): int
    {
        $validIds = array_flip(
            $hotel->rooms()->where('is_enabled', true)->pluck('id')->map(fn ($id) => (int) $id)->all()
        );
        $updated = 0;

        foreach ($dates as $dateKey) {
            foreach ($rooms as $roomId => $count) {
                $roomId = (int) $roomId;
                if (! isset($validIds[$roomId])) {
                    continue;
                }

                HotelRoomInventory::query()->updateOrCreate(
                    ['hotel_room_id' => $roomId, 'date' => $dateKey],
                    [
                        'hotel_id' => $hotel->id,
                        'available_count' => max(0, min(999, (int) $count)),
                    ]
                );
                $updated++;
            }
        }

        return $updated;
    }

    /** @param array<int, float|int|string> $plans */
    public function applyRates(Hotel $hotel, array $dates, array $plans): int
    {
        $updated = 0;

        foreach ($plans as $planId => $amount) {
            $plan = $hotel->ratePlans()->with('room')->find((int) $planId);
            if (! $plan) {
                continue;
            }

            $amount = max(0, (float) $amount);
            foreach ($dates as $dateKey) {
                $this->savePlanRate($hotel, $plan, $dateKey, $amount);
                $updated++;
            }
        }

        return $updated;
    }

    /** @param array<int, float|int|string> $plans */
    public function applyRatio(Hotel $hotel, array $dates, array $plans, float $ratio): int
    {
        $ratio = max(0.1, $ratio);
        $updated = 0;

        foreach ($plans as $planId => $_) {
            $plan = $hotel->ratePlans()->find((int) $planId);
            if (! $plan) {
                continue;
            }

            foreach ($dates as $dateKey) {
                $current = $this->rates->cmRateForPlan($plan, $dateKey);
                $this->savePlanRate($hotel, $plan, $dateKey, round($current * $ratio, 2));
                $updated++;
            }
        }

        return $updated;
    }

    /** @param array<int, float|int|string> $plans */
    public function applyIncrement(Hotel $hotel, array $dates, array $plans, float $increment): int
    {
        $updated = 0;

        foreach ($plans as $planId => $_) {
            $plan = $hotel->ratePlans()->find((int) $planId);
            if (! $plan) {
                continue;
            }

            foreach ($dates as $dateKey) {
                $current = $this->rates->cmRateForPlan($plan, $dateKey);
                $this->savePlanRate($hotel, $plan, $dateKey, max(0, round($current + $increment, 2)));
                $updated++;
            }
        }

        return $updated;
    }

    /** @param array<int, array<string, mixed>> $plans @param list<string> $channels */
    public function applyRateRestrictions(Hotel $hotel, array $dates, array $channels, array $plans): int
    {
        $settings = $this->settingsService->ensureDefaults($hotel);
        $rateplan = is_array($settings->rateplan) ? $settings->rateplan : [];
        $stored = $rateplan['rate_restrictions'] ?? [];
        $updated = 0;

        foreach ($plans as $planId => $row) {
            $planId = (string) (int) $planId;
            if (! isset($stored[$planId]) || ! is_array($stored[$planId])) {
                $stored[$planId] = [];
            }

            foreach ($dates as $dateKey) {
                $stored[$planId][$dateKey] = $this->normalizeRestrictionRow($row);
                if (! empty($row['stop_sell'])) {
                    $this->applyStopSell($hotel, $dateKey, $channels, true);
                }
                $updated++;
            }
        }

        $rateplan['rate_restrictions'] = $stored;
        $settings->update(['rateplan' => $rateplan]);

        return $updated;
    }

    /** @param array<int, array<string, mixed>> $rooms @param list<string> $channels */
    public function applyInventoryRestrictions(Hotel $hotel, array $dates, array $channels, array $rooms): int
    {
        $settings = $this->settingsService->ensureDefaults($hotel);
        $rateplan = is_array($settings->rateplan) ? $settings->rateplan : [];
        $stored = $rateplan['inventory_restrictions'] ?? [];
        $updated = 0;

        foreach ($rooms as $roomId => $row) {
            $roomId = (string) (int) $roomId;
            if (! isset($stored[$roomId]) || ! is_array($stored[$roomId])) {
                $stored[$roomId] = [];
            }

            foreach ($dates as $dateKey) {
                $stored[$roomId][$dateKey] = $this->normalizeRestrictionRow($row);
                if (! empty($row['stop_sell'])) {
                    $this->applyStopSell($hotel, $dateKey, $channels, true);
                }
                $updated++;
            }
        }

        $rateplan['inventory_restrictions'] = $stored;
        $settings->update(['rateplan' => $rateplan]);

        return $updated;
    }

    /** @return array<string, mixed> */
    public function planRow(HotelRatePlan $plan): array
    {
        return [
            'id' => $plan->id,
            'label' => trim(($plan->room?->name ?? 'Room').' '.$this->occupancyLetter((string) $plan->occupancy).' '.($plan->meal_plan ?: 'EP')),
        ];
    }

    private function savePlanRate(Hotel $hotel, HotelRatePlan $plan, string $dateKey, float $amount): void
    {
        $mode = $plan->pricing_mode ?? HotelRatePlan::PRICING_BOTH;
        [$local, $international] = match ($mode) {
            HotelRatePlan::PRICING_LOCAL => [$amount, null],
            HotelRatePlan::PRICING_INTERNATIONAL => [null, $amount],
            default => [$amount, $amount],
        };

        HotelRateInventory::query()->updateOrCreate(
            ['hotel_rate_plan_id' => $plan->id, 'date' => $dateKey],
            [
                'hotel_id' => $hotel->id,
                'local_rate' => $local,
                'international_rate' => $international,
            ]
        );
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeRestrictionRow(array $row): array
    {
        return [
            'stop_sell' => ! empty($row['stop_sell']),
            'close_on_arrival' => ! empty($row['close_on_arrival']),
            'close_on_departure' => ! empty($row['close_on_departure']),
            'min_stay' => isset($row['min_stay']) && $row['min_stay'] !== '' ? (int) $row['min_stay'] : null,
            'min_stay_arrival' => isset($row['min_stay_arrival']) && $row['min_stay_arrival'] !== '' ? (int) $row['min_stay_arrival'] : null,
            'max_stay' => isset($row['max_stay']) && $row['max_stay'] !== '' ? (int) $row['max_stay'] : null,
            'max_stay_arrival' => isset($row['max_stay_arrival']) && $row['max_stay_arrival'] !== '' ? (int) $row['max_stay_arrival'] : null,
            'exact_stay_arrival' => isset($row['exact_stay_arrival']) && $row['exact_stay_arrival'] !== '' ? (int) $row['exact_stay_arrival'] : null,
            'min_advance' => isset($row['min_advance']) && $row['min_advance'] !== '' ? (int) $row['min_advance'] : null,
            'max_advance' => isset($row['max_advance']) && $row['max_advance'] !== '' ? (int) $row['max_advance'] : null,
        ];
    }

    /** @param list<string> $channels */
    private function applyStopSell(Hotel $hotel, string $dateKey, array $channels, bool $stop): void
    {
        $slugs = $this->otaConnections->configuredSlugs($hotel);
        if ($slugs === []) {
            return;
        }

        $targetSlugs = $channels === [] || in_array('all', $channels, true)
            ? $slugs
            : array_values(array_intersect($slugs, $channels));

        if ($targetSlugs === []) {
            return;
        }

        $existing = \App\Models\HotelInventoryDay::query()
            ->where('hotel_id', $hotel->id)
            ->where('date', $dateKey)
            ->first();

        $status = is_array($existing?->ota_status)
            ? $this->inventory->normalizeOtaStatus($hotel, $existing->ota_status)
            : $this->inventory->defaultOtaStatus($hotel);

        foreach ($targetSlugs as $slug) {
            $status[$slug] = ! $stop;
        }

        $this->inventory->setDayOtaStatus($hotel, $dateKey, $status);
    }

    private function occupancyLetter(string $occupancy): string
    {
        if (preg_match('/\(([sdtq])\)/i', $occupancy, $matches)) {
            return strtoupper($matches[1]);
        }

        $lower = strtolower($occupancy);

        return match (true) {
            str_contains($lower, 'quad') => 'Q',
            str_contains($lower, 'triple') => 'T',
            str_contains($lower, 'double') => 'D',
            default => 'S',
        };
    }
}
