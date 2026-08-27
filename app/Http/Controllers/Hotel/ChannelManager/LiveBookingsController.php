<?php

namespace App\Http\Controllers\Hotel\ChannelManager;

use App\Http\Controllers\Controller;
use App\Models\CmReservation;
use App\Services\ChannelManager\ChannelManagerCodeResolver;
use App\Services\CmReservationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LiveBookingsController extends Controller
{
    public function __construct(
        private ChannelManagerCodeResolver $codes,
        private CmReservationService $reservations,
    ) {}

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel()->with('settings')->firstOrFail();
        $hotelCode = $this->codes->hotelCode($hotel);
        $filters = $this->filtersFromRequest($request);

        $bookings = $this->filteredQuery($hotel->id, $hotelCode, $filters)
            ->paginate(20)
            ->withQueryString();

        $bookingDetails = $bookings->getCollection()
            ->mapWithKeys(fn (CmReservation $booking) => [(string) $booking->id => $booking->detailForView()])
            ->all();

        return view('hotel.channel-manager.live-bookings', [
            'hotel' => $hotel,
            'hotelCode' => $hotelCode,
            'bookings' => $bookings,
            'bookingDetails' => $bookingDetails,
            'webhookUrl' => config('app.url').'/webhooks/cm/reservations',
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $hotelCode = $this->codes->hotelCode($hotel);
        $filters = $this->filtersFromRequest($request);

        $rows = $this->filteredQuery($hotel->id, $hotelCode, $filters)->get();
        $filename = 'live-bookings-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Channel',
                'Booking ID',
                'Customer Name',
                'Payment',
                'Booked On',
                'Check-in',
                'Check-out',
                'Room',
                'Total Room Night',
                '# of Rooms',
                'Meal Plan',
                'Price',
                'Status',
            ]);

            foreach ($rows as $booking) {
                fputcsv($handle, [
                    $booking->channel,
                    $booking->booking_id,
                    $booking->guestName(),
                    $booking->paymentLabel(),
                    $booking->bookedOnLabel(),
                    $booking->checkinLabel(),
                    $booking->checkoutLabel(),
                    $booking->roomLabel(),
                    $booking->roomNightCount() ?? '',
                    $booking->roomCount(),
                    $booking->mealPlanLabel(),
                    $booking->priceLabel(),
                    $booking->statusLabel(),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function sync(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $hotelCode = $this->codes->hotelCode($hotel);

        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $start = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])->format('Y-m-d')
            : now()->subDays(30)->format('Y-m-d');

        $end = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])->format('Y-m-d')
            : now()->addDays(90)->format('Y-m-d');

        $result = $this->reservations->syncFromChannelManager($hotelCode, $hotel->id, $start, $end);

        return redirect()
            ->route('hotel.channel-manager.live-bookings')
            ->with($result['success'] ? 'success' : 'warning', $result['message']);
    }

    /** @return array<string, mixed> */
    private function filtersFromRequest(Request $request): array
    {
        $filterBy = $request->input('filter_by', 'booking_date');
        if (! in_array($filterBy, ['booking_date', 'checkin', 'checkout'], true)) {
            $filterBy = 'booking_date';
        }

        return [
            'filter_by' => $filterBy,
            'from_date' => $request->input('from_date', now()->subDays(7)->format('Y-m-d')),
            'to_date' => $request->input('to_date', now()->format('Y-m-d')),
            'search' => trim((string) $request->input('search', '')),
            'cancelled_only' => $request->boolean('cancelled_only'),
        ];
    }

    /** @param  array<string, mixed>  $filters */
    private function filteredQuery(int $hotelId, string $hotelCode, array $filters): Builder
    {
        $query = CmReservation::query()
            ->where(function ($q) use ($hotelId, $hotelCode) {
                $q->where('hotel_id', $hotelId)
                    ->orWhere('hotel_code', $hotelCode);
            });

        $dateColumn = match ($filters['filter_by']) {
            'checkin' => 'checkin',
            'checkout' => 'checkout',
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

        if ($filters['cancelled_only']) {
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
}
