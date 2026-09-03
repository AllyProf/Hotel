<?php

namespace App\Services;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Services\ChannelManager\ChannelManagerCodeResolver;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class HotelAnalyticsService
{
    public function __construct(
        private ChannelManagerCodeResolver $codes,
    ) {}

    /** @return array<string, mixed> */
    public function uiConfig(): array
    {
        return config('hotel_analytics.hotel_performance', []);
    }

    /** @return array<string, mixed> */
    public function filtersFromRequest(Request $request): array
    {
        $ui = $this->uiConfig();
        $views = array_keys($ui['views'] ?? ['monthly' => 'Monthly']);
        $view = $request->input('view', $ui['default_view'] ?? 'monthly');

        if (! in_array($view, $views, true)) {
            $view = $ui['default_view'] ?? 'monthly';
        }

        $filterOptions = array_keys($ui['filter_options'] ?? ['checkout' => 'Checkout Date']);
        $filterBy = $request->input('filter_by', $ui['default_filter'] ?? 'checkout');

        if (! in_array($filterBy, $filterOptions, true)) {
            $filterBy = $ui['default_filter'] ?? 'checkout';
        }

        $defaultMonths = (int) ($ui['default_from_months'] ?? 6);
        $fromDate = $request->input('from_date', now()->subMonths($defaultMonths)->startOfMonth()->format('Y-m-d'));
        $toDate = $request->input('to_date', now()->format('Y-m-d'));

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return [
            'view' => $view,
            'filter_by' => $filterBy,
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];
    }

    /** @return array<string, mixed> */
    public function hotelPerformance(Hotel $hotel, array $filters): array
    {
        $hotelCode = $this->codes->hotelCode($hotel);
        $from = Carbon::parse($filters['from_date'])->startOfDay();
        $to = Carbon::parse($filters['to_date'])->endOfDay();
        $periods = $this->buildPeriods($from, $to, $filters['view']);
        $totalRooms = $this->totalSellableRooms($hotel);

        $reservations = CmReservation::query()
            ->where(function (Builder $query) use ($hotel, $hotelCode) {
                $query->where('hotel_id', $hotel->id)
                    ->orWhere('hotel_code', $hotelCode);
            })
            ->where('status', '!=', CmReservation::STATUS_CANCELLED)
            ->get();

        $periodKeys = collect($periods)->pluck('key')->all();
        $metrics = $this->emptyMetrics($periodKeys);
        $channels = [];
        $segments = [];

        foreach ($reservations as $reservation) {
            $eventDate = $this->eventDate($reservation, $filters['filter_by']);

            if ($eventDate === null || $eventDate->lt($from) || $eventDate->gt($to)) {
                continue;
            }

            $periodKey = $this->periodKeyForDate($eventDate, $filters['view']);

            if (! isset($metrics['totals'][$periodKey])) {
                continue;
            }

            $roomNights = max(1, (int) ($reservation->roomNightCount() ?? 1)) * max(1, $reservation->roomCount());
            $sales = (float) ($reservation->amount_after_tax ?? 0);
            $channel = $reservation->sourceDisplayLabel() ?: 'Direct';
            $segment = $this->segmentLabel($reservation);

            $metrics['totals'][$periodKey]['room_nights'] += $roomNights;
            $metrics['totals'][$periodKey]['sales'] += $sales;

            $this->accumulateBucket($channels, $channel, $periodKey, $sales, $roomNights);
            $this->accumulateBucket($segments, $segment, $periodKey, $sales, $roomNights);
        }

        $labels = collect($periods)->pluck('label')->all();
        $chartLabels = collect($periods)->pluck('chart_label')->all();
        $closingOccupancy = [];
        $arr = [];
        $revpar = [];
        $totalSales = [];
        $totalRoomNights = [];

        foreach ($periods as $period) {
            $key = $period['key'];
            $sold = $metrics['totals'][$key]['room_nights'];
            $sales = $metrics['totals'][$key]['sales'];
            $available = max(1, $totalRooms * $period['days']);

            $closingOccupancy[] = round(($sold / $available) * 100, 1);
            $arr[] = $sold > 0 ? round($sales / $sold, 2) : 0;
            $revpar[] = round($sales / $available, 2);
            $totalSales[] = round($sales, 2);
            $totalRoomNights[] = $sold;
        }

        return [
            'labels' => $labels,
            'chart_labels' => $chartLabels,
            'period_keys' => $periodKeys,
            'periods' => $periods,
            'closing_occupancy' => $closingOccupancy,
            'arr' => $arr,
            'revpar' => $revpar,
            'total_sales' => $totalSales,
            'total_room_nights' => $totalRoomNights,
            'channels' => $this->formatBuckets($channels, $periodKeys),
            'segments' => $this->formatBuckets($segments, $periodKeys),
            'segment_sales_table' => $this->segmentTableRows($segments, $periods, 'sales', $hotel->currency),
            'segment_nights_table' => $this->segmentTableRows($segments, $periods, 'room_nights'),
            'currency' => strtoupper($hotel->currency ?: 'USD'),
            'total_rooms' => $totalRooms,
            'avg_sold' => (int) round(collect($totalRoomNights)->avg() ?: 0),
        ];
    }

    /** @return list<array{key: string, label: string, chart_label: string, days: int}> */
    private function buildPeriods(Carbon $from, Carbon $to, string $view): array
    {
        $periods = [];

        if ($view === 'daily') {
            foreach (CarbonPeriod::create($from->copy()->startOfDay(), '1 day', $to->copy()->startOfDay()) as $day) {
                $periods[] = [
                    'key' => $day->format('Y-m-d'),
                    'label' => $day->format('M Y'),
                    'chart_label' => $day->format('Y-m-d'),
                    'days' => 1,
                ];
            }

            return $periods;
        }

        if ($view === 'weekly') {
            $cursor = $from->copy()->startOfWeek();
            $end = $to->copy()->endOfWeek();

            while ($cursor->lte($end)) {
                $weekStart = $cursor->copy();
                $weekEnd = $cursor->copy()->endOfWeek();
                $overlapStart = $weekStart->lt($from) ? $from->copy() : $weekStart->copy();
                $overlapEnd = $weekEnd->gt($to) ? $to->copy() : $weekEnd->copy();
                $days = max(1, $overlapStart->diffInDays($overlapEnd) + 1);

                $periods[] = [
                    'key' => $weekStart->format('o-\WW'),
                    'label' => $weekStart->format('d M').' - '.$weekEnd->format('d M Y'),
                    'chart_label' => $weekStart->format('Y-m-d'),
                    'days' => $days,
                ];

                $cursor->addWeek();
            }

            return $periods;
        }

        $cursor = $from->copy()->startOfMonth();
        $end = $to->copy()->endOfMonth();

        while ($cursor->lte($end)) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();
            $overlapStart = $monthStart->lt($from) ? $from->copy() : $monthStart->copy();
            $overlapEnd = $monthEnd->gt($to) ? $to->copy() : $monthEnd->copy();
            $days = max(1, $overlapStart->diffInDays($overlapEnd) + 1);

            $periods[] = [
                'key' => $cursor->format('Y-m'),
                'label' => $cursor->format('M Y'),
                'chart_label' => $cursor->format('Y-m'),
                'days' => $days,
            ];

            $cursor->addMonth();
        }

        return $periods;
    }

    /** @param  list<string>  $periodKeys */
    private function emptyMetrics(array $periodKeys): array
    {
        $totals = [];

        foreach ($periodKeys as $key) {
            $totals[$key] = ['sales' => 0.0, 'room_nights' => 0];
        }

        return ['totals' => $totals];
    }

    private function eventDate(CmReservation $reservation, string $filterBy): ?Carbon
    {
        return match ($filterBy) {
            'checkin' => $reservation->checkin ? Carbon::parse($reservation->checkin) : null,
            'booking' => $reservation->created_at ? Carbon::parse($reservation->created_at) : null,
            default => $reservation->checkout ? Carbon::parse($reservation->checkout) : null,
        };
    }

    private function periodKeyForDate(Carbon $date, string $view): string
    {
        return match ($view) {
            'daily' => $date->format('Y-m-d'),
            'weekly' => $date->copy()->startOfWeek()->format('o-\WW'),
            default => $date->format('Y-m'),
        };
    }

    /** @param  array<string, array<string, array{sales: float, room_nights: int}>>  $buckets */
    private function accumulateBucket(array &$buckets, string $label, string $periodKey, float $sales, int $roomNights): void
    {
        if (! isset($buckets[$label][$periodKey])) {
            $buckets[$label][$periodKey] = ['sales' => 0.0, 'room_nights' => 0];
        }

        $buckets[$label][$periodKey]['sales'] += $sales;
        $buckets[$label][$periodKey]['room_nights'] += $roomNights;
    }

    /**
     * @param  array<string, array<string, array{sales: float, room_nights: int}>>  $buckets
     * @param  list<string>  $periodKeys
     * @return list<array{name: string, sales: list<float>, room_nights: list<int>}>
     */
    private function formatBuckets(array $buckets, array $periodKeys): array
    {
        return collect($buckets)
            ->map(function (array $periodData, string $name) use ($periodKeys) {
                $sales = [];
                $roomNights = [];

                foreach ($periodKeys as $key) {
                    $sales[] = round($periodData[$key]['sales'] ?? 0, 2);
                    $roomNights[] = (int) ($periodData[$key]['room_nights'] ?? 0);
                }

                return compact('name', 'sales', 'roomNights');
            })
            ->sortByDesc(fn (array $row) => array_sum($row['sales']))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array<string, array{sales: float, room_nights: int}>>  $segments
     * @param  list<array{key: string, label: string, chart_label: string, days: int}>  $periods
     * @return list<array<string, mixed>>
     */
    private function segmentTableRows(array $segments, array $periods, string $metric, ?string $currency = null): array
    {
        $rows = [];

        foreach ($segments as $name => $periodData) {
            $row = ['label' => $name];
            $total = 0;

            foreach ($periods as $period) {
                $value = $periodData[$period['key']][$metric === 'sales' ? 'sales' : 'room_nights'] ?? 0;

                if ($metric === 'sales') {
                    $row[$period['key']] = number_format((float) $value, 2);
                    $total += (float) $value;
                } else {
                    $row[$period['key']] = (int) $value;
                    $total += (int) $value;
                }
            }

            $row['total'] = $metric === 'sales'
                ? number_format($total, 2)
                : (string) $total;
            $rows[] = $row;
        }

        $totalRow = ['label' => 'Total'];

        foreach ($periods as $period) {
            $sum = collect($rows)->sum(function (array $row) use ($period, $metric) {
                $value = $row[$period['key']] ?? 0;

                return $metric === 'sales'
                    ? (float) str_replace(',', '', (string) $value)
                    : (int) $value;
            });

            $totalRow[$period['key']] = $metric === 'sales' ? number_format($sum, 2) : (string) $sum;
        }

        $grandTotal = collect($rows)->sum(function (array $row) use ($metric) {
            $value = $row['total'] ?? 0;

            return $metric === 'sales'
                ? (float) str_replace(',', '', (string) $value)
                : (int) $value;
        });

        $totalRow['total'] = $metric === 'sales' ? number_format($grandTotal, 2) : (string) $grandTotal;
        $rows[] = $totalRow;

        return $rows;
    }

    private function segmentLabel(CmReservation $reservation): string
    {
        $payload = is_array($reservation->payload) ? $reservation->payload : [];

        foreach (['segment', 'category', 'booking_category'] as $key) {
            $value = trim((string) ($payload[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return 'Unassigned';
    }

    /** @return array<string, mixed> */
    public function dailyReportUiConfig(): array
    {
        return config('hotel_analytics.daily_report', []);
    }

    /** @return array<string, mixed> */
    public function dailyReportFiltersFromRequest(Request $request): array
    {
        return [
            'report_date' => $request->input('report_date', now()->format('Y-m-d')),
        ];
    }

    /** @return array<string, mixed> */
    public function dailyReport(Hotel $hotel, array $filters): array
    {
        $hotelCode = $this->codes->hotelCode($hotel);
        $date = Carbon::parse($filters['report_date'])->startOfDay();
        $periodStart = $date->copy()->startOfMonth();
        $totalRooms = $this->totalSellableRooms($hotel);
        $currency = strtoupper($hotel->currency ?: 'USD');

        $dayLabel = $date->format('d-M');
        $periodLabel = $periodStart->format('d-M').' to '.$date->format('d-M');

        $reservations = CmReservation::query()
            ->where(function (Builder $query) use ($hotel, $hotelCode) {
                $query->where('hotel_id', $hotel->id)
                    ->orWhere('hotel_code', $hotelCode);
            })
            ->where('status', '!=', CmReservation::STATUS_CANCELLED)
            ->get();

        $dayPerformance = $this->emptyPerformanceBucket();
        $periodPerformance = $this->emptyPerformanceBucket();
        $daySources = [];
        $periodSources = [];
        $dayPickup = $this->emptyPerformanceBucket();
        $periodPickup = $this->emptyPerformanceBucket();
        $pickupSources = [];

        foreach ($reservations as $reservation) {
            $roomNights = max(1, (int) ($reservation->roomNightCount() ?? 1)) * max(1, $reservation->roomCount());
            $revenue = (float) ($reservation->amount_after_tax ?? 0);
            $channel = $reservation->sourceDisplayLabel() ?: 'Direct';
            $checkout = $reservation->checkout ? Carbon::parse($reservation->checkout)->startOfDay() : null;
            $bookedOn = $reservation->created_at ? Carbon::parse($reservation->created_at)->startOfDay() : null;
            $occupiedNight = $this->occupiedRoomsOnNight($reservation, $date);

            if ($checkout && $checkout->equalTo($date)) {
                $this->addPerformance($dayPerformance, $roomNights, $revenue, $occupiedNight, $totalRooms);
                $this->addSource($daySources, $channel, $roomNights, $revenue);
            }

            if ($checkout && $checkout->gte($periodStart) && $checkout->lte($date)) {
                $this->addPerformance($periodPerformance, $roomNights, $revenue, $occupiedNight, $totalRooms);
                $this->addSource($periodSources, $channel, $roomNights, $revenue);
            }

            if ($bookedOn && $bookedOn->equalTo($date)) {
                $this->addPerformance($dayPickup, $roomNights, $revenue, 0, $totalRooms);
                $this->addSource($pickupSources, $channel, $roomNights, $revenue);
            }

            if ($bookedOn && $bookedOn->gte($periodStart) && $bookedOn->lte($date)) {
                $this->addPerformance($periodPickup, $roomNights, $revenue, 0, $totalRooms);
            }
        }

        $dayOccupancy = $totalRooms > 0
            ? round(($this->occupiedRoomCount($reservations, $date) / $totalRooms) * 100)
            : 0;

        $periodOccupancy = $this->averagePeriodOccupancy($reservations, $periodStart, $date, $totalRooms);

        return [
            'report_date' => $date->format('Y-m-d'),
            'day_label' => $dayLabel,
            'period_label' => $periodLabel,
            'currency' => $currency,
            'last_night' => [
                ['label' => 'No. of Rooms Sold', 'day' => (string) $dayPerformance['rooms_sold'], 'period' => (string) $periodPerformance['rooms_sold']],
                ['label' => 'Overall Occupancy (assumption)', 'day' => $dayOccupancy.' %', 'period' => $periodOccupancy.' %'],
                ['label' => 'Revenue ('.$currency.')', 'day' => $this->formatMoney($dayPerformance['revenue']), 'period' => $this->formatMoney($periodPerformance['revenue'])],
                ['label' => 'ARR ('.$currency.')', 'day' => $this->formatMoney($this->arr($dayPerformance)), 'period' => $this->formatMoney($this->arr($periodPerformance))],
                ['label' => 'RevPAR ('.$currency.')', 'day' => $this->formatMoney($this->revpar($dayPerformance, $totalRooms)), 'period' => $this->formatMoney($this->revpar($periodPerformance, $totalRooms, $periodStart->diffInDays($date) + 1))],
            ],
            'sources' => $this->formatDailySourceRows($daySources, $periodSources),
            'pickup' => $this->formatDailyPickupRows($pickupSources, $dayPickup, $periodPickup),
        ];
    }

    /** @return array{rooms_sold: int, revenue: float, occupied_nights: int} */
    private function emptyPerformanceBucket(): array
    {
        return ['rooms_sold' => 0, 'revenue' => 0.0, 'occupied_nights' => 0];
    }

    /** @param  array{rooms_sold: int, revenue: float, occupied_nights: int}  $bucket */
    private function addPerformance(array &$bucket, int $roomNights, float $revenue, int $occupiedNight, int $totalRooms): void
    {
        $bucket['rooms_sold'] += $roomNights;
        $bucket['revenue'] += $revenue;
        $bucket['occupied_nights'] += $occupiedNight;
    }

    /** @param  array<string, array{rooms: int, revenue: float}>  $sources */
    private function addSource(array &$sources, string $name, int $rooms, float $revenue): void
    {
        if (! isset($sources[$name])) {
            $sources[$name] = ['rooms' => 0, 'revenue' => 0.0];
        }

        $sources[$name]['rooms'] += $rooms;
        $sources[$name]['revenue'] += $revenue;
    }

    /** @param  array{rooms_sold: int, revenue: float, occupied_nights: int}  $bucket */
    private function arr(array $bucket): float
    {
        return $bucket['rooms_sold'] > 0 ? round($bucket['revenue'] / $bucket['rooms_sold'], 2) : 0;
    }

    /** @param  array{rooms_sold: int, revenue: float, occupied_nights: int}  $bucket */
    private function revpar(array $bucket, int $totalRooms, int $days = 1): float
    {
        $available = max(1, $totalRooms * max(1, $days));

        return round($bucket['revenue'] / $available, 2);
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, $amount == floor($amount) ? 0 : 2);
    }

    private function occupiedRoomsOnNight(CmReservation $reservation, Carbon $date): int
    {
        if ($reservation->checkin === null || $reservation->checkout === null) {
            return 0;
        }

        $checkin = Carbon::parse($reservation->checkin)->startOfDay();
        $checkout = Carbon::parse($reservation->checkout)->startOfDay();

        if ($checkin->lte($date) && $checkout->gt($date)) {
            return max(1, $reservation->roomCount());
        }

        return 0;
    }

    /** @param  \Illuminate\Support\Collection<int, CmReservation>  $reservations */
    private function occupiedRoomCount(Collection $reservations, Carbon $date): int
    {
        return $reservations->sum(fn (CmReservation $reservation) => $this->occupiedRoomsOnNight($reservation, $date));
    }

    /** @param  \Illuminate\Support\Collection<int, CmReservation>  $reservations */
    private function averagePeriodOccupancy(Collection $reservations, Carbon $from, Carbon $to, int $totalRooms): int
    {
        if ($totalRooms <= 0) {
            return 0;
        }

        $days = max(1, $from->diffInDays($to) + 1);
        $occupiedTotal = 0;

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $occupiedTotal += $this->occupiedRoomCount($reservations, Carbon::parse($day));
        }

        return (int) round(($occupiedTotal / ($totalRooms * $days)) * 100);
    }

    /**
     * @param  array<string, array{rooms: int, revenue: float}>  $daySources
     * @param  array<string, array{rooms: int, revenue: float}>  $periodSources
     * @return list<array<string, mixed>>
     */
    private function formatDailySourceRows(array $daySources, array $periodSources): array
    {
        $names = collect(array_keys($daySources))
            ->merge(array_keys($periodSources))
            ->unique()
            ->sort()
            ->values();

        if ($names->isEmpty()) {
            return [[
                'label' => 'Total',
                'day_rooms' => '0',
                'day_usd' => '0',
                'period_rooms' => '0',
                'period_usd' => '0',
            ]];
        }

        $rows = [];

        foreach ($names as $name) {
            if (($daySources[$name]['rooms'] ?? 0) === 0 && ($daySources[$name]['revenue'] ?? 0) == 0) {
                continue;
            }

            $rows[] = [
                'label' => $name,
                'day_rooms' => (string) ($daySources[$name]['rooms'] ?? 0),
                'day_usd' => $this->formatMoney($daySources[$name]['revenue'] ?? 0),
                'period_rooms' => (string) ($periodSources[$name]['rooms'] ?? 0),
                'period_usd' => $this->formatMoney($periodSources[$name]['revenue'] ?? 0),
            ];
        }

        $rows[] = [
            'label' => 'Total',
            'day_rooms' => (string) collect($daySources)->sum('rooms'),
            'day_usd' => $this->formatMoney(collect($daySources)->sum('revenue')),
            'period_rooms' => (string) collect($periodSources)->sum('rooms'),
            'period_usd' => $this->formatMoney(collect($periodSources)->sum('revenue')),
        ];

        return $rows;
    }

    /**
     * @param  array<string, array{rooms: int, revenue: float}>  $pickupSources
     * @param  array{rooms_sold: int, revenue: float, occupied_nights: int}  $dayPickup
     * @param  array{rooms_sold: int, revenue: float, occupied_nights: int}  $periodPickup
     * @return list<array<string, mixed>>
     */
    private function formatDailyPickupRows(array $pickupSources, array $dayPickup, array $periodPickup): array
    {
        $rows = [];

        foreach ($pickupSources as $name => $data) {
            $rows[] = [
                'label' => $name,
                'day_rns' => (string) $data['rooms'],
                'day_usd' => $this->formatMoney($data['revenue']),
                'period_rns' => '0',
                'period_usd' => '0',
            ];
        }

        $rows[] = [
            'label' => 'Total',
            'day_rns' => (string) $dayPickup['rooms_sold'],
            'day_usd' => $this->formatMoney($dayPickup['revenue']),
            'period_rns' => (string) $periodPickup['rooms_sold'],
            'period_usd' => $this->formatMoney($periodPickup['revenue']),
        ];

        return $rows;
    }

    /** @return array<string, mixed> */
    public function trendAnalysisUiConfig(): array
    {
        return config('hotel_analytics.trend_analysis', []);
    }

    /** @return array<string, mixed> */
    public function trendAnalysisFiltersFromRequest(Request $request): array
    {
        return $this->analyticsFiltersFromRequest($request, $this->trendAnalysisUiConfig());
    }

    /** @return array<string, mixed> */
    public function trendAnalysis(Hotel $hotel, array $filters): array
    {
        $ui = $this->trendAnalysisUiConfig();
        $hotelCode = $this->codes->hotelCode($hotel);
        $from = Carbon::parse($filters['from_date'])->startOfDay();
        $to = Carbon::parse($filters['to_date'])->endOfDay();
        $totalRooms = $this->totalSellableRooms($hotel);

        $allReservations = CmReservation::query()
            ->where(function (Builder $query) use ($hotel, $hotelCode) {
                $query->where('hotel_id', $hotel->id)
                    ->orWhere('hotel_code', $hotelCode);
            })
            ->get();

        $activeReservations = $allReservations->where('status', '!=', CmReservation::STATUS_CANCELLED);
        $filtered = $activeReservations->filter(function (CmReservation $reservation) use ($filters, $from, $to) {
            $eventDate = $this->eventDate($reservation, $filters['filter_by']);

            return $eventDate !== null && $eventDate->gte($from) && $eventDate->lte($to);
        })->values();

        $leadBuckets = $ui['lead_time_buckets'] ?? ['0', '1', '2-10', '10-30', '30-60', '60-90', '90+'];
        $leadCounts = array_fill_keys($leadBuckets, 0);
        $losLabels = $ui['length_of_stay_labels'] ?? ['0', '1', '2', '3', '4', '5', '6', '7'];
        $losCounts = array_fill_keys($losLabels, 0);
        $guestLabels = $ui['occupancy_guest_labels'] ?? ['1', '2', '3', '4', '5'];
        $guestCounts = array_fill_keys($guestLabels, 0);
        $dowLabels = $ui['dow_labels'] ?? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $dowOccupancy = array_fill_keys($dowLabels, 0);
        $dowBookings = array_fill_keys($dowLabels, 0);
        $lastMinuteCounts = array_fill(0, 24, 0);
        $roomTypeCounts = [];
        $rateCounts = [];
        $mealCounts = [];
        $mealOtaCounts = [];
        $paymentCounts = [];
        $paymentOtaCounts = [];

        foreach ($filtered as $reservation) {
            $bookedAt = $reservation->created_at ? Carbon::parse($reservation->created_at) : null;
            $checkin = $reservation->checkin ? Carbon::parse($reservation->checkin)->startOfDay() : null;

            if ($bookedAt && $checkin) {
                $leadDays = max(0, $bookedAt->copy()->startOfDay()->diffInDays($checkin));
                $leadKey = $this->leadTimeBucket($leadDays);
                $leadCounts[$leadKey] = ($leadCounts[$leadKey] ?? 0) + 1;

                $hoursBefore = max(0, min(23, $bookedAt->diffInHours($checkin)));
                $lastMinuteCounts[$hoursBefore]++;
            }

            $nights = (int) ($reservation->roomNightCount() ?? 0);
            $losKey = (string) min(7, max(0, $nights));
            $losCounts[$losKey] = ($losCounts[$losKey] ?? 0) + 1;

            $guests = min(5, max(1, $reservation->guestCount()));
            $guestKey = (string) $guests;
            $guestCounts[$guestKey] = ($guestCounts[$guestKey] ?? 0) + 1;

            if ($checkin) {
                $dowKey = $dowLabels[$checkin->dayOfWeekIso - 1] ?? 'Mon';
                $dowBookings[$dowKey] = ($dowBookings[$dowKey] ?? 0) + 1;
            }

            if ($reservation->checkin && $reservation->checkout) {
                $stayStart = Carbon::parse($reservation->checkin)->startOfDay()->max($from);
                $stayEnd = Carbon::parse($reservation->checkout)->subDay()->startOfDay()->min($to);

                if ($stayStart->lte($stayEnd)) {
                    foreach (CarbonPeriod::create($stayStart, '1 day', $stayEnd) as $day) {
                        $dowKey = $dowLabels[Carbon::parse($day)->dayOfWeekIso - 1] ?? 'Mon';
                        $dowOccupancy[$dowKey] = ($dowOccupancy[$dowKey] ?? 0) + $reservation->roomCount();
                    }
                }
            }

            $roomType = $reservation->roomLabel();
            $roomTypeCounts[$roomType] = ($roomTypeCounts[$roomType] ?? 0) + 1;

            $ratePlan = $this->ratePlanLabel($reservation);
            $rateCounts[$ratePlan] = ($rateCounts[$ratePlan] ?? 0) + 1;

            $mealPlan = $reservation->mealPlanLabel();
            if ($mealPlan !== '—') {
                $mealCounts[$mealPlan] = ($mealCounts[$mealPlan] ?? 0) + 1;
                $channel = $reservation->sourceDisplayLabel() ?: 'Direct';
                $mealOtaCounts[$channel][$mealPlan] = ($mealOtaCounts[$channel][$mealPlan] ?? 0) + 1;
            }

            $payment = $reservation->paymentLabel();
            if ($payment !== '—') {
                $paymentCounts[$payment] = ($paymentCounts[$payment] ?? 0) + 1;
                $channel = $reservation->sourceDisplayLabel() ?: 'Direct';
                $paymentOtaCounts[$channel][$payment] = ($paymentOtaCounts[$channel][$payment] ?? 0) + 1;
            }
        }

        $periods = $this->buildPeriods($from, $to, $filters['view']);
        $cancelLabels = collect($periods)->pluck('chart_label')->all();
        $cancelValues = [];

        foreach ($periods as $period) {
            $periodStart = match ($filters['view']) {
                'daily' => Carbon::parse($period['key'])->startOfDay(),
                'weekly' => Carbon::parse($period['chart_label'])->startOfWeek(),
                default => Carbon::parse($period['key'].'-01')->startOfMonth(),
            };
            $periodEnd = match ($filters['view']) {
                'daily' => $periodStart->copy()->endOfDay(),
                'weekly' => $periodStart->copy()->endOfWeek(),
                default => $periodStart->copy()->endOfMonth(),
            };

            $periodTotal = 0;
            $periodCancelled = 0;

            foreach ($allReservations as $reservation) {
                $eventDate = $this->eventDate($reservation, $filters['filter_by']);

                if ($eventDate === null || $eventDate->lt($periodStart) || $eventDate->gt($periodEnd)) {
                    continue;
                }

                $periodTotal++;

                if ($reservation->isCancelled()) {
                    $periodCancelled++;
                }
            }

            $cancelValues[] = $periodTotal > 0 ? round(($periodCancelled / $periodTotal) * 100, 1) : 0;
        }

        $historicalDays = (int) ($ui['historical_days'] ?? 10);
        $futureDays = (int) ($ui['future_days'] ?? 30);
        $anchor = $to->copy()->startOfDay();
        $historicalStart = $anchor->copy()->subDays($historicalDays - 1);
        $historicalLabels = [];
        $historicalOccupancy = [];
        $historicalSold = [];
        $historicalRooms = [];

        foreach (CarbonPeriod::create($historicalStart, '1 day', $anchor) as $day) {
            $date = Carbon::parse($day);
            $occupied = $this->occupiedRoomCount($activeReservations, $date);
            $historicalLabels[] = $date->format('d M');
            $historicalOccupancy[] = $totalRooms > 0 ? round(($occupied / $totalRooms) * 100, 1) : 0;
            $historicalSold[] = $occupied;
            $historicalRooms[] = $totalRooms;
        }

        $futureStart = $anchor->copy()->addDay();
        $futureEnd = $futureStart->copy()->addDays($futureDays - 1);
        $futureLabels = [];
        $futureOccupancy = [];
        $futureRooms = [];

        foreach (CarbonPeriod::create($futureStart, '1 day', $futureEnd) as $day) {
            $date = Carbon::parse($day);
            $occupied = $this->occupiedRoomCount($activeReservations, $date);
            $futureLabels[] = $date->format('d M');
            $futureOccupancy[] = $totalRooms > 0 ? round(($occupied / $totalRooms) * 100, 1) : 0;
            $futureRooms[] = $totalRooms;
        }

        $roomTypeSorted = collect($roomTypeCounts)->sortDesc()->take(8);

        return [
            'lead_time' => [
                'labels' => $leadBuckets,
                'values' => $this->percentageValues(array_values(array_replace(array_fill_keys($leadBuckets, 0), $leadCounts))),
            ],
            'length_of_stay' => [
                'labels' => $losLabels,
                'values' => $this->percentageValues(array_map(fn ($label) => $losCounts[$label] ?? 0, $losLabels)),
            ],
            'dow_occupancy' => [
                'labels' => $dowLabels,
                'occupancy' => array_map(fn ($label) => $dowOccupancy[$label] ?? 0, $dowLabels),
                'bookings' => array_map(fn ($label) => $dowBookings[$label] ?? 0, $dowLabels),
            ],
            'last_minute' => [
                'labels' => range(0, 23),
                'values' => $this->percentageValues($lastMinuteCounts),
            ],
            'occupancy_guests' => [
                'labels' => $guestLabels,
                'values' => $this->percentageValues(array_map(fn ($label) => $guestCounts[$label] ?? 0, $guestLabels)),
            ],
            'room_types' => [
                'labels' => $roomTypeSorted->keys()->all() ?: ['—'],
                'values' => $this->percentageValues($roomTypeSorted->values()->all() ?: [0]),
            ],
            'cancellations' => [
                'labels' => $cancelLabels,
                'values' => $cancelValues,
            ],
            'rates' => $this->distributionChart(collect($rateCounts)->sortDesc()->take(8)),
            'historical' => [
                'labels' => $historicalLabels,
                'closing_occupancy' => $historicalOccupancy,
                'inventory_sold' => $historicalSold,
                'total_rooms' => $historicalRooms,
            ],
            'future' => [
                'labels' => $futureLabels,
                'future_occupancy' => $futureOccupancy,
                'total_rooms' => $futureRooms,
            ],
            'meal_plan' => $this->distributionChart(collect($mealCounts)->sortDesc()->take(8)),
            'meal_plan_ota' => $this->otaDistributionChart($mealOtaCounts),
            'payment_mode' => $this->distributionChart(collect($paymentCounts)->sortDesc()->take(8)),
            'payment_mode_ota' => $this->otaDistributionChart($paymentOtaCounts),
            'total_rooms' => $totalRooms,
        ];
    }

    /** @return array<string, mixed> */
    private function analyticsFiltersFromRequest(Request $request, array $ui): array
    {
        $views = array_keys($ui['views'] ?? ['monthly' => 'Monthly']);
        $view = $request->input('view', $ui['default_view'] ?? 'monthly');

        if (! in_array($view, $views, true)) {
            $view = $ui['default_view'] ?? 'monthly';
        }

        $filterOptions = array_keys($ui['filter_options'] ?? ['checkout' => 'Checkout Date']);
        $filterBy = $request->input('filter_by', $ui['default_filter'] ?? 'checkout');

        if (! in_array($filterBy, $filterOptions, true)) {
            $filterBy = $ui['default_filter'] ?? 'checkout';
        }

        $defaultMonths = (int) ($ui['default_from_months'] ?? 6);
        $fromDate = $request->input('from_date', now()->subMonths($defaultMonths)->startOfMonth()->format('Y-m-d'));
        $toDate = $request->input('to_date', now()->format('Y-m-d'));

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return [
            'view' => $view,
            'filter_by' => $filterBy,
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];
    }

    private function leadTimeBucket(int $days): string
    {
        if ($days <= 0) {
            return '0';
        }

        if ($days === 1) {
            return '1';
        }

        if ($days <= 10) {
            return '2-10';
        }

        if ($days <= 30) {
            return '10-30';
        }

        if ($days <= 60) {
            return '30-60';
        }

        if ($days <= 90) {
            return '60-90';
        }

        return '90+';
    }

    private function ratePlanLabel(CmReservation $reservation): string
    {
        foreach ($reservation->roomLines() as $line) {
            $name = trim((string) ($line['rateplanName'] ?? $line['rate_plan_name'] ?? $line['ratePlanName'] ?? ''));

            if ($name !== '') {
                return $name;
            }

            $code = trim((string) ($line['rateplanCode'] ?? $line['rateplan_code'] ?? $line['ratePlanCode'] ?? ''));

            if ($code !== '') {
                return $code;
            }
        }

        return 'Unassigned';
    }

    /** @param  list<int|float>  $counts */
    private function percentageValues(array $counts): array
    {
        $total = array_sum($counts);

        if ($total <= 0) {
            return array_fill(0, count($counts), 0);
        }

        return array_map(fn ($count) => round(((float) $count / $total), 4), $counts);
    }

    /** @param  \Illuminate\Support\Collection<int|string, int>  $counts */
    private function distributionChart(Collection $counts): array
    {
        if ($counts->isEmpty()) {
            return ['labels' => ['—'], 'values' => [0]];
        }

        return [
            'labels' => $counts->keys()->all(),
            'values' => $this->percentageValues($counts->values()->all()),
        ];
    }

    /**
     * @param  array<string, array<string, int>>  $matrix
     * @return array{labels: list<string>, datasets: list<array{name: string, data: list<float>}>}
     */
    private function otaDistributionChart(array $matrix): array
    {
        if ($matrix === []) {
            return ['labels' => ['—'], 'datasets' => []];
        }

        $labels = collect($matrix)
            ->flatMap(fn (array $items) => array_keys($items))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $datasets = collect($matrix)
            ->sortByDesc(fn (array $items) => array_sum($items))
            ->take(5)
            ->map(function (array $items, string $channel) use ($labels) {
                $counts = array_map(fn (string $label) => $items[$label] ?? 0, $labels);

                return [
                    'name' => $channel,
                    'data' => $this->percentageValues($counts),
                ];
            })
            ->values()
            ->all();

        return compact('labels', 'datasets');
    }

    private function totalSellableRooms(Hotel $hotel): int
    {
        $rooms = HotelRoom::query()
            ->where('hotel_id', $hotel->id)
            ->where('is_enabled', true)
            ->get();

        $total = (int) $rooms->sum(fn (HotelRoom $room) => max(1, (int) $room->room_count));

        return max(1, $total);
    }
}
