<?php

namespace App\Services\ChannelManager;

use App\Models\Hotel;
use App\Models\HotelRatePlan;
use App\Models\HotelRoom;
use App\Services\HotelIntegrationService;
use App\Services\PlatformIntegrationService;

class ChannelManagerPropertyService
{
    public function __construct(
        private ChannelManagerClient $client,
        private PlatformIntegrationService $platformIntegrations,
        private HotelIntegrationService $hotelIntegrations,
    ) {}

    public function isSandbox(): bool
    {
        return $this->platformIntegrations->isChannelManagerSandbox();
    }

    /** @return array{success: bool, message: string, property: array<string, mixed>|null} */
    public function fetchProperty(Hotel $hotel): array
    {
        $hotelCode = $this->resolveHotelCode($hotel);
        $result = $this->client->getPropertyDetails($hotelCode);

        if (! $result['success'] || ! is_array($result['response'])) {
            return [
                'success' => false,
                'message' => $result['message'],
                'property' => null,
            ];
        }

        return [
            'success' => true,
            'message' => 'Property details loaded.',
            'property' => $result['response'],
        ];
    }

    /** Build sandbox room/rate mappings from Aiosell property details. */
    public function ensureHotelMapping(Hotel $hotel): bool
    {
        if (! $this->isSandbox()) {
            return true;
        }

        $fetch = $this->fetchProperty($hotel);
        if (! $fetch['success'] || ! is_array($fetch['property'])) {
            return $this->hasCachedMappings($hotel);
        }

        $property = $fetch['property'];
        $propertyRooms = collect($property['rooms'] ?? [])->values();
        $hotelRooms = $hotel->rooms()->where('is_enabled', true)->orderBy('rank')->orderBy('id')->get();
        $ratePlans = HotelRatePlan::query()->where('hotel_id', $hotel->id)->with('room')->get();

        $roomMappings = [];
        foreach ($hotelRooms as $index => $room) {
            $propertyRoom = $propertyRooms->get($index);
            if (! is_array($propertyRoom) || empty($propertyRoom['room_id'])) {
                continue;
            }
            $roomMappings[(string) $room->id] = (string) $propertyRoom['room_id'];
        }

        $ratePlanMappings = [];
        foreach ($ratePlans as $plan) {
            if (! $plan->room) {
                continue;
            }

            $roomCode = $roomMappings[(string) $plan->hotel_room_id] ?? null;
            if ($roomCode === null) {
                continue;
            }

            $propertyRoom = $propertyRooms->first(fn ($row) => is_array($row) && ($row['room_id'] ?? '') === $roomCode);
            if (! is_array($propertyRoom)) {
                continue;
            }

            $rateplanId = $this->matchRateplanId($propertyRoom, $plan);
            if ($rateplanId !== null) {
                $ratePlanMappings[(string) $plan->id] = $rateplanId;
            }
        }

        $settings = $this->hotelIntegrations->ensureSettings($hotel);
        $integrations = $settings->integrations ?? $this->hotelIntegrations->defaultIntegrations($hotel);
        $integrations['channel_manager'] = array_merge(
            $this->hotelIntegrations->defaultChannelManager($hotel),
            $integrations['channel_manager'] ?? [],
            [
                'hotel_code' => $property['hotel_id'] ?? config('channel_manager_integration.sandbox.hotel_code'),
                'sandbox_property' => [
                    'hotel_id' => $property['hotel_id'] ?? null,
                    'hotel_name' => $property['hotel_name'] ?? null,
                    'synced_at' => now()->toIso8601String(),
                ],
                'room_mappings' => $roomMappings,
                'rate_plan_mappings' => $ratePlanMappings,
            ]
        );

        $settings->update(['integrations' => $integrations]);

        return $roomMappings !== [];
    }

    public function mappedRoomCode(Hotel $hotel, HotelRoom $room): ?string
    {
        if (! $this->isSandbox()) {
            return null;
        }

        $settings = $hotel->settings;
        $mappings = $settings?->integrations['channel_manager']['room_mappings'] ?? [];

        return $mappings[(string) $room->id] ?? null;
    }

    public function mappedRateplanCode(Hotel $hotel, HotelRatePlan $plan): ?string
    {
        if (! $this->isSandbox()) {
            return null;
        }

        $settings = $hotel->settings;
        $mappings = $settings?->integrations['channel_manager']['rate_plan_mappings'] ?? [];

        return $mappings[(string) $plan->id] ?? null;
    }

    /** @param array<string, mixed> $propertyRoom */
    private function matchRateplanId(array $propertyRoom, HotelRatePlan $plan): ?string
    {
        $rateplans = collect($propertyRoom['rateplans'] ?? []);
        if ($rateplans->isEmpty()) {
            return null;
        }

        $meals = HotelRatePlan::mealsForPlan((string) ($plan->meal_plan ?? 'EP'));
        $occupancy = $this->occupancyLetter((string) ($plan->occupancy ?? 'Standard'));

        $exact = $rateplans->first(function ($row) use ($meals, $occupancy) {
            if (! is_array($row)) {
                return false;
            }

            return (int) ($row['no_of_meals'] ?? -1) === $meals
                && str_contains(strtolower((string) ($row['rateplan_id'] ?? '')), '-'.$occupancy.'-');
        });

        if (is_array($exact) && ! empty($exact['rateplan_id'])) {
            return (string) $exact['rateplan_id'];
        }

        $byMeals = $rateplans->first(fn ($row) => is_array($row) && (int) ($row['no_of_meals'] ?? -1) === $meals);
        if (is_array($byMeals) && ! empty($byMeals['rateplan_id'])) {
            return (string) $byMeals['rateplan_id'];
        }

        $first = $rateplans->first();

        return is_array($first) ? ($first['rateplan_id'] ?? null) : null;
    }

    private function hasCachedMappings(Hotel $hotel): bool
    {
        $hotel->loadMissing('settings');
        $cm = $hotel->settings?->integrations['channel_manager'] ?? [];

        return ! empty($cm['room_mappings']);
    }

    private function resolveHotelCode(Hotel $hotel): string
    {
        if ($this->isSandbox()) {
            return config('channel_manager_integration.sandbox.hotel_code', 'sandbox-pms');
        }

        $hotel->loadMissing('settings');
        $settings = $hotel->settings;

        if ($settings) {
            $hotelCm = array_merge(
                $this->hotelIntegrations->defaultChannelManager($hotel),
                $settings->integrations['channel_manager'] ?? []
            );
        } else {
            $hotelCm = $this->hotelIntegrations->defaultChannelManager($hotel);
        }

        return $hotelCm['hotel_code'] ?: \Illuminate\Support\Str::slug($hotel->name ?: 'hotel-'.$hotel->id);
    }

    private function occupancyLetter(string $occupancy): string
    {
        if (preg_match('/\(([sdtq])\)/i', $occupancy, $matches)) {
            return strtolower($matches[1]);
        }

        $lower = strtolower($occupancy);

        if (str_contains($lower, 'quad')) {
            return 'q';
        }

        if (str_contains($lower, 'triple')) {
            return 't';
        }

        if (str_contains($lower, 'double')) {
            return 'd';
        }

        return 's';
    }
}
