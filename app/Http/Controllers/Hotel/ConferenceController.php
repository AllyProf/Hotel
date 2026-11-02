<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelConferenceBooking;
use App\Services\Conference\ConferenceBookingService;
use App\Services\Conference\ConferenceRateDataService;
use App\Services\FrontDeskPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class ConferenceController extends Controller
{
    public function __construct(
        private ConferenceBookingService $bookings,
        private ConferenceRateDataService $rates,
        private FrontDeskPaymentService $deskPayments,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $service = $this->bookings->conferenceServiceForHotel($hotel);

        if (! $service) {
            return redirect()->route('hotel.services.index')->with('error', 'Add the Conference service first.');
        }

        $tab = $request->input('tab', 'upcoming');
        $items = $this->bookings->bookingsQuery($hotel, $tab)->limit(100)->get();

        return view('hotel.pms.conference.index', [
            'hotel' => $hotel,
            'service' => $service,
            'tab' => $tab,
            'bookings' => $items,
            'ui' => $this->bookings->uiConfig(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $service = $this->bookings->conferenceServiceForHotel($hotel);

        if (! $service) {
            return redirect()->route('hotel.services.index')->with('error', 'Add the Conference service first.');
        }

        $service->load('serviceCategories');
        $this->rates->ensureDefaultRateRows($service);
        $rateRows = $this->rates->ratesForService($service)->where('is_active', true)->values();

        return view('hotel.pms.conference.create', [
            'hotel' => $hotel,
            'service' => $service,
            'reservations' => $this->bookings->inHouseReservations($hotel),
            'rates' => $rateRows,
            'settings' => $service->conferenceSettings(),
            'defaultCurrency' => $service->displayCurrency($hotel),
            'defaultPhoneCode' => $hotel->phone_country_code ?: '+255',
            'prefillReservationId' => (int) $request->input('cm_reservation_id', 0),
            'statuses' => config('hotel_pms.conference.statuses', []),
            'paymentOptions' => $this->deskPaymentViewOptions($hotel, $service),
            'ui' => $this->bookings->uiConfig(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $service = $this->bookings->conferenceServiceForHotel($hotel);

        if (! $service) {
            return redirect()->route('hotel.services.index')->with('error', 'Conference service not found.');
        }

        $validated = $request->validate([
            'cm_reservation_id' => ['nullable', 'integer'],
            'event_name' => ['required', 'string', 'max:200'],
            'organizer_name' => ['nullable', 'string', 'max:120'],
            'company_name' => ['nullable', 'string', 'max:120'],
            'contact_phone_country_code' => ['nullable', 'string', 'max:8'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'guest_name' => ['nullable', 'string', 'max:120'],
            'room_no' => ['nullable', 'string', 'max:20'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'delegates' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'billing_quantity' => ['nullable', 'numeric', 'min:0'],
            'hotel_pms_conference_rate_id' => ['required', 'integer'],
            'discount_type' => ['nullable', 'string', 'in:percent,amount'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:24'],
            'notes' => ['nullable', 'string', 'max:500'],
            'payment_mode' => ['nullable', 'string', 'max:80'],
            'payment_provider' => ['nullable', 'string', 'max:120'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $booking = $this->bookings->createBooking($hotel, $service, $validated, auth()->user());
        } catch (InvalidArgumentException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('hotel.conference.show', $booking)
            ->with('success', 'Booking '.$booking->booking_no.' saved.');
    }

    public function show(HotelConferenceBooking $booking): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $booking->hotel_id === (int) $hotel->id, 404);

        $booking->load(['category', 'conferenceRate', 'reservation.roomUnit', 'service', 'creator']);

        return view('hotel.pms.conference.show', [
            'hotel' => $hotel,
            'booking' => $booking,
        ]);
    }

    public function print(HotelConferenceBooking $booking): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $booking->hotel_id === (int) $hotel->id, 404);

        $booking->load(['category', 'conferenceRate', 'service', 'creator']);

        return view('hotel.pms.conference.print', [
            'hotel' => $hotel,
            'booking' => $booking,
        ]);
    }

    public function postFolio(HotelConferenceBooking $booking): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $booking->hotel_id === (int) $hotel->id, 404);

        $booking->load('reservation');

        try {
            $this->bookings->postToFolio($booking, $booking->reservation);
        } catch (InvalidArgumentException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->back()->with('success', 'Charge posted to guest folio.');
    }

    /** @return array<string, mixed> */
    private function deskPaymentViewOptions($hotel, $service): array
    {
        $walkInModes = $this->deskPayments->walkInPaymentModes($hotel);

        return array_merge($this->deskPayments->optionsForHotel($hotel), [
            'currency' => $service->displayCurrency($hotel),
            'walk_in_payment_modes' => $walkInModes,
            'default_payment_mode' => $walkInModes[0] ?? 'Cash',
        ]);
    }
}
