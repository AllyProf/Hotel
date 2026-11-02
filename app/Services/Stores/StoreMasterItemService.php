<?php

namespace App\Services\Stores;

use App\Models\Hotel;
use App\Models\HotelDepartment;
use App\Models\HotelPmsService;
use App\Models\HotelPmsServiceCategory;
use App\Models\HotelPmsServiceItem;
use App\Models\HotelPmsServiceItemSellOption;
use App\Models\HotelStoreCategory;
use App\Models\HotelStoreIndent;
use App\Models\HotelStoreIndentItem;
use App\Models\HotelStoreMasterItem;
use App\Services\Bar\BarPurchaseListService;
use App\Support\DepartmentProcurement;
use Illuminate\Support\Collection;
use App\Models\HotelStoreMasterItemSellOption;
use App\Models\HotelStorePackage;
use App\Models\HotelStoreTaxGroup;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class StoreMasterItemService
{
    public function __construct(
        private StoreStockService $storeStock,
    ) {}

    /** @return Collection<int, string> */
    public function purchaseListItemNames(Hotel $hotel, ?int $hotelDepartmentId, string $workflowKey): Collection
    {
        $config = DepartmentProcurement::workflow($workflowKey);
        $templateKey = $config['template_key'] ?? null;
        $categoryIds = $this->storeCategoryIdsForPurchaseList($hotel, $hotelDepartmentId, $workflowKey);

        $query = HotelStoreMasterItem::query()
            ->where('hotel_id', $hotel->id);

        if ($categoryIds !== []) {
            $query->whereIn('hotel_store_category_id', $categoryIds);
        } elseif (is_string($templateKey) && $templateKey !== '') {
            $query->whereHas('category.pmsService', fn (Builder $q) => $q->where('template_key', $templateKey));
        } elseif ($hotelDepartmentId) {
            return collect();
        }

        return $query->orderBy('name')->pluck('name')->unique()->values();
    }

    /** @return list<int> */
    public function storeCategoryIdsForPurchaseList(Hotel $hotel, ?int $hotelDepartmentId, string $workflowKey): array
    {
        $config = DepartmentProcurement::workflow($workflowKey);
        $ids = collect();

        if ($hotelDepartmentId) {
            $exactCategoryId = $this->storeCategoryIdForHotelDepartment($hotel, $hotelDepartmentId);
            if ($exactCategoryId !== null) {
                return [$exactCategoryId];
            }

            $dept = HotelDepartment::query()
                ->where('hotel_id', $hotel->id)
                ->whereKey($hotelDepartmentId)
                ->first();

            if ($dept) {
                $deptName = strtolower(trim($dept->name));
                $patterns = collect($config['department_name_patterns'] ?? [])
                    ->map(fn ($p) => strtolower(str_replace('%', '', (string) $p)))
                    ->filter();

                $categories = HotelStoreCategory::query()
                    ->where('hotel_id', $hotel->id)
                    ->get();

                foreach ($categories as $category) {
                    $catName = strtolower(trim($category->name));
                    if ($catName === $deptName || str_contains($catName, $deptName) || str_contains($deptName, $catName)) {
                        $ids->push($category->id);

                        continue;
                    }
                    foreach ($patterns as $needle) {
                        if ($needle !== '' && (str_contains($catName, $needle) || str_contains($deptName, $needle))) {
                            $ids->push($category->id);
                            break;
                        }
                    }
                }
            }
        }

        $templateKey = $config['template_key'] ?? null;
        if (is_string($templateKey) && $templateKey !== '' && $ids->isEmpty()) {
            $serviceIds = HotelPmsService::query()
                ->where('hotel_id', $hotel->id)
                ->where('template_key', $templateKey)
                ->pluck('id');

            if ($serviceIds->isNotEmpty()) {
                $ids = $ids->merge(
                    HotelStoreCategory::query()
                        ->where('hotel_id', $hotel->id)
                        ->whereIn('hotel_pms_service_id', $serviceIds)
                        ->pluck('id')
                );
            }
        }

        return $ids->unique()->values()->all();
    }

    /**
     * Limit a PMS service catalog (e.g. Bar register) to items for one HR department / store category.
     *
     * @param  Collection<int, HotelPmsServiceItem>  $items
     * @return Collection<int, HotelPmsServiceItem>
     */
    public function filterServiceCatalogForDepartment(
        Hotel $hotel,
        HotelPmsService $service,
        Collection $items,
        int $hotelDepartmentId,
        string $workflowKey,
    ): Collection {
        if ($hotelDepartmentId <= 0) {
            return $items;
        }

        $categoryIds = $this->storeCategoryIdsForPurchaseList($hotel, $hotelDepartmentId, $workflowKey);
        if ($categoryIds === []) {
            return $items;
        }

        $allowedNames = HotelStoreMasterItem::query()
            ->where('hotel_id', $hotel->id)
            ->whereIn('hotel_store_category_id', $categoryIds)
            ->pluck('name')
            ->map(fn (string $name) => mb_strtolower(trim($name)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($allowedNames !== []) {
            $allowed = array_fill_keys($allowedNames, true);

            return $items->filter(function (HotelPmsServiceItem $item) use ($allowed) {
                $key = mb_strtolower(trim($item->item_name));

                return isset($allowed[$key]);
            })->values();
        }

        $storeCategoryNames = HotelStoreCategory::query()
            ->where('hotel_id', $hotel->id)
            ->whereIn('id', $categoryIds)
            ->pluck('name')
            ->map(fn ($n) => mb_strtolower(trim((string) $n)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($storeCategoryNames === []) {
            return $items;
        }

        $pmsCategoryIds = $service->serviceCategories()
            ->where('is_active', true)
            ->get()
            ->filter(function (HotelPmsServiceCategory $cat) use ($storeCategoryNames) {
                $name = mb_strtolower(trim($cat->name));
                foreach ($storeCategoryNames as $storeName) {
                    if ($name === $storeName || str_contains($name, $storeName) || str_contains($storeName, $name)) {
                        return true;
                    }
                }

                return false;
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($pmsCategoryIds === []) {
            return collect();
        }

        return $items->filter(
            fn (HotelPmsServiceItem $item) => in_array((int) $item->hotel_pms_service_category_id, $pmsCategoryIds, true)
        )->values();
    }

    /** @return list<int> */
    public function storeCategoryIdsForHotelDepartment(Hotel $hotel, int $hotelDepartmentId): array
    {
        $categoryId = $this->storeCategoryIdForHotelDepartment($hotel, $hotelDepartmentId);

        return $categoryId !== null ? [$categoryId] : [];
    }

    public function storeCategoryIdForHotelDepartment(Hotel $hotel, int $hotelDepartmentId): ?int
    {
        if ($hotelDepartmentId <= 0) {
            return null;
        }

        $linked = HotelStoreCategory::query()
            ->where('hotel_id', $hotel->id)
            ->where('hotel_department_id', $hotelDepartmentId)
            ->value('id');
        if ($linked) {
            return (int) $linked;
        }

        $dept = HotelDepartment::query()
            ->where('hotel_id', $hotel->id)
            ->whereKey($hotelDepartmentId)
            ->first();

        if (! $dept) {
            return null;
        }

        $deptName = strtolower(trim($dept->name));
        if ($deptName === '') {
            return null;
        }

        $categories = HotelStoreCategory::query()
            ->where('hotel_id', $hotel->id)
            ->get();

        $category = $categories->first(fn (HotelStoreCategory $row) => strtolower(trim($row->name)) === $deptName);
        if ($category) {
            return (int) $category->id;
        }

        $category = $categories->first(function (HotelStoreCategory $row) use ($deptName) {
            $catName = strtolower(trim($row->name));
            if ($catName === '' || $deptName === '') {
                return false;
            }

            return str_contains($deptName, $catName) || str_contains($catName, $deptName);
        });

        return $category ? (int) $category->id : null;
    }

    /**
     * Master items for a department purchase list, with receive / usage unit options.
     *
     * @return list<array<string, mixed>>
     */
    public function purchaseListCatalogForDepartment(Hotel $hotel, int $hotelDepartmentId, string $workflowKey): array
    {
        if ($hotelDepartmentId <= 0) {
            return [];
        }

        $categoryIds = [];
        $exactCategoryId = $this->storeCategoryIdForHotelDepartment($hotel, $hotelDepartmentId);
        if ($exactCategoryId !== null) {
            $categoryIds = [$exactCategoryId];
        } else {
            $categoryIds = $this->storeCategoryIdsForPurchaseList($hotel, $hotelDepartmentId, $workflowKey);
        }

        if ($categoryIds === []) {
            return [];
        }

        return HotelStoreMasterItem::query()
            ->where('hotel_id', $hotel->id)
            ->whereIn('hotel_store_category_id', $categoryIds)
            ->with(['storePackage', 'sellOptions.storePackage', 'category', 'subCategory'])
            ->orderBy('name')
            ->get()
            ->map(fn (HotelStoreMasterItem $item) => $this->mapMasterItemIndentPayload($item))
            ->values()
            ->all();
    }

    public function resolveHotelDepartmentIdForStoreCategory(Hotel $hotel, int $storeCategoryId): int
    {
        $category = HotelStoreCategory::query()
            ->where('hotel_id', $hotel->id)
            ->whereKey($storeCategoryId)
            ->first();

        if (! $category) {
            throw new InvalidArgumentException('Select a valid department.');
        }

        if ((int) ($category->hotel_department_id ?? 0) > 0) {
            return (int) $category->hotel_department_id;
        }

        $dept = $this->hotelDepartmentForStoreCategory($hotel, $category);
        if (! $dept) {
            throw new InvalidArgumentException(
                'Link this store department to HR: add a hotel department whose name matches "'.$category->name.'" (Stores → Settings → Department).'
            );
        }

        return (int) $dept->id;
    }

    public function hotelDepartmentForStoreCategory(Hotel $hotel, HotelStoreCategory $category): ?HotelDepartment
    {
        $dept = HotelDepartment::query()
            ->where('hotel_id', $hotel->id)
            ->where('name', $category->name)
            ->first();
        if ($dept) {
            return $dept;
        }

        $catName = strtolower(trim($category->name));
        if ($catName === '') {
            return null;
        }

        $departments = HotelDepartment::query()
            ->where('hotel_id', $hotel->id)
            ->get();

        $dept = $departments->first(fn (HotelDepartment $row) => strtolower(trim($row->name)) === $catName);
        if ($dept) {
            return $dept;
        }

        return $departments->first(function (HotelDepartment $row) use ($catName) {
            $deptName = strtolower(trim($row->name));
            if ($deptName === '') {
                return false;
            }

            return str_contains($catName, $deptName) || str_contains($deptName, $catName);
        });
    }

    public function requesterStoreCategoryIdForIndent(Hotel $hotel, HotelStoreIndent $indent): ?int
    {
        if ((int) ($indent->hotel_store_category_id ?? 0) > 0) {
            return (int) $indent->hotel_store_category_id;
        }

        return $this->storeCategoryIdForHotelDepartment($hotel, (int) $indent->hotel_department_id);
    }

    public function requesterLabelForIndent(Hotel $hotel, HotelStoreIndent $indent): string
    {
        $indent->loadMissing(['department', 'storeCategory']);
        if ($indent->storeCategory?->name) {
            return $indent->storeCategory->name;
        }

        $categoryId = $this->requesterStoreCategoryIdForIndent($hotel, $indent);
        if ($categoryId) {
            $name = HotelStoreCategory::query()->whereKey($categoryId)->value('name');
            if ($name) {
                return (string) $name;
            }
        }

        return $indent->department?->name ?? '—';
    }

    /**
     * @return array{price: float, unit: string}|null
     */
    public function indentLineSellPrice(Hotel $hotel, HotelStoreIndentItem $line): ?array
    {
        $masterId = (int) ($line->hotel_store_master_item_id ?? 0);
        if ($masterId <= 0) {
            return null;
        }

        $master = HotelStoreMasterItem::query()
            ->where('hotel_id', $hotel->id)
            ->whereKey($masterId)
            ->first();
        if (! $master || ! $master->isForSale()) {
            return null;
        }

        $payload = $this->mapMasterItemIndentPayload($master);
        $lineUnit = trim((string) ($line->unit ?? ''));
        $options = $payload['unit_options'] ?? [];

        foreach ($options as $option) {
            $unit = trim((string) ($option['unit'] ?? ''));
            if ($lineUnit !== '' && strcasecmp($lineUnit, $unit) === 0) {
                $price = (float) ($option['unit_price'] ?? 0);
                if ($price > 0) {
                    return ['price' => round($price, 2), 'unit' => $unit];
                }
            }
        }

        $primary = $payload['primary_sell_price'] ?? null;
        if ($primary !== null && (float) $primary > 0) {
            return [
                'price' => round((float) $primary, 2),
                'unit' => (string) ($payload['primary_sell_unit'] ?? $lineUnit),
            ];
        }

        return null;
    }

    /**
     * @param  iterable<int, HotelStoreCategory|int>  $storeCategories
     * @return array<int, list<array<string, mixed>>>
     */
    public function indentCatalogPayloadByStoreCategory(Hotel $hotel, iterable $storeCategories): array
    {
        $byCategory = [];
        foreach ($storeCategories as $category) {
            $categoryId = is_int($category) ? $category : (int) $category->id;
            $byCategory[$categoryId] = $this->indentCatalogForStoreCategory($hotel, $categoryId);
        }

        return $byCategory;
    }

    /** @return list<array<string, mixed>> */
    public function indentCatalogForStoreCategory(Hotel $hotel, int $storeCategoryId): array
    {
        if ($storeCategoryId <= 0) {
            return [];
        }

        return HotelStoreMasterItem::query()
            ->where('hotel_id', $hotel->id)
            ->where('hotel_store_category_id', $storeCategoryId)
            ->with(['storePackage', 'sellOptions.storePackage', 'category', 'subCategory'])
            ->orderBy('name')
            ->get()
            ->map(fn (HotelStoreMasterItem $item) => $this->mapMasterItemIndentPayload($item))
            ->values()
            ->all();
    }

    /**
     * @param  iterable<int, HotelDepartment|int>  $departments
     * @return array<int, list<array<string, mixed>>>
     */
    public function indentCatalogPayloadByDepartment(Hotel $hotel, iterable $departments): array
    {
        $byDept = [];
        foreach ($departments as $department) {
            $departmentId = is_int($department) ? $department : (int) $department->id;
            $byDept[$departmentId] = $this->indentCatalogForDepartment($hotel, $departmentId);
        }

        return $byDept;
    }

    /** @return list<array<string, mixed>> */
    public function indentCatalogForDepartment(Hotel $hotel, int $hotelDepartmentId): array
    {
        if ($hotelDepartmentId <= 0) {
            return [];
        }

        $storeCategoryId = $this->storeCategoryIdForHotelDepartment($hotel, $hotelDepartmentId);
        if ($storeCategoryId === null) {
            return [];
        }

        return $this->indentCatalogForStoreCategory($hotel, $storeCategoryId);
    }

    /** @return array<string, mixed> */
    private function mapMasterItemIndentPayload(HotelStoreMasterItem $item): array
    {
        $item->loadMissing(['storePackage', 'sellOptions.storePackage', 'category']);

        $unitOptions = $item->indentUnitOptions();
        $primarySell = null;
        if ($item->isForSale()) {
            $primarySell = $this->primarySellPriceFromMasterItem($item);
            $pmsItem = $this->pmsCatalogItemForMasterItem($item);
            if ($pmsItem) {
                $unitOptions = $this->attachSellPricesToIndentUnitOptions($unitOptions, $item, $pmsItem);
                $primarySell ??= $this->primarySellPriceFromPms($pmsItem);
            }
        }

        return [
            'id' => $item->id,
            'name' => $item->name,
            'rate' => (float) $item->rate,
            'usage_type' => (string) ($item->usage_type ?? HotelStoreMasterItem::USAGE_INTERNAL),
            'primary_sell_price' => $primarySell['price'] ?? null,
            'primary_sell_unit' => $primarySell['unit'] ?? null,
            'category_id' => $item->hotel_store_category_id,
            'sub_category_id' => $item->hotel_store_sub_category_id,
            'unit' => $item->defaultIndentUnit(),
            'stock_unit' => $item->storePackage?->unitLabel() ?: $item->defaultIndentUnit(),
            'unit_options' => $unitOptions,
            'pieces_per_receive_unit' => max(0.01, (float) $item->pieces_per_receive_unit),
            'stock' => (float) $item->stock,
            'inventoried' => (bool) $item->inventoried,
        ];
    }

    private function pmsCatalogItemForMasterItem(HotelStoreMasterItem $item): ?HotelPmsServiceItem
    {
        $item->loadMissing('category');

        return $this->pmsCatalogItemByName(
            $item->hotel,
            (int) ($item->category?->hotel_pms_service_id ?? 0),
            (string) $item->name,
        );
    }

    private function pmsCatalogItemByName(Hotel $hotel, int $pmsServiceId, string $itemName): ?HotelPmsServiceItem
    {
        $nameKey = mb_strtolower(trim($itemName));
        if ($nameKey === '') {
            return null;
        }

        if ($pmsServiceId > 0) {
            $match = HotelPmsServiceItem::query()
                ->where('hotel_id', $hotel->id)
                ->where('hotel_pms_service_id', $pmsServiceId)
                ->whereRaw('LOWER(TRIM(item_name)) = ?', [$nameKey])
                ->with(['sellOptions.package'])
                ->first();
            if ($match) {
                return $match;
            }
        }

        return HotelPmsServiceItem::query()
            ->where('hotel_id', $hotel->id)
            ->whereRaw('LOWER(TRIM(item_name)) = ?', [$nameKey])
            ->with(['sellOptions.package'])
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Bar / PMS catalog sell prices for the master item modal (by store package id).
     *
     * @return array{
     *     by_package: array<int, array{unit_price: float, pieces_per_sell_unit: float}>,
     *     options: list<array{package_id: int, pieces_per_sell_unit: float, unit_price: float}>,
     *     source: string|null
     * }
     */
    /**
     * @param  list<array{package_id?: int|string, pieces_per_sell_unit?: float|string}>  $sellUnits
     * @return array{
     *     by_package: array<int, array{unit_price: float, pieces_per_sell_unit: float}>,
     *     options: list<array{package_id: int, pieces_per_sell_unit: float, unit_price: float}>,
     *     source: string|null,
     *     primary: array{package_id: int, pieces_per_sell_unit: float, unit_price: float}|null
     * }
     */
    public function recommendedSellPricesPayload(
        Hotel $hotel,
        int $categoryId,
        string $itemName,
        ?int $masterItemId = null,
        ?float $buyingRate = null,
        ?float $piecesPerReceiveUnit = null,
        array $sellUnits = [],
    ): array {
        $empty = ['by_package' => [], 'options' => [], 'source' => null, 'primary' => null];
        $nameKey = mb_strtolower(trim($itemName));
        if ($nameKey === '' || $categoryId <= 0) {
            return $empty;
        }

        $category = HotelStoreCategory::query()
            ->where('hotel_id', $hotel->id)
            ->whereKey($categoryId)
            ->first();
        if (! $category) {
            return $empty;
        }

        $masterItem = null;
        if ($masterItemId !== null && $masterItemId > 0) {
            $masterItem = HotelStoreMasterItem::query()
                ->where('hotel_id', $hotel->id)
                ->whereKey($masterItemId)
                ->with(['sellOptions'])
                ->first();
        }

        if ($masterItem) {
            if ($buyingRate === null) {
                $buyingRate = (float) $masterItem->rate;
            }
            if ($piecesPerReceiveUnit === null) {
                $piecesPerReceiveUnit = max(0.01, (float) ($masterItem->pieces_per_receive_unit ?? 1));
            }
            if ($sellUnits === []) {
                $sellUnits = $masterItem->sellOptions->map(fn (HotelStoreMasterItemSellOption $opt) => [
                    'package_id' => (int) $opt->hotel_store_package_id,
                    'pieces_per_sell_unit' => (float) $opt->pieces_per_sell_unit,
                ])->values()->all();
            }
        }

        $pmsPayload = $this->recommendedSellPricesFromPmsCatalog(
            $hotel,
            (int) ($category->hotel_pms_service_id ?? 0),
            $itemName,
        );

        $costPayload = $this->recommendedSellPricesFromBuyingCost(
            (float) ($buyingRate ?? 0),
            (float) ($piecesPerReceiveUnit ?? 0),
            $sellUnits,
        );

        $byPackage = $costPayload['by_package'];
        foreach ($pmsPayload['by_package'] as $packageId => $row) {
            $byPackage[(int) $packageId] = $row;
        }

        $options = [];
        $seen = [];
        foreach (array_merge($pmsPayload['options'], $costPayload['options']) as $row) {
            $pkgId = (int) ($row['package_id'] ?? 0);
            $key = $pkgId.'|'.(string) ($row['pieces_per_sell_unit'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            if ($pkgId > 0 && isset($pmsPayload['by_package'][$pkgId])) {
                $options[] = [
                    'package_id' => $pkgId,
                    'pieces_per_sell_unit' => (float) $pmsPayload['by_package'][$pkgId]['pieces_per_sell_unit'],
                    'unit_price' => (float) $pmsPayload['by_package'][$pkgId]['unit_price'],
                ];

                continue;
            }
            $options[] = [
                'package_id' => $pkgId,
                'pieces_per_sell_unit' => (float) ($row['pieces_per_sell_unit'] ?? 1),
                'unit_price' => (float) ($row['unit_price'] ?? 0),
            ];
        }

        usort($options, fn (array $a, array $b) => ($a['pieces_per_sell_unit'] <=> $b['pieces_per_sell_unit']));

        $source = null;
        if ($pmsPayload['source'] !== null && $costPayload['source'] !== null) {
            $source = $pmsPayload['source'].' · cost';
        } elseif ($pmsPayload['source'] !== null) {
            $source = $pmsPayload['source'];
        } elseif ($costPayload['source'] !== null) {
            $source = $costPayload['source'];
        }

        $primary = $options[0] ?? null;

        return [
            'by_package' => $byPackage,
            'options' => $options,
            'source' => $source,
            'primary' => $primary,
        ];
    }

    /** @return array{by_package: array<int, array{unit_price: float, pieces_per_sell_unit: float}>, options: list<array{package_id: int, pieces_per_sell_unit: float, unit_price: float}>, source: string|null} */
    private function recommendedSellPricesFromPmsCatalog(Hotel $hotel, int $pmsServiceId, string $itemName): array
    {
        $empty = ['by_package' => [], 'options' => [], 'source' => null];
        $pmsItem = $this->pmsCatalogItemByName($hotel, $pmsServiceId, $itemName);
        if (! $pmsItem) {
            return $empty;
        }

        $itemUnitPrice = $pmsItem->unit_price !== null ? (float) $pmsItem->unit_price : 0.0;
        $byPackage = [];
        $options = [];

        foreach ($pmsItem->sellOptions as $opt) {
            $price = (float) ($opt->unit_price ?? 0);
            if ($price <= 0) {
                $price = $itemUnitPrice;
            }
            if ($price <= 0) {
                continue;
            }

            $storePkg = $this->storePackageForPmsSellOption($hotel, $opt);
            if (! $storePkg) {
                continue;
            }

            $row = [
                'unit_price' => round($price, 2),
                'pieces_per_sell_unit' => max(0.01, (float) ($opt->pieces_per_sell_unit ?? 1)),
            ];
            $byPackage[(int) $storePkg->id] = $row;
            $options[] = [
                'package_id' => (int) $storePkg->id,
                'pieces_per_sell_unit' => $row['pieces_per_sell_unit'],
                'unit_price' => $row['unit_price'],
            ];
        }

        if ($options === [] && $itemUnitPrice > 0) {
            $options[] = [
                'package_id' => 0,
                'pieces_per_sell_unit' => 1.0,
                'unit_price' => round($itemUnitPrice, 2),
            ];
        }

        usort($options, fn (array $a, array $b) => ($a['pieces_per_sell_unit'] <=> $b['pieces_per_sell_unit']));

        return [
            'by_package' => $byPackage,
            'options' => $options,
            'source' => $pmsItem->item_name,
        ];
    }

    /**
     * Break-even sell price from buying price and receive / sale packaging.
     *
     * @param  list<array{package_id?: int|string, pieces_per_sell_unit?: float|string}>  $sellUnits
     * @return array{by_package: array<int, array{unit_price: float, pieces_per_sell_unit: float}>, options: list<array{package_id: int, pieces_per_sell_unit: float, unit_price: float}>, source: string|null}
     */
    private function recommendedSellPricesFromBuyingCost(float $buyingRate, float $piecesPerReceiveUnit, array $sellUnits): array
    {
        $empty = ['by_package' => [], 'options' => [], 'source' => null];
        if ($buyingRate <= 0 || $piecesPerReceiveUnit <= 0) {
            return $empty;
        }

        $costPerPiece = $buyingRate / $piecesPerReceiveUnit;
        $byPackage = [];
        $options = [];

        if ($sellUnits === []) {
            $sellUnits = [['package_id' => 0, 'pieces_per_sell_unit' => 1]];
        }

        foreach ($sellUnits as $unit) {
            $pieces = max(0.01, (float) ($unit['pieces_per_sell_unit'] ?? 1));
            $price = round($costPerPiece * $pieces, 2);
            if ($price <= 0) {
                continue;
            }
            $packageId = (int) ($unit['package_id'] ?? 0);
            $row = [
                'unit_price' => $price,
                'pieces_per_sell_unit' => $pieces,
            ];
            if ($packageId > 0) {
                $byPackage[$packageId] = $row;
            }
            $options[] = [
                'package_id' => $packageId,
                'pieces_per_sell_unit' => $pieces,
                'unit_price' => $price,
            ];
        }

        usort($options, fn (array $a, array $b) => ($a['pieces_per_sell_unit'] <=> $b['pieces_per_sell_unit']));

        return [
            'by_package' => $byPackage,
            'options' => $options,
            'source' => 'buying cost',
        ];
    }

    private function storePackageForPmsSellOption(Hotel $hotel, HotelPmsServiceItemSellOption $opt): ?HotelStorePackage
    {
        $pmsPkgId = (int) ($opt->hotel_pms_service_package_id ?? 0);
        if ($pmsPkgId > 0) {
            $linked = HotelStorePackage::query()
                ->where('hotel_id', $hotel->id)
                ->where('hotel_pms_service_package_id', $pmsPkgId)
                ->first();
            if ($linked) {
                return $linked;
            }
        }

        $pmsPkg = $opt->package;
        if (! $pmsPkg) {
            return null;
        }

        $needleAbbr = strtoupper(trim((string) ($pmsPkg->abbreviation ?: '')));
        $needleName = strtoupper(trim((string) $pmsPkg->name));

        return HotelStorePackage::query()
            ->where('hotel_id', $hotel->id)
            ->get()
            ->first(function (HotelStorePackage $storePkg) use ($needleAbbr, $needleName) {
                $abbr = strtoupper(trim((string) ($storePkg->abbreviation ?: '')));
                $name = strtoupper(trim((string) $storePkg->name));
                if ($needleAbbr !== '' && ($needleAbbr === $abbr || $needleAbbr === $name)) {
                    return true;
                }
                if ($needleName !== '' && ($needleName === $name || $needleName === $abbr)) {
                    return true;
                }

                return false;
            });
    }

    /** @return array{price: float, unit: string}|null */
    private function primarySellPriceFromMasterItem(HotelStoreMasterItem $item): ?array
    {
        $item->loadMissing(['sellOptions.storePackage']);
        $best = null;
        foreach ($item->sellOptions as $opt) {
            if ($opt->unit_price === null || (float) $opt->unit_price <= 0) {
                continue;
            }
            $pieces = max(0.01, (float) $opt->pieces_per_sell_unit);
            $unit = trim((string) ($opt->storePackage?->unitLabel() ?? ''));
            if ($best === null || $pieces < $best['pieces']) {
                $best = [
                    'price' => round((float) $opt->unit_price, 2),
                    'unit' => $unit,
                    'pieces' => $pieces,
                ];
            }
        }

        return $best !== null ? ['price' => $best['price'], 'unit' => $best['unit']] : null;
    }

    /** @return array{price: float, unit: string}|null */
    private function primarySellPriceFromPms(HotelPmsServiceItem $pmsItem): ?array
    {
        $pmsItem->loadMissing('sellOptions.package');
        $best = null;
        foreach ($pmsItem->sellOptions as $opt) {
            if ($opt->unit_price === null || (float) $opt->unit_price <= 0) {
                continue;
            }
            $pieces = $opt->pieces_per_sell_unit !== null ? (float) $opt->pieces_per_sell_unit : 1.0;
            $unit = trim((string) ($opt->package?->abbreviation ?: $opt->package?->name ?: ''));
            if ($best === null || $pieces < $best['pieces']) {
                $best = [
                    'price' => round((float) $opt->unit_price, 2),
                    'unit' => $unit,
                    'pieces' => $pieces,
                ];
            }
        }
        if ($best !== null) {
            return ['price' => $best['price'], 'unit' => $best['unit']];
        }
        if ($pmsItem->unit_price !== null && (float) $pmsItem->unit_price > 0) {
            return [
                'price' => round((float) $pmsItem->unit_price, 2),
                'unit' => '',
            ];
        }

        return null;
    }

    private function resolvePmsPackageIdForStorePackage(HotelStorePackage $storePkg, HotelPmsServiceItem $pmsItem): ?int
    {
        $linked = (int) ($storePkg->hotel_pms_service_package_id ?? 0);
        if ($linked > 0) {
            return $linked;
        }

        $needleAbbr = strtoupper(trim((string) ($storePkg->abbreviation ?: '')));
        $needleName = strtoupper(trim((string) $storePkg->name));
        foreach ($pmsItem->sellOptions as $opt) {
            $pkg = $opt->package;
            if (! $pkg) {
                continue;
            }
            $abbr = strtoupper(trim((string) ($pkg->abbreviation ?: '')));
            $name = strtoupper(trim((string) $pkg->name));
            if ($needleAbbr !== '' && ($needleAbbr === $abbr || $needleAbbr === $name)) {
                return (int) $opt->hotel_pms_service_package_id;
            }
            if ($needleName !== '' && ($needleName === $name || $needleName === $abbr)) {
                return (int) $opt->hotel_pms_service_package_id;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $unitOptions
     * @return list<array<string, mixed>>
     */
    private function attachSellPricesToIndentUnitOptions(
        array $unitOptions,
        HotelStoreMasterItem $item,
        HotelPmsServiceItem $pmsItem,
    ): array {
        $item->loadMissing(['sellOptions.storePackage']);

        $pricesByPmsPkg = [];
        foreach ($pmsItem->sellOptions as $opt) {
            $pkgId = (int) $opt->hotel_pms_service_package_id;
            if ($pkgId > 0 && $opt->unit_price !== null) {
                $pricesByPmsPkg[$pkgId] = (float) $opt->unit_price;
            }
        }

        $fallbackPrice = $pmsItem->unit_price !== null ? (float) $pmsItem->unit_price : null;

        foreach ($unitOptions as &$option) {
            if (($option['kind'] ?? '') !== 'usage') {
                continue;
            }
            if (! preg_match('/^usage:(\d+)$/', (string) ($option['value'] ?? ''), $matches)) {
                continue;
            }
            $sellOpt = $item->sellOptions->firstWhere('id', (int) $matches[1]);
            $storePkg = $sellOpt?->storePackage;
            if (! $storePkg) {
                continue;
            }
            if ($sellOpt->unit_price !== null && (float) $sellOpt->unit_price > 0) {
                $option['unit_price'] = round((float) $sellOpt->unit_price, 2);

                continue;
            }
            $pmsPkgId = $this->resolvePmsPackageIdForStorePackage($storePkg, $pmsItem) ?? 0;
            $price = ($pmsPkgId > 0 && array_key_exists($pmsPkgId, $pricesByPmsPkg))
                ? $pricesByPmsPkg[$pmsPkgId]
                : $fallbackPrice;
            if ($price !== null && $price > 0) {
                $option['unit_price'] = round($price, 2);
            }
        }
        unset($option);

        return $unitOptions;
    }

    /** @return array{categories: \Illuminate\Support\Collection, items: \Illuminate\Support\Collection, total_value: float} */
    public function masterItemsIndex(Hotel $hotel, array $filters): array
    {
        $this->storeStock->syncPendingReceiptLines($hotel);

        $categoryId = (int) ($filters['category_id'] ?? 0);
        $search = trim((string) ($filters['search'] ?? ''));

        $categories = HotelStoreCategory::query()
            ->where('hotel_id', $hotel->id)
            ->with('pmsService')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $itemsQuery = HotelStoreMasterItem::query()
            ->where('hotel_id', $hotel->id)
            ->with(['category.pmsService', 'pmsServiceCategory', 'vendor', 'taxGroup', 'storePackage', 'sellOptions.storePackage']);

        if ($categoryId > 0) {
            $itemsQuery->where('hotel_store_category_id', $categoryId);
        }

        if ($search !== '') {
            $needle = '%'.$search.'%';
            $itemsQuery->where(function (Builder $q) use ($needle) {
                $q->where('name', 'like', $needle)
                    ->orWhere('unit', 'like', $needle)
                    ->orWhere('tax_group', 'like', $needle);
            });
        }

        $items = $itemsQuery->orderBy('name')->get();
        $totalValue = round($items->sum(fn (HotelStoreMasterItem $item) => $item->lineValue()), 2);

        return [
            'categories' => $categories,
            'items' => $items,
            'total_value' => $totalValue,
        ];
    }

    /** @param  array<string, mixed>  $data */
    public function createItem(Hotel $hotel, array $data): HotelStoreMasterItem
    {
        $item = HotelStoreMasterItem::query()->create(
            array_merge(['hotel_id' => $hotel->id], $this->mapItemAttributes($hotel, $data))
        );
        $this->syncSellOptions($item, $data);
        $item = $item->fresh(['category', 'vendor', 'taxGroup', 'storePackage', 'sellOptions.storePackage']);
        $this->syncMasterItemSellPricesToPmsCatalog($item);

        return $item->fresh(['category', 'vendor', 'taxGroup', 'storePackage', 'sellOptions.storePackage']);
    }

    /** @param  array<string, mixed>  $data */
    public function updateItem(Hotel $hotel, HotelStoreMasterItem $item, array $data): HotelStoreMasterItem
    {
        abort_unless((int) $item->hotel_id === (int) $hotel->id, 404);

        $item->update($this->mapItemAttributes($hotel, $data));
        $this->syncSellOptions($item->fresh(), $data);
        $item = $item->fresh(['category', 'vendor', 'taxGroup', 'storePackage', 'sellOptions.storePackage']);
        $this->syncMasterItemSellPricesToPmsCatalog($item);

        return $item->fresh(['category', 'vendor', 'taxGroup', 'storePackage', 'sellOptions.storePackage']);
    }

    /**
     * Sell-option rows for the master item modal (includes PMS prices when DB is empty).
     *
     * @return list<array{package_id: int, pieces_per_sell_unit: float, unit_price: float|string}>
     */
    public function sellOptionRowsForForm(HotelStoreMasterItem $item): array
    {
        $item->loadMissing(['sellOptions.storePackage']);
        $pmsItem = $item->isForSale() ? $this->pmsCatalogItemForMasterItem($item) : null;
        $pricesByPmsPkg = [];
        if ($pmsItem) {
            foreach ($pmsItem->sellOptions as $opt) {
                if ($opt->unit_price !== null && (float) $opt->unit_price > 0) {
                    $pricesByPmsPkg[(int) $opt->hotel_pms_service_package_id] = (float) $opt->unit_price;
                }
            }
        }

        return $item->sellOptions->map(function (HotelStoreMasterItemSellOption $option) use ($pmsItem, $pricesByPmsPkg) {
            $price = $option->unit_price;
            if (($price === null || (float) $price <= 0) && $pmsItem && $option->storePackage) {
                $pmsPkgId = $this->resolvePmsPackageIdForStorePackage($option->storePackage, $pmsItem) ?? 0;
                if ($pmsPkgId > 0 && isset($pricesByPmsPkg[$pmsPkgId])) {
                    $price = $pricesByPmsPkg[$pmsPkgId];
                }
            }

            return [
                'package_id' => (int) $option->hotel_store_package_id,
                'pieces_per_sell_unit' => (float) $option->pieces_per_sell_unit,
                'unit_price' => $price !== null && (float) $price > 0 ? (float) $price : '',
            ];
        })->values()->all();
    }

    /** @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mapItemAttributes(Hotel $hotel, array $data): array
    {
        $category = $this->resolveCategory($hotel, (int) $data['hotel_store_category_id']);
        $pmsCategoryId = $this->resolvePmsServiceCategoryId(
            $hotel,
            $category,
            (int) ($data['hotel_pms_service_category_id'] ?? 0)
        );
        $taxInclusive = (int) ($data['tax_inclusive'] ?? 0) === 1;
        $taxGroupId = $taxInclusive ? (int) ($data['hotel_store_tax_group_id'] ?? 0) : 0;
        $taxGroup = $this->resolveTaxGroup($hotel, $taxGroupId);
        $package = $this->resolvePackage($hotel, (int) ($data['hotel_store_package_id'] ?? 0));
        $usage = (string) ($data['usage_type'] ?? HotelStoreMasterItem::USAGE_INTERNAL);
        if (! in_array($usage, [HotelStoreMasterItem::USAGE_SALE, HotelStoreMasterItem::USAGE_INTERNAL], true)) {
            $usage = HotelStoreMasterItem::USAGE_INTERNAL;
        }

        return [
            'hotel_store_category_id' => $category->id,
            'hotel_vendor_id' => $this->resolveVendorId($data),
            'hotel_store_sub_category_id' => null,
            'hotel_pms_service_category_id' => $pmsCategoryId,
            'name' => trim((string) $data['name']),
            'rate' => max(0, (float) ($data['rate'] ?? 0)),
            'hotel_store_tax_group_id' => $taxGroup?->id,
            'tax_group' => $taxGroup?->name,
            'tax_inclusive' => (int) ($data['tax_inclusive'] ?? 0) === 1,
            'inventoried' => (int) ($data['inventoried'] ?? 1) === 1,
            'capital_item' => (int) ($data['capital_item'] ?? 0) === 1,
            'stock' => max(0, (float) ($data['stock'] ?? 0)),
            'hotel_store_package_id' => $package?->id,
            'pieces_per_receive_unit' => max(0.01, (float) ($data['pieces_per_receive_unit'] ?? 1)),
            'unit' => $package?->unitLabel(),
            'usage_type' => $usage,
        ];
    }

    private function resolveTaxGroup(Hotel $hotel, int $taxGroupId): ?HotelStoreTaxGroup
    {
        if ($taxGroupId <= 0) {
            return null;
        }

        return HotelStoreTaxGroup::query()
            ->where('hotel_id', $hotel->id)
            ->whereKey($taxGroupId)
            ->firstOrFail();
    }

    private function resolvePackage(Hotel $hotel, int $packageId): ?HotelStorePackage
    {
        if ($packageId <= 0) {
            return null;
        }

        return HotelStorePackage::query()
            ->where('hotel_id', $hotel->id)
            ->whereKey($packageId)
            ->firstOrFail();
    }

    /** @param  array<string, mixed>  $data */
    private function resolveVendorId(array $data): ?int
    {
        $id = (int) ($data['hotel_vendor_id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    public function deleteItem(Hotel $hotel, HotelStoreMasterItem $item): void
    {
        abort_unless((int) $item->hotel_id === (int) $hotel->id, 404);
        $item->delete();
    }

    /**
     * Catalog rows for the Stores Create PO modal (master items + PMS service items e.g. Bar register).
     *
     * @return list<array{id: int, name: string, rate: float, vendor_id: int|null, category_id: int|null, tax_rate: float, tax_inclusive: bool}>
     */
    public function purchaseOrderCatalogPayload(Hotel $hotel): array
    {
        $storeCategories = HotelStoreCategory::query()
            ->where('hotel_id', $hotel->id)
            ->get();

        $storeCategoryByPmsServiceId = $storeCategories
            ->filter(fn (HotelStoreCategory $category) => (int) ($category->hotel_pms_service_id ?? 0) > 0)
            ->keyBy('hotel_pms_service_id');

        $pmsServices = HotelPmsService::query()
            ->where('hotel_id', $hotel->id)
            ->get(['id', 'template_key', 'name']);

        foreach ($pmsServices as $service) {
            if ($storeCategoryByPmsServiceId->has($service->id)) {
                continue;
            }
            $guessed = $this->guessStoreCategoryForPmsService($storeCategories, $service);
            if ($guessed) {
                $storeCategoryByPmsServiceId->put($service->id, $guessed);
            }
        }

        $payload = [];
        $masterNamesByCategory = [];

        $masterItems = HotelStoreMasterItem::query()
            ->where('hotel_id', $hotel->id)
            ->with('taxGroup')
            ->orderBy('name')
            ->get();

        foreach ($masterItems as $item) {
            $categoryId = $item->hotel_store_category_id ? (int) $item->hotel_store_category_id : null;
            $payload[] = [
                'id' => (int) $item->id,
                'name' => $item->name,
                'rate' => (float) $item->rate,
                'vendor_id' => $item->hotel_vendor_id ? (int) $item->hotel_vendor_id : null,
                'category_id' => $categoryId,
                'tax_rate' => (float) ($item->taxGroup?->rate_percent ?? 0),
                'tax_inclusive' => (bool) $item->tax_inclusive,
            ];

            if ($categoryId) {
                $masterNamesByCategory[$categoryId][mb_strtolower(trim($item->name))] = true;
            }
        }

        if ($storeCategoryByPmsServiceId->isNotEmpty()) {
            $pmsItems = HotelPmsServiceItem::query()
                ->where('hotel_id', $hotel->id)
                ->where('is_active', true)
                ->whereIn('hotel_pms_service_id', $storeCategoryByPmsServiceId->keys())
                ->orderBy('item_name')
                ->get();

            foreach ($pmsItems as $pmsItem) {
                $storeCategory = $storeCategoryByPmsServiceId->get($pmsItem->hotel_pms_service_id);
                if (! $storeCategory) {
                    continue;
                }

                $categoryId = (int) $storeCategory->id;
                $nameKey = mb_strtolower(trim($pmsItem->item_name));
                if ($masterNamesByCategory[$categoryId][$nameKey] ?? false) {
                    continue;
                }

                $payload[] = [
                    'id' => -1 * (int) $pmsItem->id,
                    'name' => $pmsItem->item_name,
                    'rate' => (float) ($pmsItem->expected_unit_price ?? 0),
                    'vendor_id' => null,
                    'category_id' => $categoryId,
                    'tax_rate' => 0.0,
                    'tax_inclusive' => false,
                ];
            }
        }

        usort($payload, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $payload;
    }

    public function formatMoney(float $amount, Hotel $hotel): string
    {
        $currency = strtoupper(trim((string) ($hotel->currency ?? 'TZS'))) ?: 'TZS';
        $decimals = in_array($currency, ['TZS', 'UGX', 'RWF', 'BIF', 'KES'], true) ? 0 : 2;

        return number_format($amount, $decimals, '.', ',');
    }

    /** @param  array<string, mixed>  $data */
    private function syncSellOptions(HotelStoreMasterItem $item, array $data): void
    {
        $usage = (string) ($data['usage_type'] ?? $item->usage_type ?? HotelStoreMasterItem::USAGE_INTERNAL);
        if (! in_array($usage, [HotelStoreMasterItem::USAGE_SALE, HotelStoreMasterItem::USAGE_INTERNAL], true)) {
            $item->sellOptions()->delete();

            return;
        }

        $rows = $data['selling_options'] ?? [];
        if (! is_array($rows)) {
            $rows = [];
        }

        $seenPackages = [];
        $sort = 0;
        $keepIds = [];

        foreach ($rows as $row) {
            $packageId = (int) ($row['package_id'] ?? 0);
            if ($packageId <= 0 || in_array($packageId, $seenPackages, true)) {
                continue;
            }
            $seenPackages[] = $packageId;

            $unitPrice = ($row['unit_price'] ?? '') !== ''
                ? max(0, (float) $row['unit_price'])
                : null;
            if ($unitPrice !== null && $unitPrice <= 0) {
                $unitPrice = null;
            }

            $option = HotelStoreMasterItemSellOption::query()->updateOrCreate(
                [
                    'hotel_store_master_item_id' => $item->id,
                    'hotel_store_package_id' => $packageId,
                ],
                [
                    'pieces_per_sell_unit' => max(0.01, (float) ($row['pieces_per_sell_unit'] ?? 1)),
                    'unit_price' => $unitPrice,
                    'sort_order' => $sort,
                ],
            );
            $keepIds[] = $option->id;
            $sort++;
        }

        if ($keepIds === []) {
            $item->sellOptions()->delete();

            return;
        }

        $item->sellOptions()->whereNotIn('id', $keepIds)->delete();
    }

    private function syncMasterItemSellPricesToPmsCatalog(HotelStoreMasterItem $item): void
    {
        if (! $item->isForSale()) {
            return;
        }

        $pmsItem = $this->pmsCatalogItemForMasterItem($item);
        if (! $pmsItem) {
            return;
        }

        $item->loadMissing(['sellOptions.storePackage']);
        $sort = 0;

        foreach ($item->sellOptions as $sellOpt) {
            $storePkg = $sellOpt->storePackage;
            if (! $storePkg) {
                continue;
            }

            $pmsPkgId = $this->resolvePmsPackageIdForStorePackage($storePkg, $pmsItem);
            if (! $pmsPkgId) {
                continue;
            }

            $unitPrice = $sellOpt->unit_price !== null && (float) $sellOpt->unit_price > 0
                ? (float) $sellOpt->unit_price
                : null;

            HotelPmsServiceItemSellOption::query()->updateOrCreate(
                [
                    'hotel_pms_service_item_id' => $pmsItem->id,
                    'hotel_pms_service_package_id' => $pmsPkgId,
                ],
                [
                    'pieces_per_sell_unit' => max(0.01, (float) $sellOpt->pieces_per_sell_unit),
                    'unit_price' => $unitPrice,
                    'sort_order' => $sort,
                ],
            );
            $sort++;
        }

        $pmsItem->load('sellOptions');
        $primary = $pmsItem->sellOptions->sortBy('sort_order')->first();
        if (! $primary) {
            return;
        }

        $updates = [
            'selling_hotel_pms_service_package_id' => $primary->hotel_pms_service_package_id,
        ];
        if ($primary->unit_price !== null) {
            $updates['unit_price'] = $primary->unit_price;
        }
        $item->loadMissing('hotel');
        if ($item->hotel) {
            $updates['currency'] = app(BarPurchaseListService::class)->budgetCurrencyForHotel($item->hotel);
        }
        $pmsItem->update($updates);
    }

    /** @param  Collection<int, HotelStoreCategory>  $storeCategories */
    private function guessStoreCategoryForPmsService(Collection $storeCategories, HotelPmsService $service): ?HotelStoreCategory
    {
        $templateKey = trim((string) ($service->template_key ?? ''));
        if ($templateKey === '') {
            return null;
        }

        $workflow = null;
        foreach (DepartmentProcurement::workflowKeys() as $workflowKey) {
            $config = DepartmentProcurement::workflow($workflowKey);
            if (($config['template_key'] ?? null) === $templateKey) {
                $workflow = $config;
                break;
            }
        }

        if (! $workflow) {
            return null;
        }

        $patterns = collect($workflow['department_name_patterns'] ?? [])
            ->map(fn ($pattern) => strtolower(str_replace('%', '', (string) $pattern)))
            ->filter();

        $serviceName = strtolower(trim($service->name));

        foreach ($storeCategories as $category) {
            $categoryName = strtolower(trim($category->name));
            if ($serviceName !== '' && ($categoryName === $serviceName || str_contains($categoryName, $serviceName))) {
                return $category;
            }
            foreach ($patterns as $needle) {
                if ($needle !== '' && str_contains($categoryName, $needle)) {
                    return $category;
                }
            }
        }

        return null;
    }

    private function resolveCategory(Hotel $hotel, int $categoryId): HotelStoreCategory
    {
        if ($categoryId <= 0) {
            throw new InvalidArgumentException('Select a category.');
        }

        return HotelStoreCategory::query()
            ->where('hotel_id', $hotel->id)
            ->whereKey($categoryId)
            ->firstOrFail();
    }

    private function resolvePmsServiceCategoryId(Hotel $hotel, HotelStoreCategory $category, int $pmsCategoryId): ?int
    {
        if (! $category->hotel_pms_service_id) {
            return null;
        }

        if ($pmsCategoryId <= 0) {
            throw new InvalidArgumentException('Select a service item category for this department.');
        }

        HotelPmsServiceCategory::query()
            ->where('hotel_id', $hotel->id)
            ->where('hotel_pms_service_id', $category->hotel_pms_service_id)
            ->whereKey($pmsCategoryId)
            ->firstOrFail();

        return $pmsCategoryId;
    }

}
