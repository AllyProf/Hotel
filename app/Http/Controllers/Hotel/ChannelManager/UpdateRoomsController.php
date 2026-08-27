<?php

namespace App\Http\Controllers\Hotel\ChannelManager;

use App\Http\Controllers\Controller;
use App\Services\ChannelManager\ChannelManagerPushService;
use App\Services\HotelSettingsService;
use App\Services\OtaConnectionService;
use App\Services\RoomInventoryService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UpdateRoomsController extends Controller
{
    public function __construct(
        private RoomInventoryService $inventoryService,
        private HotelSettingsService $settingsService,
        private ChannelManagerPushService $cmPush,
        private OtaConnectionService $otaConnections,
    ) {}

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel()->with('rooms')->firstOrFail();
        $this->settingsService->ensureDefaults($hotel);
        $hotel->load('rooms');

        $startDate = $this->resolveStartDate($request->input('start_date'));
        $grid = $this->inventoryService->grid($hotel, $startDate);

        return view('hotel.channel-manager.update-rooms', [
            'hotel' => $hotel,
            'startDate' => $startDate->format('Y-m-d'),
            'windowDays' => RoomInventoryService::WINDOW_DAYS,
            'grid' => $grid,
            'otas' => $this->otaConnections->configured($hotel),
            'hasConfiguredOtas' => count($this->otaConnections->configuredSlugs($hotel)) > 0,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $startDate = $this->resolveStartDate($request->input('start_date'));

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'rooms' => ['required', 'array'],
            'rooms.*' => ['array'],
            'rooms.*.*' => ['integer', 'min:0', 'max:999'],
            'availability' => ['nullable', 'array'],
            'availability.*' => ['nullable'],
        ]);

        $this->inventoryService->save(
            $hotel,
            $startDate,
            $validated['rooms'],
            $validated['availability'] ?? []
        );

        $flash = $this->cmPush->flashForSaveResult(
            'Available rooms updated successfully.',
            $this->cmPush->pushAfterInventorySave($hotel->fresh(), $startDate)
        );

        return redirect()
            ->route('hotel.channel-manager.update-rooms', ['start_date' => $startDate->format('Y-m-d')])
            ->with($flash['flash_key'], $flash['message']);
    }

    private function resolveStartDate(?string $value): Carbon
    {
        try {
            return Carbon::parse($value ?? now())->startOfDay();
        } catch (\Throwable) {
            return now()->startOfDay();
        }
    }
}
