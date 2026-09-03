<?php

namespace App\Services;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelRatePlan;
use App\Services\ChannelManager\ChannelManagerCodeResolver;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class HotelPickupReportService
{
    public function __construct(
        private ChannelManagerCodeResolver $codes,
        private RateInventoryService $rates,
    ) {}

    /** @return array<string, mixed> */
    public function uiConfig(): array
    {
        return config('hotel_analytics.pickup_report', []);
    }

    /** @return array<string, mixed> */
    public function filtersFromRequest(Request $request): array
    {
        $ui = $this->uiConfig();
        $modes = array_keys($ui['modes'] ?? ['by_date' => 'Pick Up By Date']);
        $mode = $request->input('mode', $ui['default_mode'] ?? 'by_date');

        if (! in_array($mode, $modes, true)) {
            $mode = $ui['default_mode'] ?? 'by_date';
        }

        $reportTypes = array_keys($ui['report_types'] ?? ['date_wise' => 'Date Wise']);
        $reportType = $request->input('report_type', $ui['default_report_type'] ?? 'date_wise');

        if (! in_array($reportType, $reportTypes, true)) {
            $reportType = $ui['default_report_type'] ?? 'date_wise';
        }

        $pickupDate = $request->input('pickup_date', now()->format('Y-m-d'));
        $pickupFrom = $request->input('pickup_from', now()->subDays(10)->format('Y-m-d'));
        $pickupTo = $request->input('pickup_to', now()->format('Y-m-d'));

        if ($pickupFrom > $pickupTo) {
            [$pickupFrom, $pickupTo] = [$pickupTo, $pickupFrom];
        }

        return [
            'mode' => $mode,
            'report_type' => $reportType,
            'pickup_date' => $pickupDate,
            'pickup_from' => $pickupFrom,
            'pickup_to' => $pickupTo,
        ];
    }

    /** @return array<string, mixed> */
    public function report(Hotel $hotel, array $filters): array
    {
        $ui = $this->uiConfig();
        $totalRooms = $this->totalSellableRooms($hotel);
        $currency = strtoupper($hotel->currency ?: 'USD');
        $barPlan = $this->barRatePlan($hotel);
        $reservations = $this->reservations($hotel);

        [$pickupStart, $pickupEnd, $stayStart, $stayEnd] = $this->resolveWindows($filters, $ui);

        $rows = [];

        foreach (CarbonPeriod::create($stayStart, '1 day', $stayEnd) as $day) {
            $stayDate = Carbon::parse($day)->startOfDay();
            $occupied = $this->occupiedRoomCount($reservations, $stayDate);
            $pickupStats = $this->pickupStatsForStayDate($reservations, $stayDate, $pickupStart, $pickupEnd);
            $baseRate = $this->baseRateForDate($barPlan, $stayDate->format('Y-m-d'));

            $rows[] = [
                'stay_date' => $stayDate->format('Y-m-d'),
                'stay_date_label' => $stayDate->format('d-M-Y'),
                'total_rooms' => $totalRooms,
                'rooms_occupied' => $occupied,
                'occupancy_forecast' => $totalRooms > 0 ? round(($occupied / $totalRooms) * 100).'%' : '0%',
                'pickup' => $pickupStats['pickup'],
                'revenue' => $this->formatMoney($pickupStats['revenue']),
                'revenue_raw' => $pickupStats['revenue'],
                'avg_revenue' => $this->formatMoney($pickupStats['avg_revenue']),
                'base_rate' => $this->formatRate($baseRate),
            ];
        }

        if ($filters['report_type'] === 'week_wise') {
            $rows = $this->aggregateRows($rows, 'week');
        } elseif ($filters['report_type'] === 'month_wise') {
            $rows = $this->aggregateRows($rows, 'month');
        }

        $pickupNights = (int) collect($rows)->sum('pickup');
        $totalRevenue = collect($rows)->sum('revenue_raw');

        return [
            'rows' => array_map(function (array $row) {
                unset($row['revenue_raw']);

                return $row;
            }, $rows),
            'summary' => [
                'room_nights_pickup' => $pickupNights,
                'total_revenue' => $this->formatMoney($totalRevenue),
                'arr' => $this->formatMoney($pickupNights > 0 ? $totalRevenue / $pickupNights : 0),
            ],
            'currency' => $currency,
        ];
    }

    /** @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon} */
    private function resolveWindows(array $filters, array $ui): array
    {
        $forwardDays = (int) ($ui['forward_days'] ?? 30);

        if ($filters['mode'] === 'by_range') {
            $pickupStart = Carbon::parse($filters['pickup_from'])->startOfDay();
            $pickupEnd = Carbon::parse($filters['pickup_to'])->endOfDay();

            return [$pickupStart, $pickupEnd, $pickupStart->copy(), $pickupEnd->copy()->startOfDay()];
        }

        $pickupDate = Carbon::parse($filters['pickup_date'])->startOfDay();
        $stayEnd = $pickupDate->copy()->addDays($forwardDays - 1);

        return [$pickupDate, $pickupDate->copy()->endOfDay(), $pickupDate->copy(), $stayEnd];
    }

    /** @return array{pickup: int, revenue: float, avg_revenue: float} */
    private function pickupStatsForStayDate(Collection $reservations, Carbon $stayDate, Carbon $pickupStart, Carbon $pickupEnd): array
    {
        $pickup = 0;
        $revenue = 0.0;

        foreach ($reservations as $reservation) {
            if ($reservation->isCancelled()) {
                continue;
            }

            if (! $this->createdInWindow($reservation, $pickupStart, $pickupEnd)) {
                continue;
            }

            if (! $this->coversStayDate($reservation, $stayDate)) {
                continue;
            }

            $rooms = max(1, $reservation->roomCount());
            $pickup += $rooms;
            $revenue += $this->revenueForStayNight($reservation, $rooms);
        }

        return [
            'pickup' => $pickup,
            'revenue' => $revenue,
            'avg_revenue' => $pickup > 0 ? $revenue / $pickup : 0.0,
        ];
    }

    private function revenueForStayNight(CmReservation $reservation, int $rooms): float
    {
        $nights = max(1, (int) ($reservation->roomNightCount() ?? 1));

        return ((float) ($reservation->amount_after_tax ?? 0) / $nights) * $rooms;
    }

    private function createdInWindow(CmReservation $reservation, Carbon $from, Carbon $to): bool
    {
        if ($reservation->created_at === null) {
            return false;
        }

        $created = Carbon::parse($reservation->created_at);

        return $created->gte($from) && $created->lte($to);
    }

    private function coversStayDate(CmReservation $reservation, Carbon $stayDate): bool
    {
        if ($reservation->checkin === null || $reservation->checkout === null) {
            return false;
        }

        $checkin = Carbon::parse($reservation->checkin)->startOfDay();
        $checkout = Carbon::parse($reservation->checkout)->startOfDay();

        return $checkin->lte($stayDate) && $checkout->gt($stayDate);
    }

    /** @param  Collection<int, CmReservation>  $reservations */
    private function occupiedRoomCount(Collection $reservations, Carbon $date): int
    {
        $total = 0;

        foreach ($reservations as $reservation) {
            if ($reservation->isCancelled()) {
                continue;
            }

            if ($this->coversStayDate($reservation, $date)) {
                $total += max(1, $reservation->roomCount());
            }
        }

        return $total;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function aggregateRows(array $rows, string $period): array
    {
        return collect($rows)
            ->groupBy(function (array $row) use ($period) {
                $date = Carbon::parse($row['stay_date']);

                return $period === 'week'
                    ? $date->copy()->startOfWeek()->format('Y-m-d')
                    : $date->format('Y-m');
            })
            ->map(function (Collection $group, string $key) use ($period) {
                $first = Carbon::parse($group->first()['stay_date']);
                $label = $period === 'week'
                    ? $first->copy()->startOfWeek()->format('d-M-Y').' - '.$first->copy()->endOfWeek()->format('d-M-Y')
                    : $first->format('M Y');

                $pickup = (int) $group->sum('pickup');
                $revenue = $group->sum('revenue_raw');
                $occupied = (int) round($group->avg('rooms_occupied'));
                $totalRooms = (int) ($group->first()['total_rooms'] ?? 0);

                return [
                    'stay_date' => $key,
                    'stay_date_label' => $label,
                    'total_rooms' => $totalRooms,
                    'rooms_occupied' => $occupied,
                    'occupancy_forecast' => $totalRooms > 0 ? round(($occupied / $totalRooms) * 100).'%' : '0%',
                    'pickup' => $pickup,
                    'revenue' => $this->formatMoney($revenue),
                    'revenue_raw' => $revenue,
                    'avg_revenue' => $this->formatMoney($pickup > 0 ? $revenue / $pickup : 0),
                    'base_rate' => $group->last()['base_rate'] ?? '0',
                ];
            })
            ->values()
            ->all();
    }

    /** @return Collection<int, CmReservation> */
    private function reservations(Hotel $hotel): Collection
    {
        $hotelCode = $this->codes->hotelCode($hotel);

        return CmReservation::query()
            ->where(function (Builder $query) use ($hotel, $hotelCode) {
                $query->where('hotel_id', $hotel->id)
                    ->orWhere('hotel_code', $hotelCode);
            })
            ->get();
    }

    private function barRatePlan(Hotel $hotel): ?HotelRatePlan
    {
        return $hotel->ratePlans()->where('is_master', true)->first()
            ?? $hotel->ratePlans()->orderBy('id')->first();
    }

    private function baseRateForDate(?HotelRatePlan $plan, string $dateKey): float
    {
        if ($plan === null) {
            return 0;
        }

        return $this->rates->cmRateForPlan($plan, $dateKey);
    }

    private function totalSellableRooms(Hotel $hotel): int
    {
        $total = (int) $hotel->rooms()
            ->where('is_enabled', true)
            ->get()
            ->sum(fn ($room) => max(1, (int) $room->room_count));

        return max(1, $total);
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, $amount == floor($amount) ? 0 : 1);
    }

    private function formatRate(float $amount): string
    {
        return number_format($amount, 1);
    }
}
