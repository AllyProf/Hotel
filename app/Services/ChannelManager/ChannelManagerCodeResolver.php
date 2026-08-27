<?php

namespace App\Services\ChannelManager;

use App\Models\Hotel;
use App\Models\HotelRatePlan;
use App\Models\HotelRoom;
use App\Services\HotelIntegrationService;
use App\Services\PlatformIntegrationService;
use Illuminate\Support\Str;

class ChannelManagerCodeResolver
{
    public function __construct(
        private HotelIntegrationService $hotelIntegrations,
        private PlatformIntegrationService $platformIntegrations,
        private ChannelManagerPropertyService $propertyService,
    ) {}

    public function hotelCode(Hotel $hotel): string
    {
        if ($this->platformIntegrations->isChannelManagerSandbox()) {
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

        return $hotelCm['hotel_code'] ?: Str::slug($hotel->name ?: 'hotel-'.$hotel->id);
    }

    public function roomCode(Hotel $hotel, HotelRoom $room): string
    {
        $hotel->loadMissing('settings');
        $mapped = $this->propertyService->mappedRoomCode($hotel, $room);
        if ($mapped !== null) {
            return $mapped;
        }

        return Str::slug($room->name ?: 'room-'.$room->id);
    }

    public function rateplanCode(Hotel $hotel, HotelRatePlan $plan): string
    {
        $hotel->loadMissing('settings');
        $mapped = $this->propertyService->mappedRateplanCode($hotel, $plan);
        if ($mapped !== null) {
            return strtolower($mapped);
        }

        $plan->loadMissing('room');
        $code = trim((string) $plan->code);

        if ($code !== '' && preg_match('/^.+-.+-.+$/i', $code)) {
            return strtolower($code);
        }

        $room = $plan->room;
        $roomSlug = $room ? $this->roomCode($hotel, $room) : 'room';
        $occupancy = $this->occupancyLetter((string) $plan->occupancy);
        $meal = strtolower((string) ($plan->meal_plan ?: 'ep'));

        return "{$roomSlug}-{$occupancy}-{$meal}";
    }

    public function occupancyLetter(string $occupancy): string
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
