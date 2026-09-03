<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\CmReservation;
use App\Services\ChannelManager\ChannelManagerCodeResolver;
use App\Services\CmReservationService;
use App\Services\CreateReservationService;
use App\Services\GuestDataService;
use App\Services\HotelLogService;
use App\Services\ReservationDataService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReservationController extends Controller
{
    public function __construct(
        private ReservationDataService $reservationData,
        private CreateReservationService $createReservation,
        private GuestDataService $guestData,
        private ChannelManagerCodeResolver $codes,
        private CmReservationService $reservations,
        private HotelLogService $logs,
    ) {}

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $hotelCode = $this->codes->hotelCode($hotel);
        $filters = $this->reservationData->filtersFromRequest($request);

        $bookings = $this->reservationData->query($hotel->id, $hotelCode, $filters)
            ->paginate($filters['per_page'])
            ->withQueryString();

        $bookingDetails = $bookings->getCollection()
            ->mapWithKeys(fn (CmReservation $booking) => [(string) $booking->id => $booking->detailForView()])
            ->all();

        return view('hotel.pms.reservations.index', [
            'hotel' => $hotel,
            'bookings' => $bookings,
            'bookingDetails' => $bookingDetails,
            'filters' => $filters,
            'ui' => $this->reservationData->uiConfig(),
        ]);
    }

    public function sync(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $hotelCode = $this->codes->hotelCode($hotel);
        $filters = $this->reservationData->filtersFromRequest($request);

        $start = Carbon::parse($filters['from_date'])->format('Y-m-d');
        $end = Carbon::parse($filters['to_date'])->format('Y-m-d');

        $result = $this->reservations->syncFromChannelManager($hotelCode, $hotel->id, $start, $end);

        $this->logs->record($hotel, [
            'action_type' => 'Reservation Data Sync',
            'details' => "Synced reservations from {$start} to {$end}. ".($result['message'] ?? ''),
        ]);

        return redirect()
            ->route('hotel.reservations.index', $request->only([
                'filter_by', 'from_date', 'to_date', 'search', 'per_page',
            ]))
            ->with($result['success'] ? 'success' : 'warning', $result['message']);
    }

    public function create(): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $options = $this->createReservation->formOptions($hotel);

        return view('hotel.pms.reservations.create', [
            'hotel' => $hotel,
            'options' => $options,
        ]);
    }

    public function createGroup(): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $options = $this->createReservation->formOptions($hotel);

        return view('hotel.pms.reservations.group-booking', [
            'hotel' => $hotel,
            'options' => $options,
        ]);
    }

    public function createMulti(): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $options = $this->createReservation->formOptions($hotel);

        return view('hotel.pms.reservations.multi-booking', [
            'hotel' => $hotel,
            'options' => $options,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'checkin' => ['required', 'date'],
            'checkout' => ['required', 'date', 'after:checkin'],
            'hotel_room_id' => ['required', Rule::exists('hotel_rooms', 'id')->where('hotel_id', $hotel->id)],
            'hotel_rate_plan_id' => ['required', Rule::exists('hotel_rate_plans', 'id')->where('hotel_id', $hotel->id)],
            'guest_count' => ['required', 'integer', 'min:1', 'max:20'],
            'room_count' => ['required', 'integer', 'min:1', 'max:50'],
            'booked_by' => ['nullable', 'string', 'max:120'],
            'segment' => ['nullable', 'string', 'max:80'],
            'bill_to' => ['nullable', 'string', 'max:160'],
            'bill_to_company_id' => ['nullable', Rule::exists('hotel_companies', 'id')->where('hotel_id', $hotel->id)],
            'payment_mode' => ['nullable', 'string', 'max:80'],
            'daily_rate' => ['required', 'numeric', 'min:0'],
            'daily_tax' => ['nullable', 'numeric', 'min:0'],
            'tax_inclusive' => ['nullable', 'boolean'],
            'room_unit_id' => ['nullable', 'integer', 'exists:hotel_room_units,id'],
            'guest_type' => ['nullable', 'in:local,international'],
            'guest_name' => ['required', 'string', 'max:160'],
            'guest_email' => ['nullable', 'email', 'max:160'],
            'guest_phone' => ['nullable', 'string', 'max:40'],
            'guest_gender' => ['nullable', 'string', 'max:20'],
            'guest_vip' => ['nullable', 'boolean'],
            'guest_country_code' => ['nullable', 'string', 'max:3'],
            'guest_country' => ['nullable', 'string', 'max:80'],
            'guest_city' => ['nullable', 'string', 'max:80'],
            'guest_zip' => ['nullable', 'string', 'max:20'],
            'guest_address' => ['nullable', 'string', 'max:255'],
            'special_request' => ['nullable', 'string', 'max:500'],
            'identity_type' => ['nullable', 'string', 'max:80'],
            'identity_detail' => ['nullable', 'string', 'max:160'],
            'send_payment_link' => ['nullable', 'boolean'],
            'photo_id' => ['nullable', 'file', 'max:4096', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $validated['tax_inclusive'] = $request->boolean('tax_inclusive');
        $validated['guest_vip'] = $request->boolean('guest_vip');
        $validated['send_payment_link'] = $request->boolean('send_payment_link');

        $booking = $this->createReservation->store($hotel, $validated);

        if ($request->hasFile('photo_id')) {
            $path = $request->file('photo_id')->store('guest-photos/'.$hotel->id, 'public');
            $this->guestData->attachPhoto(
                $hotel,
                (string) $validated['guest_name'],
                $validated['guest_email'] ?? null,
                $validated['guest_phone'] ?? null,
                $path,
            );
        }

        return redirect()
            ->route('hotel.reservations.index')
            ->with('success', 'Reservation '.$booking->booking_id.' created successfully.');
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'group_name' => ['required', 'string', 'max:160'],
            'checkin' => ['required', 'date'],
            'checkout' => ['required', 'date', 'after:checkin'],
            'tax_inclusive' => ['nullable', 'boolean'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.hotel_room_id' => ['required', Rule::exists('hotel_rooms', 'id')->where('hotel_id', $hotel->id)],
            'lines.*.hotel_rate_plan_id' => ['required', Rule::exists('hotel_rate_plans', 'id')->where('hotel_id', $hotel->id)],
            'lines.*.room_count' => ['required', 'integer', 'min:1', 'max:50'],
            'lines.*.guest_count' => ['required', 'integer', 'min:1', 'max:20'],
            'lines.*.daily_rate' => ['required', 'numeric', 'min:0'],
            'payment_mode' => ['nullable', 'string', 'max:80'],
            'bill_to' => ['nullable', 'string', 'max:160'],
            'bill_to_company_id' => ['nullable', Rule::exists('hotel_companies', 'id')->where('hotel_id', $hotel->id)],
            'booked_by' => ['nullable', 'string', 'max:120'],
            'segment' => ['nullable', 'string', 'max:80'],
            'guest_name' => ['required', 'string', 'max:160'],
            'guest_email' => ['nullable', 'email', 'max:160'],
            'guest_phone' => ['nullable', 'string', 'max:40'],
            'guest_country_code' => ['nullable', 'string', 'max:3'],
            'guest_country' => ['nullable', 'string', 'max:80'],
            'guest_city' => ['nullable', 'string', 'max:80'],
            'special_request' => ['nullable', 'string', 'max:500'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'advance_amount' => ['nullable', 'numeric', 'min:0'],
            'advance_payment_mode' => ['nullable', 'string', 'max:80'],
            'advance_comments' => ['nullable', 'string', 'max:500'],
            'advance_attachment' => ['nullable', 'file', 'max:4096', 'mimes:jpg,jpeg,png,pdf,doc,docx'],
        ]);

        $validated['tax_inclusive'] = $request->boolean('tax_inclusive');

        if ($request->hasFile('advance_attachment')) {
            $validated['advance_attachment_path'] = $request->file('advance_attachment')
                ->store('group-advances/'.$hotel->id, 'public');
        }

        $booking = $this->createReservation->storeGroup($hotel, $validated);

        $this->logs->record($hotel, [
            'action_type' => 'Group Booking Created',
            'details' => 'Group "'.$validated['group_name'].'" · Booking '.$booking->booking_id,
            'booking_id' => $booking->booking_id,
            'guest_name' => $validated['guest_name'],
        ]);

        return redirect()
            ->route('hotel.reservations.index')
            ->with('success', 'Group booking '.$booking->booking_id.' created successfully.');
    }

    public function storeMulti(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'bookings' => ['required', 'array', 'min:1'],
            'bookings.*.checkin' => ['required', 'date'],
            'bookings.*.checkout' => ['required', 'date'],
            'bookings.*.hotel_room_id' => ['required', Rule::exists('hotel_rooms', 'id')->where('hotel_id', $hotel->id)],
            'bookings.*.hotel_rate_plan_id' => ['required', Rule::exists('hotel_rate_plans', 'id')->where('hotel_id', $hotel->id)],
            'bookings.*.room_count' => ['required', 'integer', 'min:1', 'max:50'],
            'bookings.*.guest_count' => ['required', 'integer', 'min:1', 'max:20'],
            'bookings.*.daily_rate' => ['required', 'numeric', 'min:0'],
            'bookings.*.tax_inclusive' => ['nullable', 'boolean'],
            'bookings.*.guest_name' => ['required', 'string', 'max:160'],
            'bookings.*.guest_email' => ['nullable', 'email', 'max:160'],
            'bookings.*.guest_phone' => ['nullable', 'string', 'max:40'],
            'bookings.*.guest_country_code' => ['nullable', 'string', 'max:3'],
            'bookings.*.guest_country' => ['nullable', 'string', 'max:80'],
            'bookings.*.guest_city' => ['nullable', 'string', 'max:80'],
            'bookings.*.payment_mode' => ['nullable', 'string', 'max:80'],
        ]);

        foreach ($validated['bookings'] as $index => $booking) {
            $validated['bookings'][$index]['tax_inclusive'] = $request->boolean("bookings.{$index}.tax_inclusive");

            if (\Carbon\Carbon::parse($booking['checkout'])->lte(\Carbon\Carbon::parse($booking['checkin']))) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "bookings.{$index}.checkout" => 'Check-out must be after check-in.',
                ]);
            }
        }

        $created = $this->createReservation->storeMulti($hotel, $validated);

        $ids = collect($created)->pluck('booking_id')->implode(', ');

        $this->logs->record($hotel, [
            'action_type' => 'Multi Booking Created',
            'details' => count($created).' reservation(s) created: '.$ids,
        ]);

        return redirect()
            ->route('hotel.reservations.index')
            ->with('success', count($created).' reservation(s) created: '.$ids);
    }

    public function checkMultiAvailability(Request $request): JsonResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'checkin' => ['required', 'date'],
            'checkout' => ['required', 'date', 'after:checkin'],
            'hotel_room_id' => ['required', Rule::exists('hotel_rooms', 'id')->where('hotel_id', $hotel->id)],
            'room_count' => ['required', 'integer', 'min:1', 'max:50'],
            'other_bookings' => ['nullable', 'array'],
            'other_bookings.*.checkin' => ['required', 'date'],
            'other_bookings.*.checkout' => ['required', 'date'],
            'other_bookings.*.hotel_room_id' => ['required', 'integer'],
            'other_bookings.*.room_count' => ['required', 'integer', 'min:1'],
        ]);

        $result = $this->createReservation->multiLineAvailability(
            $hotel,
            $validated['checkin'],
            $validated['checkout'],
            (int) $validated['hotel_room_id'],
            (int) $validated['room_count'],
            $validated['other_bookings'] ?? []
        );

        return response()->json($result);
    }

    public function checkGroupAvailability(Request $request): JsonResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'checkin' => ['required', 'date'],
            'checkout' => ['required', 'date', 'after:checkin'],
            'hotel_room_id' => ['required', Rule::exists('hotel_rooms', 'id')->where('hotel_id', $hotel->id)],
            'room_count' => ['required', 'integer', 'min:1', 'max:50'],
            'other_room_counts' => ['nullable', 'array'],
            'other_room_counts.*.hotel_room_id' => ['required', 'integer'],
            'other_room_counts.*.room_count' => ['required', 'integer', 'min:0'],
        ]);

        $roomId = (int) $validated['hotel_room_id'];
        $requested = (int) $validated['room_count'];

        foreach ($validated['other_room_counts'] ?? [] as $other) {
            if ((int) ($other['hotel_room_id'] ?? 0) === $roomId) {
                $requested += max(0, (int) ($other['room_count'] ?? 0));
            }
        }

        $result = $this->createReservation->lineAvailability(
            $hotel,
            $roomId,
            $validated['checkin'],
            $validated['checkout'],
            $requested
        );

        return response()->json($result);
    }

    public function searchGuests(Request $request): JsonResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $guests = $this->guestData->searchGuests($hotel, (string) ($validated['q'] ?? ''));

        return response()->json(['guests' => $guests]);
    }

    public function export(Request $request): StreamedResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $hotelCode = $this->codes->hotelCode($hotel);
        $filters = $this->reservationData->filtersFromRequest($request);
        $columns = $this->reservationData->uiConfig()['columns'] ?? [];

        $rows = $this->reservationData->query($hotel->id, $hotelCode, $filters)->get();
        $filename = 'reservation-data-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows, $columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, array_column($columns, 'label'));

            foreach ($rows as $booking) {
                fputcsv($handle, [
                    $booking->booking_id,
                    $booking->guestName(),
                    $booking->bookedOnLabel(),
                    $booking->checkinLabel(),
                    $booking->checkoutLabel(),
                    $booking->sourceLabel(),
                    $booking->guestCount(),
                    $booking->roomCount(),
                    $booking->roomNightCount() ?? '',
                    $booking->priceLabel(),
                    $booking->paymentLabel(),
                    $booking->paymentLinkLabel(),
                    $booking->categoryLabel(),
                    $booking->mealPlanLabel(),
                    $booking->statusLabel(),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
