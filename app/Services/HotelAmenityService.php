<?php

namespace App\Services;

use App\Models\Hotel;
use Illuminate\Support\Str;

class HotelAmenityService
{
    /** @return array<string, array{label: string, icon: string, custom?: bool}> */
    public function allForHotel(?Hotel $hotel): array
    {
        $amenities = config('hotel_amenities', []);

        foreach ($this->customForHotel($hotel) as $key => $item) {
            $amenities[$key] = $item;
        }

        return $amenities;
    }

    /** @return array<string, array{label: string, icon: string, custom: bool}> */
    public function customForHotel(?Hotel $hotel): array
    {
        if (! $hotel) {
            return [];
        }

        $settings = $hotel->relationLoaded('settings') ? $hotel->settings : $hotel->settings()->first();
        $items = $settings?->custom_amenities ?? [];
        $mapped = [];

        foreach ($items as $item) {
            if (! is_array($item) || empty($item['key']) || empty($item['label'])) {
                continue;
            }

            $mapped[$item['key']] = [
                'label' => $item['label'],
                'icon' => $item['icon'] ?? 'fa fa-star',
                'custom' => true,
            ];
        }

        return $mapped;
    }

    /** @return list<string> */
    public function allowedKeys(?Hotel $hotel): array
    {
        return array_keys($this->allForHotel($hotel));
    }

    /** @return array{key: string, label: string, icon: string} */
    public function makeCustomEntry(string $label, ?string $icon = null): array
    {
        $base = Str::slug($label, '_');

        return [
            'key' => 'custom_'.($base !== '' ? $base : 'amenity').'_'.Str::lower(Str::random(4)),
            'label' => trim($label),
            'icon' => $icon ?: 'fa fa-star',
        ];
    }
}
