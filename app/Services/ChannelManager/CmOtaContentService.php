<?php

namespace App\Services\ChannelManager;

use App\Models\Hotel;

class CmOtaContentService
{
    public function __construct(private ChannelManagerPropertyService $propertyService) {}

    /** @return array{success: bool, message: string, property: array<string, mixed>|null, rooms: list<array<string, mixed>>} */
    public function load(Hotel $hotel): array
    {
        $fetch = $this->propertyService->fetchProperty($hotel);

        if (! $fetch['success'] || ! is_array($fetch['property'])) {
            return [
                'success' => false,
                'message' => $fetch['message'],
                'property' => null,
                'rooms' => [],
            ];
        }

        $property = $fetch['property'];
        $rooms = [];

        foreach ($property['rooms'] ?? [] as $room) {
            if (! is_array($room)) {
                continue;
            }

            $ratePlans = [];

            foreach ($room['rateplans'] ?? [] as $plan) {
                if (! is_array($plan)) {
                    continue;
                }

                $ratePlans[] = [
                    'code' => (string) ($plan['rateplan_id'] ?? ''),
                    'name' => (string) ($plan['rateplan_name'] ?? ''),
                    'description' => trim((string) ($plan['description'] ?? '')),
                    'occupancy' => (int) ($plan['occupancy'] ?? 0),
                    'meals' => (int) ($plan['no_of_meals'] ?? 0),
                ];
            }

            $rooms[] = [
                'code' => (string) ($room['room_id'] ?? ''),
                'name' => (string) ($room['room_name'] ?? ''),
                'description' => trim((string) ($room['description'] ?? '')),
                'count' => (int) ($room['count'] ?? 0),
                'active' => (bool) ($room['active'] ?? true),
                'min_occ' => (int) ($room['min_occ'] ?? 0),
                'max_occ' => (int) ($room['max_occ'] ?? 0),
                'rate_plans' => $ratePlans,
            ];
        }

        return [
            'success' => true,
            'message' => 'Property content loaded from Channel Manager.',
            'property' => [
                'hotel_id' => (string) ($property['hotel_id'] ?? ''),
                'hotel_name' => (string) ($property['hotel_name'] ?? ''),
                'category' => (string) ($property['property_category'] ?? ''),
                'currency' => (string) ($property['currency'] ?? ''),
                'timezone' => (string) ($property['timezone'] ?? ''),
                'address' => is_array($property['address'] ?? null) ? $property['address'] : [],
                'contact' => is_array($property['contact'] ?? null) ? $property['contact'] : [],
            ],
            'rooms' => $rooms,
        ];
    }
}
