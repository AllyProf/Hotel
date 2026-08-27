<?php

namespace App\Http\Controllers\Hotel\ChannelManager;

use App\Http\Controllers\Controller;
use App\Services\ChannelManager\ChannelManagerPushService;
use App\Services\HotelSettingsService;
use App\Services\OtaConnectionService;
use App\Services\RateInventoryService;
use App\Services\RoomInventoryService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UpdateRatesController extends Controller
{
    public function __construct(
        private RateInventoryService $rateInventory,
        private RoomInventoryService $roomInventory,
        private HotelSettingsService $settingsService,
        private ChannelManagerPushService $cmPush,
        private OtaConnectionService $otaConnections,
    ) {}

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel()->with(['rooms', 'ratePlans.room', 'settings'])->firstOrFail();
        $this->settingsService->ensureDefaults($hotel);

        if ($request->input('view') === 'calendar') {
            return $this->calendarView($request, $hotel);
        }

        $startDate = $this->resolveStartDate($request->input('start_date'));
        $grid = $this->rateInventory->calendarGrid($hotel, $startDate);

        return view('hotel.channel-manager.update-rates', [
            'hotel' => $hotel,
            'startDate' => $startDate->format('Y-m-d'),
            'startDateLabel' => $startDate->format('d M y'),
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
            'rates' => ['required', 'array'],
            'rates.*' => ['array'],
            'rates.*.*' => ['array'],
            'rates.*.*.amount' => ['nullable', 'numeric', 'min:0'],
            'rates.*.*.local' => ['nullable', 'numeric', 'min:0'],
            'rates.*.*.international' => ['nullable', 'numeric', 'min:0'],
            'dynamic_rates' => ['nullable', 'array'],
            'dynamic_rates.*' => ['nullable', 'boolean'],
        ]);

        $this->rateInventory->save($hotel, $startDate, $validated['rates']);
        $this->saveDynamicRates($hotel, $startDate, $validated['dynamic_rates'] ?? []);

        $flash = $this->cmPush->flashForSaveResult(
            'Rates updated successfully.',
            $this->cmPush->pushAfterRateSave($hotel->fresh(), $startDate)
        );

        return redirect()
            ->route('hotel.channel-manager.update-rates', ['start_date' => $startDate->format('Y-m-d')])
            ->with($flash['flash_key'], $flash['message']);
    }

    public function updateAvailability(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'action' => ['required', 'in:available,stop_sell,custom'],
            'ota_status' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
        ]);

        if ($validated['action'] === 'custom') {
            if (count($this->otaConnections->configuredSlugs($hotel)) === 0) {
                return redirect()
                    ->route('hotel.channel-manager.ota-mapping')
                    ->with('warning', 'Connect at least one OTA in Mapping Setup first.');
            }

            $decoded = json_decode($validated['ota_status'] ?? '', true);
            if (! is_array($decoded)) {
                return redirect()
                    ->back()
                    ->with('error', 'Invalid channel selection.');
            }

            $this->roomInventory->setDayOtaStatus($hotel, $validated['date'], $decoded);
            $message = 'Channel availability updated.';
        } else {
            if (count($this->otaConnections->configuredSlugs($hotel)) === 0) {
                return redirect()
                    ->route('hotel.channel-manager.ota-mapping')
                    ->with('warning', 'Connect at least one OTA in Mapping Setup first.');
            }

            $available = $validated['action'] === 'available';
            $this->roomInventory->setDayAvailability($hotel, $validated['date'], $available);
            $message = $available
                ? 'Date marked as available on all channels.'
                : 'Stop sell applied on all channels.';
        }

        $this->cmPush->pushAfterInventorySave(
            $hotel->fresh(),
            Carbon::parse($validated['date'])->startOfDay()
        );

        $params = ['start_date' => $validated['start_date'] ?? $validated['date']];

        return redirect()
            ->route('hotel.channel-manager.update-rates', $params)
            ->with('success', $message);
    }

    public function storeEvent(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $settings = $this->settingsService->ensureDefaults($hotel);

        $validated = $request->validate([
            'event_date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'venue' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'event_start' => ['nullable', 'date'],
            'event_end' => ['nullable', 'date', 'after_or_equal:event_start'],
            'pax' => ['nullable', 'integer', 'min:0'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'demand_override' => ['nullable', 'boolean'],
            'market_demand' => ['nullable', 'string', 'max:100'],
            'return_start_date' => ['nullable', 'date'],
            'return_view' => ['nullable', 'in:grid,calendar'],
            'return_month' => ['nullable', 'date_format:Y-m'],
        ]);

        $rateplan = is_array($settings->rateplan) ? $settings->rateplan : [];
        $events = $rateplan['events'] ?? [];
        $events[] = [
            'id' => uniqid('evt_', true),
            'event_date' => $validated['event_date'],
            'name' => $validated['name'],
            'venue' => $validated['venue'] ?? '',
            'start' => $validated['event_start'] ?? $validated['event_date'],
            'end' => $validated['event_end'] ?? $validated['event_date'],
            'pax' => $validated['pax'] ?? null,
            'value' => $validated['value'] ?? null,
            'demand_override' => $request->boolean('demand_override'),
            'market_demand' => $validated['market_demand'] ?? '',
            'created_at' => now()->toIso8601String(),
        ];
        $rateplan['events'] = $events;
        $settings->update(['rateplan' => $rateplan]);

        if (($validated['return_view'] ?? 'grid') === 'calendar') {
            return redirect()
                ->route('hotel.channel-manager.update-rates', [
                    'view' => 'calendar',
                    'month' => $validated['return_month'] ?? Carbon::parse($validated['event_date'])->format('Y-m'),
                    'room_id' => $request->input('room_id'),
                ])
                ->with('success', 'Event saved.');
        }

        return redirect()
            ->route('hotel.channel-manager.update-rates', [
                'start_date' => $validated['return_start_date'] ?? $validated['event_date'],
            ])
            ->with('success', 'Event saved.');
    }

    private function calendarView(Request $request, $hotel): View
    {
        try {
            $month = Carbon::parse($request->input('month', now()))->startOfMonth();
        } catch (\Throwable) {
            $month = now()->startOfMonth();
        }

        $roomId = $request->filled('room_id') ? (int) $request->input('room_id') : null;
        $calendar = $this->rateInventory->monthCalendar($hotel, $month, $roomId);

        return view('hotel.channel-manager.update-rates-calendar', [
            'hotel' => $hotel,
            'calendar' => $calendar,
            'month' => $month->format('Y-m'),
            'otas' => $this->otaConnections->configured($hotel),
            'hasConfiguredOtas' => count($this->otaConnections->configuredSlugs($hotel)) > 0,
        ]);
    }

    /** @param array<string, mixed> $dynamicRates */
    private function saveDynamicRates($hotel, Carbon $startDate, array $dynamicRates): void
    {
        $settings = $this->settingsService->ensureDefaults($hotel);
        $rateplan = is_array($settings->rateplan) ? $settings->rateplan : [];
        $dateKeys = app(RoomInventoryService::class)->dateRange($startDate)
            ->map(fn (Carbon $date) => $date->format('Y-m-d'))
            ->all();
        $normalized = [];

        foreach ($dateKeys as $dateKey) {
            $normalized[$dateKey] = isset($dynamicRates[$dateKey])
                && filter_var($dynamicRates[$dateKey], FILTER_VALIDATE_BOOLEAN);
        }

        $rateplan['dynamic_rates'] = $normalized;
        $settings->update(['rateplan' => $rateplan]);
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
