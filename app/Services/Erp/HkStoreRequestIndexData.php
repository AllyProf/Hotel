<?php

namespace App\Services\Erp;

use App\Models\Hotel;
use App\Models\HotelStoreIndent;
use App\Models\User;
use App\Services\Bar\BarPurchaseListService;
use App\Services\Hr\HotelHrService;
use App\Services\Stores\StoreCatalogService;
use App\Services\Stores\StoreCategoryService;
use App\Services\Stores\StoreIndentService;
use App\Services\Stores\StoreMasterItemService;
use App\Support\DepartmentProcurement;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class HkStoreRequestIndexData
{
    public function __construct(
        private StoreCatalogService $storeCatalog,
        private StoreCategoryService $storeCategories,
        private StoreIndentService $storeIndents,
        private StoreMasterItemService $storeMasterItems,
        private BarPurchaseListService $purchaseLists,
        private HotelHrService $hotelHr,
    ) {}

    /** @return array<string, mixed> */
    public function payload(Hotel $hotel, User $user, Request $request): array
    {
        $this->storeCatalog->ensureDefaults($hotel);
        $this->hotelHr->ensureDefaults($hotel);
        $this->purchaseLists->ensureProcurementDepartments($hotel, 'hk');

        $poSearch = trim((string) $request->input('po_search', ''));
        $poFrom = (string) $request->input('po_from', now()->subDays(7)->format('Y-m-d'));
        $poTo = (string) $request->input('po_to', now()->format('Y-m-d'));
        $indentStatus = (string) $request->input('indent_status', 'all');
        $departmentIds = $this->hkDepartmentIds($hotel);

        $indentDepartments = $this->hkDepartments($hotel);
        $selectedDepartmentId = is_numeric($request->query('department'))
            ? (int) $request->query('department')
            : $this->purchaseLists->resolveDepartmentId($hotel, null, 'hk');

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
            'hkStoreRequestPermissions' => $this->permissions($user),
            'hkDepartmentIds' => $departmentIds,
            'indentDepartments' => $indentDepartments,
            'hkDefaultDepartmentId' => $selectedDepartmentId,
            'storeIndentsList' => $storeIndentsList,
            'indentCategories' => $indentCategories,
            'indentFilterItemsByDepartment' => true,
            'indentCatalogByDepartment' => $indentCatalogByDepartment,
            'indentMasterItemsPayload' => [],
            'indentDetailRoute' => route('hotel.maintenance-hk.store-requests.show', ['indent' => '__ID__']),
            'indentAutoOpenId' => (int) $request->query('view_indent', 0),
            'poSearch' => $poSearch,
            'poFrom' => $poFrom,
            'poTo' => $poTo,
            'indentStatus' => $indentStatus,
            'indentStatusFilters' => config('hotel_stores.indent_status_filters', []),
            'indentIndexRoute' => route('hotel.maintenance-hk.index', ['tab' => 'store-requests']),
            'indentIndexTab' => 'store-requests',
            'indentRoutes' => [
                'store' => route('hotel.maintenance-hk.store-requests.store'),
                'update' => url('/hotel/maintenance-hk/store-requests/__ID__'),
                'destroy' => url('/hotel/maintenance-hk/store-requests/__ID__'),
                'approve' => url('/hotel/maintenance-hk/store-requests/__ID__/approve'),
                'receive' => url('/hotel/maintenance-hk/store-requests/__ID__/receive'),
            ],
            'indentShowApprove' => false,
            'indentHideDepartmentSelect' => $selectedDepartmentId !== null,
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

    public function pendingReceiveCount(Hotel $hotel): int
    {
        return $this->storeIndents->pendingDepartmentReceiveCount($hotel->id, $this->hkDepartmentIds($hotel));
    }

    /** @return array<string, bool> */
    public function permissions(User $user): array
    {
        $keys = config('hotel_erp_hk.store_request_permission_keys', []);

        return [
            'create' => $user->hasPermission($keys['create'] ?? 'hk.store_request.create'),
            'edit' => $user->hasPermission($keys['edit'] ?? 'hk.store_request.edit'),
            'delete' => $user->hasPermission($keys['delete'] ?? 'hk.store_request.delete'),
            'receive' => $user->hasPermission($keys['receive'] ?? 'hk.store_request.receive'),
        ];
    }

    /** @return list<int> */
    public function hkDepartmentIds(Hotel $hotel): array
    {
        $patterns = DepartmentProcurement::workflow('hk')['department_name_patterns'] ?? [];
        $query = \App\Models\HotelDepartment::query()
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true);

        if ($patterns !== []) {
            $query->where(function ($q) use ($patterns) {
                foreach ($patterns as $pattern) {
                    $q->orWhere('name', 'like', $pattern);
                }
            });
        }

        return $query->orderBy('name')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function assertIndentBelongsToHk(Hotel $hotel, HotelStoreIndent $indent): void
    {
        abort_unless((int) $indent->hotel_id === (int) $hotel->id, 404);
        $allowed = $this->hkDepartmentIds($hotel);
        abort_unless(in_array((int) $indent->hotel_department_id, $allowed, true), 404);
    }

    public function assertDepartmentAllowed(Hotel $hotel, int $departmentId): void
    {
        abort_unless(in_array($departmentId, $this->hkDepartmentIds($hotel), true), 422);
    }

    /** @return Collection<int, \App\Models\HotelDepartment> */
    private function hkDepartments(Hotel $hotel): Collection
    {
        $ids = $this->hkDepartmentIds($hotel);
        if ($ids === []) {
            return collect();
        }

        return \App\Models\HotelDepartment::query()
            ->where('hotel_id', $hotel->id)
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();
    }
}
