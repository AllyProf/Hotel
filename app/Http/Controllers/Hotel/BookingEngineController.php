<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Services\BookingEngineService;
use App\Services\HotelAmenityService;
use App\Services\HotelIntegrationService;
use App\Services\HotelSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingEngineController extends Controller
{
    public function __construct(
        private BookingEngineService $bookingEngine,
        private HotelIntegrationService $integrations,
        private HotelSettingsService $settingsService,
        private HotelAmenityService $amenityService,
    ) {}

    public function liveLink(): View
    {
        $hotel = auth()->user()->hotel()->with('settings')->firstOrFail();
        $bookingEngine = $this->bookingEngine->liveLinkData($hotel);

        return view('hotel.booking-engine.live-link', [
            'hotel' => $hotel,
            'bookingEngine' => $bookingEngine,
            'ui' => $this->bookingEngine->uiConfig(),
        ]);
    }

    public function updateLiveLink(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->with('settings')->firstOrFail();
        $settings = $this->integrations->ensureSettings($hotel);

        $validated = $request->validate([
            'public_slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/'],
            'custom_domain' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['enabled'] = $request->boolean('enabled');

        $this->integrations->updateBookingEngine($hotel, $settings, $validated);

        return redirect()
            ->route('hotel.booking-engine.live-link')
            ->with('success', 'Direct booking settings saved.');
    }

    public function coupons(): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        return view('hotel.booking-engine.coupons', [
            'hotel' => $hotel,
            'coupons' => $this->bookingEngine->coupons($hotel),
            'ui' => $this->bookingEngine->uiConfig(),
        ]);
    }

    public function storeCoupon(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'discount_type' => ['required', 'in:percent,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'active' => ['nullable', 'boolean'],
            'min_stay' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['active'] = $request->boolean('active', true);

        $this->bookingEngine->storeCoupon($hotel, $validated);

        return redirect()
            ->route('hotel.booking-engine.coupons')
            ->with('success', 'Coupon added.');
    }

    public function updateCoupon(Request $request, string $coupon): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'discount_type' => ['required', 'in:percent,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'active' => ['nullable', 'boolean'],
            'min_stay' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['active'] = $request->boolean('active');

        $this->bookingEngine->updateCoupon($hotel, $coupon, $validated);

        return redirect()
            ->route('hotel.booking-engine.coupons')
            ->with('success', 'Coupon updated.');
    }

    public function destroyCoupon(string $coupon): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->bookingEngine->deleteCoupon($hotel, $coupon);

        return redirect()
            ->route('hotel.booking-engine.coupons')
            ->with('success', 'Coupon removed.');
    }

    public function photos(): View
    {
        $hotel = auth()->user()->hotel()
            ->with(['rooms' => fn ($query) => $query->with('photos')->orderBy('rank')->orderBy('name')])
            ->firstOrFail();

        return view('hotel.booking-engine.photos', [
            'hotel' => $hotel,
            'rooms' => $hotel->rooms,
            'ui' => $this->bookingEngine->uiConfig(),
        ]);
    }

    public function websiteConfig(Request $request): View
    {
        $hotel = auth()->user()->hotel()
            ->with(['rooms.ratePlans', 'ratePlans', 'settings', 'plan'])
            ->firstOrFail();

        $settings = $this->settingsService->ensureDefaults($hotel);
        $amenities = $this->amenityService->allForHotel($hotel);

        $beRoomId = (int) $request->query('be_room_id', $hotel->rooms->first()?->id ?? 0);
        $beRateplanId = (int) $request->query('be_rateplan_id', $hotel->ratePlans->first()?->id ?? 0);

        $beRoom = $hotel->rooms->firstWhere('id', $beRoomId) ?? $hotel->rooms->first();
        $beRateplan = $hotel->ratePlans->firstWhere('id', $beRateplanId) ?? $hotel->ratePlans->first();

        return view('hotel.booking-engine.website-config', [
            'hotel' => $hotel,
            'settings' => $settings,
            'amenities' => $amenities,
            'beRoom' => $beRoom,
            'beRateplan' => $beRateplan,
            'ui' => $this->bookingEngine->uiConfig(),
        ]);
    }

}
