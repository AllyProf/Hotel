<?php

namespace App\Services;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelRatePlan;
use App\Models\HotelRoom;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ReportDataService
{
    public function __construct(
        private CreateReservationService $reservations,
        private OtaLogoService $otaLogos,
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

        return [
            'report' => $this->resolveReportKey((string) $request->input('report', $defaultReport)),
            'from_date' => $request->input('from_date', now()->format('Y-m-d')),
            'to_date' => $request->input('to_date', now()->format('Y-m-d')),
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
            ];
        }

        $dateColumn = $filters['report'] === 'arrival_report' ? 'checkin' : 'checkout';
        $bookings = $this->baseQuery($hotel, $hotelCode, $filters, $dateColumn)->get();

        $rows = $bookings->map(function (CmReservation $booking) {
            return [
                'Booking ID' => $booking->booking_id,
                'Guest Name' => $booking->guestName(),
                'Check-In' => $booking->checkinLabel(),
                'Check-Out' => $booking->checkoutLabel(),
                'Source' => $this->otaLogos->presentationForChannel((string) $booking->channel)['name'],
                'Segment' => $booking->categoryLabel() !== '—' ? $booking->categoryLabel() : '',
                'Room Type' => $booking->roomLabel() !== '—' ? $booking->roomLabel() : '',
                'Rate Plan' => $booking->mealPlanLabel() !== '—' ? $booking->mealPlanLabel() : '',
                'Status' => $booking->statusLabel(),
                'Amount' => $booking->priceLabel() !== '—' ? $booking->priceLabel() : '',
            ];
        })->values()->all();

        return [
            'available' => true,
            'title' => $meta['label'],
            'columns' => array_keys($rows[0] ?? [
                'Booking ID' => '',
                'Guest Name' => '',
                'Check-In' => '',
                'Check-Out' => '',
                'Source' => '',
                'Segment' => '',
                'Room Type' => '',
                'Rate Plan' => '',
                'Status' => '',
                'Amount' => '',
            ]),
            'rows' => $rows,
        ];
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

    /** @param  array<string, mixed>  $filters */
    private function baseQuery(Hotel $hotel, string $hotelCode, array $filters, string $dateColumn): Builder
    {
        $query = CmReservation::query()
            ->where(function (Builder $builder) use ($hotel, $hotelCode) {
                $builder->where('hotel_id', $hotel->id)
                    ->orWhere('hotel_code', $hotelCode);
            });

        if (! empty($filters['from_date'])) {
            $query->whereDate($dateColumn, '>=', Carbon::parse($filters['from_date'])->format('Y-m-d'));
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate($dateColumn, '<=', Carbon::parse($filters['to_date'])->format('Y-m-d'));
        }

        if ($filters['source'] !== '') {
            $query->where('channel', $filters['source']);
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['segment'] !== '') {
            $segment = $filters['segment'];
            $query->where(function (Builder $builder) use ($segment) {
                $builder->where('payload->segment', $segment)
                    ->orWhere('payload->category', $segment)
                    ->orWhere('payload->bookingCategory', $segment);
            });
        }

        if ($filters['guest_search'] !== '') {
            $term = '%'.$filters['guest_search'].'%';
            $query->where(function (Builder $builder) use ($term) {
                $builder->where('guest_first_name', 'like', $term)
                    ->orWhere('guest_last_name', 'like', $term)
                    ->orWhere('booking_id', 'like', $term);
            });
        }

        $bookings = $query->orderBy($dateColumn)->orderBy('booking_id')->get();

        if ($filters['room_type'] !== '' || $filters['rate_plan'] !== '') {
            $room = $filters['room_type'] !== ''
                ? HotelRoom::query()->where('hotel_id', $hotel->id)->find($filters['room_type'])
                : null;

            $plan = $filters['rate_plan'] !== ''
                ? HotelRatePlan::query()->where('hotel_id', $hotel->id)->with('room')->find($filters['rate_plan'])
                : null;

            $bookingIds = $bookings->filter(function (CmReservation $booking) use ($room, $plan) {
                if ($room && ! $this->bookingMatchesRoom($booking, $room)) {
                    return false;
                }

                if ($plan && ! $this->bookingMatchesRatePlan($booking, $plan)) {
                    return false;
                }

                return true;
            })->pluck('id');

            return CmReservation::query()->whereIn('id', $bookingIds)->orderBy($dateColumn)->orderBy('booking_id');
        }

        return $query->orderBy($dateColumn)->orderBy('booking_id');
    }

    private function bookingMatchesRoom(CmReservation $booking, HotelRoom $room): bool
    {
        $roomName = $room->display_name ?: $room->name;

        foreach ($booking->roomLines() as $line) {
            $code = trim((string) ($line['roomCode'] ?? $line['room_code'] ?? ''));
            $name = trim((string) ($line['roomName'] ?? $line['room_name'] ?? ''));

            if ($code === $room->name || $name === $roomName) {
                return true;
            }
        }

        return false;
    }

    private function bookingMatchesRatePlan(CmReservation $booking, HotelRatePlan $plan): bool
    {
        foreach ($booking->roomLines() as $line) {
            $code = trim((string) ($line['rateplanCode'] ?? $line['rateplan_code'] ?? ''));
            $mealPlan = strtoupper(trim((string) ($line['mealPlan'] ?? $line['meal_plan'] ?? '')));

            if ($code === $plan->code) {
                return true;
            }

            if ($mealPlan !== '' && strtoupper((string) $plan->meal_plan) === $mealPlan) {
                return true;
            }
        }

        $payloadPlan = strtoupper(trim((string) (is_array($booking->payload) ? ($booking->payload['meal_plan'] ?? '') : '')));

        return $payloadPlan !== '' && $payloadPlan === strtoupper((string) $plan->meal_plan);
    }

    private function resolveReportKey(string $reportKey): string
    {
        return $this->reportMeta($reportKey) ? $reportKey : ($this->uiConfig()['default_report'] ?? 'departure_report');
    }
}
