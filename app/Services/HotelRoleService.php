<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelRole;

class HotelRoleService
{
    /** @return list<string> */
    public function allPermissionKeys(): array
    {
        $keys = [];
        foreach (config('hotel_permissions.groups', []) as $permissions) {
            foreach ($permissions as $key => $label) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public function ensureDefaults(Hotel $hotel): void
    {
        if ($hotel->roles()->exists()) {
            return;
        }

        $defaults = [
            [
                'name' => 'Manager',
                'slug' => 'manager',
                'description' => 'Full operational access except role management.',
                'permissions' => array_values(array_diff($this->allPermissionKeys(), ['roles.manage'])),
                'is_system' => true,
            ],
            [
                'name' => 'Front Desk',
                'slug' => 'front-desk',
                'description' => 'Dashboard, reservations, and channel visibility.',
                'permissions' => [
                    'dashboard.view',
                    'pms.view',
                    'pms.manage',
                    'channel_manager.view',
                    'booking_engine.view',
                ],
                'is_system' => true,
            ],
            [
                'name' => 'Housekeeping',
                'slug' => 'housekeeping',
                'description' => 'Dashboard and basic PMS visibility.',
                'permissions' => [
                    'dashboard.view',
                    'pms.view',
                ],
                'is_system' => true,
            ],
        ];

        foreach ($defaults as $role) {
            $hotel->roles()->create($role);
        }
    }

    /** @param list<string> $submitted */
    public function sanitizePermissions(array $submitted): array
    {
        $valid = array_flip($this->allPermissionKeys());

        return array_values(array_filter($submitted, fn ($key) => isset($valid[$key])));
    }
}
