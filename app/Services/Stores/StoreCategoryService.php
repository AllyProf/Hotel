<?php

namespace App\Services\Stores;

use App\Models\Hotel;
use App\Models\HotelPmsService;
use App\Models\HotelPmsServiceCategory;
use App\Models\HotelStoreCategory;
use App\Models\HotelStorePackage;
use App\Models\HotelStoreSubCategory;
use InvalidArgumentException;

class StoreCategoryService
{
    public function ensureDefaultCategories(Hotel $hotel): void
    {
        if (HotelStoreCategory::query()->where('hotel_id', $hotel->id)->exists()) {
            return;
        }

        $names = config('hotel_stores.default_item_categories', []);
        $sort = 0;
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            HotelStoreCategory::query()->firstOrCreate(
                ['hotel_id' => $hotel->id, 'name' => $name],
                ['sort_order' => $sort]
            );
            $sort++;
        }
    }

    /** Remove legacy PMS-linked sub-categories (no longer shown on Item Category). */
    public function purgeSyncedPmsServiceSubCategories(Hotel $hotel): void
    {
        HotelStoreSubCategory::query()
            ->where('hotel_id', $hotel->id)
            ->whereNotNull('hotel_pms_service_id')
            ->delete();
    }

    /** Drop the old "Services" store category (PMS outlets do not belong here). */
    public function removeLegacyServicesStoreCategory(Hotel $hotel): void
    {
        HotelStoreCategory::query()
            ->where('hotel_id', $hotel->id)
            ->where('name', 'Services')
            ->each(fn (HotelStoreCategory $category) => $category->delete());
    }

    public function cleanupItemCategoryLegacyData(Hotel $hotel): void
    {
        $this->purgeSyncedPmsServiceSubCategories($hotel);
        $this->removeLegacyServicesStoreCategory($hotel);
    }

    /** @param  array{search?: string}  $filters */
    public function categoriesForHotel(Hotel $hotel, array $filters = [])
    {
        $search = trim((string) ($filters['search'] ?? ''));

        $query = HotelStoreCategory::query()
            ->where('hotel_id', $hotel->id)
            ->with('pmsService')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where('name', 'like', $term);
        }

        return $query->get();
    }

    public function createCategory(Hotel $hotel, string $name, ?int $pmsServiceId = null): HotelStoreCategory
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Department name is required.');
        }

        if (HotelStoreCategory::query()->where('hotel_id', $hotel->id)->where('name', $name)->exists()) {
            throw new InvalidArgumentException('This department name already exists.');
        }

        $serviceId = null;
        if ($pmsServiceId !== null && $pmsServiceId > 0) {
            $service = $this->resolvePmsService($hotel, $pmsServiceId);
            $serviceId = $service->id;
            $this->syncServicePackages($hotel, $service);
        }

        $category = HotelStoreCategory::query()->create([
            'hotel_id' => $hotel->id,
            'hotel_pms_service_id' => $serviceId,
            'name' => $name,
        ]);

        $this->syncHotelDepartmentLink($hotel, $category);

        return $category->fresh(['pmsService', 'hotelDepartment']);
    }

    public function createSubCategory(Hotel $hotel, int $categoryId, string $name): HotelStoreSubCategory
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Sub category name is required.');
        }

        $category = HotelStoreCategory::query()
            ->where('hotel_id', $hotel->id)
            ->whereKey($categoryId)
            ->firstOrFail();

        return HotelStoreSubCategory::query()->create([
            'hotel_id' => $hotel->id,
            'hotel_store_category_id' => $category->id,
            'name' => $name,
        ]);
    }

    public function updateCategory(Hotel $hotel, HotelStoreCategory $category, string $name, ?int $pmsServiceId = null): HotelStoreCategory
    {
        abort_unless((int) $category->hotel_id === (int) $hotel->id, 404);

        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Department name is required.');
        }

        $payload = ['name' => $name];
        if ($pmsServiceId !== null && $pmsServiceId > 0) {
            $service = $this->resolvePmsService($hotel, $pmsServiceId);
            $payload['hotel_pms_service_id'] = $service->id;
            $this->syncServicePackages($hotel, $service);
        }

        $category->update($payload);
        $this->syncHotelDepartmentLink($hotel, $category->fresh());

        return $category->fresh(['pmsService', 'hotelDepartment']);
    }

    private function syncHotelDepartmentLink(Hotel $hotel, HotelStoreCategory $category): void
    {
        /** @var StoreMasterItemService $masterItems */
        $masterItems = app(StoreMasterItemService::class);
        $department = $masterItems->hotelDepartmentForStoreCategory($hotel, $category);
        if (! $department) {
            return;
        }

        $category->update(['hotel_department_id' => $department->id]);
    }

    /** @return array{linked: bool, pms_categories: list<array{id: int, name: string}>, packages: list<array{id: int, label: string, unit_label: string}>} */
    public function catalogOptionsForCategory(Hotel $hotel, HotelStoreCategory $category): array
    {
        abort_unless((int) $category->hotel_id === (int) $hotel->id, 404);

        $service = $category->pmsService;
        if (! $service) {
            return [
                'linked' => false,
                'pms_categories' => [],
                'packages' => [],
            ];
        }

        $this->syncServicePackages($hotel, $service);

        $pmsCategories = HotelPmsServiceCategory::query()
            ->where('hotel_id', $hotel->id)
            ->where('hotel_pms_service_id', $service->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (HotelPmsServiceCategory $row) => [
                'id' => $row->id,
                'name' => $row->name,
            ])
            ->values()
            ->all();

        $pmsPackageIds = $service->servicePackages()
            ->where('is_active', true)
            ->pluck('id');

        $packages = HotelStorePackage::query()
            ->where('hotel_id', $hotel->id)
            ->whereIn('hotel_pms_service_package_id', $pmsPackageIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (HotelStorePackage $pkg) => [
                'id' => $pkg->id,
                'label' => $pkg->displayLabel(),
                'unit_label' => $pkg->unitLabel(),
            ])
            ->values()
            ->all();

        return [
            'linked' => true,
            'pms_categories' => $pmsCategories,
            'packages' => $packages,
        ];
    }

    public function syncServicePackages(Hotel $hotel, HotelPmsService $service): void
    {
        abort_unless((int) $service->hotel_id === (int) $hotel->id, 404);

        $service->loadMissing('servicePackages');

        foreach ($service->servicePackages->where('is_active', true) as $pmsPackage) {
            $name = trim((string) $pmsPackage->name);
            if ($name === '') {
                continue;
            }

            $linked = HotelStorePackage::query()
                ->where('hotel_id', $hotel->id)
                ->where('hotel_pms_service_package_id', $pmsPackage->id)
                ->first();

            if ($linked) {
                $linked->update([
                    'name' => $name,
                    'abbreviation' => $pmsPackage->abbreviation,
                    'sort_order' => (int) $pmsPackage->sort_order,
                ]);

                continue;
            }

            $byName = HotelStorePackage::query()
                ->where('hotel_id', $hotel->id)
                ->where('name', $name)
                ->first();

            if ($byName) {
                $existingLink = $byName->hotel_pms_service_package_id;
                if ($existingLink === null || (int) $existingLink === (int) $pmsPackage->id) {
                    $byName->update([
                        'hotel_pms_service_package_id' => $pmsPackage->id,
                        'abbreviation' => $pmsPackage->abbreviation,
                        'sort_order' => (int) $pmsPackage->sort_order,
                    ]);

                    continue;
                }

                $name = $name.' · '.$service->name;
            }

            HotelStorePackage::query()->create([
                'hotel_id' => $hotel->id,
                'hotel_pms_service_package_id' => $pmsPackage->id,
                'name' => $name,
                'abbreviation' => $pmsPackage->abbreviation,
                'sort_order' => (int) $pmsPackage->sort_order,
            ]);
        }
    }

    private function resolvePmsService(Hotel $hotel, int $pmsServiceId): HotelPmsService
    {
        if ($pmsServiceId <= 0) {
            throw new InvalidArgumentException('Select the service this department offers.');
        }

        return HotelPmsService::query()
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->whereKey($pmsServiceId)
            ->firstOrFail();
    }

    public function deleteCategory(Hotel $hotel, HotelStoreCategory $category): void
    {
        abort_unless((int) $category->hotel_id === (int) $hotel->id, 404);
        $category->delete();
    }

    public function deleteSubCategory(Hotel $hotel, HotelStoreSubCategory $subCategory): void
    {
        abort_unless((int) $subCategory->hotel_id === (int) $hotel->id, 404);

        $subCategory->delete();
    }
}
