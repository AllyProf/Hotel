<?php

namespace App\Services\Laundry;

use App\Models\Hotel;
use App\Models\HotelPmsLaundryPrice;
use App\Models\HotelPmsService;
use Illuminate\Support\Collection;

class LaundryPriceDataService
{
    /** @return Collection<int, HotelPmsLaundryPrice> */
    public function pricesForService(HotelPmsService $service): Collection
    {
        return $service->laundryPrices()
            ->with(['category', 'package'])
            ->get();
    }

    /** @param  array<int, array<string, mixed>>  $rows */
    public function syncPrices(Hotel $hotel, HotelPmsService $service, array $rows): void
    {
        $keepIds = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row['item_name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $categoryId = ! empty($row['category_id']) ? (int) $row['category_id'] : null;
            $packageId = ! empty($row['package_id']) ? (int) $row['package_id'] : null;
            $currency = strtoupper(trim((string) ($row['currency'] ?? $service->displayCurrency($hotel))));

            $attrs = [
                'hotel_id' => $hotel->id,
                'hotel_pms_service_id' => $service->id,
                'hotel_pms_service_category_id' => $categoryId,
                'item_name' => $name,
                'hotel_pms_service_package_id' => $packageId,
                'unit_price' => max(0, (float) ($row['unit_price'] ?? 0)),
                'currency' => $currency !== '' ? $currency : $service->displayCurrency($hotel),
                'is_active' => ! empty($row['is_active']),
            ];

            if (! empty($row['id'])) {
                $price = $service->laundryPrices()->whereKey((int) $row['id'])->first();
                if ($price) {
                    $price->update($attrs);
                    $keepIds[] = $price->id;

                    continue;
                }
            }

            $price = $service->laundryPrices()->updateOrCreate(
                [
                    'hotel_pms_service_id' => $service->id,
                    'hotel_pms_service_category_id' => $categoryId,
                    'item_name' => $name,
                ],
                $attrs
            );
            $keepIds[] = $price->id;
        }

        if ($keepIds === []) {
            $service->laundryPrices()->delete();
        } else {
            $service->laundryPrices()->whereNotIn('id', $keepIds)->delete();
        }
    }

    public function ensureDefaultPriceRows(HotelPmsService $service): void
    {
        if ($service->laundryPrices()->exists()) {
            return;
        }

        $settings = $service->laundrySettings();
        $items = $settings['items'] ?? [];
        $categories = $service->serviceCategories()->where('is_active', true)->get();
        $categoryId = $categories->first()?->id;
        $package = $service->servicePackages()->where('is_active', true)->first();
        $service->loadMissing('hotel');
        $currency = $service->displayCurrency($service->hotel);

        $packageId = $this->defaultPackageId($service) ?? $package?->id;

        foreach ($items as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $service->laundryPrices()->create([
                'hotel_id' => $service->hotel_id,
                'hotel_pms_service_category_id' => $categoryId,
                'item_name' => $name,
                'hotel_pms_service_package_id' => $packageId,
                'unit_price' => 0,
                'currency' => $currency,
                'is_active' => true,
            ]);
        }
    }

    public function backfillMissingPackages(HotelPmsService $service): void
    {
        $packageId = $this->defaultPackageId($service);
        if (! $packageId) {
            return;
        }

        $service->laundryPrices()
            ->whereNull('hotel_pms_service_package_id')
            ->update(['hotel_pms_service_package_id' => $packageId]);
    }

    public function defaultPackageId(HotelPmsService $service): ?int
    {
        $preferred = $service->servicePackages()
            ->where('is_active', true)
            ->whereIn('abbreviation', ['pc', 'piece', 'kg'])
            ->orderByRaw("CASE abbreviation WHEN 'pc' THEN 0 WHEN 'piece' THEN 1 WHEN 'kg' THEN 2 ELSE 3 END")
            ->value('id');

        if ($preferred) {
            return (int) $preferred;
        }

        $first = $service->servicePackages()->where('is_active', true)->orderBy('id')->value('id');

        return $first ? (int) $first : null;
    }
}
