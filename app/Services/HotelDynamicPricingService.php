<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelRateInventory;
use App\Models\HotelRatePlan;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

class HotelDynamicPricingService
{
    public function __construct(
        private RateInventoryService $rates,
    ) {}

    /** @return array<string, mixed> */
    public function uiConfig(): array
    {
        return config('hotel_analytics.dynamic_pricing', []);
    }

    /** @return array<string, mixed> */
    public function filtersFromRequest(Request $request): array
    {
        $ui = $this->uiConfig();
        $futureDays = (int) ($ui['default_future_days'] ?? 30);

        $pastDate = $request->input('past_date', now()->format('Y-m-d'));
        $futureFrom = $request->input('future_from', now()->format('Y-m-d'));
        $futureTo = $request->input('future_to', now()->addDays($futureDays)->format('Y-m-d'));

        if ($futureFrom > $futureTo) {
            [$futureFrom, $futureTo] = [$futureTo, $futureFrom];
        }

        return [
            'past_date' => $pastDate,
            'future_from' => $futureFrom,
            'future_to' => $futureTo,
        ];
    }

    /** @return array<string, mixed> */
    public function report(Hotel $hotel, array $filters): array
    {
        $ui = $this->uiConfig();
        $plan = $this->barRatePlan($hotel);
        $pastDays = (int) ($ui['past_days'] ?? 10);
        $overrideMonths = (int) ($ui['override_months'] ?? 13);

        $pastEnd = Carbon::parse($filters['past_date'])->startOfDay();
        $pastStart = $pastEnd->copy()->subDays($pastDays - 1);
        $futureStart = Carbon::parse($filters['future_from'])->startOfDay();
        $futureEnd = Carbon::parse($filters['future_to'])->startOfDay();

        $past = $this->rateSeries($plan, $pastStart, $pastEnd, 'd M');
        $future = $this->rateSeries($plan, $futureStart, $futureEnd, 'Y-m-d');
        $override = $this->overrideMonthlySeries($plan, $overrideMonths);

        return [
            'past' => $past,
            'future' => $future,
            'override' => $override,
            'has_rate_plan' => $plan !== null,
            'bar_label' => 'BAR',
        ];
    }

    private function barRatePlan(Hotel $hotel): ?HotelRatePlan
    {
        $plan = $hotel->ratePlans()->with('room')->where('is_master', true)->first();

        if ($plan !== null) {
            return $plan;
        }

        return $hotel->ratePlans()->with('room')->orderBy('id')->first();
    }

    /** @return array<string, mixed> */
    private function rateSeries(?HotelRatePlan $plan, Carbon $from, Carbon $to, string $labelFormat): array
    {
        $labels = [];
        $values = [];
        $overrides = [];

        foreach (CarbonPeriod::create($from, '1 day', $to) as $day) {
            $dateKey = Carbon::parse($day)->format('Y-m-d');
            $labels[] = Carbon::parse($day)->format($labelFormat);
            $rate = $this->displayRateForPlan($plan, $dateKey);
            $isOverride = $this->hasManualOverride($plan, $dateKey);

            $values[] = round($rate, 1);
            $overrides[] = $isOverride;
        }

        $numericValues = array_filter($values, fn ($value) => $value > 0);
        $avg = $numericValues !== [] ? array_sum($numericValues) / count($numericValues) : 50;
        $minRate = $numericValues !== [] ? min($numericValues) : $avg;
        $maxRate = $numericValues !== [] ? max($numericValues) : $avg;
        $padding = max(1, ($maxRate - $minRate) * 0.1);

        return [
            'labels' => $labels,
            'values' => $values,
            'overrides' => $overrides,
            'y_min' => floor(($minRate - $padding) * 10) / 10,
            'y_max' => ceil(($maxRate + $padding) * 10) / 10,
        ];
    }

    /** @return array<string, mixed> */
    private function overrideMonthlySeries(?HotelRatePlan $plan, int $months): array
    {
        $labels = [];
        $values = [];
        $anchor = now()->startOfMonth();

        for ($offset = $months - 1; $offset >= 0; $offset--) {
            $month = $anchor->copy()->subMonths($offset);
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();
            $rangeEnd = $monthEnd->gt(now()) ? now()->copy()->startOfDay() : $monthEnd->copy()->startOfDay();

            if ($rangeEnd->lt($monthStart)) {
                $labels[] = $month->format('M Y');
                $values[] = 0;

                continue;
            }

            $totalDays = 0;
            $overrideDays = 0;

            foreach (CarbonPeriod::create($monthStart, '1 day', $rangeEnd) as $day) {
                $dateKey = Carbon::parse($day)->format('Y-m-d');
                $totalDays++;

                if ($this->hasManualOverride($plan, $dateKey)) {
                    $overrideDays++;
                }
            }

            $labels[] = $month->format('M Y');
            $values[] = $totalDays > 0 ? round(($overrideDays / $totalDays) * 100, 1) : 0;
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    private function displayRateForPlan(?HotelRatePlan $plan, string $dateKey): float
    {
        if ($plan === null) {
            return 0;
        }

        $rates = $this->rates->rateForDate($plan, $dateKey);
        $pricingMode = $plan->pricing_mode ?? HotelRatePlan::PRICING_BOTH;

        if ($pricingMode === HotelRatePlan::PRICING_LOCAL) {
            return (float) ($rates['local'] ?? 0);
        }

        if ($pricingMode === HotelRatePlan::PRICING_INTERNATIONAL) {
            return (float) ($rates['international'] ?? 0);
        }

        if (($rates['international'] ?? 0) > 0) {
            return (float) $rates['international'];
        }

        return (float) ($rates['local'] ?? 0);
    }

    private function hasManualOverride(?HotelRatePlan $plan, string $dateKey): bool
    {
        if ($plan === null) {
            return false;
        }

        return HotelRateInventory::query()
            ->where('hotel_rate_plan_id', $plan->id)
            ->where('date', $dateKey)
            ->exists();
    }
}
