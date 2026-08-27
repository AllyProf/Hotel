<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Services\HotelIntegrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function __construct(private HotelIntegrationService $integrations) {}

    public function updateBookingEngine(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->with('settings')->firstOrFail();

        if (! $hotel->hasFeature('booking_engine_website')) {
            abort(403);
        }

        $settings = $this->integrations->ensureSettings($hotel);

        $validated = $request->validate([
            'public_slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/'],
            'custom_domain' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['enabled'] = $request->boolean('enabled');

        $this->integrations->updateBookingEngine($hotel, $settings, $validated);

        return redirect()
            ->to(route('hotel.dashboard').'#be-integration')
            ->with('success', 'Direct booking settings saved.');
    }
}
