<?php

namespace App\Services;

use App\Models\CmReservation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ReservationDataService
{
    /** @return array<string, mixed> */
    public function uiConfig(): array
    {
        return config('hotel_pms.reservation_data', []);
    }

    /** @return array<string, mixed> */
    public function filtersFromRequest(Request $request): array
    {
        $ui = $this->uiConfig();
        $filterOptions = array_keys($ui['filter_options'] ?? ['booking_date' => 'Booking']);
        $filterBy = $request->input('filter_by', 'booking_date');

        if (! in_array($filterBy, $filterOptions, true)) {
            $filterBy = 'booking_date';
        }

        $defaultDays = (int) ($ui['default_date_range_days'] ?? 7);

        return [
            'filter_by' => $filterBy,
            'from_date' => $request->input('from_date', now()->subDays($defaultDays)->format('Y-m-d')),
            'to_date' => $request->input('to_date', now()->format('Y-m-d')),
            'search' => trim((string) $request->input('search', '')),
            'per_page' => $this->perPageFromRequest($request),
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function query(int $hotelId, string $hotelCode, array $filters): Builder
    {
        $query = CmReservation::query()
            ->where(function ($q) use ($hotelId, $hotelCode) {
                $q->where('hotel_id', $hotelId)
                    ->orWhere('hotel_code', $hotelCode);
            });

        $dateColumn = match ($filters['filter_by']) {
            'checkin' => 'checkin',
            'checkout' => 'checkout',
            'cancelled' => 'updated_at',
            default => 'created_at',
        };

        if (! empty($filters['from_date'])) {
            $from = Carbon::parse($filters['from_date'])->startOfDay();
            $query->where($dateColumn, '>=', $from);
        }

        if (! empty($filters['to_date'])) {
            $to = Carbon::parse($filters['to_date'])->endOfDay();
            $query->where($dateColumn, '<=', $to);
        }

        if ($filters['filter_by'] === 'cancelled') {
            $query->where('status', CmReservation::STATUS_CANCELLED);
        }

        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('booking_id', 'like', $term)
                    ->orWhere('channel', 'like', $term)
                    ->orWhere('guest_first_name', 'like', $term)
                    ->orWhere('guest_last_name', 'like', $term);
            });
        }

        return $query->orderByDesc($dateColumn === 'created_at' ? 'created_at' : $dateColumn)
            ->orderByDesc('id');
    }

    private function perPageFromRequest(Request $request): int
    {
        $ui = $this->uiConfig();
        $options = $ui['per_page_options'] ?? [20, 50, 100];
        $perPage = (int) $request->input('per_page', $ui['default_per_page'] ?? 20);

        return in_array($perPage, $options, true) ? $perPage : (int) ($ui['default_per_page'] ?? 20);
    }
}
