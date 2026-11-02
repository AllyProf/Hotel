<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelCompany;
use App\Models\HotelLog;
use App\Models\HotelPmsService;
use App\Services\ChannelManager\ChannelManagerCodeResolver;
use App\Services\CmReservationService;
use App\Services\CreateReservationService;
use App\Services\GuestDataService;
use App\Services\HotelLogService;
use App\Services\ReservationDataService;
use App\Services\ReservationFolioService;
use App\Services\ReservationManageService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        private ReservationManageService $reservationManage,
        private ReservationFolioService $reservationFolio,
    ) {}

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $hotelCode = $this->codes->hotelCode($hotel);
        $filters = $this->reservationData->filtersFromRequest($request);

        $bookings = $this->reservationData->query($hotel->id, $hotelCode, $filters)
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('hotel.pms.reservations.index', [
            'hotel' => $hotel,
            'bookings' => $bookings,
            'filters' => $filters,
            'ui' => $this->reservationData->uiConfig(),
        ]);
    }

    public function show(CmReservation $reservation): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->assertReservationBelongsToHotel($reservation, $hotel);

        $pmsServices = $this->reservationFolio->activeServicesForHotel($hotel);

        return view('hotel.pms.reservations.show', [
            'hotel' => $hotel,
            'booking' => $reservation,
            'detail' => $reservation->detailForView(),
            'canManageStay' => $reservation->canManageStay(),
            'defaultInvoiceEmail' => $this->reservationManage->defaultInvoiceEmail($reservation),
            'serviceRows' => $this->reservationFolio->serviceRowsForReservation($hotel, $reservation),
            'canAddServices' => $this->reservationFolio->canAddServices($reservation),
            'pmsServices' => $pmsServices,
            'laundryCreateUrl' => route('hotel.laundry.create', ['cm_reservation_id' => $reservation->id]),
            'swimmingCreateUrl' => route('hotel.swimming.create', ['cm_reservation_id' => $reservation->id]),
        ]);
    }

    public function storeServiceCharge(Request $request, CmReservation $reservation): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->assertReservationBelongsToHotel($reservation, $hotel);

        $validated = $request->validate([
            'hotel_pms_service_id' => ['nullable', 'integer'],
            'service_name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        if (! empty($validated['hotel_pms_service_id'])) {
            $exists = HotelPmsService::query()
                ->where('hotel_id', $hotel->id)
                ->whereKey((int) $validated['hotel_pms_service_id'])
                ->exists();
            if (! $exists) {
                return redirect()->back()->with('error', 'Invalid service selected.');
            }
        }

        try {
            $this->reservationFolio->addManualCharge($hotel, $reservation, $validated, auth()->user());
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $this->logs->record($hotel, [
            'action_type' => 'Folio Service',
            'details' => 'Added '.$validated['service_name'].' ('.number_format((float) $validated['amount'], 2).') to booking '.$reservation->booking_id,
            'booking_id' => $reservation->booking_id,
            'guest_name' => $reservation->guestName(),
        ]);

        return redirect()
            ->route('hotel.reservations.show', $reservation)
            ->with('success', 'Service charge added to guest folio.');
    }

    public function previewStayChange(Request $request, CmReservation $reservation): JsonResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->assertReservationBelongsToHotel($reservation, $hotel);

        $checkin = trim((string) $request->query('checkin', ''));
        $checkout = trim((string) $request->query('checkout', ''));

        if ($checkin === '' || $checkout === '') {
            return response()->json($this->reservationManage->previewStayFailure([
                'Check-in and check-out are required.',
            ]));
        }

        if ($checkin >= $checkout) {
            return response()->json($this->reservationManage->previewStayFailure([
                'Check-out must be after check-in.',
            ]));
        }

        try {
            return response()->json(
                $this->reservationManage->previewStayChange(
                    $hotel,
                    $reservation,
                    $checkin,
                    $checkout
                )
            );
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json($this->reservationManage->previewStayFailure([
                'Could not check availability. Please try again.',
            ]));
        }
    }

    public function updateStay(Request $request, CmReservation $reservation): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->assertReservationBelongsToHotel($reservation, $hotel);

        $validated = $request->validate([
            'checkin' => ['required', 'date'],
            'checkout' => ['required', 'date', 'after:checkin'],
            'reason' => ['required', 'string', 'max:500'],
            'recalculate_total' => ['sometimes', 'boolean'],
            'resend_invoice' => ['sometimes', 'boolean'],
            'resend_invoice_email' => ['nullable', 'required_if:resend_invoice,1', 'email', 'max:160'],
        ]);

        $result = $this->reservationManage->updateStayDates(
            $hotel,
            $reservation,
            $validated['checkin'],
            $validated['checkout'],
            [
                'reason' => $validated['reason'],
                'recalculate_total' => ! empty($validated['recalculate_total']),
                'resend_invoice' => ! empty($validated['resend_invoice']),
                'resend_invoice_email' => $validated['resend_invoice_email'] ?? null,
            ]
        );

        $updated = $result['reservation'];
        $pricing = $result['pricing'];
        $details = 'Booking '.$updated->booking_id.' dates updated to '.$validated['checkin'].' → '.$validated['checkout']
            .'. Reason: '.$validated['reason'];

        if (! empty($validated['recalculate_total'])) {
            $details .= '. Total recalculated to '.number_format($pricing['new_total'], 2).' '.$pricing['currency'];
        }

        if ($result['invoice_sent']) {
            $details .= '. Updated invoice sent to '.$validated['resend_invoice_email'];
        }

        $this->logs->record($hotel, [
            'action_type' => 'Reservation Stay Updated',
            'details' => $details,
            'booking_id' => $updated->booking_id,
            'guest_name' => $updated->guestName(),
        ]);

        $success = 'Stay dates updated successfully.';
        if (! empty($validated['recalculate_total'])) {
            $success .= ' New total: '.number_format($pricing['new_total'], 2).' '.$pricing['currency'].'.';
        }
        if ($result['invoice_sent']) {
            $success .= ' Updated invoice sent to '.$validated['resend_invoice_email'].'.';
        }

        return redirect()
            ->route('hotel.reservations.show', $updated)
            ->with('success', $success);
    }

    public function sendInvoice(Request $request, CmReservation $reservation): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->assertReservationBelongsToHotel($reservation, $hotel);

        $validated = $request->validate([
            'invoice_email' => ['required', 'email', 'max:160'],
        ]);

        $this->reservationManage->sendInvoice($hotel, $reservation, $validated['invoice_email']);

        $this->logs->record($hotel, [
            'action_type' => 'Invoice Sent',
            'details' => 'Invoice for booking '.$reservation->booking_id.' sent to '.$validated['invoice_email'],
            'booking_id' => $reservation->booking_id,
            'guest_name' => $reservation->guestName(),
        ]);

        return redirect()
            ->route('hotel.reservations.show', $reservation)
            ->with('success', 'Invoice sent to '.$validated['invoice_email'].'.');
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

    public function createComplimentary(): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $options = $this->createReservation->formOptions($hotel);

        return view('hotel.pms.reservations.complimentary', [
            'hotel' => $hotel,
            'options' => $options,
            'reasons' => config('hotel_pms.complimentary.reasons', []),
        ]);
    }

    public function createCompany(): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $options = $this->createReservation->formOptions($hotel);

        return view('hotel.pms.reservations.company-booking', [
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
            'payment_provider' => ['nullable', 'string', 'max:120'],
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
            'payment_provider' => ['nullable', 'string', 'max:120'],
            'bill_to' => [
                Rule::requiredIf(fn () => stripos((string) $request->input('payment_mode', ''), 'bill to company') !== false),
                'nullable',
                'string',
                'max:160',
            ],
            'bill_to_company_id' => [
                Rule::requiredIf(fn () => stripos((string) $request->input('payment_mode', ''), 'bill to company') !== false),
                'nullable',
                Rule::exists('hotel_companies', 'id')->where('hotel_id', $hotel->id),
            ],
            'booked_by' => ['nullable', 'string', 'max:120'],
            'segment' => ['nullable', 'string', 'max:80'],
            'guest_name' => ['required', 'string', 'max:160'],
            'guest_email' => ['nullable', 'email', 'max:160'],
            'guest_phone' => ['nullable', 'string', 'max:40'],
            'guest_phone_country_code' => ['nullable', 'string', 'max:8'],
            'guest_country_code' => ['nullable', 'string', 'max:3'],
            'guest_country' => ['nullable', 'string', 'max:80'],
            'guest_city' => ['nullable', 'string', 'max:80'],
            'special_request' => ['nullable', 'string', 'max:500'],
            'discount_type' => ['nullable', 'in:percent,amount'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'advance_amount' => ['nullable', 'numeric', 'min:0'],
            'advance_comments' => ['nullable', 'string', 'max:500'],
            'send_invoice_to_company' => ['nullable', 'boolean'],
        ]);

        $validated['tax_inclusive'] = $request->boolean('tax_inclusive');
        $validated['send_invoice_to_company'] = $request->boolean('send_invoice_to_company');
        $validated['discount_type'] = $validated['discount_type'] ?? 'percent';

        if ($validated['discount_type'] === 'percent' && isset($validated['discount_value'])) {
            $validated['discount_value'] = min(100, (float) $validated['discount_value']);
        }

        $validated['guest_phone'] = trim(
            ($validated['guest_phone_country_code'] ?? '').ltrim((string) ($validated['guest_phone'] ?? ''), '0')
        ) ?: null;

        if ($validated['send_invoice_to_company']) {
            $request->validate([
                'bill_to_company_id' => [
                    'required',
                    Rule::exists('hotel_companies', 'id')->where('hotel_id', $hotel->id),
                ],
            ]);
        }

        $booking = $this->createReservation->storeGroup($hotel, $validated);

        $logDetails = 'Group "'.$validated['group_name'].'" · Booking '.$booking->booking_id;
        if ($validated['send_invoice_to_company']) {
            $logDetails .= ' · Invoice queued for company email';
        }

        $this->logs->record($hotel, [
            'action_type' => 'Group Booking Created',
            'details' => $logDetails,
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
            'bookings.*.tax_inclusive' => ['nullable', 'boolean'],
            'bookings.*.guest_name' => ['required', 'string', 'max:160'],
            'bookings.*.guest_email' => ['nullable', 'email', 'max:160'],
            'bookings.*.guest_phone' => ['nullable', 'string', 'max:40'],
            'bookings.*.guest_phone_country_code' => ['nullable', 'string', 'max:8'],
            'bookings.*.guest_country_code' => ['nullable', 'string', 'max:3'],
            'bookings.*.guest_country' => ['nullable', 'string', 'max:80'],
            'bookings.*.guest_city' => ['nullable', 'string', 'max:80'],
            'bookings.*.payment_mode' => ['nullable', 'string', 'max:80'],
            'bookings.*.payment_provider' => ['nullable', 'string', 'max:120'],
            'bookings.*.discount_type' => ['nullable', 'in:percent,amount'],
            'bookings.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'bookings.*.advance_amount' => ['nullable', 'numeric', 'min:0'],
            'bookings.*.advance_comments' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($validated['bookings'] as $index => $booking) {
            $validated['bookings'][$index]['tax_inclusive'] = $request->boolean("bookings.{$index}.tax_inclusive");
            $validated['bookings'][$index]['guest_phone'] = trim(
                ($booking['guest_phone_country_code'] ?? '').ltrim((string) ($booking['guest_phone'] ?? ''), '0')
            ) ?: null;
            $validated['bookings'][$index]['discount_type'] = $booking['discount_type'] ?? 'percent';
            if ($validated['bookings'][$index]['discount_type'] === 'percent' && isset($booking['discount_value'])) {
                $validated['bookings'][$index]['discount_value'] = min(100, (float) $booking['discount_value']);
            }

            if (Carbon::parse($booking['checkout'])->lte(Carbon::parse($booking['checkin']))) {
                throw ValidationException::withMessages([
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

    public function storeComplimentary(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $reasonKeys = array_keys(config('hotel_pms.complimentary.reasons', []));

        $validated = $request->validate([
            'checkin' => ['required', 'date'],
            'checkout' => ['required', 'date', 'after:checkin'],
            'hotel_room_id' => ['required', Rule::exists('hotel_rooms', 'id')->where('hotel_id', $hotel->id)],
            'hotel_rate_plan_id' => ['required', Rule::exists('hotel_rate_plans', 'id')->where('hotel_id', $hotel->id)],
            'room_count' => ['required', 'integer', 'min:1', 'max:50'],
            'guest_count' => ['required', 'integer', 'min:1', 'max:20'],
            'complimentary_reason' => ['required', 'string', Rule::in($reasonKeys)],
            'guest_name' => ['required', 'string', 'max:160'],
            'guest_email' => ['nullable', 'email', 'max:160'],
            'guest_phone' => ['nullable', 'string', 'max:40'],
            'guest_phone_country_code' => ['nullable', 'string', 'max:8'],
            'guest_country_code' => ['nullable', 'string', 'max:3'],
            'guest_country' => ['nullable', 'string', 'max:80'],
            'guest_city' => ['nullable', 'string', 'max:80'],
            'special_request' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['guest_phone'] = trim(
            ($validated['guest_phone_country_code'] ?? '').ltrim((string) ($validated['guest_phone'] ?? ''), '0')
        ) ?: null;

        $booking = $this->createReservation->storeComplimentary($hotel, $validated);
        $reasonLabel = config('hotel_pms.complimentary.reasons.'.$validated['complimentary_reason'], $validated['complimentary_reason']);

        $this->logs->record($hotel, [
            'action_type' => 'Complimentary Booking Created',
            'category' => HotelLog::CATEGORY_COMPLIMENTARY,
            'booking_id' => $booking->booking_id,
            'guest_name' => $validated['guest_name'],
            'hotel_room_id' => (int) $validated['hotel_room_id'],
            'details' => $reasonLabel.($validated['special_request'] ? ' · '.$validated['special_request'] : ''),
        ]);

        return redirect()
            ->route('hotel.reservations.index')
            ->with('success', 'Complimentary booking '.$booking->booking_id.' created successfully.');
    }

    public function checkComplimentaryAvailability(Request $request): JsonResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'checkin' => ['required', 'date'],
            'checkout' => ['required', 'date', 'after:checkin'],
            'hotel_room_id' => ['required', Rule::exists('hotel_rooms', 'id')->where('hotel_id', $hotel->id)],
            'room_count' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $result = $this->createReservation->lineAvailability(
            $hotel,
            (int) $validated['hotel_room_id'],
            $validated['checkin'],
            $validated['checkout'],
            (int) $validated['room_count'],
        );

        return response()->json($result);
    }

    public function storeCompany(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'bill_to' => ['required', 'string', 'max:160'],
            'bill_to_company_id' => ['nullable', Rule::exists('hotel_companies', 'id')->where('hotel_id', $hotel->id)],
            'company_contact_person' => ['nullable', 'string', 'max:120'],
            'company_contact_email' => ['nullable', 'email', 'max:160'],
            'company_contact_phone' => ['nullable', 'string', 'max:40'],
            'company_contact_phone_country_code' => ['nullable', 'string', 'max:8'],
            'checkin' => ['required', 'date'],
            'checkout' => ['required', 'date', 'after:checkin'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.hotel_room_id' => ['required', Rule::exists('hotel_rooms', 'id')->where('hotel_id', $hotel->id)],
            'lines.*.hotel_rate_plan_id' => ['required', Rule::exists('hotel_rate_plans', 'id')->where('hotel_id', $hotel->id)],
            'lines.*.room_count' => ['required', 'integer', 'min:1', 'max:50'],
            'lines.*.guest_count' => ['required', 'integer', 'min:1', 'max:20'],
            'tax_inclusive' => ['nullable', 'boolean'],
            'guest_name' => ['required', 'string', 'max:160'],
            'guest_email' => ['nullable', 'email', 'max:160'],
            'guest_phone' => ['nullable', 'string', 'max:40'],
            'guest_phone_country_code' => ['nullable', 'string', 'max:8'],
            'guest_country_code' => ['nullable', 'string', 'max:3'],
            'guest_country' => ['nullable', 'string', 'max:80'],
            'guest_city' => ['nullable', 'string', 'max:80'],
            'special_request' => ['nullable', 'string', 'max:500'],
            'discount_type' => ['nullable', 'in:percent,amount'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'advance_amount' => ['nullable', 'numeric', 'min:0'],
            'advance_comments' => ['nullable', 'string', 'max:500'],
            'send_invoice_to_company' => ['nullable', 'boolean'],
        ]);

        $validated['tax_inclusive'] = $request->boolean('tax_inclusive');
        $validated['send_invoice_to_company'] = $request->boolean('send_invoice_to_company');
        $validated['discount_type'] = $validated['discount_type'] ?? 'percent';
        if ($validated['discount_type'] === 'percent' && isset($validated['discount_value'])) {
            $validated['discount_value'] = min(100, (float) $validated['discount_value']);
        }

        $validated['guest_phone'] = trim(
            ($validated['guest_phone_country_code'] ?? '').ltrim((string) ($validated['guest_phone'] ?? ''), '0')
        ) ?: null;
        $validated['company_contact_phone'] = trim(
            ($validated['company_contact_phone_country_code'] ?? '').ltrim((string) ($validated['company_contact_phone'] ?? ''), '0')
        ) ?: null;
        $validated['bill_to'] = trim($validated['bill_to']);
        $validated['segment'] = 'Corporate';

        $company = null;

        if (! empty($validated['bill_to_company_id'])) {
            $company = HotelCompany::query()
                ->where('hotel_id', $hotel->id)
                ->find($validated['bill_to_company_id']);
        }

        $invoiceEmail = trim((string) ($validated['company_contact_email'] ?? ''));

        if ($invoiceEmail === '' && $company !== null) {
            $invoiceEmail = trim((string) ($company->email ?? ''));
        }

        if ($validated['send_invoice_to_company'] && $invoiceEmail === '') {
            throw ValidationException::withMessages([
                'send_invoice_to_company' => 'Please enter a company contact email to send the invoice.',
            ]);
        }

        $booking = $this->createReservation->storeCompany($hotel, $validated);

        $logDetails = 'Company "'.$validated['bill_to'].'" · Booking '.$booking->booking_id;
        if ($validated['send_invoice_to_company']) {
            $logDetails .= ' · Invoice queued for company email';
        }

        $this->logs->record($hotel, [
            'action_type' => 'Company Booking Created',
            'booking_id' => $booking->booking_id,
            'guest_name' => $validated['guest_name'],
            'details' => $logDetails,
        ]);

        return redirect()
            ->route('hotel.reservations.index')
            ->with('success', 'Company booking '.$booking->booking_id.' created successfully.');
    }

    public function checkCompanyAvailability(Request $request): JsonResponse
    {
        return $this->checkGroupAvailability($request);
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
        $currentRequested = (int) $validated['room_count'];
        $othersSelected = 0;

        foreach ($validated['other_room_counts'] ?? [] as $other) {
            if ((int) ($other['hotel_room_id'] ?? 0) === $roomId) {
                $othersSelected += max(0, (int) ($other['room_count'] ?? 0));
            }
        }

        $totalRequested = $currentRequested + $othersSelected;

        $result = $this->createReservation->lineAvailability(
            $hotel,
            $roomId,
            $validated['checkin'],
            $validated['checkout'],
            max(1, $totalRequested)
        );

        $poolAvailable = (int) ($result['available'] ?? 0);
        $remainingForRow = max(0, $poolAvailable - $othersSelected);
        $roomName = (string) ($result['room_name'] ?? 'Room');
        $ok = $currentRequested <= $remainingForRow && $totalRequested <= $poolAvailable;

        if ($ok) {
            $message = $othersSelected > 0
                ? $remainingForRow.' room'.($remainingForRow === 1 ? '' : 's').' available for this row ('.$othersSelected.' already selected in other rows).'
                : $poolAvailable.' room'.($poolAvailable === 1 ? '' : 's').' available for '.$roomName.'.';
        } else {
            $message = $othersSelected > 0
                ? 'Only '.$remainingForRow.' room'.($remainingForRow === 1 ? '' : 's').' available for this row ('.$othersSelected.' already selected in other rows). You requested '.$currentRequested.'.'
                : 'Only '.$poolAvailable.' room'.($poolAvailable === 1 ? '' : 's').' available for '.$roomName.'. You requested '.$currentRequested.'.';
        }

        return response()->json([
            'available' => $poolAvailable,
            'remaining' => $remainingForRow,
            'others_selected' => $othersSelected,
            'requested' => $currentRequested,
            'total_requested' => $totalRequested,
            'ok' => $ok,
            'message' => $message,
            'room_name' => $roomName,
        ]);
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

    private function assertReservationBelongsToHotel(CmReservation $reservation, Hotel $hotel): void
    {
        $hotelCode = $this->codes->hotelCode($hotel);

        abort_if(
            (int) $reservation->hotel_id !== (int) $hotel->id
            && (string) $reservation->hotel_code !== $hotelCode,
            404
        );
    }
}
