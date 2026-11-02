<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Mail\StorePurchaseInvoiceMail;
use App\Models\Hotel;
use App\Models\HotelDepartment;
use App\Models\HotelVendor;
use App\Models\HotelStoreCategory;
use App\Models\HotelBarPurchaseList;
use App\Models\HotelPurchaseOrder;
use App\Models\HotelStoreConsumption;
use App\Models\HotelStoreIndent;
use App\Models\HotelPmsService;
use App\Models\HotelStoreMasterItem;
use App\Models\HotelStorePackage;
use App\Models\HotelStoreTaxGroup;
use App\Services\AccountsDataService;
use App\Services\Bar\BarPurchaseListService;
use App\Services\StaffProfileService;
use App\Services\Hr\HotelHrService;
use App\Services\Stores\StoreCatalogService;
use App\Services\Stores\StoreCategoryService;
use App\Services\Stores\StoreConsumptionService;
use App\Services\Stores\StoreDepartmentStockService;
use App\Services\Stores\StoreDepartmentService;
use App\Services\Stores\StoreGoodsReceiveService;
use App\Services\Stores\StoreIndentService;
use App\Services\Stores\StoreMasterItemService;
use App\Services\Stores\StoreReportService;
use App\Services\Stores\StoreStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class StoresController extends Controller
{
    /** @return array<string, bool> */
    private function storePoPermissions(): array
    {
        $user = auth()->user();
        $keys = config('hotel_stores.po_permission_keys', []);

        return [
            'create' => $user->hasPermission($keys['create'] ?? 'stores.po.create'),
            'approve' => $user->hasPermission($keys['approve'] ?? 'stores.po.approve'),
            'invoice' => $user->hasPermission($keys['invoice'] ?? 'stores.po.invoice'),
            'edit' => $user->hasPermission($keys['edit'] ?? 'stores.po.edit'),
            'delete' => $user->hasPermission($keys['delete'] ?? 'stores.po.delete'),
        ];
    }

    private function assertStorePoPermission(string $action): void
    {
        $keys = config('hotel_stores.po_permission_keys', []);
        $key = $keys[$action] ?? null;
        abort_if($key === null, 500);
        abort_unless(auth()->user()->hasPermission($key), 403);
    }

    /** @return array<string, bool> */
    private function storeIndentPermissions(): array
    {
        $user = auth()->user();
        $keys = config('hotel_stores.indent_permission_keys', []);

        $receiveKeys = config('hotel_stores.indent_receive_permission_keys', []);

        return [
            'create' => $user->hasPermission($keys['create'] ?? 'stores.indent.create'),
            'approve' => $user->hasPermission($keys['approve'] ?? 'stores.indent.approve'),
            'edit' => $user->hasPermission($keys['edit'] ?? 'stores.indent.edit'),
            'delete' => $user->hasPermission($keys['delete'] ?? 'stores.indent.delete'),
            'receive' => $user->hasPermission($receiveKeys['receive'] ?? 'stores.indent.receive'),
        ];
    }

    private function assertStoreIndentPermission(string $action): void
    {
        $keys = config('hotel_stores.indent_permission_keys', []);
        $key = $keys[$action] ?? null;
        abort_if($key === null, 500);
        abort_unless(auth()->user()->hasPermission($key), 403);
    }

    /** @return array<string, bool> */
    private function storeConsumptionPermissions(): array
    {
        $user = auth()->user();
        $keys = config('hotel_stores.consumption_permission_keys', []);

        return [
            'create' => $user->hasPermission($keys['create'] ?? 'stores.consumption.create'),
        ];
    }

    private function assertStoreConsumptionPermission(string $action): void
    {
        $keys = config('hotel_stores.consumption_permission_keys', []);
        $key = $keys[$action] ?? null;
        abort_if($key === null, 500);
        abort_unless(auth()->user()->hasPermission($key), 403);
    }

    public function __construct(
        private StoreMasterItemService $storeItems,
        private StoreCatalogService $storeCatalog,
        private StoreCategoryService $storeCategories,
        private AccountsDataService $accounts,
        private StaffProfileService $staffProfile,
        private StoreDepartmentService $storeDepartments,
        private HotelHrService $hotelHr,
        private StoreGoodsReceiveService $storeGoodsReceive,
        private StoreStockService $storeStock,
        private StoreIndentService $storeIndents,
        private StoreDepartmentStockService $departmentStock,
        private StoreConsumptionService $storeConsumption,
        private StoreReportService $storeReports,
        private BarPurchaseListService $purchaseLists,
    ) {}

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $mainTab = $this->resolveMainTab((string) $request->query('tab', 'purchase-orders'));
        $settingsTab = $this->resolveSettingsTab((string) $request->query('settings', 'master-items'));

        $filters = [
            'category_id' => (int) $request->query('category_id', 0),
            'search' => trim((string) $request->query('search', '')),
        ];
        $vendorSearch = trim((string) $request->query('vendor_search', ''));
        $departmentSearch = trim((string) $request->query('department_search', ''));
        $categorySearch = trim((string) $request->query('category_search', ''));
        $poSearch = trim((string) $request->query('po_search', ''));
        $defaultListDays = $mainTab === 'indent' ? 365 : 7;
        $poFrom = (string) $request->query('po_from', now()->subDays($defaultListDays)->format('Y-m-d'));
        $poTo = (string) $request->query('po_to', now()->format('Y-m-d'));
        $indentDepartmentFilter = (int) $request->query('indent_department', 0);
        $poStatus = (string) $request->query('po_status', 'all');

        $masterData = null;
        $stockData = null;
        $consumptionData = null;
        $storeConsumptionsList = null;
        $consumptionDepartments = collect();
        $consumptionCategories = collect();
        $consumptionStockCatalog = [];
        $consumptionDeptFilter = (int) $request->query('department_id', 0);
        $purchaseOrders = null;
        $storeKeeperPurchaseOrders = collect();
        $storeInvoices = null;
        $viewStoreInvoice = null;
        $suggestedStoreInvoiceNumber = null;
        $goodsReceiveOrder = null;
        $storeIndentsList = null;
        $indentDepartments = collect();
        $indentCategories = collect();
        $indentMasterItemsPayload = [];
        $indentCatalogByDepartment = [];
        $indentStatus = (string) $request->query('indent_status', 'all');
        $reportType = $this->resolveReportType((string) $request->query('report_type', 'indent'));
        $reportSort = $this->resolveReportSort((string) $request->query('report_sort', 'department'));
        $reportData = null;
        $poVendors = collect();
        $poStoreDepartments = collect();
        $poMasterItemsPayload = [];
        $itemVendors = collect();
        $storeTaxGroups = collect();
        $storePackages = collect();
        $usageTypes = config('hotel_stores.usage_types', []);
        $storeDepartmentPurchaseLists = collect();
        $storePlWorkflows = collect();
        $storePlDepartments = collect();
        $storePlBatchDepartments = collect();
        $storePlQuickSubmitTotal = 0;
        $canAccessStoresDepartmentLists = false;
        $storePlPendingCount = 0;
        $storeIndentPendingCount = $this->storeIndents->pendingApprovalCount($hotel->id);
        $plWorkflowFilter = trim((string) $request->query('pl_workflow', ''));
        $plDepartmentId = is_numeric($request->query('pl_department')) ? (int) $request->query('pl_department') : null;
        $user = auth()->user();
        $canAccessStoresDepartmentLists = $this->purchaseLists->userCanAccessStoresDepartmentLists($hotel, $user);
        if ($canAccessStoresDepartmentLists) {
            $storePlPendingCount = $this->purchaseLists->pendingStoreReviewListsCount($hotel, $user);
        }

        if ($mainTab === 'purchase-orders' && $canAccessStoresDepartmentLists) {
            $storePlWorkflows = $this->purchaseLists->workflowLabelsForReceivedLists($hotel, $user);
            $storePlDepartments = $this->purchaseLists->departmentsForAllReceivedLists(
                $hotel,
                $user,
                $plWorkflowFilter !== '' ? $plWorkflowFilter : null,
            );
            $storeDepartmentPurchaseLists = $this->purchaseLists->storePurchaseQueueListsForUser(
                $hotel,
                $user,
                $plWorkflowFilter !== '' ? $plWorkflowFilter : null,
                $plDepartmentId,
                100,
            );
            $quickSubmitLists = $storeDepartmentPurchaseLists->filter(
                fn (HotelBarPurchaseList $list) => $list->canQuickSubmitBudgetToAccounts()
            );
            $storePlQuickSubmitTotal = $quickSubmitLists->count();
            $storePlBatchDepartments = $quickSubmitLists
                ->groupBy(fn (HotelBarPurchaseList $list) => (int) ($list->hotel_department_id ?? 0))
                ->map(fn ($group, $deptId) => [
                    'id' => (int) $deptId,
                    'name' => $group->first()->department?->name ?? 'Department',
                    'count' => $group->count(),
                ])
                ->values();
        }

        if ($mainTab === 'settings') {
            $this->storeCatalog->ensureDefaults($hotel);
            $storeTaxGroups = $this->storeCatalog->taxGroupsForHotel($hotel);
            $storePackages = $this->storeCatalog->packagesForHotel($hotel);
        }

        if ($mainTab === 'settings' && $settingsTab === 'master-items') {
            $masterData = $this->storeItems->masterItemsIndex($hotel, $filters);
            $itemVendors = $this->accounts->vendorsQuery($hotel->id, ['search' => ''])->get();
        }

        if ($mainTab === 'stock') {
            $this->storeCatalog->ensureDefaults($hotel);
            $stockData = $this->storeStock->stockIndex($hotel, $filters);
        }

        if ($mainTab === 'consumption') {
            $this->hotelHr->ensureDefaults($hotel);
            $consumptionDepartments = $this->storeDepartments->departmentsForHotel($hotel, ['search' => '']);
            $consumptionCategories = $this->storeCategories->categoriesForHotel($hotel, ['search' => '']);
            $consumptionData = $this->departmentStock->consumptionIndex($hotel, [
                'department_id' => $consumptionDeptFilter,
                'search' => $poSearch,
            ]);
            $storeConsumptionsList = $this->storeConsumption->consumptionsQuery($hotel->id, [
                'search' => $poSearch,
                'from_date' => $poFrom,
                'to_date' => $poTo,
                'department_id' => $consumptionDeptFilter,
            ])
                ->with(['department', 'items'])
                ->paginate(20)
                ->withQueryString();

            foreach ($consumptionDepartments as $dept) {
                $consumptionStockCatalog[$dept->id] = $this->storeConsumption->departmentStockCatalog($hotel, (int) $dept->id);
            }

        }

        if ($mainTab === 'reports') {
            $reportData = $this->storeReports->build($hotel, [
                'report_type' => $reportType,
                'report_sort' => $reportSort,
                'from_date' => $poFrom,
                'to_date' => $poTo,
            ]);
        }

        if ($mainTab === 'indent') {
            $this->storeCatalog->ensureDefaults($hotel);
            $this->hotelHr->ensureDefaults($hotel);
            $indentDepartments = $this->storeDepartments->departmentsForHotel($hotel, ['search' => '']);
            $indentCategories = $this->storeCategories->categoriesForHotel($hotel, ['search' => '']);
            $indentFilters = [
                'search' => $poSearch,
                'from_date' => $poFrom,
                'to_date' => $poTo,
                'status' => $indentStatus,
            ];
            if ($indentDepartmentFilter > 0) {
                $indentFilters['department_id'] = $indentDepartmentFilter;
            }
            $storeIndentsList = $this->storeIndents->indentsQuery($hotel->id, $indentFilters)
                ->with(['department', 'storeCategory', 'items.masterItem'])
                ->paginate(20)
                ->withQueryString();

            $indentCatalogByDepartment = $this->storeItems->indentCatalogPayloadByStoreCategory($hotel, $indentCategories);
            $indentMasterItemsPayload = [];

        }

        if ($mainTab === 'purchase-orders') {
            $purchaseOrders = null;
            $storeKeeperPurchaseOrders = $this->accounts->purchaseOrdersQuery($hotel->id, [
                'search' => $poSearch,
                'from_date' => '',
                'to_date' => '',
                'status' => 'all',
                'scope' => 'purchase_orders',
            ])
                ->where('po_number', 'like', 'ORD-%')
                ->with(['vendor', 'items.masterItem.storePackage', 'storeDepartment', 'creator', 'paymentExpense', 'budgetReturnExpense'])
                ->orderByDesc('created_at')
                ->limit(100)
                ->get();
            $poVendors = $this->accounts->vendorsQuery($hotel->id, ['search' => ''])->get();
            $poStoreDepartments = HotelStoreCategory::query()
                ->where('hotel_id', $hotel->id)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']);
            $poMasterItemsPayload = $this->storeItems->purchaseOrderCatalogPayload($hotel);
        }

        if ($mainTab === 'invoices') {
            $storeInvoices = $this->accounts->purchaseOrdersQuery($hotel->id, [
                'search' => $poSearch,
                'from_date' => $poFrom,
                'to_date' => $poTo,
                'status' => 'all',
                'scope' => 'invoices',
            ])
                ->with(['vendor', 'items'])
                ->paginate(20)
                ->withQueryString();

        }

        $viewId = (int) $request->query('view', 0);
        if ($viewId > 0 && in_array($mainTab, ['purchase-orders', 'invoices'], true)) {
            $viewStoreInvoice = HotelPurchaseOrder::query()
                ->where('hotel_id', $hotel->id)
                ->whereIn('status', [HotelPurchaseOrder::STATUS_OPEN, HotelPurchaseOrder::STATUS_INVOICED])
                ->with(['vendor', 'items'])
                ->whereKey($viewId)
                ->first();

            if ($viewStoreInvoice?->isOpen() && $viewStoreInvoice->canInvoiceFromStoreDesk()) {
                $suggestedStoreInvoiceNumber = $this->accounts->suggestStoreInvoiceNumber($hotel);
            } elseif ($viewStoreInvoice?->isStoreDeskPurchaseOrder() && $viewStoreInvoice->isOpen() && ! $viewStoreInvoice->canInvoiceFromStoreDesk()) {
                $viewStoreInvoice = null;
            }
        }

        $goodsReceiveId = (int) $request->query('goods_receive', 0);
        $goodsReceiveReturnTab = 'invoices';
        if ($goodsReceiveId > 0 && $mainTab === 'invoices') {
            $goodsReceiveOrder = HotelPurchaseOrder::query()
                ->where('hotel_id', $hotel->id)
                ->where('status', HotelPurchaseOrder::STATUS_INVOICED)
                ->with(['vendor', 'items', 'goodsReceivedBy'])
                ->whereKey($goodsReceiveId)
                ->first();
        }

        $categoryTree = collect();
        $pmsServicesForStoreDepartments = collect();
        if ($mainTab === 'settings' && $settingsTab === 'item-category') {
            $this->storeCategories->cleanupItemCategoryLegacyData($hotel);
            $categoryTree = $this->storeCategories->categoriesForHotel($hotel, ['search' => $categorySearch]);
            $pmsServicesForStoreDepartments = HotelPmsService::query()
                ->where('hotel_id', $hotel->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'template_key']);
        }

        $vendors = $mainTab === 'settings' && $settingsTab === 'vendor'
            ? $this->accounts->vendorsQuery($hotel->id, ['search' => $vendorSearch])->get()
            : collect();

        $departments = collect();
        if ($mainTab === 'settings' && $settingsTab === 'department') {
            $this->hotelHr->ensureDefaults($hotel);
            $departments = $this->storeDepartments->departmentsForHotel($hotel, ['search' => $departmentSearch]);
        }

        return view('hotel.erp.stores.index', [
            'hotel' => $hotel,
            'mainTab' => $mainTab,
            'settingsTab' => $settingsTab,
            'filters' => $filters,
            'masterData' => $masterData,
            'stockData' => $stockData,
            'consumptionData' => $consumptionData,
            'itemVendors' => $itemVendors,
            'storeTaxGroups' => $storeTaxGroups,
            'storePackages' => $storePackages,
            'usageTypes' => $usageTypes,
            'categoryTree' => $categoryTree,
            'categorySearch' => $categorySearch,
            'pmsServicesForStoreDepartments' => $pmsServicesForStoreDepartments,
            'vendors' => $vendors,
            'vendorSearch' => $vendorSearch,
            'vendorColumns' => config('hotel_stores.vendor_columns', []),
            'vendorEmailDomains' => $this->staffProfile->emailSuggestions($hotel),
            'vendorEmailPresets' => $this->staffProfile->emailDatalistOptions($hotel),
            'departments' => $departments,
            'departmentSearch' => $departmentSearch,
            'ui' => config('hotel_stores.ui', []),
            'mainTabs' => config('hotel_stores.main_tabs', []),
            'settingsTabs' => config('hotel_stores.settings_tabs', []),
            'purchaseOrders' => $purchaseOrders,
            'storeKeeperPurchaseOrders' => $storeKeeperPurchaseOrders,
            'poSearch' => $poSearch,
            'poFrom' => $poFrom,
            'poTo' => $poTo,
            'poStatus' => $poStatus,
            'poStatusFilters' => config('hotel_stores.po_status_filters', []),
            'poVendors' => $poVendors,
            'poMasterItemsPayload' => $poMasterItemsPayload,
            'poStoreDepartments' => $poStoreDepartments,
            'storePoPermissions' => $this->storePoPermissions(),
            'storeInvoices' => $storeInvoices,
            'viewStoreInvoice' => $viewStoreInvoice,
            'suggestedStoreInvoiceNumber' => $suggestedStoreInvoiceNumber,
            'goodsReceiveOrder' => $goodsReceiveOrder,
            'goodsReceiveReturnTab' => $goodsReceiveReturnTab,
            'storeIndentsList' => $storeIndentsList,
            'indentDepartments' => $indentDepartments,
            'indentDetailRoute' => route('hotel.stores.indents.show', ['indent' => '__ID__']),
            'indentAutoOpenId' => $mainTab === 'indent' ? (int) $request->query('view_indent', 0) : 0,
            'indentCategories' => $indentCategories,
            'indentHideCategory' => true,
            'indentRequesterUsesStoreCategory' => true,
            'indentFilterItemsByDepartment' => true,
            'indentCatalogByDepartment' => $indentCatalogByDepartment ?? [],
            'indentMasterItemsPayload' => $indentMasterItemsPayload,
            'indentStatus' => $indentStatus,
            'indentDepartmentFilter' => $indentDepartmentFilter,
            'indentStatusFilters' => config('hotel_stores.indent_status_filters', []),
            'storeIndentPermissions' => $this->storeIndentPermissions(),
            'storeConsumptionsList' => $storeConsumptionsList,
            'consumptionDetailRoute' => route('hotel.stores.consumptions.show', ['consumption' => '__ID__']),
            'consumptionAutoOpenId' => $mainTab === 'consumption' ? (int) $request->query('view_consumption', 0) : 0,
            'consumptionDepartments' => $consumptionDepartments,
            'consumptionCategories' => $consumptionCategories,
            'consumptionStockCatalog' => $consumptionStockCatalog,
            'consumptionDeptFilter' => $consumptionDeptFilter,
            'storeConsumptionPermissions' => $this->storeConsumptionPermissions(),
            'reportData' => $reportData,
            'reportType' => $reportType,
            'reportSort' => $reportSort,
            'reportTypes' => config('hotel_stores.report_types', []),
            'reportSortOptions' => config('hotel_stores.report_sort_options', []),
            'storeDepartmentPurchaseLists' => $storeDepartmentPurchaseLists,
            'storePlWorkflows' => $storePlWorkflows,
            'storePlDepartments' => $storePlDepartments,
            'canAccessStoresDepartmentLists' => $canAccessStoresDepartmentLists,
            'storePlPendingCount' => $storePlPendingCount,
            'storeIndentPendingCount' => $storeIndentPendingCount,
            'plWorkflowFilter' => $plWorkflowFilter,
            'plDepartmentId' => $plDepartmentId,
            'storePlBatchDepartments' => $storePlBatchDepartments,
            'storePlQuickSubmitTotal' => $storePlQuickSubmitTotal,
        ]);
    }

    public function returnDepartmentPurchaseList(Request $request, HotelBarPurchaseList $purchaseList): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->assertStoreManagesPurchaseList($hotel, auth()->user(), $purchaseList);

        $validated = $request->validate([
            'return_notes' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $this->purchaseLists->returnPurchaseListToDepartment(
                $purchaseList,
                auth()->user(),
                $validated['return_notes'],
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('hotel.stores.index', ['tab' => 'purchase-orders'])
            ->with('success', 'List '.$purchaseList->reference_no.' returned to the department.');
    }

    public function submitDepartmentPurchaseListToAccounts(HotelBarPurchaseList $purchaseList): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->assertStoreManagesPurchaseList($hotel, auth()->user(), $purchaseList);

        try {
            $this->purchaseLists->quickSubmitBudgetToAccounts($purchaseList, auth()->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('hotel.stores.index', ['tab' => 'purchase-orders'])
            ->with('success', 'List '.$purchaseList->reference_no.' sent to Accounts.');
    }

    public function batchSubmitDepartmentPurchaseLists(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless($this->purchaseLists->userCanAccessStoresDepartmentLists($hotel, auth()->user()), 403);

        $validated = $request->validate([
            'department_id' => ['nullable', 'integer', Rule::exists('hotel_departments', 'id')->where('hotel_id', $hotel->id)],
        ]);

        $deptId = isset($validated['department_id']) ? (int) $validated['department_id'] : null;
        if ($deptId === 0) {
            $deptId = null;
        }

        $result = $this->purchaseLists->batchQuickSubmitBudgetsToAccounts($hotel, auth()->user(), $deptId);

        if ($result['submitted'] === []) {
            return redirect()
                ->route('hotel.stores.index', ['tab' => 'purchase-orders'])
                ->with('warning', 'No lists were ready. Open each list, enter unit costs, save a draft, then submit.');
        }

        $message = count($result['submitted']).' list(s) sent to Accounts: '.implode(', ', $result['submitted']).'.';
        if ($result['skipped'] !== []) {
            $message .= ' Skipped: '.implode(', ', $result['skipped']).'.';
        }

        return redirect()
            ->route('hotel.stores.index', ['tab' => 'purchase-orders'])
            ->with('success', $message);
    }

    public function downloadStoreReport(Request $request): StreamedResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $reportType = $this->resolveReportType((string) $request->query('report_type', 'indent'));
        $reportSort = $this->resolveReportSort((string) $request->query('report_sort', 'department'));
        $poFrom = (string) $request->query('po_from', now()->subDays(7)->format('Y-m-d'));
        $poTo = (string) $request->query('po_to', now()->format('Y-m-d'));

        $report = $this->storeReports->build($hotel, [
            'report_type' => $reportType,
            'report_sort' => $reportSort,
            'from_date' => $poFrom,
            'to_date' => $poTo,
        ]);

        $typeLabel = config('hotel_stores.report_types.'.$reportType, $reportType);
        $sortLabel = config('hotel_stores.report_sort_options.'.$reportSort, $reportSort);
        $rows = $this->storeReports->toCsvRows($report, $typeLabel, $sortLabel);
        $filename = 'stores-report-'.$reportType.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function storePurchaseOrder(Request $request): RedirectResponse
    {
        $this->assertStorePoPermission('create');
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $this->validatePurchaseOrderPayload($request, $hotel);
        $items = $this->mapPurchaseOrderItems($validated['items']);

        $nextOrd = (int) HotelPurchaseOrder::query()->where('hotel_id', $hotel->id)->max('id') + 1;

        $totals = [
            'hotel_vendor_id' => (int) $validated['hotel_vendor_id'],
            'hotel_store_category_id' => (int) $validated['hotel_store_category_id'],
            'pre_tax' => collect($items)->sum('pre_tax'),
            'tax' => collect($items)->sum('tax'),
            'total' => collect($items)->sum('total'),
            'po_number' => 'ORD-'.$nextOrd,
            'status' => HotelPurchaseOrder::STATUS_APPLIED,
        ];

        $this->accounts->createPurchaseOrder($hotel, $totals, $items, null);

        return redirect()
            ->route('hotel.stores.index', ['tab' => 'purchase-orders'])
            ->with('success', 'Purchase order created. Confirm buying prices, then submit to Accounts.');
    }

    public function updatePurchaseOrder(Request $request, HotelPurchaseOrder $order): RedirectResponse
    {
        $this->assertStorePoPermission('edit');
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);

        $validated = $this->validatePurchaseOrderPayload($request, $hotel);
        $items = $this->mapPurchaseOrderItems($validated['items']);

        try {
            $this->accounts->updatePurchaseOrder($hotel, $order, [
                'hotel_vendor_id' => (int) $validated['hotel_vendor_id'],
                'hotel_store_category_id' => (int) $validated['hotel_store_category_id'],
                'pre_tax' => collect($items)->sum('pre_tax'),
                'tax' => collect($items)->sum('tax'),
                'total' => collect($items)->sum('total'),
            ], $items);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('hotel.stores.index', ['tab' => 'purchase-orders'])
            ->with('success', 'Purchase order updated.');
    }

    public function approvePurchaseOrder(HotelPurchaseOrder $order): RedirectResponse
    {
        $this->assertStorePoPermission('approve');
        $hotel = auth()->user()->hotel()->firstOrFail();

        try {
            $this->accounts->approveStorePurchaseOrder($hotel, $order);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Purchase order approved.');
    }

    public function submitStorePurchaseOrderToAccounts(HotelPurchaseOrder $order): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);
        $this->assertStorePoPermission('edit');

        try {
            $this->accounts->submitStoreDeskPurchaseOrderToAccounts($hotel, $order);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('hotel.stores.index', ['tab' => 'purchase-orders'])
            ->with('success', 'Purchase order submitted to Accounts for approval.');
    }

    public function invoicePurchaseOrder(Request $request, HotelPurchaseOrder $order): RedirectResponse
    {
        $this->assertStorePoPermission('invoice');
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);

        if ($order->isStoreDeskPurchaseOrder() && ! $order->canInvoiceFromStoreDesk()) {
            return back()->with('error', 'Accounts must approve this purchase order before you can invoice it.');
        }

        $validated = $request->validate([
            'invoice_number' => ['nullable', 'string', 'max:80'],
            'invoiced_at' => ['nullable', 'date'],
        ]);

        $wasOpen = $order->isOpen();

        try {
            $order = $this->accounts->saveStorePurchaseOrderInvoice($hotel, $order, $validated);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $poFrom = $request->input('po_from', $request->query('po_from', now()->subDays(7)->format('Y-m-d')));
        $poTo = $request->input('po_to', $request->query('po_to', now()->format('Y-m-d')));
        $returnTab = $wasOpen ? 'invoices' : (string) $request->input('return_tab', 'invoices');

        $redirectParams = array_filter([
            'tab' => $returnTab,
            'po_from' => $poFrom,
            'po_to' => $poTo,
            'po_search' => $request->input('po_search'),
            'po_status' => $request->input('po_status'),
            'view' => $order->id,
        ], fn ($value) => $value !== null && $value !== '');

        $message = $wasOpen
            ? 'Invoice '.$order->invoice_number.' created for '.$order->po_number.'.'
            : 'Invoice '.$order->invoice_number.' updated.';

        return redirect()
            ->route('hotel.stores.index', $redirectParams)
            ->with('success', $message);
    }

    public function printStorePurchaseOrder(HotelPurchaseOrder $order): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);
        abort_unless($order->canPrintStoreDeskPurchaseList(), 403);

        $order->load(['vendor', 'items.masterItem.storePackage', 'items.masterItem.category', 'storeDepartment', 'creator']);
        $currency = $this->accounts->storeDeskCurrency($hotel);
        $moneyDecimals = in_array($currency, ['TZS', 'UGX', 'RWF', 'BIF', 'KES'], true) ? 0 : 2;

        return view('hotel.erp.stores.purchase-order-print', [
            'hotel' => $hotel,
            'order' => $order,
            'currency' => $currency,
            'moneyDecimals' => $moneyDecimals,
        ]);
    }

    public function printStorePurchaseOrderInvoice(HotelPurchaseOrder $order): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);
        abort_unless($order->isInvoiced(), 404);

        $order->load(['vendor', 'items']);
        $currency = $this->accounts->storeDeskCurrency($hotel);
        $moneyDecimals = in_array($currency, ['TZS', 'UGX', 'RWF', 'BIF', 'KES'], true) ? 0 : 2;

        return view('hotel.erp.stores.invoice-print', [
            'hotel' => $hotel,
            'order' => $order,
            'moneyDecimals' => $moneyDecimals,
        ]);
    }

    public function emailStorePurchaseOrderInvoice(Request $request, HotelPurchaseOrder $order): RedirectResponse
    {
        $this->assertStorePoPermission('invoice');
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);

        if (! $order->isInvoiced()) {
            return back()->with('error', 'Submit the invoice before emailing the vendor.');
        }

        $email = trim((string) ($order->vendor?->email ?? ''));
        if ($email === '') {
            return back()->with('error', 'Add an email address for this vendor under Settings → Vendor.');
        }

        $order->load(['vendor', 'items']);
        Mail::to($email)->send(new StorePurchaseInvoiceMail($hotel, $order));

        $redirectParams = array_filter([
            'tab' => $request->input('return_tab', 'invoices'),
            'po_from' => $request->input('po_from', now()->subDays(7)->format('Y-m-d')),
            'po_to' => $request->input('po_to', now()->format('Y-m-d')),
            'po_search' => $request->input('po_search'),
            'view' => $order->id,
        ], fn ($value) => $value !== null && $value !== '');

        return redirect()
            ->route('hotel.stores.index', $redirectParams)
            ->with('success', 'Invoice emailed to '.$email.'.');
    }

    public function showPurchaseOrderGoodsReceive(HotelPurchaseOrder $order): View|RedirectResponse
    {
        $this->assertStorePoPermission('invoice');
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);

        if (! $order->isStoreDeskPurchaseOrder()) {
            return redirect()
                ->route('hotel.stores.index', ['tab' => 'purchase-orders'])
                ->with('error', 'Use the Invoices tab to receive this order.');
        }

        if ($order->goods_received) {
            return view('hotel.erp.stores.purchase-order-goods-receive', $this->purchaseOrderReceiveViewData($hotel, $order));
        }

        try {
            $order = $this->accounts->ensureStoreDeskInvoicedForGoodsReceive($hotel, $order);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('hotel.stores.index', ['tab' => 'purchase-orders'])
                ->with('error', $e->getMessage());
        }

        return view('hotel.erp.stores.purchase-order-goods-receive', $this->purchaseOrderReceiveViewData($hotel, $order));
    }

    public function savePurchaseOrderReceiveDraft(Request $request, HotelPurchaseOrder $order): RedirectResponse
    {
        $this->assertStorePoPermission('invoice');
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);

        if ($order->goods_received) {
            return back()->with('error', 'Goods receipt was already finalized.');
        }

        $statusKeys = $this->storeGoodsReceive->receiveStatusKeys();

        $validated = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*.receive_status' => ['required', 'string', Rule::in($statusKeys)],
            'lines.*.received_quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.received_unit_rate' => ['nullable', 'numeric', 'min:0'],
            'lines.*.receive_notes' => ['nullable', 'string', 'max:500'],
            'lines.*.hotel_store_package_id' => [
                'nullable',
                'integer',
                Rule::exists('hotel_store_packages', 'id')->where('hotel_id', $hotel->id),
            ],
            'goods_receive_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            if ($order->isOpen() && ! $order->isPaid()) {
                $order = $this->accounts->ensureStoreDeskInvoicedForGoodsReceive($hotel, $order);
            }
            $this->storeGoodsReceive->saveDraft(
                $hotel,
                $order,
                $validated['lines'],
                $validated['goods_receive_notes'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('hotel.stores.purchase-orders.receive', $order)
            ->with('success', 'Draft saved for '.$order->po_number.'.');
    }

    /** @return array<string, mixed> */
    private function purchaseOrderReceiveViewData(Hotel $hotel, HotelPurchaseOrder $order): array
    {
        $order->load([
            'vendor',
            'items.masterItem.category',
            'items.masterItem.storePackage',
            'items.storePackage',
            'goodsReceivedBy',
            'storeDepartment',
        ]);

        $packages = HotelStorePackage::query()
            ->where('hotel_id', $hotel->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $currency = $this->accounts->storeDeskCurrency($hotel);

        return [
            'hotel' => $hotel,
            'goodsReceiveOrder' => $order,
            'storePoPermissions' => $this->storePoPermissions(),
            'packages' => $packages,
            'currency' => $currency,
            'readOnly' => $order->goods_received,
        ];
    }

    public function storeGoodsReceive(Request $request, HotelPurchaseOrder $order): RedirectResponse
    {
        $this->assertStorePoPermission('invoice');
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);

        if ($order->goods_received) {
            return back()->with('error', 'Goods receipt was already finalized.');
        }

        $statusKeys = $this->storeGoodsReceive->receiveStatusKeys();

        $validated = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*.receive_status' => ['required', 'string', Rule::in($statusKeys)],
            'lines.*.received_quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.received_unit_rate' => ['nullable', 'numeric', 'min:0'],
            'lines.*.receive_notes' => ['nullable', 'string', 'max:500'],
            'lines.*.hotel_store_package_id' => [
                'nullable',
                'integer',
                Rule::exists('hotel_store_packages', 'id')->where('hotel_id', $hotel->id),
            ],
            'goods_receive_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->storeGoodsReceive->finalize(
                $hotel,
                $order,
                $validated['lines'],
                $validated['goods_receive_notes'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $returnTab = (string) $request->input('return_tab', 'purchase-orders');
        if (! in_array($returnTab, ['purchase-orders', 'invoices'], true)) {
            $returnTab = 'purchase-orders';
        }

        if ($returnTab === 'purchase-orders') {
            return redirect()
                ->route('hotel.stores.index', ['tab' => 'purchase-orders'])
                ->with('success', 'Goods receipt saved for '.$order->po_number.'.');
        }

        $redirectParams = array_filter([
            'tab' => 'invoices',
            'po_from' => $request->input('po_from'),
            'po_to' => $request->input('po_to'),
            'po_search' => $request->input('po_search'),
            'goods_receive' => $order->id,
        ], fn ($value) => $value !== null && $value !== '');

        return redirect()
            ->route('hotel.stores.index', $redirectParams)
            ->with('success', 'Goods receipt saved for '.$order->po_number.'.');
    }

    public function showConsumption(HotelStoreConsumption $consumption): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $consumption->hotel_id === (int) $hotel->id, 404);

        $consumption->load([
            'department',
            'items.roomUnit',
            'items.masterItem.sellOptions.storePackage',
            'items.masterItem.storePackage',
        ]);

        return view('hotel.erp.stores.partials.consumption-detail-ajax', [
            'viewStoreConsumption' => $consumption,
            'consumptionShowRoomDistribution' => false,
        ]);
    }

    public function showIndent(HotelStoreIndent $indent): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $indent->hotel_id === (int) $hotel->id, 404);

        $indent->load(['department', 'storeCategory', 'items.masterItem', 'receivedByUser']);

        return view('hotel.erp.stores.partials.indent-detail-ajax', [
            'viewStoreIndent' => $indent,
            'hotel' => $hotel,
        ]);
    }

    public function storeConsumption(Request $request): RedirectResponse
    {
        $this->assertStoreConsumptionPermission('create');
        $hotel = auth()->user()->hotel()->firstOrFail();
        $validated = $this->validateConsumptionPayload($request, $hotel);
        $lines = $this->mapConsumptionItems($validated['items']);

        try {
            $consumption = $this->storeConsumption->createConsumption(
                $hotel,
                (int) $validated['hotel_department_id'],
                $lines,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('hotel.stores.index', [
                'tab' => 'consumption',
                'department_id' => $consumption->hotel_department_id,
                'view_consumption' => $consumption->id,
            ])
            ->with('success', 'Consumption '.$consumption->consumption_number.' recorded.');
    }

    public function storeIndent(Request $request): RedirectResponse
    {
        $this->assertStoreIndentPermission('create');
        $hotel = auth()->user()->hotel()->firstOrFail();
        $validated = $this->validateIndentPayload($request, $hotel);
        $lines = $this->mapIndentItems($hotel, $validated['items']);

        try {
            $departmentId = $this->resolveIndentRequesterDepartmentId($hotel, $request);
            $storeCategoryId = $request->filled('hotel_store_category_id')
                ? (int) $request->input('hotel_store_category_id')
                : null;
            $indent = $this->storeIndents->createIndent($hotel, $departmentId, $lines, $storeCategoryId);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('hotel.stores.index', ['tab' => 'indent'])
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('hotel.stores.index', [
                'tab' => 'indent',
                'view_indent' => $indent->id,
            ])
            ->with('success', 'Indent '.$indent->indent_number.' created.');
    }

    public function updateIndent(Request $request, HotelStoreIndent $indent): RedirectResponse
    {
        $this->assertStoreIndentPermission('edit');
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $indent->hotel_id === (int) $hotel->id, 404);

        $validated = $this->validateIndentPayload($request, $hotel);
        $lines = $this->mapIndentItems($hotel, $validated['items']);

        try {
            $departmentId = $this->resolveIndentRequesterDepartmentId($hotel, $request);
            $storeCategoryId = $request->filled('hotel_store_category_id')
                ? (int) $request->input('hotel_store_category_id')
                : null;
            $this->storeIndents->updateIndent($hotel, $indent, $departmentId, $lines, $storeCategoryId);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('hotel.stores.index', ['tab' => 'indent'])
            ->with('success', 'Indent updated.');
    }

    public function approveIndent(HotelStoreIndent $indent): RedirectResponse
    {
        $this->assertStoreIndentPermission('approve');
        $hotel = auth()->user()->hotel()->firstOrFail();

        try {
            $this->storeIndents->approveIndent($hotel, $indent);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $indent->load('department');

        return back()->with(
            'success',
            'Indent '.$indent->indent_number.' approved. Stock left main store for '.($indent->department?->name ?? 'department').' to receive.'
        );
    }

    public function receiveIndent(HotelStoreIndent $indent): RedirectResponse
    {
        if (! config('hotel_stores.indent_store_can_receive', false)) {
            return back()->with(
                'error',
                'This indent must be received by the requesting department (ERP → Department Stores or Maintenance & HK → Request from store).'
            );
        }

        $receiveKeys = config('hotel_stores.indent_receive_permission_keys', []);
        $key = $receiveKeys['receive'] ?? 'stores.indent.receive';
        abort_unless(auth()->user()->hasPermission($key), 403);

        $hotel = auth()->user()->hotel()->firstOrFail();

        try {
            $this->storeIndents->receiveIndent($hotel, $indent);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $indent->load('department');

        return back()->with(
            'success',
            'Indent '.$indent->indent_number.' received into '.($indent->department?->name ?? 'department').' stock.'
        );
    }

    public function destroyIndent(HotelStoreIndent $indent): RedirectResponse
    {
        $this->assertStoreIndentPermission('delete');
        $hotel = auth()->user()->hotel()->firstOrFail();

        try {
            $this->storeIndents->deleteIndent($hotel, $indent);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Indent deleted.');
    }

    public function destroyPurchaseOrder(HotelPurchaseOrder $order): RedirectResponse
    {
        $this->assertStorePoPermission('delete');
        $hotel = auth()->user()->hotel()->firstOrFail();

        try {
            $this->accounts->deletePurchaseOrder($hotel, $order);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Purchase order removed.');
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $validated = $this->validateItem($request, $hotel);

        try {
            $this->storeItems->createItem($hotel, $validated);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Item added.');
    }

    public function updateItem(Request $request, HotelStoreMasterItem $item): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $validated = $this->validateItem($request, $hotel);

        try {
            $this->storeItems->updateItem($hotel, $item, $validated);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Item updated.');
    }

    public function destroyItem(HotelStoreMasterItem $item): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->storeItems->deleteItem($hotel, $item);

        return back()->with('success', 'Item removed.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'hotel_pms_service_id' => [
                'required',
                'integer',
                Rule::exists('hotel_pms_services', 'id')->where(fn ($q) => $q->where('hotel_id', $hotel->id)->where('is_active', true)),
            ],
        ]);

        try {
            $category = $this->storeCategories->createCategory(
                $hotel,
                $validated['name'],
                (int) $validated['hotel_pms_service_id']
            );
        } catch (InvalidArgumentException $e) {
            return $this->itemCategoryRedirect(null, $e->getMessage())->withInput();
        }

        return $this->itemCategoryRedirect('Department '.$category->name.' added.');
    }

    public function updateCategory(Request $request, HotelStoreCategory $category): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $category->hotel_id === (int) $hotel->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'hotel_pms_service_id' => [
                'required',
                'integer',
                Rule::exists('hotel_pms_services', 'id')->where(fn ($q) => $q->where('hotel_id', $hotel->id)->where('is_active', true)),
            ],
        ]);

        try {
            $category = $this->storeCategories->updateCategory(
                $hotel,
                $category,
                $validated['name'],
                (int) $validated['hotel_pms_service_id']
            );
        } catch (InvalidArgumentException $e) {
            return $this->itemCategoryRedirect(null, $e->getMessage())->withInput();
        }

        return $this->itemCategoryRedirect('Department '.$category->name.' updated.');
    }

    public function categoryCatalogOptions(HotelStoreCategory $category): JsonResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        return response()->json(
            $this->storeCategories->catalogOptionsForCategory($hotel, $category)
        );
    }

    public function itemSellPriceRecommendations(Request $request): JsonResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('hotel_store_categories', 'id')->where('hotel_id', $hotel->id),
            ],
            'item_id' => [
                'nullable',
                'integer',
                Rule::exists('hotel_store_master_items', 'id')->where('hotel_id', $hotel->id),
            ],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'pieces_per_receive_unit' => ['nullable', 'numeric', 'min:0.01'],
            'sell_units' => ['nullable', 'array'],
            'sell_units.*.package_id' => ['nullable', 'integer'],
            'sell_units.*.pieces_per_sell_unit' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        return response()->json(
            $this->storeItems->recommendedSellPricesPayload(
                $hotel,
                (int) $validated['category_id'],
                (string) $validated['name'],
                isset($validated['item_id']) ? (int) $validated['item_id'] : null,
                isset($validated['rate']) ? (float) $validated['rate'] : null,
                isset($validated['pieces_per_receive_unit']) ? (float) $validated['pieces_per_receive_unit'] : null,
                $validated['sell_units'] ?? [],
            )
        );
    }

    public function destroyCategory(HotelStoreCategory $category): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $name = $category->name;

        try {
            $this->storeCategories->deleteCategory($hotel, $category);
        } catch (InvalidArgumentException $e) {
            return $this->itemCategoryRedirect(null, $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return $this->itemCategoryRedirect(null, 'Could not delete this category. Remove master items using it first.');
        }

        return $this->itemCategoryRedirect('Department '.$name.' removed.');
    }

    private function itemCategoryRedirect(?string $success = null, ?string $error = null): RedirectResponse
    {
        $redirect = redirect()->route('hotel.stores.index', [
            'tab' => 'settings',
            'settings' => 'item-category',
        ]);

        if ($success) {
            return $redirect->with('success', $success);
        }

        return $redirect->with('error', $error ?? 'Something went wrong.');
    }

    public function storeVendor(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $validated = $this->validatedVendorPayload($request);

        $vendor = $this->accounts->createVendor($hotel, $validated);

        return $this->vendorSettingsRedirect('Vendor '.$vendor->name.' added.');
    }

    public function updateVendor(Request $request, HotelVendor $vendor): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $validated = $this->validatedVendorPayload($request);

        $vendor = $this->accounts->updateVendor($hotel, $vendor, $validated);

        return $this->vendorSettingsRedirect('Vendor '.$vendor->name.' updated.');
    }

    public function destroyVendor(HotelVendor $vendor): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        try {
            $name = $vendor->name;
            $this->accounts->deleteVendor($hotel, $vendor);
        } catch (InvalidArgumentException $e) {
            return $this->vendorSettingsRedirect(null, $e->getMessage());
        }

        return $this->vendorSettingsRedirect('Vendor '.$name.' removed.');
    }

    private function vendorSettingsRedirect(?string $success = null, ?string $error = null): RedirectResponse
    {
        $redirect = redirect()->route('hotel.stores.index', ['tab' => 'settings', 'settings' => 'vendor']);

        if ($success) {
            return $redirect->with('success', $success);
        }

        return $redirect->with('error', $error ?? 'Something went wrong.');
    }

    /** @return array<string, mixed> */
    private function validatedVendorPayload(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'gst_num' => ['nullable', 'string', 'max:80'],
            'phone_country_code' => ['nullable', 'string', 'max:8'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'state' => ['nullable', 'string', 'max:80', Rule::in(array_merge([''], config('tanzania_regions', [])))],
            'address' => ['nullable', 'string', 'max:500'],
            'account_no' => ['nullable', 'string', 'max:64'],
            'bank_name' => ['nullable', 'string', 'max:160'],
        ]);

        $phoneLocal = ltrim(trim((string) ($validated['phone'] ?? '')), '0');
        $phoneCode = trim((string) ($validated['phone_country_code'] ?? ''));
        $validated['phone'] = $phoneLocal !== ''
            ? trim($phoneCode.$phoneLocal)
            : null;
        unset($validated['phone_country_code']);

        return $validated;
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        try {
            $validated = $this->validatedDepartmentPayload($request);
            $department = $this->storeDepartments->createDepartment($hotel, $validated);
        } catch (InvalidArgumentException $e) {
            return $this->departmentSettingsRedirect(null, $e->getMessage())->withInput();
        }

        return $this->departmentSettingsRedirect('Department '.$department->name.' added.');
    }

    public function updateDepartment(Request $request, HotelDepartment $department): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        try {
            $validated = $this->validatedDepartmentPayload($request);
            $department = $this->storeDepartments->updateDepartment($hotel, $department, $validated);
        } catch (InvalidArgumentException $e) {
            return $this->departmentSettingsRedirect(null, $e->getMessage())->withInput();
        }

        return $this->departmentSettingsRedirect('Department '.$department->name.' updated.');
    }

    public function destroyDepartment(HotelDepartment $department): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        try {
            $name = $department->name;
            $this->storeDepartments->deleteDepartment($hotel, $department);
        } catch (InvalidArgumentException $e) {
            return $this->departmentSettingsRedirect(null, $e->getMessage());
        }

        return $this->departmentSettingsRedirect('Department '.$name.' removed.');
    }

    public function storeTaxGroup(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        try {
            $group = $this->storeCatalog->createTaxGroup(
                $hotel,
                $validated['name'],
                isset($validated['rate_percent']) ? (float) $validated['rate_percent'] : null
            );
        } catch (InvalidArgumentException $e) {
            return $this->catalogSettingsRedirect('tax-groups', null, $e->getMessage())->withInput();
        }

        return $this->catalogSettingsRedirect('tax-groups', 'Tax group '.$group->name.' added.');
    }

    public function updateTaxGroup(Request $request, HotelStoreTaxGroup $taxGroup): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        try {
            $group = $this->storeCatalog->updateTaxGroup(
                $hotel,
                $taxGroup,
                $validated['name'],
                isset($validated['rate_percent']) ? (float) $validated['rate_percent'] : null
            );
        } catch (InvalidArgumentException $e) {
            return $this->catalogSettingsRedirect('tax-groups', null, $e->getMessage())->withInput();
        }

        return $this->catalogSettingsRedirect('tax-groups', 'Tax group '.$group->name.' updated.');
    }

    public function destroyTaxGroup(HotelStoreTaxGroup $taxGroup): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $name = $taxGroup->name;
        $this->storeCatalog->deleteTaxGroup($hotel, $taxGroup);

        return $this->catalogSettingsRedirect('tax-groups', 'Tax group '.$name.' removed.');
    }

    public function storePackage(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'abbreviation' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            $package = $this->storeCatalog->createPackage(
                $hotel,
                $validated['name'],
                $validated['abbreviation'] ?? null
            );
        } catch (InvalidArgumentException $e) {
            return $this->catalogSettingsRedirect('units-packaging', null, $e->getMessage())->withInput();
        }

        return $this->catalogSettingsRedirect('units-packaging', 'Unit '.$package->displayLabel().' added.');
    }

    public function updatePackage(Request $request, HotelStorePackage $package): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'abbreviation' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            $package = $this->storeCatalog->updatePackage(
                $hotel,
                $package,
                $validated['name'],
                $validated['abbreviation'] ?? null
            );
        } catch (InvalidArgumentException $e) {
            return $this->catalogSettingsRedirect('units-packaging', null, $e->getMessage())->withInput();
        }

        return $this->catalogSettingsRedirect('units-packaging', 'Unit '.$package->displayLabel().' updated.');
    }

    public function destroyPackage(HotelStorePackage $package): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $name = $package->displayLabel();
        $this->storeCatalog->deletePackage($hotel, $package);

        return $this->catalogSettingsRedirect('units-packaging', 'Unit '.$name.' removed.');
    }

    private function catalogSettingsRedirect(string $settingsTab, ?string $success = null, ?string $error = null): RedirectResponse
    {
        $redirect = redirect()->route('hotel.stores.index', ['tab' => 'settings', 'settings' => $settingsTab]);

        if ($success) {
            return $redirect->with('success', $success);
        }

        return $redirect->with('error', $error ?? 'Something went wrong.');
    }

    private function departmentSettingsRedirect(?string $success = null, ?string $error = null): RedirectResponse
    {
        $redirect = redirect()->route('hotel.stores.index', ['tab' => 'settings', 'settings' => 'department']);

        if ($success) {
            return $redirect->with('success', $success);
        }

        return $redirect->with('error', $error ?? 'Something went wrong.');
    }

    /** @return array<string, mixed> */
    private function validatedDepartmentPayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'amount_issued' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function storesRedirectParams(Request $request): array
    {
        return [
            'tab' => 'settings',
            'settings' => $request->input('redirect_settings', 'master-items'),
        ];
    }

    /** @return array<string, mixed> */
    private function validateItem(Request $request, \App\Models\Hotel $hotel): array
    {
        $packageRule = Rule::exists('hotel_store_packages', 'id')->where('hotel_id', $hotel->id);
        $usage = (string) $request->input('usage_type', 'internal');

        $hasPackages = HotelStorePackage::query()->where('hotel_id', $hotel->id)->exists();

        $rules = [
            'name' => ['required', 'string', 'max:160'],
            'hotel_store_category_id' => [
                'required',
                'integer',
                Rule::exists('hotel_store_categories', 'id')->where('hotel_id', $hotel->id),
            ],
            'hotel_pms_service_category_id' => ['nullable', 'integer'],
            'hotel_vendor_id' => [
                'nullable',
                'integer',
                Rule::exists('hotel_vendors', 'id')->where('hotel_id', $hotel->id),
            ],
            'hotel_store_tax_group_id' => [
                'nullable',
                'integer',
                Rule::exists('hotel_store_tax_groups', 'id')->where('hotel_id', $hotel->id),
            ],
            'hotel_store_package_id' => [
                $hasPackages ? 'required' : 'nullable',
                'integer',
                $packageRule,
            ],
            'pieces_per_receive_unit' => [
                $hasPackages ? 'required' : 'nullable',
                'numeric',
                'min:0.01',
            ],
            'usage_type' => ['required', 'in:sale,internal'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'tax_inclusive' => ['nullable', 'in:0,1'],
            'inventoried' => ['nullable', 'in:0,1'],
            'capital_item' => ['nullable', 'in:0,1'],
            'stock' => ['nullable', 'numeric', 'min:0'],
            'selling_options' => ['nullable', 'array'],
            'selling_options.*.package_id' => ['nullable', 'integer', $packageRule],
            'selling_options.*.pieces_per_sell_unit' => ['nullable', 'numeric', 'min:0.01'],
            'selling_options.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];

        if ($hasPackages && in_array($usage, ['sale', 'internal'], true)) {
            $rules['selling_options'] = ['required', 'array', 'min:1'];
            $rules['selling_options.*.package_id'] = ['required', 'integer', $packageRule];
            $rules['selling_options.*.pieces_per_sell_unit'] = ['required', 'numeric', 'min:0.01'];
        }

        return $request->validate($rules, [], [
            'hotel_store_package_id' => 'receiving unit',
            'pieces_per_receive_unit' => 'pieces per receiving unit',
            'rate' => 'buying price',
            'selling_options' => 'usage unit',
            'selling_options.*.package_id' => 'usage unit',
            'selling_options.*.pieces_per_sell_unit' => 'pieces per usage unit',
            'selling_options.*.unit_price' => 'sell price',
        ]);
    }

    /** @return array<string, mixed> */
    private function validatePurchaseOrderPayload(Request $request, \App\Models\Hotel $hotel): array
    {
        return $request->validate([
            'hotel_vendor_id' => [
                'required',
                'integer',
                Rule::exists('hotel_vendors', 'id')->where('hotel_id', $hotel->id),
            ],
            'hotel_store_category_id' => [
                'required',
                'integer',
                Rule::exists('hotel_store_categories', 'id')->where('hotel_id', $hotel->id),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:160'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.rate' => ['required', 'numeric', 'min:0.01'],
            'items.*.pre_tax' => ['required', 'numeric', 'min:0'],
            'items.*.tax' => ['required', 'numeric', 'min:0'],
            'items.*.total' => ['required', 'numeric', 'min:0'],
            'items.*.master_item_id' => ['nullable', 'integer'],
        ]);
    }

    /** @param  array<int, array<string, mixed>>  $rows
     * @return list<array<string, float|string|int|null>>
     */
    private function mapPurchaseOrderItems(array $rows): array
    {
        return collect($rows)->map(function (array $item) {
            $masterId = (int) ($item['master_item_id'] ?? 0);

            return [
                'hotel_store_master_item_id' => $masterId > 0 ? $masterId : null,
                'name' => $item['name'],
                'quantity' => (float) $item['quantity'],
                'rate' => (float) $item['rate'],
                'pre_tax' => (float) $item['pre_tax'],
                'tax' => (float) $item['tax'],
                'total' => (float) $item['total'],
            ];
        })->all();
    }

    /** @return array<string, mixed> */
    private function validateIndentPayload(Request $request, Hotel $hotel): array
    {
        $rules = [
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:160'],
            'items.*.master_item_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:40'],
            'items.*.comments' => ['nullable', 'string', 'max:500'],
        ];

        if ($request->filled('hotel_store_category_id')) {
            $rules['hotel_store_category_id'] = [
                'required',
                'integer',
                Rule::exists('hotel_store_categories', 'id')->where('hotel_id', $hotel->id),
            ];
        } else {
            $rules['hotel_department_id'] = [
                'required',
                'integer',
                Rule::exists('hotel_departments', 'id')->where('hotel_id', $hotel->id),
            ];
        }

        return $request->validate($rules);
    }

    private function resolveIndentRequesterDepartmentId(Hotel $hotel, Request $request): int
    {
        if ($request->filled('hotel_store_category_id')) {
            return $this->storeItems->resolveHotelDepartmentIdForStoreCategory(
                $hotel,
                (int) $request->input('hotel_store_category_id')
            );
        }

        return (int) $request->input('hotel_department_id');
    }

    /** @param  array<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function mapIndentItems(Hotel $hotel, array $rows): array
    {
        return collect($rows)->map(function (array $item) use ($hotel) {
            $masterId = (int) ($item['master_item_id'] ?? 0);
            $quantity = (float) $item['quantity'];
            $preTax = 0.0;

            if ($masterId > 0) {
                $master = HotelStoreMasterItem::query()
                    ->where('hotel_id', $hotel->id)
                    ->whereKey($masterId)
                    ->first();
                if ($master) {
                    $preTax = round($quantity * (float) $master->rate, 2);
                }
            }

            return [
                'hotel_store_master_item_id' => $masterId > 0 ? $masterId : null,
                'name' => $item['name'],
                'quantity' => $quantity,
                'unit' => trim((string) ($item['unit'] ?? '')) ?: null,
                'pre_tax' => $preTax,
                'comments' => trim((string) ($item['comments'] ?? '')) ?: null,
            ];
        })->all();
    }

    /** @return array<string, mixed> */
    private function validateConsumptionPayload(Request $request, Hotel $hotel): array
    {
        return $request->validate([
            'hotel_department_id' => [
                'required',
                'integer',
                Rule::exists('hotel_departments', 'id')->where('hotel_id', $hotel->id),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:160'],
            'items.*.master_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:40'],
            'items.*.comments' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /** @param  array<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function mapConsumptionItems(array $rows): array
    {
        return collect($rows)->map(function (array $item) {
            $masterId = (int) ($item['master_item_id'] ?? 0);

            return [
                'hotel_store_master_item_id' => $masterId,
                'name' => $item['name'],
                'quantity' => (float) $item['quantity'],
                'unit' => trim((string) ($item['unit'] ?? '')) ?: null,
                'comments' => trim((string) ($item['comments'] ?? '')) ?: null,
            ];
        })->all();
    }

    private function resolveReportType(string $type): string
    {
        $types = config('hotel_stores.report_types', []);

        return array_key_exists($type, $types) ? $type : 'indent';
    }

    private function resolveReportSort(string $sort): string
    {
        $options = config('hotel_stores.report_sort_options', []);

        return array_key_exists($sort, $options) ? $sort : 'department';
    }

    private function assertStoreManagesPurchaseList(Hotel $hotel, \App\Models\User $user, HotelBarPurchaseList $purchaseList): void
    {
        abort_unless((int) $purchaseList->hotel_id === (int) $hotel->id, 404);
        abort_unless(
            $this->purchaseLists->userCanViewReceivedPurchaseList($hotel, $user, $purchaseList),
            403,
        );
    }

    private function resolveMainTab(string $tab): string
    {
        if ($tab === 'purchase-lists') {
            $tab = 'purchase-orders';
        }

        $tabs = config('hotel_stores.main_tabs', []);

        if (array_key_exists($tab, $tabs)) {
            return $tab;
        }

        return array_key_exists('purchase-orders', $tabs) ? 'purchase-orders' : 'settings';
    }

    private function resolveSettingsTab(string $tab): string
    {
        $tabs = config('hotel_stores.settings_tabs', []);

        return array_key_exists($tab, $tabs) ? $tab : 'master-items';
    }
}
