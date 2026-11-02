<?php

namespace App\Services;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelRatePlan;
use App\Models\HotelRoom;
use App\Services\Reports\PmsReportGenerator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ReportDataService
{
    public function __construct(
        private CreateReservationService $reservations,
        private OtaLogoService $otaLogos,
        private PmsReportGenerator $generator,
    ) {}

    /** @return array<string, mixed> */
    public function uiConfig(): array
    {
        return config('hotel_pms.reports', []);
    }

    /** @return array<string, mixed> */
    public function filterOptions(Hotel $hotel, string $hotelCode): array
    {
        $reservationOptions = $this->reservations->formOptions($hotel);

        $sources = CmReservation::query()
            ->where(function (Builder $query) use ($hotel, $hotelCode) {
                $query->where('hotel_id', $hotel->id)
                    ->orWhere('hotel_code', $hotelCode);
            })
            ->whereNotNull('channel')
            ->where('channel', '!=', '')
            ->distinct()
            ->orderBy('channel')
            ->pluck('channel')
            ->map(fn (string $channel) => [
                'value' => $channel,
                'label' => $this->otaLogos->presentationForChannel($channel)['name'],
            ])
            ->values()
            ->all();

        if (! collect($sources)->contains(fn (array $row) => strtolower($row['value']) === 'direct')) {
            array_unshift($sources, ['value' => 'Direct', 'label' => 'Direct']);
        }

        $rooms = HotelRoom::query()
            ->where('hotel_id', $hotel->id)
            ->where('is_enabled', true)
            ->orderBy('rank')
            ->orderBy('name')
            ->get()
            ->map(fn (HotelRoom $room) => [
                'value' => (string) $room->id,
                'label' => $room->display_name ?: $room->name,
            ])
            ->values()
            ->all();

        $ratePlans = HotelRatePlan::query()
            ->where('hotel_id', $hotel->id)
            ->with('room')
            ->orderBy('code')
            ->get()
            ->map(fn (HotelRatePlan $plan) => [
                'value' => (string) $plan->id,
                'label' => trim(($plan->room?->display_name ?: $plan->room?->name).', '.$plan->code.', '.$plan->meal_plan, ' ,'),
            ])
            ->values()
            ->all();

        return [
            'sources' => $sources,
            'segments' => collect($reservationOptions['segments'] ?? [])
                ->map(fn (string $segment) => ['value' => $segment, 'label' => $segment])
                ->values()
                ->all(),
            'room_types' => $rooms,
            'statuses' => [
                ['value' => CmReservation::STATUS_CONFIRMED, 'label' => 'Confirmed'],
                ['value' => CmReservation::STATUS_MODIFIED, 'label' => 'Modified'],
                ['value' => CmReservation::STATUS_CANCELLED, 'label' => 'Cancelled'],
            ],
            'rate_plans' => $ratePlans,
        ];
    }

    /** @return array<string, mixed> */
    public function filtersFromRequest(Request $request): array
    {
        $defaultReport = $this->uiConfig()['default_report'] ?? 'departure_report';
        $filterBy = trim((string) $request->input('filter_by', ''));
        $filterValue = trim((string) $request->input('filter_value', ''));

        $allowed = ['source', 'segment', 'room_type', 'status', 'rate_plan'];
        if (! in_array($filterBy, $allowed, true)) {
            $filterBy = '';
            $filterValue = '';
        }

        $reportKey = $this->resolveReportKey((string) $request->input('report', $defaultReport));

        if ($reportKey === 'monthly_pl' && $request->filled('from_month')) {
            $fromDate = Carbon::parse($request->input('from_month').'-01')->startOfMonth()->format('Y-m-d');
        } else {
            $fromDate = $request->input('from_date', now()->format('Y-m-d'));
        }

        if ($reportKey === 'monthly_pl' && $request->filled('to_month')) {
            $toDate = Carbon::parse($request->input('to_month').'-01')->endOfMonth()->format('Y-m-d');
        } else {
            $toDate = $request->input('to_date', now()->format('Y-m-d'));
        }

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $fromMonth = Carbon::parse($fromDate)->format('Y-m');
        $toMonth = Carbon::parse($toDate)->format('Y-m');

        return [
            'report' => $reportKey,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'from_month' => $fromMonth,
            'to_month' => $toMonth,
            'filter_by' => $filterBy,
            'filter_value' => $filterValue,
            'source' => $filterBy === 'source' ? $filterValue : '',
            'segment' => $filterBy === 'segment' ? $filterValue : '',
            'room_type' => $filterBy === 'room_type' ? $filterValue : '',
            'status' => $filterBy === 'status' ? $filterValue : '',
            'rate_plan' => $filterBy === 'rate_plan' ? $filterValue : '',
            'guest_search' => trim((string) $request->input('guest_search', '')),
        ];
    }

    /** @return array{available: bool, title: string, columns: list<string>, rows: list<array<string, string>>} */
    public function generate(Hotel $hotel, string $hotelCode, array $filters): array
    {
        $meta = $this->reportMeta($filters['report']);

        if (! $meta || ! ($meta['implemented'] ?? false)) {
            return [
                'available' => false,
                'title' => $meta['label'] ?? 'Report',
                'columns' => [],
                'rows' => [],
                'summary' => null,
            ];
        }

        return $this->generator->generate(
            $hotel,
            $hotelCode,
            $filters['report'],
            $filters,
            (string) ($meta['label'] ?? 'Report'),
        );
    }

    /** @return array<string, mixed>|null */
    public function reportMeta(string $reportKey): ?array
    {
        foreach ($this->uiConfig()['categories'] ?? [] as $category) {
            foreach ($category['reports'] ?? [] as $report) {
                if (($report['key'] ?? '') === $reportKey) {
                    return $report;
                }
            }
        }

        return null;
    }

    private function resolveReportKey(string $reportKey): string
    {
        return $this->reportMeta($reportKey) ? $reportKey : ($this->uiConfig()['default_report'] ?? 'departure_report');
    }
}
