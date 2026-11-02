<?php

namespace App\Services\Stores;

use App\Models\Hotel;
use App\Models\HotelStorePackage;
use App\Models\HotelStoreTaxGroup;
use InvalidArgumentException;

class StoreCatalogService
{
    public function ensureDefaults(Hotel $hotel): void
    {
        $this->ensureDefaultTaxGroups($hotel);
        $this->ensureDefaultPackages($hotel);
    }

    public function ensureDefaultTaxGroups(Hotel $hotel): void
    {
        if (HotelStoreTaxGroup::query()->where('hotel_id', $hotel->id)->exists()) {
            return;
        }

        $sort = 0;
        foreach (config('hotel_stores.default_tax_groups', []) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            HotelStoreTaxGroup::query()->create([
                'hotel_id' => $hotel->id,
                'name' => $name,
                'rate_percent' => isset($row['rate_percent']) ? (float) $row['rate_percent'] : null,
                'sort_order' => $sort++,
            ]);
        }
    }

    public function ensureDefaultPackages(Hotel $hotel): void
    {
        if (HotelStorePackage::query()->where('hotel_id', $hotel->id)->exists()) {
            return;
        }

        $sort = 0;
        foreach (config('hotel_stores.default_packages', []) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            HotelStorePackage::query()->create([
                'hotel_id' => $hotel->id,
                'name' => $name,
                'abbreviation' => trim((string) ($row['abbreviation'] ?? '')) ?: null,
                'sort_order' => $sort++,
            ]);
        }
    }

    public function taxGroupsForHotel(Hotel $hotel)
    {
        return HotelStoreTaxGroup::query()
            ->where('hotel_id', $hotel->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function packagesForHotel(Hotel $hotel)
    {
        return HotelStorePackage::query()
            ->where('hotel_id', $hotel->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function createTaxGroup(Hotel $hotel, string $name, ?float $ratePercent = null): HotelStoreTaxGroup
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Tax group name is required.');
        }

        if (HotelStoreTaxGroup::query()->where('hotel_id', $hotel->id)->where('name', $name)->exists()) {
            throw new InvalidArgumentException('This tax group already exists.');
        }

        return HotelStoreTaxGroup::query()->create([
            'hotel_id' => $hotel->id,
            'name' => $name,
            'rate_percent' => $ratePercent,
        ]);
    }

    public function updateTaxGroup(Hotel $hotel, HotelStoreTaxGroup $group, string $name, ?float $ratePercent): HotelStoreTaxGroup
    {
        abort_unless((int) $group->hotel_id === (int) $hotel->id, 404);
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Tax group name is required.');
        }

        $group->update([
            'name' => $name,
            'rate_percent' => $ratePercent,
        ]);

        return $group->fresh();
    }

    public function deleteTaxGroup(Hotel $hotel, HotelStoreTaxGroup $group): void
    {
        abort_unless((int) $group->hotel_id === (int) $hotel->id, 404);
        $group->delete();
    }

    public function createPackage(Hotel $hotel, string $name, ?string $abbreviation = null): HotelStorePackage
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Unit name is required.');
        }

        if (HotelStorePackage::query()->where('hotel_id', $hotel->id)->where('name', $name)->exists()) {
            throw new InvalidArgumentException('This unit already exists.');
        }

        return HotelStorePackage::query()->create([
            'hotel_id' => $hotel->id,
            'name' => $name,
            'abbreviation' => trim((string) $abbreviation) ?: null,
        ]);
    }

    public function updatePackage(Hotel $hotel, HotelStorePackage $package, string $name, ?string $abbreviation): HotelStorePackage
    {
        abort_unless((int) $package->hotel_id === (int) $hotel->id, 404);
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Unit name is required.');
        }

        $package->update([
            'name' => $name,
            'abbreviation' => trim((string) $abbreviation) ?: null,
        ]);

        return $package->fresh();
    }

    public function deletePackage(Hotel $hotel, HotelStorePackage $package): void
    {
        abort_unless((int) $package->hotel_id === (int) $hotel->id, 404);
        $package->delete();
    }
}
