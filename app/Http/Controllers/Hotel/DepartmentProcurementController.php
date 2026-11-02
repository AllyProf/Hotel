<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\HotelBarPurchaseList;
use App\Models\HotelExpense;
use App\Models\HotelPmsService;
use App\Models\HotelPmsServiceItem;
use App\Services\AccountsDataService;
use App\Services\Bar\BarCatalogService;
use App\Services\Bar\BarPurchaseListService;
use App\Services\Hr\HotelHrService;
use App\Services\PmsServiceItemDataService;
use App\Services\Stores\StoreMasterItemService;
use App\Support\DepartmentProcurement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class DepartmentProcurementController extends Controller
{
    public function __construct(
        private BarCatalogService $barCatalog,
        private PmsServiceItemDataService $items,
        private BarPurchaseListService $purchaseLists,
        private HotelHrService $hr,
        private StoreMasterItemService $storeMasterItems,
        private AccountsDataService $accounts,
    ) {}

    public function createPurchaseList(Request $request): View|RedirectResponse
    {
        $workflow = $this->workflowKey($request);
        $hotel = auth()->user()->hotel()->firstOrFail();
        $config = DepartmentProcurement::workflow($workflow);

        if (! $this->purchaseLists->hasRecipients($hotel, $workflow)) {
            return redirect()
                ->route('hotel.settings.index', ['tab' => $config['settings_tab']])
                ->with('error', 'Configure purchase list recipients under Settings → '.$config['label'].' first.');
        }

        $this->hr->ensureDefaults($hotel);
        $this->purchaseLists->ensureProcurementDepartments($hotel, $workflow);

        $departments = $this->hr->departments($hotel)->where('is_active', true);
        $defaultDepartmentId = $this->purchaseLists->resolveDepartmentId($hotel, null, $workflow);

        if (($config['line_mode'] ?? 'manual') === 'catalog') {
            $service = $this->catalogServiceOrRedirect($hotel, $workflow);
            if ($service instanceof RedirectResponse) {
                return $service;
            }

            $this->barCatalog->ensureCatalogReady($hotel, $service);
            $service->load(['serviceCategories', 'servicePackages']);

            $catalogItems = $this->catalogItemsForDepartment($hotel, $service, $defaultDepartmentId, $workflow);

            return view('hotel.erp.bar.purchase-list-create', [
                'hotel' => $hotel,
                'service' => $service,
                'items' => $catalogItems,
                'packages' => $service->servicePackages->where('is_active', true),
                'departments' => $departments,
                'defaultDepartmentId' => $defaultDepartmentId,
                'lowStockItems' => $this->storeMasterItems->filterServiceCatalogForDepartment(
                    $hotel,
                    $service,
                    $this->barCatalog->lowStockItems($service),
                    (int) ($defaultDepartmentId ?? 0),
                    $workflow,
                ),
                'ui' => $this->barCatalog->uiConfig(),
                'procWorkflow' => $workflow,
            ]);
        }

        $requestingDept = $departments->firstWhere('id', $defaultDepartmentId);
        $catalogByDepartment = [];
        foreach ($departments as $department) {
            $catalogByDepartment[(int) $department->id] = $this->storeMasterItems->purchaseListCatalogForDepartment(
                $hotel,
                (int) $department->id,
                $workflow,
            );
        }
        $itemsByDepartment = [];
        foreach ($catalogByDepartment as $deptKey => $rows) {
            $itemsByDepartment[(string) $deptKey] = collect($rows)->pluck('name')->values()->all();
        }
        $defaultKey = (string) ($defaultDepartmentId ?: 0);
        $suggestedItemNames = collect($itemsByDepartment[$defaultKey] ?? []);

        return view('hotel.erp.procurement.purchase-list-create-manual', [
            'hotel' => $hotel,
            'departments' => $departments,
            'defaultDepartmentId' => $defaultDepartmentId,
            'requestingDepartmentName' => $requestingDept?->name ?? $config['label'],
            'lockRequestingDepartment' => in_array($workflow, ['bar', 'reception', 'laundry'], true),
            'suggestedItemNames' => $suggestedItemNames,
            'itemsByDepartment' => $itemsByDepartment,
            'catalogByDepartment' => $catalogByDepartment,
            'procWorkflow' => $workflow,
            'procLabel' => $config['label'],
        ]);
    }

    public function storePurchaseList(Request $request): RedirectResponse
    {
        $workflow = $this->workflowKey($request);
        $hotel = auth()->user()->hotel()->firstOrFail();
        $config = DepartmentProcurement::workflow($workflow);
        $departmentRule = Rule::exists('hotel_departments', 'id')->where('hotel_id', $hotel->id);

        if (($config['line_mode'] ?? 'manual') === 'catalog') {
            return $this->storeCatalogPurchaseList($request, $hotel, $workflow);
        }

        $validated = $request->validate([
            'hotel_department_id' => ['required', 'integer', $departmentRule],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_name' => ['required', 'string', 'max:160'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit' => ['nullable', 'string', 'max:40'],
            'lines.*.line_notes' => ['nullable', 'string', 'max:255'],
        ]);

        if (in_array($workflow, ['bar', 'reception', 'laundry'], true)) {
            $resolvedDept = $this->purchaseLists->resolveDepartmentId($hotel, null, $workflow);
            if ($resolvedDept) {
                $validated['hotel_department_id'] = $resolvedDept;
            }
        }

        $lines = [];
        foreach ($validated['lines'] as $line) {
            $name = trim((string) $line['item_name']);
            if ($name === '') {
                continue;
            }
            $lines[] = [
                'item_name' => $name,
                'quantity' => (float) $line['quantity'],
                'unit' => trim((string) ($line['unit'] ?? '')) ?: null,
                'line_notes' => trim((string) ($line['line_notes'] ?? '')) ?: null,
            ];
        }

        if ($lines === []) {
            return redirect()
                ->to(DepartmentProcurement::route($workflow, 'purchase-lists.create'))
                ->withInput()
                ->with('error', 'Add at least one item to the purchase list.');
        }

        return $this->sendList($hotel, $workflow, $validated['notes'] ?? null, $lines, (int) $validated['hotel_department_id']);
    }

    public function editReturnedPurchaseList(Request $request, HotelBarPurchaseList $purchaseList): View|RedirectResponse
    {
        $workflow = $this->workflowKey($request);
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->assertListAccess($hotel, $purchaseList, $workflow);

        if (! $purchaseList->isReturnedToDepartment()) {
            return redirect()
                ->to(DepartmentProcurement::route($workflow, 'purchase-lists.show', $purchaseList));
        }

        $config = DepartmentProcurement::workflow($workflow);
        $purchaseList->load(['lines', 'department']);

        if (($config['line_mode'] ?? 'manual') === 'catalog') {
            $service = $this->catalogServiceOrRedirect($hotel, $workflow);
            if ($service instanceof RedirectResponse) {
                return $service;
            }

            $this->barCatalog->ensureCatalogReady($hotel, $service);
            $service->load(['serviceCategories', 'servicePackages']);
            $departments = $this->hr->departments($hotel)->where('is_active', true);
            $defaultDepartmentId = (int) ($purchaseList->hotel_department_id ?: $this->purchaseLists->resolveDepartmentId($hotel, null, $workflow));

            $catalogItems = $this->catalogItemsForDepartment($hotel, $service, $defaultDepartmentId, $workflow);

            return view('hotel.erp.bar.purchase-list-create', [
                'hotel' => $hotel,
                'service' => $service,
                'items' => $catalogItems,
                'packages' => $service->servicePackages->where('is_active', true),
                'departments' => $departments,
                'defaultDepartmentId' => $defaultDepartmentId,
                'lowStockItems' => collect(),
                'ui' => $this->barCatalog->uiConfig(),
                'procWorkflow' => $workflow,
                'reviseList' => $purchaseList,
                'initialFormLines' => $this->catalogFormLinesFromList($service, $purchaseList),
            ]);
        }

        $this->hr->ensureDefaults($hotel);
        $this->purchaseLists->ensureProcurementDepartments($hotel, $workflow);
        $departments = $this->hr->departments($hotel)->where('is_active', true);
        $defaultDepartmentId = (int) ($purchaseList->hotel_department_id ?: $this->purchaseLists->resolveDepartmentId($hotel, null, $workflow));
        $requestingDept = $departments->firstWhere('id', $defaultDepartmentId);
        $catalogByDepartment = [];
        foreach ($departments as $department) {
            $catalogByDepartment[(int) $department->id] = $this->storeMasterItems->purchaseListCatalogForDepartment(
                $hotel,
                (int) $department->id,
                $workflow,
            );
        }
        $itemsByDepartment = [];
        foreach ($catalogByDepartment as $deptKey => $rows) {
            $itemsByDepartment[(string) $deptKey] = collect($rows)->pluck('name')->values()->all();
        }
        $defaultKey = (string) ($defaultDepartmentId ?: 0);
        $suggestedItemNames = collect($itemsByDepartment[$defaultKey] ?? []);

        return view('hotel.erp.procurement.purchase-list-create-manual', [
            'hotel' => $hotel,
            'departments' => $departments,
            'defaultDepartmentId' => $defaultDepartmentId,
            'requestingDepartmentName' => $requestingDept?->name ?? $config['label'],
            'lockRequestingDepartment' => true,
            'suggestedItemNames' => $suggestedItemNames,
            'itemsByDepartment' => $itemsByDepartment,
            'catalogByDepartment' => $catalogByDepartment,
            'procWorkflow' => $workflow,
            'procLabel' => $config['label'],
            'reviseList' => $purchaseList,
            'initialFormLines' => $this->manualFormLinesFromList($purchaseList),
        ]);
    }

    public function resubmitReturnedPurchaseList(Request $request, HotelBarPurchaseList $purchaseList): RedirectResponse
    {
        $workflow = $this->workflowKey($request);
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->assertListAccess($hotel, $purchaseList, $workflow);

        if (! $purchaseList->isReturnedToDepartment()) {
            return redirect()
                ->to(DepartmentProcurement::indexUrl($workflow, 'purchase-lists'))
                ->with('error', 'This list is not waiting for department revision.');
        }

        $config = DepartmentProcurement::workflow($workflow);
        $lines = [];
        $notes = $request->input('notes');

        if (($config['line_mode'] ?? 'manual') === 'catalog') {
            $lines = $this->validatedCatalogLinesFromRequest(
                $request,
                $hotel,
                $workflow,
                DepartmentProcurement::route($workflow, 'purchase-lists.edit', $purchaseList),
            );
            if ($lines instanceof RedirectResponse) {
                return $lines;
            }
        } else {
            $departmentRule = Rule::exists('hotel_departments', 'id')->where('hotel_id', $hotel->id);
            $validated = $request->validate([
                'hotel_department_id' => ['required', 'integer', $departmentRule],
                'notes' => ['nullable', 'string', 'max:2000'],
                'lines' => ['required', 'array', 'min:1'],
                'lines.*.item_name' => ['required', 'string', 'max:160'],
                'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
                'lines.*.unit' => ['nullable', 'string', 'max:40'],
                'lines.*.line_notes' => ['nullable', 'string', 'max:255'],
            ]);

            foreach ($validated['lines'] as $line) {
                $name = trim((string) $line['item_name']);
                if ($name === '') {
                    continue;
                }
                $lines[] = [
                    'item_name' => $name,
                    'quantity' => (float) $line['quantity'],
                    'unit' => trim((string) ($line['unit'] ?? '')) ?: null,
                    'line_notes' => trim((string) ($line['line_notes'] ?? '')) ?: null,
                ];
            }
            $notes = $validated['notes'] ?? null;
        }

        if ($lines === []) {
            return redirect()
                ->to(DepartmentProcurement::route($workflow, 'purchase-lists.edit', $purchaseList))
                ->withInput()
                ->with('error', 'Add at least one item to the purchase list.');
        }

        try {
            $this->purchaseLists->resubmitReturnedListToStores($purchaseList, is_string($notes) ? $notes : null, $lines);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->to(DepartmentProcurement::route($workflow, 'purchase-lists.edit', $purchaseList))
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->to(DepartmentProcurement::indexUrl($workflow, 'purchase-lists'))
            ->with('success', 'List '.$purchaseList->reference_no.' sent to the store again.');
    }

    public function showPurchaseList(Request $request, HotelBarPurchaseList $purchaseList): View
    {
        $workflow = $this->workflowKey($request);
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->assertListAccess($hotel, $purchaseList, $workflow);

        $purchaseList->load(['lines', 'creator', 'hotel', 'department', 'purchaseOrder.vendor']);

        return view('hotel.erp.bar.purchase-list-show', [
            'hotel' => $hotel,
            'purchaseList' => $purchaseList,
            'ui' => $this->uiForWorkflow($workflow),
            'procWorkflow' => $workflow,
        ]);
    }

    public function printPurchaseList(Request $request, HotelBarPurchaseList $purchaseList): View
    {
        $workflow = $this->workflowKey($request);
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->assertListAccess($hotel, $purchaseList, $workflow);

        $purchaseList->load(['lines', 'creator', 'hotel']);

        return view('hotel.erp.bar.purchase-list-print', [
            'hotel' => $hotel,
            'purchaseList' => $purchaseList,
            'sharedView' => false,
            'procWorkflow' => $workflow,
        ]);
    }

    public function sharedPurchaseList(Request $request, HotelBarPurchaseList $purchaseList): View
    {
        DepartmentProcurement::assertListWorkflow($purchaseList, $purchaseList->workflowKey());
        $purchaseList->load(['lines', 'creator', 'hotel']);
        $purchaseList = $this->purchaseLists->markReceived($purchaseList);
        $purchaseList = $this->purchaseLists->syncBudgetCurrencyIfEditable($purchaseList);

        return $this->recipientRespondView(
            $purchaseList,
            sharedView: true,
            submitUrl: $this->purchaseLists->sharedSubmitUrl($purchaseList),
            saved: $request->query('saved') === '1',
            submitted: $request->query('submitted') === '1',
        );
    }

    public function submitSharedPurchaseList(Request $request, HotelBarPurchaseList $purchaseList): RedirectResponse
    {
        DepartmentProcurement::assertListWorkflow($purchaseList, $purchaseList->workflowKey());

        return $this->processRecipientSubmit(
            $request,
            $purchaseList,
            fn (HotelBarPurchaseList $list, bool $submitted) => redirect()->to(
                $this->purchaseLists->sharedViewUrl($list).($submitted ? '?submitted=1' : '?saved=1')
            ),
        );
    }

    public function showReceivedPurchaseList(Request $request, HotelBarPurchaseList $purchaseList): View
    {
        $workflow = $this->workflowKey($request);
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->authorizePurchaseListRecipient($hotel, $purchaseList, $workflow);

        $purchaseList->load(['lines', 'creator', 'hotel', 'department']);

        return view('hotel.erp.bar.purchase-list-received-show', [
            'hotel' => $hotel,
            'purchaseList' => $purchaseList,
            'ui' => $this->uiForWorkflow($workflow),
            'procWorkflow' => $workflow,
        ]);
    }

    public function respondPurchaseList(Request $request, HotelBarPurchaseList $purchaseList): View|RedirectResponse
    {
        $workflow = $this->workflowKey($request);
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->authorizePurchaseListRecipient($hotel, $purchaseList, $workflow);

        $purchaseList->load(['lines', 'creator', 'hotel', 'department', 'vendor']);

        if ($purchaseList->isReturnedToDepartment()) {
            return redirect()
                ->to(DepartmentProcurement::indexUrl($workflow, 'purchase-lists'))
                ->with('error', 'This list was returned to the department and is no longer on the store desk.');
        }

        $purchaseList = $this->purchaseLists->markReceived($purchaseList);
        $purchaseList = $this->purchaseLists->syncBudgetCurrencyIfEditable($purchaseList);

        return $this->recipientRespondView(
            $purchaseList,
            sharedView: false,
            submitUrl: DepartmentProcurement::route($workflow, 'purchase-lists.respond.submit', $purchaseList),
            saved: session('saved') === true,
            submitted: session('submitted') === true,
            backUrl: $this->respondBackUrl($request, $workflow, $purchaseList),
        );
    }

    public function submitRespondPurchaseList(Request $request, HotelBarPurchaseList $purchaseList): RedirectResponse
    {
        $workflow = $this->workflowKey($request);
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->authorizePurchaseListRecipient($hotel, $purchaseList, $workflow);

        return $this->processRecipientSubmit(
            $request,
            $purchaseList,
            function (HotelBarPurchaseList $list, bool $submitted) use ($workflow) {
                if ($submitted) {
                    return redirect()
                        ->to(DepartmentProcurement::route($workflow, 'purchase-lists.respond', $list))
                        ->with('submitted', true);
                }

                return redirect()
                    ->to(DepartmentProcurement::route($workflow, 'purchase-lists.respond', $list))
                    ->with('saved', true);
            },
        );
    }

    public function formMarketPurchaseList(Request $request, HotelBarPurchaseList $purchaseList): View|RedirectResponse
    {
        $workflow = $this->workflowKey($request);
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->assertListAccess($hotel, $purchaseList, $workflow);

        if ($purchaseList->market_status !== HotelBarPurchaseList::MARKET_RELEASED) {
            return redirect()
                ->to(DepartmentProcurement::indexUrl($workflow, 'market'))
                ->with('error', 'This list is not ready for market purchase entry.');
        }

        $purchaseList->load(['lines', 'creator', 'hotel', 'department', 'purchaseOrder']);
        if (! $purchaseList->canStoreMarketReceive()) {
            $poNumber = $purchaseList->purchaseOrder?->po_number ?? 'the linked PO';

            return redirect()
                ->route('hotel.stores.index', ['tab' => 'purchase-orders'])
                ->with('error', 'Pay '.$poNumber.' in Accounts → Payables before market receive.');
        }

        $purchaseList->loadMissing(['lines', 'creator', 'hotel', 'department']);
        $catalogByName = $this->purchaseLists->catalogItemsByItemName($hotel, $workflow);
        $masterByName = $this->purchaseLists->storeMasterItemsByItemName($hotel);
        $currency = $purchaseList->resolvedBudgetCurrency();
        $packages = collect();
        $service = $this->pmsServiceForWorkflow($hotel, $workflow);
        if ($service) {
            $service->load('servicePackages');
            $packages = $service->servicePackages->where('is_active', true)->sortBy('sort_order')->values();
        }

        return view('hotel.erp.bar.purchase-list-market-complete', [
            'hotel' => $hotel,
            'purchaseList' => $purchaseList,
            'catalogByName' => $catalogByName,
            'masterByName' => $masterByName,
            'barPurchaseLists' => $this->purchaseLists,
            'packages' => $packages,
            'currency' => $currency,
            'paymentModes' => HotelExpense::paymentTypeLabels(),
            'ui' => $this->uiForWorkflow($workflow),
            'procWorkflow' => $workflow,
            'stockDestinations' => DepartmentProcurement::marketStockDestinations($workflow),
        ]);
    }

    public function saveMarketPurchaseDraft(Request $request, HotelBarPurchaseList $purchaseList): RedirectResponse
    {
        return $this->processMarketPurchaseSubmit($request, $purchaseList, complete: false);
    }

    public function completeMarketPurchaseList(Request $request, HotelBarPurchaseList $purchaseList): RedirectResponse
    {
        return $this->processMarketPurchaseSubmit($request, $purchaseList, complete: true);
    }

    public function formMarketStockReceive(Request $request, HotelBarPurchaseList $purchaseList): View|RedirectResponse
    {
        $workflow = $this->workflowKey($request);
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->assertListAccess($hotel, $purchaseList, $workflow);

        if ($purchaseList->market_status !== HotelBarPurchaseList::MARKET_COMPLETED) {
            return redirect()
                ->to(DepartmentProcurement::indexUrl($workflow, 'market'))
                ->with('error', 'This purchase is not completed yet.');
        }

        if ($purchaseList->market_stock_posted_at) {
            return redirect()
                ->to(DepartmentProcurement::indexUrl($workflow, 'stock-receive'))
                ->with('success', 'Stock for '.$purchaseList->reference_no.' was already received.');
        }

        $purchaseList->load(['lines.receivingPackage', 'lines.sellingPackage', 'department', 'marketCompletedBy']);

        $packages = collect();
        $service = $this->pmsServiceForWorkflow($hotel, $workflow);
        if ($service) {
            $service->load('servicePackages');
            $packages = $service->servicePackages->keyBy('id');
        }

        return view('hotel.erp.bar.purchase-list-market-stock-receive', [
            'hotel' => $hotel,
            'purchaseList' => $purchaseList,
            'currency' => $purchaseList->resolvedBudgetCurrency(),
            'packages' => $packages,
            'ui' => $this->uiForWorkflow($workflow),
            'stockDestinations' => DepartmentProcurement::marketStockDestinations($workflow),
            'procWorkflow' => $workflow,
        ]);
    }

    public function postMarketStockReceive(Request $request, HotelBarPurchaseList $purchaseList): RedirectResponse
    {
        $workflow = $this->workflowKey($request);
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->assertListAccess($hotel, $purchaseList, $workflow);

        try {
            $this->purchaseLists->postMarketStock($purchaseList, auth()->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $dest = $purchaseList->fresh()->marketStockDestinationLabel() ?? 'inventory';
        $indexTab = DepartmentProcurement::workflow($workflow)['line_mode'] === 'catalog' ? 'stock' : 'stock-receive';

        return redirect()
            ->to(DepartmentProcurement::indexUrl($workflow, $indexTab))
            ->with('success', 'Stock received for '.$purchaseList->reference_no.'. '.$dest.' updated where catalog items matched.');
    }

    private function storeCatalogPurchaseList(
        Request $request,
        $hotel,
        string $workflow,
    ): RedirectResponse {
        $lines = $this->validatedCatalogLinesFromRequest(
            $request,
            $hotel,
            $workflow,
            DepartmentProcurement::route($workflow, 'purchase-lists.create'),
        );
        if ($lines instanceof RedirectResponse) {
            return $lines;
        }

        return $this->sendList(
            $hotel,
            $workflow,
            $request->input('notes'),
            $lines,
            (int) $request->input('hotel_department_id'),
        );
    }

    /**
     * @return list<array{item_name: string, quantity: float, unit?: ?string, line_notes?: ?string}>|RedirectResponse
     */
    private function validatedCatalogLinesFromRequest(
        Request $request,
        $hotel,
        string $workflow,
        string $failureUrl,
    ): array|RedirectResponse {
        $service = $this->catalogServiceOrRedirect($hotel, $workflow);
        if ($service instanceof RedirectResponse) {
            return $service;
        }

        $departmentRule = Rule::exists('hotel_departments', 'id')->where('hotel_id', $hotel->id);

        $itemIdRule = Rule::exists('hotel_pms_service_items', 'id')
            ->where('hotel_id', $hotel->id)
            ->where('hotel_pms_service_id', $service->id)
            ->where('is_active', true);
        $packageRule = Rule::exists('hotel_pms_service_packages', 'id')
            ->where('hotel_pms_service_id', $service->id);

        $validated = $request->validate([
            'hotel_department_id' => ['required', 'integer', $departmentRule],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', $itemIdRule],
            'lines.*.package_id' => ['required', 'integer', $packageRule],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.line_notes' => ['nullable', 'string', 'max:255'],
        ], [], [
            'lines.*.item_id' => 'item',
            'lines.*.package_id' => 'unit',
        ]);

        $service->load('servicePackages');
        $packagesById = $service->servicePackages->keyBy('id');

        $catalogItems = HotelPmsServiceItem::query()
            ->where('hotel_pms_service_id', $service->id)
            ->whereIn('id', collect($validated['lines'])->pluck('item_id'))
            ->get()
            ->keyBy('id');

        $lines = [];
        foreach ($validated['lines'] as $line) {
            $item = $catalogItems->get((int) $line['item_id']);
            $package = $packagesById->get((int) $line['package_id']);
            if (! $item || ! $package) {
                continue;
            }
            $unit = trim((string) ($package->abbreviation ?: $package->name));

            $lines[] = [
                'item_name' => $item->item_name,
                'quantity' => (float) $line['quantity'],
                'unit' => $unit !== '' ? $unit : null,
                'line_notes' => trim((string) ($line['line_notes'] ?? '')) ?: null,
            ];
        }

        if ($lines === []) {
            return redirect()
                ->to($failureUrl)
                ->withInput()
                ->with('error', 'Add at least one item to the purchase list.');
        }

        return $lines;
    }

    /** @return list<array{item_id: int|string, package_id: int|string, quantity: string, line_notes: string}> */
    private function catalogFormLinesFromList(HotelPmsService $service, HotelBarPurchaseList $list): array
    {
        $service->load('servicePackages');
        $packages = $service->servicePackages;

        $items = HotelPmsServiceItem::query()
            ->where('hotel_pms_service_id', $service->id)
            ->get()
            ->keyBy(fn (HotelPmsServiceItem $item) => mb_strtolower(trim($item->item_name)));

        $formLines = [];
        foreach ($list->lines as $line) {
            $item = $items->get(mb_strtolower(trim($line->item_name)));
            $unit = trim((string) ($line->unit ?? ''));
            $package = $packages->first(function ($package) use ($unit) {
                $abbr = trim((string) ($package->abbreviation ?? ''));
                $name = trim((string) ($package->name ?? ''));

                return ($unit !== '' && strcasecmp($abbr, $unit) === 0)
                    || ($unit !== '' && strcasecmp($name, $unit) === 0);
            }) ?? $packages->first();

            $qty = (string) max(0.01, (float) $line->quantity);
            $formLines[] = [
                'item_id' => $item?->id ?? '',
                'package_id' => $package?->id ?? '',
                'quantity' => $qty,
                'previous_quantity' => $qty,
                'line_notes' => (string) ($line->line_notes ?? ''),
            ];
        }

        if ($formLines === []) {
            return [['item_id' => '', 'package_id' => '', 'quantity' => '1', 'line_notes' => '']];
        }

        return $formLines;
    }

    /** @return list<array{item_name: string, quantity: string, unit: string, line_notes: string}> */
    private function manualFormLinesFromList(HotelBarPurchaseList $list): array
    {
        $formLines = $list->lines->map(function ($line) {
            $qty = (string) max(0.01, (float) $line->quantity);

            return [
                'item_name' => (string) $line->item_name,
                'quantity' => $qty,
                'previous_quantity' => $qty,
                'unit' => (string) ($line->unit ?? ''),
                'line_notes' => (string) ($line->line_notes ?? ''),
            ];
        })->all();

        if ($formLines === []) {
            return [['item_name' => '', 'quantity' => '1', 'unit' => '', 'line_notes' => '']];
        }

        return $formLines;
    }

    /** @param  list<array{item_name: string, quantity: float, unit?: ?string, line_notes?: ?string}>  $lines */
    private function sendList($hotel, string $workflow, ?string $notes, array $lines, int $departmentId): RedirectResponse
    {
        $config = DepartmentProcurement::workflow($workflow);

        try {
            $list = $this->purchaseLists->createAndSend(
                $hotel,
                auth()->user(),
                $notes,
                $lines,
                $departmentId,
                $workflow,
            );
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('hotel.settings.index', ['tab' => $config['settings_tab']])
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->to(DepartmentProcurement::route($workflow, 'purchase-lists.show', $list))
            ->with(
                $list->status === HotelBarPurchaseList::STATUS_SENT ? 'success' : 'warning',
                $list->status === HotelBarPurchaseList::STATUS_SENT
                    ? 'Purchase list sent to '.count($list->sent_to ?? []).' recipient(s).'
                    : 'List saved in the system. Email could not be sent — share the recipient link below until mail is configured.'
            );
    }

    private function processMarketPurchaseSubmit(
        Request $request,
        HotelBarPurchaseList $purchaseList,
        bool $complete,
    ): RedirectResponse {
        $workflow = $purchaseList->workflowKey();
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->assertListAccess($hotel, $purchaseList, $workflow);

        $paymentKeys = array_keys(HotelExpense::paymentTypeLabels());
        $service = $this->pmsServiceForWorkflow($hotel, $workflow);
        $packageRule = Rule::exists('hotel_pms_service_packages', 'id');
        if ($service) {
            $packageRule = $packageRule->where('hotel_pms_service_id', $service->id);
        }

        $destKeys = DepartmentProcurement::allowedStockDestinationKeys($workflow);

        $rules = [
            'market_budget_amount' => ['nullable', 'numeric', 'min:0'],
            'market_completion_notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.market_item_found' => ['nullable', 'boolean'],
            'lines.*.quantity_purchased' => ['nullable', 'numeric', 'min:0'],
            'lines.*.purchase_line_total' => ['nullable', 'numeric', 'min:0'],
            'lines.*.purchase_unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.selling_unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.receiving_package_id' => ['nullable', 'integer', $packageRule],
            'lines.*.selling_package_id' => ['nullable', 'integer', $packageRule],
            'lines.*.sell_option_prices' => ['nullable', 'array'],
            'lines.*.sell_option_prices.*' => ['nullable', 'numeric', 'min:0'],
            'lines.*.expiry_date' => ['nullable', 'date'],
            'lines.*.market_line_notes' => ['nullable', 'string', 'max:500'],
            'lines.*.market_stock_destination' => ['nullable', 'string', Rule::in($destKeys)],
        ];

        if ($complete) {
            $rules['market_payment_type'] = ['required', 'string', Rule::in($paymentKeys)];
        } else {
            $rules['market_payment_type'] = ['nullable', 'string', Rule::in($paymentKeys)];
        }

        $validated = $request->validate($rules, [], [
            'market_payment_type' => 'payment method',
            'lines.*.quantity_purchased' => 'qty bought',
            'lines.*.purchase_line_total' => 'total cost',
        ]);

        $lineIds = $purchaseList->lines()->pluck('id')->all();
        foreach ($validated['lines'] as $row) {
            if (! in_array((int) $row['id'], $lineIds, true)) {
                return back()->withInput()->with('error', 'Invalid line on this purchase list.');
            }
        }

        $payload = [
            'market_budget_amount' => $validated['market_budget_amount'] ?? null,
            'market_payment_type' => $validated['market_payment_type'] ?? null,
            'market_completion_notes' => $validated['market_completion_notes'] ?? null,
        ];

        try {
            if ($complete) {
                $this->purchaseLists->completeMarketRun(
                    $purchaseList,
                    auth()->user(),
                    $payload,
                    $validated['lines'],
                );
            } else {
                $this->purchaseLists->saveMarketDraft($purchaseList, $payload, $validated['lines']);
            }
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($complete) {
            $purchaseList->refresh();
            $message = 'Purchase recorded for '.$purchaseList->reference_no.'.';
            $indexTab = DepartmentProcurement::workflow($workflow)['line_mode'] === 'catalog' ? 'stock' : 'stock-receive';
            if ($purchaseList->market_stock_posted_at) {
                $message .= ' Stock updated in inventory.';
            } else {
                $message .= ' Finish receiving stock from the Receive stock queue if needed.';
                $indexTab = 'stock-receive';
            }

            return redirect()
                ->to(DepartmentProcurement::indexUrl($workflow, $indexTab))
                ->with('success', $message);
        }

        return redirect()
            ->to(DepartmentProcurement::route($workflow, 'purchase-lists.market.form', $purchaseList))
            ->with('success', 'Draft saved. You can continue when you return from the market.');
    }

    private function respondBackUrl(Request $request, string $workflow, HotelBarPurchaseList $purchaseList): string
    {
        if ($request->query('return') === 'stores') {
            return route('hotel.stores.index', ['tab' => 'purchase-orders']);
        }

        return DepartmentProcurement::route($workflow, 'purchase-lists.received.show', $purchaseList);
    }

    private function recipientRespondView(
        HotelBarPurchaseList $purchaseList,
        bool $sharedView,
        string $submitUrl,
        bool $saved = false,
        bool $submitted = false,
        ?string $backUrl = null,
    ): View {
        $workflow = $purchaseList->workflowKey();
        $view = $sharedView
            ? 'hotel.erp.bar.purchase-list-respond-shared'
            : 'hotel.erp.bar.purchase-list-respond';

        $packages = collect();
        $catalogItems = collect();
        $service = $this->pmsServiceForWorkflow($purchaseList->hotel, $workflow);
        if ($service) {
            $service->load('servicePackages');
            $packages = $service->servicePackages->where('is_active', true)->values();
            $catalogItems = $this->items->itemsForService($service)
                ->where('is_active', true)
                ->sortBy('item_name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();
        }

        $workflowConfig = DepartmentProcurement::workflow($workflow);
        $respondBackLabel = request()->query('return') === 'stores'
            ? 'Stores'
            : ($workflowConfig['label'] ?? 'Received');

        $vendors = $this->accounts->vendorsQuery($purchaseList->hotel_id, ['search' => ''])->get();

        return view($view, [
            'hotel' => $purchaseList->hotel,
            'purchaseList' => $purchaseList,
            'sharedView' => $sharedView,
            'submitUrl' => $submitUrl,
            'expectedPrices' => $this->purchaseLists->expectedPricesByItemName($purchaseList->hotel, $workflow),
            'packages' => $packages,
            'catalogItems' => $catalogItems,
            'budgetCurrency' => $this->purchaseLists->resolvedBudgetCurrency($purchaseList),
            'locked' => $purchaseList->recipientBudgetLocked(),
            'saved' => $saved,
            'submitted' => $submitted,
            'procWorkflow' => $workflow,
            'respondBackUrl' => $backUrl ?? DepartmentProcurement::route($workflow, 'purchase-lists.received.show', $purchaseList),
            'respondBackLabel' => $respondBackLabel,
            'respondStoresLayout' => request()->query('return') === 'stores',
            'purchaseListVendors' => $vendors,
        ]);
    }

    private function processRecipientSubmit(
        Request $request,
        HotelBarPurchaseList $purchaseList,
        callable $redirectTo,
    ): RedirectResponse {
        if ($purchaseList->recipientBudgetLocked()) {
            return $redirectTo($purchaseList, false)->with('error', 'This budget was already submitted.');
        }

        $hotel = $purchaseList->hotel;

        $validated = $request->validate([
            'action' => ['required', 'in:draft,submit'],
            'hotel_vendor_id' => [
                'required',
                'integer',
                Rule::exists('hotel_vendors', 'id')->where('hotel_id', $hotel->id),
            ],
            'recipient_notes' => ['nullable', 'string', 'max:2000'],
            'responder_name' => ['nullable', 'string', 'max:120'],
            'responder_email' => ['nullable', 'email', 'max:160'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.item_name' => ['required', 'string', 'max:160'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.unit' => ['nullable', 'string', 'max:40'],
            'lines.*.line_notes' => ['nullable', 'string', 'max:255'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ], [], [
            'hotel_vendor_id' => 'supplier',
            'lines.*.item_name' => 'item',
            'lines.*.unit_cost' => 'unit cost',
        ]);

        $submitBudget = $validated['action'] === 'submit';
        [$responderName, $responderEmail] = $this->resolveRecipientIdentity($validated, $submitBudget);
        if ($submitBudget && ($responderName === null || $responderEmail === null)) {
            return back()->withInput()->with('error', 'Enter your name and email, or update your user profile before submitting.');
        }

        try {
            $list = $this->purchaseLists->saveRecipientResponse(
                $purchaseList,
                $validated,
                $validated['lines'],
                $submitBudget,
                $responderName,
                $responderEmail,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return $redirectTo($list, $submitBudget);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveRecipientIdentity(array $validated, bool $submitBudget): array
    {
        $name = trim((string) ($validated['responder_name'] ?? ''));
        $email = trim((string) ($validated['responder_email'] ?? ''));

        if ($name === '' && auth()->check()) {
            $name = trim((string) auth()->user()->name);
        }
        if ($email === '' && auth()->check()) {
            $email = trim((string) auth()->user()->email);
        }

        if (! $submitBudget) {
            return [$name !== '' ? $name : null, $email !== '' ? $email : null];
        }

        if ($name === '' || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [null, null];
        }

        return [mb_substr($name, 0, 120), mb_substr($email, 0, 160)];
    }

    private function workflowKey(Request $request): string
    {
        $workflow = $request->route('procurement_workflow');

        return is_string($workflow) && $workflow !== ''
            ? $workflow
            : 'bar';
    }

    private function assertListAccess($hotel, HotelBarPurchaseList $purchaseList, string $workflow): void
    {
        abort_unless((int) $purchaseList->hotel_id === (int) $hotel->id, 404);
        DepartmentProcurement::assertListWorkflow($purchaseList, $workflow);
    }

    private function authorizePurchaseListRecipient($hotel, HotelBarPurchaseList $purchaseList, string $workflow): void
    {
        abort_unless((int) $purchaseList->hotel_id === (int) $hotel->id, 404);
        DepartmentProcurement::assertListWorkflow($purchaseList, $workflow);
        abort_unless(
            $this->purchaseLists->userCanVerifyPurchaseList($hotel, auth()->user(), $purchaseList),
            403,
            'Only the store team can verify this list. Bar staff cannot approve lists they sent — use ERP → Stores → Purchase orders.'
        );
    }

    private function catalogItemsForDepartment(
        Hotel $hotel,
        HotelPmsService $service,
        ?int $departmentId,
        string $workflow,
    ): Collection {
        $items = $this->items->itemsForService($service);
        $deptId = (int) ($departmentId ?? 0);
        if ($deptId <= 0) {
            return $items;
        }

        return $this->storeMasterItems->filterServiceCatalogForDepartment($hotel, $service, $items, $deptId, $workflow);
    }

    private function catalogServiceOrRedirect($hotel, string $workflow): HotelPmsService|RedirectResponse
    {
        if ($workflow === 'bar') {
            $service = $this->barCatalog->barServiceForHotel($hotel);
            if (! $service) {
                return redirect()
                    ->route('hotel.services.index')
                    ->with('error', 'Add the Bar service under PMS → Services first.');
            }

            return $service;
        }

        $templateKey = DepartmentProcurement::workflow($workflow)['template_key'] ?? null;
        if (! $templateKey) {
            abort(404);
        }

        $service = HotelPmsService::query()
            ->where('hotel_id', $hotel->id)
            ->where('template_key', $templateKey)
            ->where('is_active', true)
            ->first();

        if (! $service) {
            return redirect()
                ->route('hotel.services.index')
                ->with('error', 'Add the '.DepartmentProcurement::label($workflow).' service under PMS → Services first.');
        }

        return $service;
    }

    private function pmsServiceForWorkflow($hotel, string $workflow): ?HotelPmsService
    {
        if ($workflow === 'bar') {
            return $this->barCatalog->barServiceForHotel($hotel);
        }

        $templateKey = DepartmentProcurement::workflow($workflow)['template_key'] ?? null;
        if (! $templateKey) {
            return null;
        }

        return HotelPmsService::query()
            ->where('hotel_id', $hotel->id)
            ->where('template_key', $templateKey)
            ->where('is_active', true)
            ->first();
    }

    /** @return array<string, mixed> */
    private function uiForWorkflow(string $workflow): array
    {
        if ($workflow === 'bar') {
            return $this->barCatalog->uiConfig();
        }

        return [
            'title' => DepartmentProcurement::label($workflow),
        ];
    }
}
