<?php

namespace App\Services;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelBarSale;
use App\Models\HotelConferenceBooking;
use App\Models\HotelLaundryOrder;
use App\Models\HotelRoom;
use App\Models\HotelRoomUnit;
use App\Models\HotelSwimmingVisit;
use App\Services\Bar\BarPurchaseListService;
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
        private FrankfurterExchangeRateService $exchangeRates,
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
        $periodDays = max(1, $periodStart->diffInDays($date) + 1);
        $totalRooms = $this->totalSellableRooms($hotel);
        $currency = $this->reportCurrencyForHotel($hotel);

        $dayLabel = $date->format('d-M');
        $periodLabel = $periodStart->format('d-M').' to '.$date->format('d-M');

        $reservations = CmReservation::query()
            ->where(function (Builder $query) use ($hotel, $hotelCode) {
                $query->where('hotel_id', $hotel->id)
                    ->orWhere('hotel_code', $hotelCode);
            })
            ->where('status', '!=', CmReservation::STATUS_CANCELLED)
            ->whereDate('checkin', '<=', $date)
            ->whereDate('checkout', '>', $periodStart)
            ->get();

        $dayPerformance = $this->emptyPerformanceBucket();
        $periodPerformance = $this->emptyPerformanceBucket();
        /** @var array<string, float> $dayRevenueByCurrency */
        $dayRevenueByCurrency = [];
        /** @var array<string, float> $periodRevenueByCurrency */
        $periodRevenueByCurrency = [];
        $daySources = [];
        $periodSources = [];

        foreach ($reservations as $reservation) {
            $channel = $reservation->sourceDisplayLabel() ?: 'Direct';
            $resCurrency = $this->reservationReportCurrency($reservation, $currency);
            $roomsOccupied = $this->occupiedRoomsOnNight($reservation, $date);

            if ($roomsOccupied > 0) {
                $nightlyRevenue = $this->nightlyRevenue($reservation);
                $this->addPerformance($dayPerformance, $roomsOccupied, $nightlyRevenue);
                $dayRevenueByCurrency[$resCurrency] = ($dayRevenueByCurrency[$resCurrency] ?? 0) + $nightlyRevenue;
                $this->addDailySourceRevenue($daySources, $channel, $roomsOccupied, $nightlyRevenue, $resCurrency);
            }

            foreach (CarbonPeriod::create($periodStart, $date) as $periodDay) {
                $roomsOnDay = $this->occupiedRoomsOnNight($reservation, Carbon::parse($periodDay)->startOfDay());

                if ($roomsOnDay <= 0) {
                    continue;
                }

                $nightlyRevenue = $this->nightlyRevenue($reservation);
                $this->addPerformance($periodPerformance, $roomsOnDay, $nightlyRevenue);
                $periodRevenueByCurrency[$resCurrency] = ($periodRevenueByCurrency[$resCurrency] ?? 0) + $nightlyRevenue;
                $this->addDailySourceRevenue($periodSources, $channel, $roomsOnDay, $nightlyRevenue, $resCurrency);
            }
        }

        $dayOccupancy = $totalRooms > 0
            ? (int) round(($dayPerformance['rooms_sold'] / $totalRooms) * 100)
            : 0;

        $periodOccupancy = $this->averagePeriodOccupancy($reservations, $periodStart, $date, $totalRooms);

        $dayRoomRevenue = $this->formatRevenueDisplayWithConversion($dayRevenueByCurrency, $currency, $date);
        $periodRoomRevenue = $this->formatRevenueDisplayWithConversion($periodRevenueByCurrency, $currency, $date);
        $serviceRows = $this->dailyReportServiceRows($hotel, $date, $periodStart, $currency);
        $sourceRows = $this->formatDailySourceRows($daySources, $periodSources, $currency, $date);

        $fxDates = array_filter([
            $dayRoomRevenue['rate_date'],
            $periodRoomRevenue['rate_date'],
            $serviceRows['fx_rate_date'] ?? null,
            $sourceRows['fx_rate_date'] ?? null,
        ]);

        return [
            'report_date' => $date->format('Y-m-d'),
            'day_label' => $dayLabel,
            'period_label' => $periodLabel,
            'currency' => $currency,
            'total_rooms' => $totalRooms,
            'exchange_rate_note' => $this->dailyReportExchangeNote($currency, $fxDates),
            'performance' => [
                ['label' => 'Rooms Sold', 'day' => (string) $dayPerformance['rooms_sold'], 'period' => (string) $periodPerformance['rooms_sold']],
                ['label' => 'Occupancy %', 'day' => $dayOccupancy.' %', 'period' => $periodOccupancy.' %'],
                [
                    'label' => 'Room revenue',
                    'day' => $dayRoomRevenue['display'],
                    'day_native' => $dayRoomRevenue['native'],
                    'period' => $periodRoomRevenue['display'],
                    'period_native' => $periodRoomRevenue['native'],
                ],
                [
                    'label' => 'ARR',
                    'day' => $this->dailyReportArrLabelWithFx($dayRevenueByCurrency, $dayPerformance['rooms_sold'], $currency, $date),
                    'period' => $this->dailyReportArrLabelWithFx($periodRevenueByCurrency, $periodPerformance['rooms_sold'], $currency, $date),
                ],
                [
                    'label' => 'RevPAR',
                    'day' => $this->dailyReportRevparLabelWithFx($dayRevenueByCurrency, $totalRooms, 1, $currency, $date),
                    'period' => $this->dailyReportRevparLabelWithFx($periodRevenueByCurrency, $totalRooms, $periodDays, $currency, $date),
                ],
            ],
            'sources' => $sourceRows['rows'],
            'services' => $serviceRows['rows'],
        ];
    }

    private function dailyReportUseFx(): bool
    {
        return (bool) config('hotel_analytics.daily_report.convert_mixed_currencies', true);
    }

    /**
     * @param  array<string, float>  $amountsByCurrency
     * @return array{display: string, native: ?string, rate_date: ?string}
     */
    private function formatRevenueDisplayWithConversion(array $amountsByCurrency, string $targetCurrency, Carbon $date): array
    {
        $native = $this->formatDailyRevenueAmounts($amountsByCurrency, $targetCurrency);
        $active = $this->nonZeroCurrencyAmounts($amountsByCurrency);

        if ($active === [] || ! $this->dailyReportUseFx() || ! $this->amountsNeedFxConversion($active, $targetCurrency)) {
            return [
                'display' => $native,
                'native' => null,
                'rate_date' => null,
            ];
        }

        $sum = $this->exchangeRates->sumInCurrency($active, $targetCurrency, $date->format('Y-m-d'));
        $display = $this->formatReportMoney($sum['total'], $targetCurrency);
        if ($sum['failed'] !== []) {
            $display .= ' + '.$this->formatDailyRevenueAmounts($sum['failed'], $targetCurrency);
        }

        return [
            'display' => $display,
            'native' => $native,
            'rate_date' => $sum['rate_date'],
        ];
    }

    /**
     * @param  array<string, float>  $amountsByCurrency
     * @return array<string, float>
     */
    private function nonZeroCurrencyAmounts(array $amountsByCurrency): array
    {
        return array_filter(
            $amountsByCurrency,
            fn ($amount): bool => abs((float) $amount) >= 0.009,
        );
    }

    /**
     * @param  array<string, float>  $amountsByCurrency
     */
    private function amountsNeedFxConversion(array $amountsByCurrency, string $targetCurrency): bool
    {
        $targetCurrency = strtoupper(trim($targetCurrency));
        foreach (array_keys($amountsByCurrency) as $code) {
            if (strtoupper(trim((string) $code)) !== $targetCurrency) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, float>  $amountsByCurrency
     */
    private function convertedRevenueTotal(array $amountsByCurrency, string $targetCurrency, Carbon $date): float
    {
        $active = $this->nonZeroCurrencyAmounts($amountsByCurrency);
        if ($active === []) {
            return 0.0;
        }

        if (! $this->dailyReportUseFx() || ! $this->amountsNeedFxConversion($active, $targetCurrency)) {
            return round(array_sum($active), 2);
        }

        return $this->exchangeRates->sumInCurrency($active, $targetCurrency, $date->format('Y-m-d'))['total'];
    }

    /**
     * @param  array<string, float>  $revenueByCurrency
     */
    private function dailyReportArrLabelWithFx(array $revenueByCurrency, int $roomsSold, string $targetCurrency, Carbon $date): string
    {
        if ($roomsSold <= 0) {
            return $this->formatReportMoney(0, $targetCurrency);
        }

        $total = $this->convertedRevenueTotal($revenueByCurrency, $targetCurrency, $date);

        return $this->formatReportMoney($total / $roomsSold, $targetCurrency);
    }

    /**
     * @param  array<string, float>  $revenueByCurrency
     */
    private function dailyReportRevparLabelWithFx(array $revenueByCurrency, int $totalRooms, int $days, string $targetCurrency, Carbon $date): string
    {
        if ($totalRooms <= 0) {
            return $this->formatReportMoney(0, $targetCurrency);
        }

        $available = max(1, $totalRooms * max(1, $days));
        $total = $this->convertedRevenueTotal($revenueByCurrency, $targetCurrency, $date);

        return $this->formatReportMoney($total / $available, $targetCurrency);
    }

    /** @param  list<?string>  $rateDates */
    private function dailyReportExchangeNote(string $targetCurrency, array $rateDates): ?string
    {
        if (! $this->dailyReportUseFx()) {
            return null;
        }

        $rateDates = array_values(array_unique(array_filter($rateDates)));
        if ($rateDates === []) {
            return null;
        }

        sort($rateDates);
        $dateLabel = count($rateDates) === 1 ? $rateDates[0] : ($rateDates[0].' – '.end($rateDates));

        return 'Mixed currencies converted to '.$targetCurrency.' using Frankfurter rates (as of '.$dateLabel.').';
    }

    private function reportCurrencyForHotel(Hotel $hotel): string
    {
        return app(BarPurchaseListService::class)->budgetCurrencyForHotel($hotel);
    }

    private function reservationReportCurrency(CmReservation $reservation, string $fallbackCurrency): string
    {
        $code = strtoupper(trim((string) ($reservation->currency ?? '')));

        return $code !== '' ? $code : $fallbackCurrency;
    }

    /**
     * @param  array<string, float>  $revenueByCurrency
     */
    private function formatDailyRevenueAmounts(array $revenueByCurrency, string $fallbackCurrency): string
    {
        if ($revenueByCurrency === []) {
            return $this->formatReportMoney(0, $fallbackCurrency);
        }

        $parts = [];
        ksort($revenueByCurrency);
        foreach ($revenueByCurrency as $code => $amount) {
            if (abs((float) $amount) < 0.009) {
                continue;
            }
            $parts[] = $this->formatReportMoney((float) $amount, (string) $code);
        }

        return $parts !== [] ? implode(' · ', $parts) : $this->formatReportMoney(0, $fallbackCurrency);
    }

    /**
     * @param  array<string, float>  $revenueByCurrency
     */
    private function dailyReportArrLabel(array $revenueByCurrency, int $roomsSold, string $fallbackCurrency): string
    {
        if ($roomsSold <= 0) {
            return $this->formatReportMoney(0, $fallbackCurrency);
        }

        $active = array_filter($revenueByCurrency, fn ($amount): bool => abs((float) $amount) >= 0.009);
        if (count($active) !== 1) {
            return '—';
        }

        $code = (string) array_key_first($active);

        return $this->formatReportMoney((float) $active[$code] / $roomsSold, $code);
    }

    /**
     * @param  array<string, float>  $revenueByCurrency
     */
    private function dailyReportRevparLabel(array $revenueByCurrency, int $totalRooms, int $days, string $fallbackCurrency): string
    {
        $available = max(1, $totalRooms * max(1, $days));
        if ($totalRooms <= 0) {
            return $this->formatReportMoney(0, $fallbackCurrency);
        }

        $active = array_filter($revenueByCurrency, fn ($amount): bool => abs((float) $amount) >= 0.009);
        if (count($active) !== 1) {
            return '—';
        }

        $code = (string) array_key_first($active);

        return $this->formatReportMoney((float) $active[$code] / $available, $code);
    }

    /**
     * @param  array<string, array{rooms: int, by_currency: array<string, float>}>  $sources
     */
    private function addDailySourceRevenue(array &$sources, string $name, int $rooms, float $revenue, string $currency): void
    {
        if (! isset($sources[$name])) {
            $sources[$name] = ['rooms' => 0, 'by_currency' => []];
        }

        $sources[$name]['rooms'] += $rooms;
        $sources[$name]['by_currency'][$currency] = ($sources[$name]['by_currency'][$currency] ?? 0) + $revenue;
    }

    private function formatReportMoney(float $amount, string $currency): string
    {
        $currency = strtoupper(trim($currency ?: 'TZS'));
        $wholeNumberCurrencies = ['TZS', 'UGX', 'RWF', 'BIF', 'JPY'];
        $useZeroDecimals = in_array($currency, $wholeNumberCurrencies, true);
        $decimals = 2;
        if ($useZeroDecimals) {
            $decimals = 0;
        } elseif ($amount == floor($amount)) {
            $decimals = 0;
        }

        return number_format($amount, $decimals).' '.$currency;
    }

    /**
     * @param  array<string, array{count: int, revenue: float}>  $byCurrency
     */
    private function formatDailyRevenueMix(array $byCurrency, string $fallbackCurrency): string
    {
        $amounts = [];
        foreach ($byCurrency as $code => $bucket) {
            $amounts[(string) $code] = (float) ($bucket['revenue'] ?? 0);
        }

        return $this->formatDailyRevenueAmounts($amounts, $fallbackCurrency);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object{currency?: string|null, total?: mixed}>  $records
     * @return array{count: int, by_currency: array<string, array{count: int, revenue: float}>}
     */
    private function aggregateDailyRecordsByCurrency(Collection $records, string $fallbackCurrency, Hotel $hotel): array
    {
        $byCurrency = [];
        foreach ($records as $record) {
            $code = $this->serviceTransactionCurrency($record, $hotel, $fallbackCurrency);
            if (! isset($byCurrency[$code])) {
                $byCurrency[$code] = ['count' => 0, 'revenue' => 0.0];
            }
            $byCurrency[$code]['count']++;
            $byCurrency[$code]['revenue'] += (float) ($record->total ?? 0);
        }

        return [
            'count' => $records->count(),
            'by_currency' => $byCurrency,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dailyReportServiceRows(Hotel $hotel, Carbon $date, Carbon $periodStart, string $reportCurrency): array
    {
        $definitions = config('hotel_analytics.daily_report.services', []);
        $metricsByKey = [
            'bar' => $this->dailyReportBarMetrics($hotel, $date, $periodStart, $reportCurrency),
            'laundry' => $this->dailyReportLaundryMetrics($hotel, $date, $periodStart, $reportCurrency),
            'swimming' => $this->dailyReportSwimmingMetrics($hotel, $date, $periodStart, $reportCurrency),
            'conference' => $this->dailyReportConferenceMetrics($hotel, $date, $periodStart, $reportCurrency),
        ];

        $rows = [];
        $dayCountTotal = 0;
        $periodCountTotal = 0;
        /** @var array<string, float> $dayRevenueTotals */
        $dayRevenueTotals = [];
        /** @var array<string, float> $periodRevenueTotals */
        $periodRevenueTotals = [];

        foreach ($definitions as $definition) {
            $key = (string) ($definition['key'] ?? '');
            $label = (string) ($definition['label'] ?? $key);
            if ($key === '' || ! isset($metricsByKey[$key])) {
                continue;
            }

            $metrics = $metricsByKey[$key];
            $dayCountTotal += $metrics['day_count'];
            $periodCountTotal += $metrics['period_count'];

            foreach ($metrics['day_by_currency'] as $code => $amount) {
                $dayRevenueTotals[$code] = ($dayRevenueTotals[$code] ?? 0) + (float) $amount;
            }
            foreach ($metrics['period_by_currency'] as $code => $amount) {
                $periodRevenueTotals[$code] = ($periodRevenueTotals[$code] ?? 0) + (float) $amount;
            }

            $rows[] = [
                'label' => $label,
                'day_count' => (string) $metrics['day_count'],
                'day_revenue' => $this->formatDailyRevenueAmounts($metrics['day_by_currency'], $reportCurrency),
                'period_count' => (string) $metrics['period_count'],
                'period_revenue' => $this->formatDailyRevenueAmounts($metrics['period_by_currency'], $reportCurrency),
            ];
        }

        if ($rows === []) {
            return ['rows' => [], 'fx_rate_date' => null];
        }

        $dayTotal = $this->formatRevenueDisplayWithConversion($dayRevenueTotals, $reportCurrency, $date);
        $periodTotal = $this->formatRevenueDisplayWithConversion($periodRevenueTotals, $reportCurrency, $date);

        $rows[] = [
            'label' => 'Total',
            'day_count' => (string) $dayCountTotal,
            'day_revenue' => $dayTotal['display'],
            'day_revenue_native' => $dayTotal['native'],
            'period_count' => (string) $periodCountTotal,
            'period_revenue' => $periodTotal['display'],
            'period_revenue_native' => $periodTotal['native'],
            'is_total' => true,
        ];

        return [
            'rows' => $rows,
            'fx_rate_date' => $periodTotal['rate_date'] ?? $dayTotal['rate_date'],
        ];
    }

    /**
     * @return array{day_count: int, period_count: int, day_by_currency: array<string, float>, period_by_currency: array<string, float>}
     */
    private function dailyReportBarMetrics(Hotel $hotel, Carbon $date, Carbon $periodStart, string $fallbackCurrency): array
    {
        $base = HotelBarSale::query()->where('hotel_id', $hotel->id);

        return $this->dailyReportCountAndRevenue($base, 'sold_at', $date, $periodStart, $fallbackCurrency, $hotel);
    }

    /**
     * @return array{day_count: int, period_count: int, day_by_currency: array<string, float>, period_by_currency: array<string, float>}
     */
    private function dailyReportLaundryMetrics(Hotel $hotel, Carbon $date, Carbon $periodStart, string $fallbackCurrency): array
    {
        $base = HotelLaundryOrder::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', '!=', HotelLaundryOrder::STATUS_CANCELLED)
            ->with('service');

        return $this->dailyReportCountAndRevenue($base, 'received_at', $date, $periodStart, $fallbackCurrency, $hotel);
    }

    /**
     * @return array{day_count: int, period_count: int, day_by_currency: array<string, float>, period_by_currency: array<string, float>}
     */
    private function dailyReportSwimmingMetrics(Hotel $hotel, Carbon $date, Carbon $periodStart, string $fallbackCurrency): array
    {
        $base = HotelSwimmingVisit::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', '!=', HotelSwimmingVisit::STATUS_CANCELLED)
            ->with('service');

        $dayRecords = (clone $base)->where(function (Builder $query) use ($date) {
            $this->scopeSwimmingOnReportDay($query, $date);
        })->get();

        $periodRecords = (clone $base)->where(function (Builder $query) use ($periodStart, $date) {
            $this->scopeSwimmingInReportPeriod($query, $periodStart, $date);
        })->get();

        $day = $this->aggregateDailyRecordsByCurrency($dayRecords, $fallbackCurrency, $hotel);
        $period = $this->aggregateDailyRecordsByCurrency($periodRecords, $fallbackCurrency, $hotel);

        return $this->packageDailyServiceMetrics($day, $period);
    }

    /**
     * @return array{day_count: int, period_count: int, day_by_currency: array<string, float>, period_by_currency: array<string, float>}
     */
    private function dailyReportConferenceMetrics(Hotel $hotel, Carbon $date, Carbon $periodStart, string $fallbackCurrency): array
    {
        $base = HotelConferenceBooking::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', '!=', HotelConferenceBooking::STATUS_CANCELLED)
            ->with('service');

        return $this->dailyReportCountAndRevenue($base, 'starts_at', $date, $periodStart, $fallbackCurrency, $hotel);
    }

    /**
     * @param  array{count: int, by_currency: array<string, array{count: int, revenue: float}>}  $day
     * @param  array{count: int, by_currency: array<string, array{count: int, revenue: float}>}  $period
     * @return array{day_count: int, period_count: int, day_by_currency: array<string, float>, period_by_currency: array<string, float>}
     */
    private function packageDailyServiceMetrics(array $day, array $period): array
    {
        return [
            'day_count' => $day['count'],
            'period_count' => $period['count'],
            'day_by_currency' => $this->revenueAmountsFromServiceBuckets($day['by_currency']),
            'period_by_currency' => $this->revenueAmountsFromServiceBuckets($period['by_currency']),
        ];
    }

    /**
     * @param  array<string, array{count: int, revenue: float}>  $byCurrency
     * @return array<string, float>
     */
    private function revenueAmountsFromServiceBuckets(array $byCurrency): array
    {
        $amounts = [];
        foreach ($byCurrency as $code => $bucket) {
            $amounts[(string) $code] = (float) ($bucket['revenue'] ?? 0);
        }

        return $amounts;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $base
     * @return array{day_count: int, period_count: int, day_by_currency: array<string, float>, period_by_currency: array<string, float>}
     */
    private function dailyReportCountAndRevenue(
        Builder $base,
        string $dateColumn,
        Carbon $date,
        Carbon $periodStart,
        string $fallbackCurrency,
        Hotel $hotel,
    ): array {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();
        $periodBegin = $periodStart->copy()->startOfDay();
        $periodEnd = $date->copy()->endOfDay();

        $dayRecords = (clone $base)->whereBetween($dateColumn, [$dayStart, $dayEnd])->get();
        $periodRecords = (clone $base)->whereBetween($dateColumn, [$periodBegin, $periodEnd])->get();

        $day = $this->aggregateDailyRecordsByCurrency($dayRecords, $fallbackCurrency, $hotel);
        $period = $this->aggregateDailyRecordsByCurrency($periodRecords, $fallbackCurrency, $hotel);

        return $this->packageDailyServiceMetrics($day, $period);
    }

    private function serviceTransactionCurrency(object $record, Hotel $hotel, string $fallbackCurrency): string
    {
        if ($record instanceof HotelLaundryOrder) {
            return strtoupper($record->displayCurrency($hotel));
        }

        if ($record instanceof HotelSwimmingVisit) {
            $code = strtoupper(trim((string) ($record->currency ?? '')));
            if ($code !== '') {
                return $code;
            }
            $record->loadMissing('service');
            if ($record->service) {
                return strtoupper($record->service->displayCurrency($hotel));
            }
        }

        if ($record instanceof HotelConferenceBooking) {
            $code = strtoupper(trim((string) ($record->currency ?? '')));
            if ($code !== '') {
                return $code;
            }
            $record->loadMissing('service');
            if ($record->service) {
                return strtoupper($record->service->displayCurrency($hotel));
            }
        }

        $code = strtoupper(trim((string) ($record->currency ?? '')));

        return $code !== '' ? $code : $fallbackCurrency;
    }

    /** @param  Builder<HotelSwimmingVisit>  $query */
    private function scopeSwimmingOnReportDay(Builder $query, Carbon $date): void
    {
        $query->where(function (Builder $inner) use ($date) {
            $inner->whereDate('entered_at', $date)
                ->orWhere(function (Builder $fallback) use ($date) {
                    $fallback->whereNull('entered_at')
                        ->whereDate('scheduled_date', $date);
                });
        });
    }

    /** @param  Builder<HotelSwimmingVisit>  $query */
    private function scopeSwimmingInReportPeriod(Builder $query, Carbon $periodStart, Carbon $date): void
    {
        $from = $periodStart->copy()->startOfDay()->format('Y-m-d');
        $to = $date->copy()->format('Y-m-d');

        $query->where(function (Builder $inner) use ($from, $to) {
            $inner->where(function (Builder $entered) use ($from, $to) {
                $entered->whereNotNull('entered_at')
                    ->whereDate('entered_at', '>=', $from)
                    ->whereDate('entered_at', '<=', $to);
            })->orWhere(function (Builder $scheduled) use ($from, $to) {
                $scheduled->whereNull('entered_at')
                    ->whereDate('scheduled_date', '>=', $from)
                    ->whereDate('scheduled_date', '<=', $to);
            });
        });
    }

    private function nightlyRevenue(CmReservation $reservation): float
    {
        $total = (float) ($reservation->amount_after_tax ?? 0);
        $nights = max(1, (int) ($reservation->roomNightCount() ?? 1));

        return round($total / $nights, 2);
    }

    /** @return array{rooms_sold: int, revenue: float} */
    private function emptyPerformanceBucket(): array
    {
        return ['rooms_sold' => 0, 'revenue' => 0.0];
    }

    /** @param  array{rooms_sold: int, revenue: float}  $bucket */
    private function addPerformance(array &$bucket, int $roomsSold, float $revenue): void
    {
        $bucket['rooms_sold'] += $roomsSold;
        $bucket['revenue'] += $revenue;
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

    /** @param  array{rooms_sold: int, revenue: float}  $bucket */
    private function arr(array $bucket): float
    {
        return $bucket['rooms_sold'] > 0 ? round($bucket['revenue'] / $bucket['rooms_sold'], 2) : 0;
    }

    /** @param  array{rooms_sold: int, revenue: float}  $bucket */
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
     * @param  array<string, array{rooms: int, by_currency: array<string, float>}>  $daySources
     * @param  array<string, array{rooms: int, by_currency: array<string, float>}>  $periodSources
     * @return list<array<string, mixed>>
     */
    /**
     * @return array{rows: list<array<string, mixed>>, fx_rate_date: ?string}
     */
    private function formatDailySourceRows(array $daySources, array $periodSources, string $currency, Carbon $date): array
    {
        $names = collect(array_keys($daySources))
            ->merge(array_keys($periodSources))
            ->unique()
            ->sort()
            ->values();

        if ($names->isEmpty()) {
            return [
                'rows' => [[
                    'label' => 'Total',
                    'day_rooms' => '0',
                    'day_revenue' => $this->formatReportMoney(0, $currency),
                    'period_rooms' => '0',
                    'period_revenue' => $this->formatReportMoney(0, $currency),
                ]],
                'fx_rate_date' => null,
            ];
        }

        $rows = [];
        $dayRevenueTotals = [];
        $periodRevenueTotals = [];

        foreach ($names as $name) {
            $dayRooms = (int) ($daySources[$name]['rooms'] ?? 0);
            $dayByCurrency = $daySources[$name]['by_currency'] ?? [];
            $periodRooms = (int) ($periodSources[$name]['rooms'] ?? 0);
            $periodByCurrency = $periodSources[$name]['by_currency'] ?? [];

            foreach ($dayByCurrency as $code => $amount) {
                $dayRevenueTotals[$code] = ($dayRevenueTotals[$code] ?? 0) + (float) $amount;
            }
            foreach ($periodByCurrency as $code => $amount) {
                $periodRevenueTotals[$code] = ($periodRevenueTotals[$code] ?? 0) + (float) $amount;
            }

            if ($dayRooms === 0 && $dayByCurrency === [] && $periodRooms === 0 && $periodByCurrency === []) {
                continue;
            }

            $rows[] = [
                'label' => $name,
                'day_rooms' => (string) $dayRooms,
                'day_revenue' => $this->formatDailyRevenueAmounts($dayByCurrency, $currency),
                'period_rooms' => (string) $periodRooms,
                'period_revenue' => $this->formatDailyRevenueAmounts($periodByCurrency, $currency),
            ];
        }

        $dayTotal = $this->formatRevenueDisplayWithConversion($dayRevenueTotals, $currency, $date);
        $periodTotal = $this->formatRevenueDisplayWithConversion($periodRevenueTotals, $currency, $date);

        $rows[] = [
            'label' => 'Total',
            'day_rooms' => (string) collect($daySources)->sum('rooms'),
            'day_revenue' => $dayTotal['display'],
            'day_revenue_native' => $dayTotal['native'],
            'period_rooms' => (string) collect($periodSources)->sum('rooms'),
            'period_revenue' => $periodTotal['display'],
            'period_revenue_native' => $periodTotal['native'],
            'is_total' => true,
        ];

        return [
            'rows' => $rows,
            'fx_rate_date' => $periodTotal['rate_date'] ?? $dayTotal['rate_date'],
        ];
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
        $unitCount = HotelRoomUnit::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', '!=', HotelRoomUnit::STATUS_INACTIVE)
            ->whereHas('roomType', fn (Builder $query) => $query->where('is_enabled', true))
            ->count();

        if ($unitCount > 0) {
            return $unitCount;
        }

        $rooms = HotelRoom::query()
            ->where('hotel_id', $hotel->id)
            ->where('is_enabled', true)
            ->get();

        $total = (int) $rooms->sum(fn (HotelRoom $room) => max(1, (int) $room->room_count));

        return max(1, $total);
    }
}
