<?php

namespace App\Http\Controllers\Hotel\ChannelManager;

use App\Http\Controllers\Controller;
use App\Services\ChannelManager\ChannelManagerCodeResolver;
use App\Services\ChannelManager\ChannelManagerPropertyService;
use App\Services\ChannelManager\ChannelManagerPushService;
use App\Services\HotelIntegrationService;
use App\Services\OtaConnectionService;
use App\Services\OtaLogoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OtaMappingController extends Controller
{
    public function __construct(
        private OtaLogoService $otaLogos,
        private HotelIntegrationService $hotelIntegrations,
        private ChannelManagerCodeResolver $codes,
        private ChannelManagerPropertyService $propertyService,
        private OtaConnectionService $otaConnections,
        private ChannelManagerPushService $cmPush,
    ) {}

    public function index(): View
    {
        $hotel = auth()->user()->hotel()->with(['rooms.ratePlans', 'settings'])->firstOrFail();
        $otas = $this->otaLogos->all();

        if ($this->propertyService->isSandbox()) {
            $this->propertyService->ensureHotelMapping($hotel);
            $hotel->load('settings');
        }

        $cm = array_merge(
            $this->hotelIntegrations->defaultChannelManager($hotel),
            $hotel->settings?->integrations['channel_manager'] ?? []
        );

        $roomMappings = $cm['room_mappings'] ?? [];
        $rateMappings = $cm['rate_plan_mappings'] ?? [];

        $rooms = $hotel->rooms()
            ->where('is_enabled', true)
            ->orderBy('rank')
            ->orderBy('name')
            ->with(['ratePlans' => fn ($q) => $q->orderBy('id')])
            ->get()
            ->map(function ($room) use ($roomMappings, $rateMappings) {
                $cmRoomCode = $roomMappings[(string) $room->id] ?? Str::slug($room->name ?: 'room-'.$room->id);

                return [
                    'id' => $room->id,
                    'name' => $room->name,
                    'slug' => $cmRoomCode,
                    'rate_plans' => $room->ratePlans->map(function ($plan) use ($rateMappings, $cmRoomCode) {
                        $cmRateCode = $rateMappings[(string) $plan->id]
                            ?? strtolower($cmRoomCode.'-s-'.strtolower($plan->meal_plan ?: 'ep'));

                        return [
                            'id' => $plan->id,
                            'label' => $plan->meal_plan.' · '.($plan->description ?: 'Standard'),
                            'code' => $cmRateCode,
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();

        $connections = [];
        foreach ($otas as $ota) {
            $connections[$ota['slug']] = $this->otaConnections->connection($hotel, $ota['slug']);
        }

        return view('hotel.channel-manager.ota-mapping', [
            'hotel' => $hotel,
            'otas' => $otas,
            'connections' => $connections,
            'rooms' => $rooms,
            'hotelCode' => $this->codes->hotelCode($hotel),
            'sandboxProperty' => $cm['sandbox_property'] ?? null,
            'configuredCount' => count($this->otaConnections->configuredSlugs($hotel)),
        ]);
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'hotel_code' => ['nullable', 'string', 'max:120'],
            'currency' => ['nullable', 'string', 'max:8'],
            'rate_multiplier' => ['nullable', 'numeric', 'min:0.1', 'max:10'],
        ]);

        if ($request->boolean('enabled') && trim((string) ($validated['hotel_code'] ?? '')) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Enter a hotel code before enabling this channel.',
            ], 422);
        }

        $enabled = $request->boolean('enabled');
        $multiplier = (float) ($validated['rate_multiplier'] ?? 1);

        $this->otaConnections->saveConnection($hotel, $slug, [
            'enabled' => $enabled,
            'hotel_code' => $validated['hotel_code'] ?? '',
            'currency' => $validated['currency'] ?? 'USD',
            'rate_multiplier' => $multiplier,
        ]);

        $message = 'Mapping saved. This channel will appear on Update Rates and Update Rooms.';
        $multiplierPushed = false;

        if ($enabled) {
            $push = $this->cmPush->pushOtaRateMultiplier($hotel, $slug, $multiplier);

            if ($push['success']) {
                $multiplierPushed = true;
                $message .= ' Rate multiplier pushed to Channel Manager.';
            } elseif ($this->cmPush->canPush()) {
                $message .= ' Could not push rate multiplier: '.$push['message'];
            }
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'configured' => $this->otaConnections->isConfigured($hotel->fresh(), $slug),
            'multiplier_pushed' => $multiplierPushed,
        ]);
    }

    public function syncMultipliers(): JsonResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $result = $this->cmPush->pushAllOtaRateMultipliers($hotel);

        if (! $result['attempted']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'details' => $result['details'],
            ], 422);
        }

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'details' => $result['details'],
        ], $result['success'] ? 200 : 422);
    }

    public function fetchProperty(Request $request): JsonResponse
    {
        auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'hotel_code' => ['required', 'string', 'max:120'],
        ]);

        $hotelCode = trim($validated['hotel_code']);
        $client = app(\App\Services\ChannelManager\ChannelManagerClient::class);

        if (! $client->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Channel Manager is not configured. Ask your platform admin to enable CM credentials.',
            ], 422);
        }

        $result = $client->getPropertyDetails($hotelCode);

        if (! $result['success'] || ! is_array($result['response'])) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?: 'Could not fetch property details from Channel Manager.',
            ], 422);
        }

        $property = $result['response'];
        $rooms = is_array($property['rooms'] ?? null) ? $property['rooms'] : [];

        return response()->json([
            'success' => true,
            'message' => 'Property details loaded from Channel Manager.',
            'property' => [
                'hotel_id' => (string) ($property['hotel_id'] ?? $hotelCode),
                'hotel_name' => (string) ($property['hotel_name'] ?? ''),
                'currency' => (string) ($property['currency'] ?? ''),
                'city' => (string) ($property['address']['city'] ?? ''),
                'room_count' => count($rooms),
                'rateplan_count' => collect($rooms)->sum(fn ($room) => is_array($room) ? count($room['rateplans'] ?? []) : 0),
            ],
        ]);
    }
}
