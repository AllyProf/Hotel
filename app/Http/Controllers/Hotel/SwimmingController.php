<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelSwimmingVisit;
use App\Services\FrontDeskPaymentService;
use App\Services\Swimming\SwimmingRateDataService;
use App\Services\Swimming\SwimmingVisitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class SwimmingController extends Controller
{
    public function __construct(
        private SwimmingVisitService $visits,
        private SwimmingRateDataService $rates,
        private FrontDeskPaymentService $deskPayments,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $service = $this->visits->swimmingServiceForHotel($hotel);

        if (! $service) {
            return redirect()
                ->route('hotel.services.index')
                ->with('error', 'Add the Swimming service under PMS → Services first.');
        }

        $tab = $request->input('tab', 'today');
        if (! in_array($tab, ['today', 'scheduled', 'in_pool', 'completed', 'all'], true)) {
            $tab = 'today';
        }

        $visits = $this->visits->visitsQuery($hotel, $tab === 'all' ? null : $tab)->limit(100)->get();

        return view('hotel.pms.swimming.index', [
            'hotel' => $hotel,
            'service' => $service,
            'tab' => $tab,
            'visits' => $visits,
            'ui' => $this->visits->uiConfig(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $service = $this->visits->swimmingServiceForHotel($hotel);
        $prefillReservationId = (int) $request->input('cm_reservation_id', 0);

        if (! $service) {
            return redirect()->route('hotel.services.index')->with('error', 'Add the Swimming service first.');
        }

        $service->load('serviceCategories');
        $this->rates->ensureDefaultRateRows($service);
        $rateRows = $this->rates->ratesForService($service)->where('is_active', true)->values();
        return view('hotel.pms.swimming.create', [
            'hotel' => $hotel,
            'service' => $service,
            'defaultPhoneCode' => $hotel->phone_country_code ?: '+255',
            'reservations' => $this->visits->inHouseReservations($hotel),
            'categories' => $service->serviceCategories->where('is_active', true)->values(),
            'rates' => $rateRows,
            'settings' => $service->swimmingSettings(),
            'defaultCurrency' => $service->displayCurrency($hotel),
            'prefillReservationId' => $prefillReservationId,
            'childRateMap' => $this->visits->childRateMap($service),
            'ui' => $this->visits->uiConfig(),
            'paymentOptions' => $this->deskPaymentViewOptions($hotel, $service),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $service = $this->visits->swimmingServiceForHotel($hotel);

        if (! $service) {
            return redirect()->route('hotel.services.index')->with('error', 'Add the Swimming service first.');
        }

        $validated = $request->validate([
            'cm_reservation_id' => ['nullable', 'integer'],
            'guest_name' => ['nullable', 'string', 'max:120'],
            'guest_phone_country_code' => ['nullable', 'string', 'max:8'],
            'guest_phone' => ['nullable', 'string', 'max:40'],
            'guest_email' => ['nullable', 'email', 'max:160'],
            'scheduled_date' => ['required', 'date', 'after_or_equal:today'],
            'room_no' => ['nullable', 'string', 'max:20'],
            'hotel_pms_swimming_rate_id' => ['required', 'integer'],
            'adults' => ['nullable', 'integer', 'min:0', 'max:99'],
            'children' => ['nullable', 'integer', 'min:0', 'max:99'],
            'billing_quantity' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'string', 'in:percent,amount'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'apply_complimentary' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
            'payment_mode' => ['nullable', 'string', 'max:80'],
            'payment_provider' => ['nullable', 'string', 'max:120'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['apply_complimentary'] = $request->boolean('apply_complimentary');

        try {
            $visit = $this->visits->createVisit($hotel, $service, $validated, auth()->user());
        } catch (InvalidArgumentException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        $message = $visit->status === HotelSwimmingVisit::STATUS_SCHEDULED
            ? 'Visit '.$visit->visit_no.' scheduled for '.$visit->scheduled_date?->format('d/m/Y').'.'
            : 'Pool entry '.$visit->visit_no.' registered.';

        return redirect()
            ->route('hotel.swimming.show', $visit)
            ->with('success', $message);
    }

    public function checkIn(HotelSwimmingVisit $visit): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $visit->hotel_id === (int) $hotel->id, 404);

        try {
            $this->visits->checkInScheduled($visit, auth()->user());
        } catch (InvalidArgumentException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->back()->with('success', 'Guest checked in to the pool.');
    }

    public function show(HotelSwimmingVisit $visit): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $visit->hotel_id === (int) $hotel->id, 404);

        $visit->load(['category', 'reservation.roomUnit', 'creator', 'service']);

        return view('hotel.pms.swimming.show', [
            'hotel' => $hotel,
            'visit' => $visit,
            'ui' => $this->visits->uiConfig(),
        ]);
    }

    public function print(HotelSwimmingVisit $visit): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $visit->hotel_id === (int) $hotel->id, 404);

        $visit->load(['category', 'service', 'creator']);

        return view('hotel.pms.swimming.print', [
            'hotel' => $hotel,
            'visit' => $visit,
        ]);
    }

    public function complete(HotelSwimmingVisit $visit): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $visit->hotel_id === (int) $hotel->id, 404);

        try {
            $this->visits->completeVisit($visit, auth()->user());
        } catch (InvalidArgumentException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->back()->with('success', 'Guest marked as left the pool.');
    }

    public function postFolio(HotelSwimmingVisit $visit): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $visit->hotel_id === (int) $hotel->id, 404);

        $visit->load('reservation');

        try {
            $this->visits->postToFolio($visit, $visit->reservation);
        } catch (InvalidArgumentException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('hotel.swimming.show', $visit)
            ->with('success', 'Charge posted to guest folio.');
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
