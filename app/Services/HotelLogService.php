<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelLog;
use App\Models\HotelRoom;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HotelLogService
{
    /** @return array<string, mixed> */
    public function uiConfig(): array
    {
        return config('hotel_pms.logs', []);
    }

    /** @return array<string, mixed> */
    public function filtersFromRequest(Request $request): array
    {
        $ui = $this->uiConfig();
        $defaultDays = (int) ($ui['default_date_range_days'] ?? 1);
        $fromDate = $request->input('from_date', now()->subDays($defaultDays)->format('Y-m-d'));
        $toDate = $request->input('to_date', now()->format('Y-m-d'));

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $roomType = trim((string) $request->input('room_type', ''));
        $roomNo = trim((string) $request->input('room_no', ''));

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'room_type' => $roomType,
            'room_no' => $roomNo,
            'invoice_no' => trim((string) $request->input('invoice_no', '')),
            'booking_id' => trim((string) $request->input('booking_id', '')),
            'payments' => $request->boolean('payments'),
            'out_of_order' => $request->boolean('out_of_order'),
            'complimentary' => $request->boolean('complimentary'),
            'per_page' => $this->perPageFromRequest($request),
            'submitted' => $request->has('submitted'),
        ];
    }

    /** @return array<string, mixed> */
    public function filterOptions(Hotel $hotel): array
    {
        $rooms = $hotel->rooms()
            ->where('is_enabled', true)
            ->orderBy('rank')
            ->orderBy('name')
            ->get();

        $roomTypes = $rooms->map(fn (HotelRoom $room) => [
            'value' => (string) $room->id,
            'label' => $room->display_name ?: $room->name,
        ])->values()->all();

        $roomNumbers = $rooms
            ->flatMap(fn (HotelRoom $room) => $room->units()->orderBy('room_number')->get())
            ->map(fn ($unit) => [
                'value' => (string) ($unit->room_number ?: $unit->label),
                'label' => (string) ($unit->room_number ?: $unit->label),
            ])
            ->filter(fn (array $row) => $row['value'] !== '' && $row['value'] !== '—')
            ->unique('value')
            ->values()
            ->all();

        return [
            'room_types' => $roomTypes,
            'room_numbers' => $roomNumbers,
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function query(int $hotelId, array $filters): Builder
    {
        $query = HotelLog::query()
            ->where('hotel_id', $hotelId)
            ->orderByDesc('logged_at')
            ->orderByDesc('id');

        if (! empty($filters['from_date'])) {
            $query->where('logged_at', '>=', Carbon::parse($filters['from_date'])->startOfDay());
        }

        if (! empty($filters['to_date'])) {
            $query->where('logged_at', '<=', Carbon::parse($filters['to_date'])->endOfDay());
        }

        if ($filters['room_type'] !== '') {
            $query->where('hotel_room_id', (int) $filters['room_type']);
        }

        if ($filters['room_no'] !== '') {
            $query->where('room_no', $filters['room_no']);
        }

        if ($filters['invoice_no'] !== '') {
            $query->where('folio_no', 'like', '%'.$filters['invoice_no'].'%');
        }

        if ($filters['booking_id'] !== '') {
            $query->where('booking_id', 'like', '%'.$filters['booking_id'].'%');
        }

        $categories = [];

        if ($filters['payments']) {
            $categories[] = HotelLog::CATEGORY_PAYMENTS;
        }

        if ($filters['out_of_order']) {
            $categories[] = HotelLog::CATEGORY_OUT_OF_ORDER;
        }

        if ($filters['complimentary']) {
            $categories[] = HotelLog::CATEGORY_COMPLIMENTARY;
        }

        if ($categories !== []) {
            $query->whereIn('category', $categories);
        }

        return $query;
    }

    /** @param  array<string, mixed>  $data */
    public function record(Hotel $hotel, array $data): HotelLog
    {
        $user = Auth::user();

        return HotelLog::query()->create([
            'hotel_id' => $hotel->id,
            'action_type' => $data['action_type'],
            'category' => $data['category'] ?? HotelLog::CATEGORY_GENERAL,
            'booking_id' => $data['booking_id'] ?? null,
            'guest_name' => $data['guest_name'] ?? null,
            'folio_no' => $data['folio_no'] ?? null,
            'room_no' => $data['room_no'] ?? null,
            'hotel_room_id' => $data['hotel_room_id'] ?? null,
            'details' => $data['details'] ?? null,
            'changed_by' => $data['changed_by'] ?? ($user?->email ?: 'system'),
            'logged_at' => $data['logged_at'] ?? now(),
        ]);
    }

    private function perPageFromRequest(Request $request): int
    {
        $ui = $this->uiConfig();
        $options = $ui['per_page_options'] ?? [20, 50, 100];
        $perPage = (int) $request->input('per_page', $ui['default_per_page'] ?? 20);

        return in_array($perPage, $options, true) ? $perPage : (int) ($ui['default_per_page'] ?? 20);
    }
}
