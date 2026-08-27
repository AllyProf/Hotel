<?php

namespace App\Http\Controllers\Hotel\ChannelManager;

use App\Http\Controllers\Controller;
use App\Services\BulkUpdateService;
use App\Services\ChannelManager\ChannelManagerPushService;
use App\Services\HotelSettingsService;
use App\Services\OtaConnectionService;
use App\Services\PlatformIntegrationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BulkUpdateController extends Controller
{
    public function __construct(
        private BulkUpdateService $bulkUpdate,
        private HotelSettingsService $settingsService,
        private OtaConnectionService $otaConnections,
        private ChannelManagerPushService $cmPush,
        private PlatformIntegrationService $platformIntegrations,
    ) {}

    public function index(): View
    {
        $hotel = auth()->user()->hotel()->with(['rooms', 'ratePlans.room', 'settings'])->firstOrFail();
        $this->settingsService->ensureDefaults($hotel);

        $rooms = $hotel->rooms()->where('is_enabled', true)->orderBy('rank')->orderBy('name')->get();
        $plans = $hotel->ratePlans()->with('room')->get()->filter(fn ($p) => $p->room !== null);

        return view('hotel.channel-manager.bulk-update', [
            'hotel' => $hotel,
            'rooms' => $rooms,
            'ratePlans' => $plans->map(fn ($plan) => $this->bulkUpdate->planRow($plan))->values(),
            'otas' => $this->otaConnections->configured($hotel),
            'today' => now()->format('Y-m-d'),
            'cmConnected' => $this->cmPush->canPush(),
            'cmSandbox' => $this->platformIntegrations->isChannelManagerSandbox(),
            'configuredOtaCount' => count($this->otaConnections->configuredSlugs($hotel)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date'],
            'update_type' => ['required', 'in:inventory,rate,ratio,increment,restrictions_rates,restrictions_inventory'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['string', 'in:all,sun,mon,tue,wed,thu,fri,sat'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string'],
            'rooms' => ['nullable', 'array'],
            'rooms.*' => ['nullable', 'integer', 'min:0', 'max:999'],
            'plans' => ['nullable', 'array'],
            'plans.*' => ['nullable', 'numeric', 'min:0'],
            'ratio' => ['nullable', 'numeric', 'min:0.1'],
            'increment' => ['nullable', 'numeric'],
            'selected_rooms' => ['nullable', 'array'],
            'selected_plans' => ['nullable', 'array'],
            'restrictions' => ['nullable', 'array'],
            'sync_otas' => ['nullable', 'boolean'],
        ]);

        $weekdays = $validated['weekdays'] ?? ['all'];
        $dates = $this->bulkUpdate->matchingDates(
            Carbon::parse($validated['from_date']),
            Carbon::parse($validated['to_date']),
            $weekdays
        );

        if ($dates === []) {
            return back()->with('warning', 'No dates matched your range and weekday selection.');
        }

        $type = $validated['update_type'];
        $count = 0;

        if ($type === 'inventory') {
            $rooms = $this->filterSelected($validated['rooms'] ?? [], $validated['selected_rooms'] ?? []);
            $count = $this->bulkUpdate->applyInventory($hotel, $dates, $rooms);
        } elseif ($type === 'rate') {
            $plans = $this->filterSelected($validated['plans'] ?? [], $validated['selected_plans'] ?? []);
            $count = $this->bulkUpdate->applyRates($hotel, $dates, $plans);
        } elseif ($type === 'ratio') {
            $plans = $this->filterSelectedKeys($validated['selected_plans'] ?? []);
            $count = $this->bulkUpdate->applyRatio($hotel, $dates, $plans, (float) ($validated['ratio'] ?? 1));
        } elseif ($type === 'increment') {
            $plans = $this->filterSelectedKeys($validated['selected_plans'] ?? []);
            $count = $this->bulkUpdate->applyIncrement($hotel, $dates, $plans, (float) ($validated['increment'] ?? 0));
        } elseif ($type === 'restrictions_rates') {
            $restrictions = $this->filterRestrictions($validated['restrictions'] ?? [], $validated['selected_plans'] ?? [], 'plan');
            $channels = $validated['channels'] ?? ['all'];
            $count = $this->bulkUpdate->applyRateRestrictions($hotel, $dates, $channels, $restrictions);
        } else {
            $restrictions = $this->filterRestrictions($validated['restrictions'] ?? [], $validated['selected_rooms'] ?? [], 'room');
            $channels = $validated['channels'] ?? ['all'];
            $count = $this->bulkUpdate->applyInventoryRestrictions($hotel, $dates, $channels, $restrictions);
        }

        $from = Carbon::parse($validated['from_date']);
        $to = Carbon::parse($validated['to_date']);

        $pushInventory = in_array($type, ['inventory', 'restrictions_rates', 'restrictions_inventory'], true);
        $pushRates = in_array($type, ['rate', 'ratio', 'increment'], true);

        $syncResult = $this->cmPush->pushBulk($hotel->fresh(), $from, $to, $pushInventory, $pushRates);

        $baseMessage = "Bulk update applied to {$count} record(s) across ".count($dates).' day(s).';
        $flash = $this->cmPush->flashForSaveResult($baseMessage, $syncResult);

        return back()->with($flash['flash_key'], $flash['message']);
    }

    /** @param array<int|string, mixed> $values @param list<int|string> $selected @return array<int, mixed> */
    private function filterSelected(array $values, array $selected): array
    {
        $selected = array_map('intval', $selected);
        $out = [];

        foreach ($values as $id => $value) {
            if (in_array((int) $id, $selected, true) && $value !== null && $value !== '') {
                $out[(int) $id] = $value;
            }
        }

        return $out;
    }

    /** @param list<int|string> $selected @return array<int, int> */
    private function filterSelectedKeys(array $selected): array
    {
        $out = [];
        foreach ($selected as $id) {
            $out[(int) $id] = 1;
        }

        return $out;
    }

    /** @param array<string, array<string, mixed>> $restrictions @param list<int|string> $selected @return array<int, array<string, mixed>> */
    private function filterRestrictions(array $restrictions, array $selected, string $key): array
    {
        $out = [];
        foreach ($selected as $id) {
            $id = (int) $id;
            if (isset($restrictions[$key.'_'.$id]) && is_array($restrictions[$key.'_'.$id])) {
                $out[$id] = $restrictions[$key.'_'.$id];
            }
        }

        return $out;
    }
}
