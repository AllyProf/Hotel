<?php

namespace App\Services\Bar;

use App\Models\Hotel;
use App\Models\HotelPmsService;
use App\Models\HotelPmsServiceItem;
use Illuminate\Support\Collection;
use App\Services\PmsServiceCategoryDataService;
use App\Services\PmsServiceItemDataService;
use App\Services\PmsServicePackageDataService;

class BarCatalogService
{
    public function __construct(
        private PmsServiceCategoryDataService $categories,
        private PmsServicePackageDataService $packages,
        private PmsServiceItemDataService $items,
    ) {}

    public function barServiceForHotel(Hotel $hotel): ?HotelPmsService
    {
        return HotelPmsService::query()
            ->where('hotel_id', $hotel->id)
            ->where('template_key', 'bar')
            ->where('is_active', true)
            ->first();
    }

    /** @return array<string, mixed> */
    public function uiConfig(): array
    {
        return config('hotel_bar.ui', [
            'title' => 'Bar',
            'items_title' => 'Register items',
        ]);
    }

    public function ensureCatalogReady(Hotel $hotel, HotelPmsService $service): void
    {
        if ($service->serviceCategories()->count() === 0
            && count($this->categories->categoryTemplatesForService($service)) > 0) {
            $this->categories->importAllTemplates($hotel, $service);
        }

        if ($service->servicePackages()->count() === 0
            && count($this->packages->packageTemplatesForService($service)) > 0) {
            $this->packages->importAllTemplates($hotel, $service);
        }

        $service->load(['serviceCategories', 'servicePackages']);
    }

    /** @return Collection<int, HotelPmsServiceItem> */
    public function lowStockItems(HotelPmsService $service): Collection
    {
        return $service->catalogItems()
            ->with(['category', 'package'])
            ->where('is_active', true)
            ->whereNotNull('reorder_level')
            ->whereRaw('COALESCE(stock_on_hand, 0) <= reorder_level')
            ->orderBy('item_name')
            ->get();
    }
}
