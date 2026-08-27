<?php

namespace App\Services;

use App\Models\Hotel;

class HotelMenuService
{
    public function __construct(private PlanFeatureService $features) {}

    /** @return list<array<string, mixed>> */
    public function sidebarGroups(Hotel $hotel): array
    {
        $enabled = array_flip($hotel->plan?->enabledFeatureKeys() ?? []);
        $groups = [];

        foreach (config('hotel_sidebar.groups', []) as $group) {
            $built = $this->buildGroup($group, $enabled);
            if ($built !== null) {
                $groups[] = $built;
            }
        }

        $account = config('hotel_sidebar.account_group');
        if ($account) {
            $accountBuilt = $this->buildAccountGroup($account, $enabled);
            if ($accountBuilt !== null) {
                $groups[] = $accountBuilt;
            }
        }

        return $groups;
    }

    /** @return list<array{key: string, label: string, icon: string, url: string, route_is: ?string, active: bool, description: string, available: bool, getting_started: int}> */
    public function moduleCards(Hotel $hotel): array
    {
        $modules = config('hotel_modules', []);
        $enabled = array_flip($hotel->plan?->enabledFeatureKeys() ?? []);
        $cards = [];

        foreach ($modules as $key => $module) {
            if (! isset($enabled[$key])) {
                continue;
            }

            $routeName = $module['route'] ?? null;
            $routeIs = $module['route_is'] ?? null;

            $cards[] = [
                'key' => $key,
                'label' => $module['label'] ?? $this->features->label($key),
                'icon' => $module['icon'] ?? 'fa fa-circle-o',
                'url' => $routeName ? route($routeName) : '#',
                'route_is' => $routeIs,
                'active' => $routeIs ? request()->routeIs($routeIs) : false,
                'description' => $module['description'] ?? '',
                'available' => ! empty($routeName),
                'getting_started' => $module['getting_started'] ?? 99,
            ];
        }

        return $cards;
    }

    /** @return list<array{title: string, description: string, url: string, icon: string, step: int, available: bool, key: string}> */
    public function gettingStartedSteps(Hotel $hotel): array
    {
        $cards = $this->moduleCards($hotel);
        usort($cards, fn ($a, $b) => $a['getting_started'] <=> $b['getting_started']);

        $steps = [];
        $stepNumber = 1;

        foreach ($cards as $card) {
            if ($card['key'] === 'groups_chain_hotels_system' && $hotel->branches()->exists()) {
                continue;
            }

            $url = $card['available'] ? $card['url'] : '#';

            if ($card['key'] === 'groups_chain_hotels_system' && $hotel->canAddBranch()) {
                $url = route('hotel.branches.create');
            }

            $steps[] = [
                'step' => $stepNumber++,
                'key' => $card['key'],
                'title' => $card['key'] === 'groups_chain_hotels_system' && ! $hotel->branches()->exists()
                    ? 'Add your first branch'
                    : 'Set up '.$card['label'],
                'description' => $card['description'],
                'url' => $url,
                'icon' => $card['icon'],
                'available' => $card['available'] || ($card['key'] === 'groups_chain_hotels_system' && $hotel->canAddBranch()),
            ];

            if (count($steps) >= 3) {
                break;
            }
        }

        return $steps;
    }

    /** @param  array<string, bool>  $enabled */
    private function buildGroup(array $group, array $enabled): ?array
    {
        if (! $this->hasAnyFeature($enabled, $group['features'] ?? [])) {
            return null;
        }

        $items = [];
        foreach ($group['items'] ?? [] as $item) {
            if (! $this->hasAnyFeature($enabled, $item['features'] ?? [])) {
                continue;
            }

            $items[] = $this->buildItem($item);
        }

        if ($items === []) {
            return null;
        }

        $expanded = collect($items)->contains(fn ($item) => $item['active']);

        return [
            'key' => $group['key'],
            'label' => $group['label'],
            'icon' => $group['icon'],
            'expanded' => $expanded,
            'active' => $expanded,
            'items' => $items,
        ];
    }

    private function buildAccountGroup(array $group, array $enabled): ?array
    {
        $items = [];

        foreach ($group['items'] ?? [] as $item) {
            if (! empty($item['features']) && ! $this->hasAnyFeature($enabled, $item['features'])) {
                continue;
            }

            $items[] = $this->buildItem($item);
        }

        if ($items === []) {
            return null;
        }

        $expanded = collect($items)->contains(fn ($item) => $item['active']);

        return [
            'key' => $group['key'],
            'label' => $group['label'],
            'icon' => $group['icon'],
            'expanded' => $expanded,
            'active' => $expanded,
            'items' => $items,
        ];
    }

    /** @return array<string, mixed> */
    private function buildItem(array $item): array
    {
        $routeIs = $item['route_is'] ?? null;
        $routeName = $item['route'] ?? null;
        $active = $routeIs ? request()->routeIs($routeIs) : false;

        return [
            'label' => $item['label'],
            'icon' => $item['icon'] ?? 'fa fa-circle-o',
            'url' => $routeName ? route($routeName) : '#',
            'route_is' => $routeIs,
            'active' => $active,
            'available' => ! empty($routeName),
        ];
    }

    /** @param  array<string, bool>  $enabled */
    private function hasAnyFeature(array $enabled, array $features): bool
    {
        foreach ($features as $feature) {
            if (isset($enabled[$feature])) {
                return true;
            }
        }

        return false;
    }
}
