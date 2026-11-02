<?php

namespace App\Services\Stores;

use App\Models\Hotel;
use App\Models\HotelDepartment;
use App\Models\HotelStoreConsumption;
use App\Models\HotelStoreIndent;
use App\Models\User;
use App\Services\Hr\HotelHrService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DepartmentStorePortalIndexData
{
    public function __construct(
        private StoreCatalogService $storeCatalog,
        private StoreCategoryService $storeCategories,
        private StoreIndentService $storeIndents,
        private StoreMasterItemService $storeMasterItems,
        private StoreDepartmentStockService $departmentStock,
        private StoreConsumptionService $storeConsumption,
        private HotelHrService $hotelHr,
    ) {}

    /** @return list<int> */
    public function departmentIds(Hotel $hotel, User $user): array
    {
        $all = HotelDepartment::query()
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($all === []) {
            return [];
        }

        $accessAll = config('hotel_department_stores.access_all_departments_permission', 'dept_stores.access_all_departments');
        if ($user->hasPermission($accessAll)) {
            return $all;
        }

        $staffDept = (int) ($user->hotel_department_id ?? 0);
        if ($staffDept > 0 && in_array($staffDept, $all, true)) {
            return [$staffDept];
        }

        return $all;
    }

    public function pendingReceiveCount(Hotel $hotel, User $user): int
    {
        $ids = $this->departmentIds($hotel, $user);

        return $this->storeIndents->pendingDepartmentReceiveCount($hotel->id, $ids);
    }

    /** @return array<string, mixed> */
    public function storeRequestPayload(Hotel $hotel, User $user, Request $request): array
    {
        $this->storeCatalog->ensureDefaults($hotel);
        $this->hotelHr->ensureDefaults($hotel);

        $departmentIds = $this->departmentIds($hotel, $user);
        $indentDepartments = $this->departments($hotel, $departmentIds);

        $poSearch = trim((string) $request->input('po_search', ''));
        $poFrom = (string) $request->input('po_from', now()->subDays(7)->format('Y-m-d'));
        $poTo = (string) $request->input('po_to', now()->format('Y-m-d'));
        $indentStatus = (string) $request->input('indent_status', 'all');

        $selectedDepartmentId = is_numeric($request->query('department'))
            ? (int) $request->query('department')
            : ($departmentIds[0] ?? null);

        if ($selectedDepartmentId && ! in_array($selectedDepartmentId, $departmentIds, true)) {
            $selectedDepartmentId = $departmentIds[0] ?? null;
        }

        $storeIndentsList = $this->storeIndents->indentsQuery($hotel->id, [
            'search' => $poSearch,
            'from_date' => $poFrom,
            'to_date' => $poTo,
            'status' => $indentStatus,
            'department_ids' => $departmentIds,
        ])
            ->with(['department', 'items'])
            ->paginate(20)
            ->withQueryString();

        $indentCategories = $this->storeCategories->categoriesForHotel($hotel, ['search' => '']);
        $indentCatalogByDepartment = $this->storeMasterItems->indentCatalogPayloadByDepartment($hotel, $indentDepartments);

        return [
            'deptStoreRequestPermissions' => $this->storeRequestPermissions($user),
            'indentDepartments' => $indentDepartments,
            'hkDefaultDepartmentId' => count($departmentIds) === 1 ? $departmentIds[0] : $selectedDepartmentId,
            'storeIndentsList' => $storeIndentsList,
            'indentCategories' => $indentCategories,
            'indentFilterItemsByDepartment' => true,
            'indentCatalogByDepartment' => $indentCatalogByDepartment,
            'indentMasterItemsPayload' => [],
            'indentDetailRoute' => route('hotel.department-stores.store-requests.show', ['indent' => '__ID__']),
            'indentAutoOpenId' => (int) $request->query('view_indent', 0),
            'poSearch' => $poSearch,
            'poFrom' => $poFrom,
            'poTo' => $poTo,
            'indentStatus' => $indentStatus,
            'indentStatusFilters' => config('hotel_stores.indent_status_filters', []),
            'indentIndexRoute' => route('hotel.department-stores.index', ['tab' => 'store-requests']),
            'indentIndexTab' => 'store-requests',
            'indentRoutes' => [
                'store' => route('hotel.department-stores.store-requests.store'),
                'update' => url('/hotel/department-stores/store-requests/__ID__'),
                'destroy' => url('/hotel/department-stores/store-requests/__ID__'),
                'approve' => url('/hotel/department-stores/store-requests/__ID__/approve'),
                'receive' => url('/hotel/department-stores/store-requests/__ID__/receive'),
            ],
            'indentShowApprove' => false,
            'indentHideDepartmentSelect' => count($departmentIds) === 1,
            'indentHideDepartmentDisplay' => true,
            'indentHideCategory' => true,
            'indentHideUnit' => false,
            'indentModalTitleCreate' => 'Request items',
            'indentModalTitleEdit' => 'Edit request',
            'indentEmptyMessage' => 'No store requests in this period. Click <strong>Request items</strong> to ask the store for stock.',
            'indentCreateButtonLabel' => 'Request items',
            'indentAllowDepartmentReceive' => true,
        ];
    }

    /** @return array<string, mixed> */
    public function stockPayload(Hotel $hotel, User $user, Request $request): array
    {
        $this->hotelHr->ensureDefaults($hotel);
        $departmentIds = $this->departmentIds($hotel, $user);
        $search = trim((string) $request->input('po_search', ''));

        $stockData = $this->departmentStock->consumptionIndex($hotel, [
            'department_id' => 0,
            'department_ids' => $departmentIds,
            'search' => $search,
        ]);

        return [
            'deptStockPermissions' => $this->stockPermissions($user),
            'consumptionData' => $stockData,
            'poSearch' => $search,
            'deptStockIndexRoute' => route('hotel.department-stores.index', ['tab' => 'dept-stock']),
        ];
    }

    /** @return array<string, mixed> */
    public function consumptionPayload(Hotel $hotel, User $user, Request $request): array
    {
        $this->hotelHr->ensureDefaults($hotel);
        $departmentIds = $this->departmentIds($hotel, $user);

        $poSearch = trim((string) $request->input('po_search', ''));
        $poFrom = (string) $request->input('po_from', now()->subDays(7)->format('Y-m-d'));
        $poTo = (string) $request->input('po_to', now()->format('Y-m-d'));
        $departmentFilter = is_numeric($request->query('department')) ? (int) $request->query('department') : 0;
        if ($departmentFilter > 0 && ! in_array($departmentFilter, $departmentIds, true)) {
            $departmentFilter = 0;
        }

        $defaultDepartmentId = count($departmentIds) === 1 ? $departmentIds[0] : ($departmentFilter ?: null);

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
            ->with(['department', 'items.masterItem.sellOptions.storePackage', 'items.masterItem.storePackage'])
            ->paginate(20)
            ->withQueryString();

        $consumptionCategories = $this->storeCategories->categoriesForHotel($hotel, ['search' => '']);

        $consumptionStockCatalog = [];
        foreach ($consumptionDepartments as $dept) {
            $consumptionStockCatalog[$dept->id] = $this->storeConsumption->departmentStockCatalog($hotel, (int) $dept->id);
        }

        return [
            'deptConsumptionPermissions' => $this->consumptionPermissions($user),
            'consumptionData' => $consumptionData,
            'consumptionDepartments' => $consumptionDepartments,
            'consumptionCategories' => $consumptionCategories,
            'consumptionStockCatalog' => $consumptionStockCatalog,
            'storeConsumptionsList' => $storeConsumptionsList,
            'consumptionDetailRoute' => route('hotel.department-stores.consumptions.show', ['consumption' => '__ID__']),
            'consumptionAutoOpenId' => (int) $request->query('view_consumption', 0),
            'poSearch' => $poSearch,
            'poFrom' => $poFrom,
            'poTo' => $poTo,
            'consumptionDeptFilter' => $departmentFilter,
            'hkDefaultDepartmentId' => $defaultDepartmentId,
            'consumptionIndexRoute' => route('hotel.department-stores.index'),
            'consumptionIndexTab' => 'consumption',
            'consumptionDeptFilterParam' => 'department',
            'consumptionFormRoute' => route('hotel.department-stores.consumptions.store'),
            'consumptionHideDeptStockSection' => true,
            'consumptionHideDepartmentSelect' => count($departmentIds) === 1,
            'consumptionHideCategory' => true,
            'consumptionHideUnit' => true,
            'consumptionEmptyMessage' => 'No consumption in this period. Click <strong>Record use</strong> when items are used.',
            'consumptionCreateButtonLabel' => 'Record use',
            'consumptionModalTitle' => 'Record consumption',
            'consumptionShowRoomDistribution' => false,
            'consumptionRoomOptions' => [],
            'consumptionPreserveFilter' => true,
        ];
    }

    public function assertIndentBelongsToPortal(Hotel $hotel, User $user, HotelStoreIndent $indent): void
    {
        abort_unless((int) $indent->hotel_id === (int) $hotel->id, 404);
        abort_unless(in_array((int) $indent->hotel_department_id, $this->departmentIds($hotel, $user), true), 404);
    }

    public function assertDepartmentAllowed(Hotel $hotel, User $user, int $departmentId): void
    {
        abort_unless(in_array($departmentId, $this->departmentIds($hotel, $user), true), 422);
    }

    public function consumptionForDetail(Hotel $hotel, User $user, HotelStoreConsumption $consumption): HotelStoreConsumption
    {
        abort_unless((int) $consumption->hotel_id === (int) $hotel->id, 404);
        abort_unless(in_array((int) $consumption->hotel_department_id, $this->departmentIds($hotel, $user), true), 404);

        return $consumption->load([
            'department',
            'items.masterItem.sellOptions.storePackage',
            'items.masterItem.storePackage',
        ]);
    }

    /** @return array<string, bool> */
    public function storeRequestPermissions(User $user): array
    {
        $keys = config('hotel_department_stores.store_request_permission_keys', []);

        return [
            'create' => $user->hasPermission($keys['create'] ?? 'dept_stores.store_request.create'),
            'edit' => $user->hasPermission($keys['edit'] ?? 'dept_stores.store_request.edit'),
            'delete' => $user->hasPermission($keys['delete'] ?? 'dept_stores.store_request.delete'),
            'receive' => $user->hasPermission($keys['receive'] ?? 'dept_stores.store_request.receive'),
        ];
    }

    /** @return array<string, bool> */
    public function stockPermissions(User $user): array
    {
        $keys = config('hotel_department_stores.dept_stock_permission_keys', []);

        return [
            'view' => $user->hasPermission($keys['view'] ?? 'dept_stores.dept_stock.view'),
        ];
    }

    /** @return array<string, bool> */
    public function consumptionPermissions(User $user): array
    {
        $keys = config('hotel_department_stores.consumption_permission_keys', []);

        return [
            'create' => $user->hasPermission($keys['create'] ?? 'dept_stores.consumption.create'),
            'view' => $user->hasPermission($keys['view'] ?? 'dept_stores.consumption.view'),
        ];
    }

    /** @param  list<int>  $departmentIds
     * @return Collection<int, HotelDepartment>
     */
    private function departments(Hotel $hotel, array $departmentIds): Collection
    {
        if ($departmentIds === []) {
            return collect();
        }

        return HotelDepartment::query()
            ->where('hotel_id', $hotel->id)
            ->whereIn('id', $departmentIds)
            ->orderBy('name')
            ->get();
    }
}
