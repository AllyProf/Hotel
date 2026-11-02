<?php

namespace App\Services\Erp;

use App\Models\Hotel;
use App\Models\HotelStoreConsumption;
use App\Models\User;
use App\Services\Bar\BarPurchaseListService;
use App\Services\Hr\HotelHrService;
use App\Services\Stores\StoreCategoryService;
use App\Services\Stores\StoreConsumptionService;
use App\Services\Stores\StoreDepartmentStockService;
use Illuminate\Http\Request;
class HkDepartmentStoresIndexData
{
    public function __construct(
        private HkStoreRequestIndexData $hkStoreRequests,
        private StoreDepartmentStockService $departmentStock,
        private StoreConsumptionService $storeConsumption,
        private StoreCategoryService $storeCategories,
        private BarPurchaseListService $purchaseLists,
        private HotelHrService $hotelHr,
        private HousekeepingMaintenanceService $erp,
    ) {}

    /** @return array<string, mixed> */
    public function stockPayload(Hotel $hotel, User $user, Request $request): array
    {
        $this->hotelHr->ensureDefaults($hotel);
        $this->purchaseLists->ensureProcurementDepartments($hotel, 'hk');

        $departmentIds = $this->hkStoreRequests->hkDepartmentIds($hotel);
        $search = trim((string) $request->input('po_search', ''));
        $departmentFilter = is_numeric($request->query('department')) ? (int) $request->query('department') : 0;
        if ($departmentFilter > 0 && ! in_array($departmentFilter, $departmentIds, true)) {
            $departmentFilter = 0;
        }

        $stockData = $this->departmentStock->consumptionIndex($hotel, [
            'department_id' => $departmentFilter,
            'department_ids' => $departmentIds,
            'search' => $search,
        ]);

        return [
            'hkDeptStockPermissions' => $this->stockPermissions($user),
            'consumptionData' => $stockData,
            'consumptionDepartments' => $stockData['departments'],
            'poSearch' => $search,
            'consumptionDeptFilter' => $departmentFilter,
            'deptStockIndexRoute' => route('hotel.maintenance-hk.index'),
        ];
    }

    /** @return array<string, mixed> */
    public function consumptionPayload(Hotel $hotel, User $user, Request $request): array
    {
        $this->hotelHr->ensureDefaults($hotel);
        $this->purchaseLists->ensureProcurementDepartments($hotel, 'hk');

        $departmentIds = $this->hkStoreRequests->hkDepartmentIds($hotel);
        $poSearch = trim((string) $request->input('po_search', ''));
        $poFrom = (string) $request->input('po_from', now()->subDays(7)->format('Y-m-d'));
        $poTo = (string) $request->input('po_to', now()->format('Y-m-d'));
        $departmentFilter = is_numeric($request->query('department')) ? (int) $request->query('department') : 0;
        if ($departmentFilter > 0 && ! in_array($departmentFilter, $departmentIds, true)) {
            $departmentFilter = 0;
        }

        $defaultDepartmentId = $this->purchaseLists->resolveDepartmentId($hotel, $departmentFilter ?: null, 'hk');

        $consumptionData = $this->departmentStock->consumptionIndex($hotel, [
            'department_id' => $departmentFilter,
            'department_ids' => $departmentIds,
            'search' => '',
        ]);

        $consumptionDepartments = $consumptionData['departments'];

        $storeConsumptionsList = $this->storeConsumption->consumptionsQuery($hotel->id, [
            'search' => $poSearch,
            'from_date' => $poFrom,
            'to_date' => $poTo,
            'department_id' => $departmentFilter,
            'department_ids' => $departmentIds,
        ])
            ->with(['department', 'items.roomUnit', 'items.masterItem.sellOptions.storePackage', 'items.masterItem.storePackage'])
            ->paginate(20)
            ->withQueryString();

        $consumptionCategories = $this->storeCategories->categoriesForHotel($hotel, ['search' => '']);

        $consumptionStockCatalog = [];
        foreach ($consumptionDepartments as $dept) {
            $consumptionStockCatalog[$dept->id] = $this->storeConsumption->departmentStockCatalog($hotel, (int) $dept->id);
        }

        return [
            'hkConsumptionPermissions' => $this->consumptionPermissions($user),
            'consumptionData' => $consumptionData,
            'consumptionDepartments' => $consumptionDepartments,
            'consumptionCategories' => $consumptionCategories,
            'consumptionStockCatalog' => $consumptionStockCatalog,
            'storeConsumptionsList' => $storeConsumptionsList,
            'consumptionDetailRoute' => route('hotel.maintenance-hk.consumptions.show', ['consumption' => '__ID__']),
            'consumptionAutoOpenId' => (int) $request->query('view_consumption', 0),
            'poSearch' => $poSearch,
            'poFrom' => $poFrom,
            'poTo' => $poTo,
            'consumptionDeptFilter' => $departmentFilter,
            'hkDefaultDepartmentId' => $defaultDepartmentId,
            'consumptionIndexRoute' => route('hotel.maintenance-hk.index'),
            'consumptionIndexTab' => 'consumption',
            'consumptionDeptFilterParam' => 'department',
            'consumptionFormRoute' => route('hotel.maintenance-hk.consumptions.store'),
            'consumptionHideDeptStockSection' => true,
            'consumptionHideDepartmentSelect' => $defaultDepartmentId !== null,
            'consumptionHideCategory' => true,
            'consumptionHideUnit' => true,
            'consumptionEmptyMessage' => 'No consumption in this period. Click <strong>Record use</strong> when items are used.',
            'consumptionCreateButtonLabel' => 'Record use',
            'consumptionModalTitle' => 'Record consumption',
            'consumptionShowRoomDistribution' => true,
            'consumptionRoomOptions' => $this->erp->roomUnitOptions($hotel),
            'consumptionPreserveFilter' => true,
        ];
    }

    /** @return array<string, bool> */
    public function stockPermissions(User $user): array
    {
        $keys = config('hotel_erp_hk.dept_stock_permission_keys', []);

        return [
            'view' => $user->hasPermission($keys['view'] ?? 'hk.dept_stock.view'),
        ];
    }

    /** @return array<string, bool> */
    public function consumptionPermissions(User $user): array
    {
        $keys = config('hotel_erp_hk.consumption_permission_keys', []);

        return [
            'create' => $user->hasPermission($keys['create'] ?? 'hk.consumption.create'),
            'view' => $user->hasPermission($keys['view'] ?? 'hk.consumption.view'),
        ];
    }

    public function assertDepartmentAllowed(Hotel $hotel, int $departmentId): void
    {
        $this->hkStoreRequests->assertDepartmentAllowed($hotel, $departmentId);
    }

    public function consumptionForDetail(Hotel $hotel, HotelStoreConsumption $consumption): HotelStoreConsumption
    {
        abort_unless((int) $consumption->hotel_id === (int) $hotel->id, 404);
        abort_unless(in_array((int) $consumption->hotel_department_id, $this->hkStoreRequests->hkDepartmentIds($hotel), true), 404);

        return $consumption->load([
            'department',
            'items.roomUnit',
            'items.masterItem.sellOptions.storePackage',
            'items.masterItem.storePackage',
        ]);
    }
}
