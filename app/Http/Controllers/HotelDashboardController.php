<?php

namespace App\Http\Controllers;

use App\Services\HotelIntegrationService;
use App\Services\HotelMenuService;
use Illuminate\View\View;

class HotelDashboardController extends Controller
{
    public function __construct(
        private HotelMenuService $menu,
        private HotelIntegrationService $integrations,
    ) {}

    public function index(): View
    {
        $hotel = auth()->user()->hotel()->with('plan', 'settings')->withCount('branches')->first();
        $moduleCards = $hotel ? $this->menu->moduleCards($hotel) : [];
        $gettingStartedSteps = $hotel ? $this->menu->gettingStartedSteps($hotel) : [];

        $settings = $hotel ? $this->integrations->ensureSettings($hotel) : null;
        $hasChannelManager = $hotel?->hasFeature('channel_manager') ?? false;
        $hasBookingEngine = $hotel?->hasFeature('booking_engine_website') ?? false;

        $cmStatus = ($hotel && $settings && $hasChannelManager)
            ? $this->integrations->channelManagerStatus($hotel, $settings)
            : null;

        $bookingEngine = ($hotel && $settings && $hasBookingEngine)
            ? $this->integrations->bookingEngineForDisplay($hotel, $settings)
            : null;

        return view('hotel.dashboard', compact(
            'hotel',
            'moduleCards',
            'gettingStartedSteps',
            'hasChannelManager',
            'hasBookingEngine',
            'cmStatus',
            'bookingEngine',
        ));
    }
}
