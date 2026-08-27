<?php

namespace App\Http\Controllers\Hotel\ChannelManager;

use App\Http\Controllers\Controller;
use App\Services\ChannelManager\ChannelManagerCodeResolver;
use App\Services\ChannelManager\ChannelManagerPropertyService;
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

        $this->otaConnections->saveConnection($hotel, $slug, [
            'enabled' => $request->boolean('enabled'),
            'hotel_code' => $validated['hotel_code'] ?? '',
            'currency' => $validated['currency'] ?? 'USD',
            'rate_multiplier' => $validated['rate_multiplier'] ?? 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mapping saved. This channel will appear on Update Rates and Update Rooms.',
            'configured' => $this->otaConnections->isConfigured($hotel->fresh(), $slug),
        ]);
    }
}
