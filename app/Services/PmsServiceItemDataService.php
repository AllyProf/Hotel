<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelPmsService;
use App\Models\HotelPmsServiceItem;
use Illuminate\Support\Collection;

class PmsServiceItemDataService
{
    /** @return Collection<int, HotelPmsServiceItem> */
    public function itemsForService(HotelPmsService $service): Collection
    {
        return $service->catalogItems()
            ->with(['category', 'package', 'sellOptions.package'])
            ->orderBy('item_name')
            ->get();
    }

    /** @param  array<int, array<string, mixed>>  $rows */
    public function syncItems(Hotel $hotel, HotelPmsService $service, array $rows): void
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
                $item = $service->catalogItems()->whereKey((int) $row['id'])->first();
                if ($item) {
                    $item->update($attrs);
                    $keepIds[] = $item->id;

                    continue;
                }
            }

            $item = $service->catalogItems()->updateOrCreate(
                [
                    'hotel_pms_service_id' => $service->id,
                    'hotel_pms_service_category_id' => $categoryId,
                    'item_name' => $name,
                ],
                $attrs
            );
            $keepIds[] = $item->id;
        }

        if ($keepIds === []) {
            $service->catalogItems()->delete();
        } else {
            $service->catalogItems()->whereNotIn('id', $keepIds)->delete();
        }
    }

    public function ensureDefaultItemRows(HotelPmsService $service): void
    {
        if ($service->catalogItems()->exists()) {
            return;
        }

        $settings = $service->menuItemSettings();
        $items = $settings['items'] ?? [];
        $categories = $service->serviceCategories()->where('is_active', true)->get();
        $categoryId = $categories->first()?->id;
        $service->loadMissing('hotel');
        $currency = $service->displayCurrency($service->hotel);
        $packageId = $this->defaultPackageId($service);

        foreach ($items as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $service->catalogItems()->create([
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

        $service->catalogItems()
            ->whereNull('hotel_pms_service_package_id')
            ->update(['hotel_pms_service_package_id' => $packageId]);
    }

    public function defaultPackageId(HotelPmsService $service): ?int
    {
        $abbrevs = match ($service->template_key) {
            'bar' => ['gls', 'glass', 'btl', 'bottle', 'can'],
            'restaurant', 'room_service' => ['plate', 'ptn', 'portion', 'item'],
            default => [],
        };

        if ($abbrevs !== []) {
            $preferred = $service->servicePackages()
                ->where('is_active', true)
                ->whereIn('abbreviation', $abbrevs)
                ->orderByRaw("FIELD(abbreviation, '".implode("','", $abbrevs)."')")
                ->value('id');

            if ($preferred) {
                return (int) $preferred;
            }
        }

        $first = $service->servicePackages()->where('is_active', true)->orderBy('id')->value('id');

        return $first ? (int) $first : null;
    }
}
