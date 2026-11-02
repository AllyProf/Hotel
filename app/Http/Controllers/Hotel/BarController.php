<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelBarPurchaseList;
use App\Models\HotelExpense;
use App\Models\HotelPmsService;
use App\Models\HotelPmsServiceItem;
use App\Models\HotelPmsServiceItemSellOption;
use App\Services\AccountsDataService;
use App\Services\Bar\BarCatalogService;
use App\Services\Bar\BarPurchaseListService;
use App\Services\Bar\BarSaleService;
use App\Services\FrontDeskPaymentService;
use App\Services\Hr\HotelHrService;
use App\Services\PmsServiceItemDataService;
use App\Models\HotelStoreIndent;
use App\Services\Bar\BarIndentReceiveService;
use App\Services\Stores\StoreIndentService;
use App\Services\Stores\StoreMasterItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class BarController extends Controller
{
    public function __construct(
        private BarCatalogService $bar,
        private PmsServiceItemDataService $items,
        private BarPurchaseListService $purchaseLists,
        private BarSaleService $barSales,
        private FrontDeskPaymentService $deskPayments,
        private HotelHrService $hr,
        private AccountsDataService $accounts,
        private StoreMasterItemService $storeMasterItems,
        private StoreIndentService $storeIndents,
        private BarIndentReceiveService $barIndents,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $service = $this->barServiceOrRedirect($hotel);
        if ($service instanceof RedirectResponse) {
            return $service;
        }

        $tab = $request->query('tab', 'stock');
        if ($tab === 'sell') {
            $posDept = app(\App\Services\Pos\PosOrderService::class)->posDepartmentForService($hotel, $service->id);

            return redirect()->route('hotel.pos.index', array_filter([
                'department' => $posDept?->id,
                'outlet' => $posDept ? null : $service->id,
            ]));
        }
        $departmentTabs = ['items', 'stock', 'store-indents', 'purchase-lists'];
        $storeBackOfficeTabs = ['received', 'market', 'stock-receive'];
        if (in_array($tab, $storeBackOfficeTabs, true)) {
            return redirect()
                ->route('hotel.bar.index', ['tab' => 'purchase-lists'])
                ->with(
                    'info',
                    'After you send a list, the store team verifies it under ERP → Stores → Purchase orders (same as HK).'
                );
        }
        if (! in_array($tab, $departmentTabs, true)) {
            $tab = 'stock';
        }

        $user = auth()->user();
        $this->hr->ensureDefaults($hotel);
        $this->purchaseLists->ensureBarDepartment($hotel);
        $this->purchaseLists->backfillSentListDepartments($hotel);
        $departmentFilter = $request->query('department');
        $departmentId = is_numeric($departmentFilter) ? (int) $departmentFilter : null;
        $receivedDepartments = $this->purchaseLists->departmentsWithSentListsForUser($hotel, $user);
        if ($departmentId && ! $receivedDepartments->contains('id', $departmentId)) {
            $departmentId = null;
        }

        $this->bar->ensureCatalogReady($hotel, $service);
        $service->load(['serviceCategories', 'servicePackages']);

        $catalogItems = $this->items->itemsForService($service);
        $stockSearch = trim((string) $request->query('search', ''));
        $stockItems = $catalogItems;
        if ($stockSearch !== '') {
            $needle = mb_strtolower($stockSearch);
            $stockItems = $catalogItems->filter(function ($item) use ($needle) {
                $recv = $item->package
                    ? mb_strtolower(trim((string) ($item->package->abbreviation ?: $item->package->name)))
                    : '';
                $category = mb_strtolower(trim((string) ($item->category?->name ?? '')));
                $name = mb_strtolower(trim((string) $item->item_name));
                $brand = mb_strtolower(trim((string) ($item->brand ?? '')));

                return str_contains($name, $needle)
                    || str_contains($category, $needle)
                    || str_contains($brand, $needle)
                    || ($recv !== '' && str_contains($recv, $needle));
            })->values();
        }

        $barSettings = $service->barSettings();
        $stockReceivePendingCount = $this->purchaseLists->pendingMarketStockReceiveCount($hotel, departmentLinesOnly: true, workflowKey: 'bar');
        $barDepartmentIds = $this->barIndents->barHotelDepartmentIds($hotel);
        $barStoreIndentsPending = $this->storeIndents->pendingDepartmentReceiveCount($hotel->id, $barDepartmentIds);
        $barStoreIndentsList = null;
        if ($tab === 'store-indents') {
            $barStoreIndentsList = $this->storeIndents->indentsQuery($hotel->id, [
                'status' => 'all',
                'department_ids' => $barDepartmentIds,
            ])
                ->with(['department', 'storeCategory', 'items'])
                ->paginate(20)
                ->withQueryString();
        }

        return view('hotel.erp.bar.index', [
            'hotel' => $hotel,
            'service' => $service,
            'tab' => $tab,
            'barSettings' => $barSettings,
            'receivedDepartments' => $receivedDepartments,
            'receivedDepartmentId' => $departmentId,
            'items' => $catalogItems,
            'stockItems' => $stockItems,
            'stockSearch' => $stockSearch,
            'categories' => $service->serviceCategories->where('is_active', true),
            'packages' => $service->servicePackages->where('is_active', true),
            'defaultCurrency' => $service->displayCurrency($hotel),
            'barOperatingCurrency' => $this->purchaseLists->budgetCurrencyForHotel($hotel),
            'currencies' => config('currencies', []),
            'nameSuggestions' => collect($service->menuItemSettings()['items'] ?? [])->pluck('name')->filter()->values(),
            'defaultPackageId' => $this->items->defaultPackageId($service),
            'purchaseLists' => $this->purchaseLists->recentLists($hotel),
            'receivedLists' => $this->purchaseLists->receivedListsForUser($hotel, $user, $departmentId),
            'isPurchaseListRecipient' => $this->purchaseLists->userShouldSeeDepartmentReceivedTab($hotel, $user, 'bar'),
            'purchaseListRecipientsConfigured' => $this->purchaseLists->hasRecipients($hotel),
            'marketLists' => $this->purchaseLists->listsForMarket($hotel),
            'stockReceiveLists' => $this->purchaseLists->listsPendingMarketStockReceive($hotel, departmentLinesOnly: true, workflowKey: 'bar'),
            'stockReceivePendingCount' => $stockReceivePendingCount,
            'barStoreIndentsList' => $barStoreIndentsList,
            'barStoreIndentsPending' => $barStoreIndentsPending,
            'barTabBadges' => [
                'stock' => $this->bar->lowStockItems($service)->count(),
                'store-indents' => $barStoreIndentsPending,
                'purchase-lists' => $this->purchaseLists->departmentReturnedPurchaseListsCount($hotel),
                'received' => $this->purchaseLists->receivedListsNeedingActionCount($hotel, $user),
                'market' => $this->purchaseLists->marketListsPendingPurchaseCount($hotel),
                'stock-receive' => $stockReceivePendingCount,
            ],
            'procWorkflow' => 'bar',
            'procSettingsTab' => 'bar',
            'ui' => $this->bar->uiConfig(),
        ]);
    }

    public function receiveStoreIndent(HotelStoreIndent $indent): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $receiveKey = config('hotel_department_stores.store_request_permission_keys.receive', 'dept_stores.store_request.receive');
        abort_unless(auth()->user()->hasPermission($receiveKey), 403);

        try {
            $this->barIndents->assertIndentAwaitingBarReceive($hotel, $indent);
            $this->storeIndents->receiveIndent($hotel, $indent);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('hotel.bar.index', ['tab' => 'store-indents'])
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('hotel.bar.index', ['tab' => 'stock'])
            ->with('success', 'Indent '.$indent->indent_number.' received. Stock updated on the Stock tab.');
    }

    public function storeBarSale(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $service = $this->barServiceOrRedirect($hotel);
        if ($service instanceof RedirectResponse) {
            return $service;
        }

        $lines = collect($request->input('lines', []))
            ->map(function ($line) {
                if (! is_array($line)) {
                    return $line;
                }
                $pkg = $line['sell_package_id'] ?? null;
                if ($pkg === '' || $pkg === null) {
                    unset($line['sell_package_id']);
                } else {
                    $line['sell_package_id'] = (int) $pkg;
                }

                return $line;
            })
            ->all();
        $request->merge(['lines' => $lines]);

        $validated = $request->validate([
            'cm_reservation_id' => ['nullable', 'integer'],
            'room_no' => ['nullable', 'string', 'max:40'],
            'guest_name' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:500'],
            'payment_mode' => ['nullable', 'string', 'max:80'],
            'payment_provider' => ['nullable', 'string', 'max:120'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer'],
            'lines.*.sell_package_id' => ['nullable', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        try {
            $sale = $this->barSales->createSale(
                $hotel,
                $service,
                $validated,
                array_values($validated['lines']),
                auth()->user(),
            );
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->to($this->barSaleReturnUrl($request, $service))
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        $folioNote = $sale->posted_to_folio ? ' Charge posted to guest folio.' : '';

        return redirect()
            ->to($this->barSaleReturnUrl($request, $service))
            ->with('success', 'Sale '.$sale->sale_no.' recorded.'.$folioNote);
    }

    public function formMarketPurchaseList(HotelBarPurchaseList $purchaseList): View|RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $purchaseList->hotel_id === (int) $hotel->id, 404);

        if ($purchaseList->market_status !== HotelBarPurchaseList::MARKET_RELEASED) {
            return redirect()
                ->route('hotel.bar.index', ['tab' => 'market'])
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
        $catalogByName = $this->purchaseLists->catalogItemsByItemName($hotel);
        $masterByName = $this->purchaseLists->storeMasterItemsByItemName($hotel);
        $currency = $purchaseList->resolvedBudgetCurrency();
        $packages = collect();
        $barService = $this->bar->barServiceForHotel($hotel);
        if ($barService) {
            $barService->load('servicePackages');
            $packages = $barService->servicePackages->where('is_active', true)->sortBy('sort_order')->values();
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
            'ui' => $this->bar->uiConfig(),
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

    public function formMarketStockReceive(HotelBarPurchaseList $purchaseList): View|RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $purchaseList->hotel_id === (int) $hotel->id, 404);

        if ($purchaseList->market_status !== HotelBarPurchaseList::MARKET_COMPLETED) {
            return redirect()
                ->route('hotel.bar.index', ['tab' => 'market'])
                ->with('error', 'This purchase is not completed yet.');
        }

        if ($purchaseList->market_stock_posted_at) {
            return redirect()
                ->route('hotel.bar.index', ['tab' => 'stock-receive'])
                ->with('success', 'Stock for '.$purchaseList->reference_no.' was already received.');
        }

        $purchaseList->load(['lines.receivingPackage', 'lines.sellingPackage', 'department', 'marketCompletedBy']);

        $packages = collect();
        $barService = $this->bar->barServiceForHotel($hotel);
        if ($barService) {
            $barService->load('servicePackages');
            $packages = $barService->servicePackages->keyBy('id');
        }

        return view('hotel.erp.bar.purchase-list-market-stock-receive', [
            'hotel' => $hotel,
            'purchaseList' => $purchaseList,
            'currency' => $purchaseList->resolvedBudgetCurrency(),
            'packages' => $packages,
            'ui' => $this->bar->uiConfig(),
            'stockDestinations' => config('hotel_bar.market_stock_destinations', []),
        ]);
    }

    public function postMarketStockReceive(HotelBarPurchaseList $purchaseList): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $purchaseList->hotel_id === (int) $hotel->id, 404);

        try {
            $this->purchaseLists->postMarketStock($purchaseList, auth()->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $dest = $purchaseList->fresh()->marketStockDestinationLabel() ?? 'inventory';

        return redirect()
            ->route('hotel.bar.index', ['tab' => 'stock'])
            ->with('success', 'Stock received for '.$purchaseList->reference_no.'. Quantities are on the Stock tab.');
    }

    private function processMarketPurchaseSubmit(
        Request $request,
        HotelBarPurchaseList $purchaseList,
        bool $complete,
    ): RedirectResponse {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $purchaseList->hotel_id === (int) $hotel->id, 404);

        $paymentKeys = array_keys(HotelExpense::paymentTypeLabels());
        $barService = $this->bar->barServiceForHotel($hotel);
        $packageRule = Rule::exists('hotel_pms_service_packages', 'id');
        if ($barService) {
            $packageRule = $packageRule->where('hotel_pms_service_id', $barService->id);
        }

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
        ];

        if ($complete) {
            $rules['market_payment_type'] = ['required', 'string', Rule::in($paymentKeys)];
        } else {
            $rules['market_payment_type'] = ['nullable', 'string', Rule::in($paymentKeys)];
        }

        $rules['lines.*.market_stock_destination'] = ['nullable', 'string', Rule::in([
            HotelBarPurchaseList::STOCK_DEST_STORE,
            HotelBarPurchaseList::STOCK_DEST_BAR,
        ])];

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
            if ($purchaseList->market_stock_posted_at) {
                $message .= ' Stock updated on the Stock tab.';
                $tab = 'stock';
            } else {
                $message .= ' Finish receiving stock from the Receive stock queue if needed.';
                $tab = 'stock-receive';
            }

            return redirect()
                ->route('hotel.bar.index', ['tab' => $tab])
                ->with('success', $message);
        }

        return redirect()
            ->route('hotel.bar.purchase-lists.market.form', $purchaseList)
            ->with('success', 'Draft saved. You can continue when you return from the market.');
    }

    public function createItem(): View|RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $service = $this->barServiceOrRedirect($hotel);
        if ($service instanceof RedirectResponse) {
            return $service;
        }

        $this->bar->ensureCatalogReady($hotel, $service);
        $service->load(['serviceCategories', 'servicePackages']);

        return view('hotel.erp.bar.item-create', $this->barItemFormViewData($hotel, $service));
    }

    public function editItem(HotelPmsServiceItem $item): View|RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $service = $this->authorizeBarItem($hotel, $item);

        $this->bar->ensureCatalogReady($hotel, $service);
        $service->load(['serviceCategories', 'servicePackages']);
        $item->load(['sellOptions.package', 'category', 'package']);

        return view('hotel.erp.bar.item-edit', array_merge(
            $this->barItemFormViewData($hotel, $service),
            ['item' => $item],
        ));
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $service = $this->barServiceOrRedirect($hotel);
        if ($service instanceof RedirectResponse) {
            return $service;
        }

        $itemsInput = collect($request->input('items', []))
            ->filter(fn ($row) => is_array($row) && trim((string) ($row['item_name'] ?? '')) !== '')
            ->values()
            ->all();
        $request->merge(['items' => $itemsInput !== [] ? $itemsInput : [[]]]);

        $validated = $this->validateItems($request, $hotel, $service);
        $rows = collect($validated['items']);

        if ($rows->isEmpty() || trim((string) ($rows->first()['item_name'] ?? '')) === '') {
            return back()->withInput()->with('error', 'Add at least one item with a name.');
        }

        $count = 0;
        DB::transaction(function () use ($rows, $service, $hotel, &$count) {
            foreach ($rows as $row) {
                $this->createCatalogItemFromRow($service, $hotel, $row);
                $count++;
            }
        });

        $message = $count === 1 ? '1 item registered.' : $count.' items registered.';

        return redirect()
            ->route('hotel.bar.index', ['tab' => 'items'])
            ->with('success', $message);
    }

    public function updateItem(Request $request, HotelPmsServiceItem $item): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $service = $this->authorizeBarItem($hotel, $item);

        $validated = $this->normalizeItemStock($this->validateItem($request, $hotel, $service, $item));

        $item->update([
            'hotel_pms_service_category_id' => $validated['category_id'] ?? null,
            'item_name' => $validated['item_name'],
            'brand' => trim((string) ($validated['brand'] ?? '')) ?: null,
            'hotel_pms_service_package_id' => $validated['package_id'] ?? null,
            'pieces_per_receive_unit' => $validated['pieces_per_receive_unit'],
            'is_active' => $request->boolean('is_active', true),
            'stock_on_hand' => $validated['stock_on_hand'] ?? null,
            'reorder_level' => $validated['reorder_level'] ?? null,
            'expected_unit_price' => $this->normalizeExpectedPrice($validated['expected_unit_price'] ?? null),
        ]);

        $this->syncItemSellOptions($item, $validated['selling_options'] ?? []);
        $this->applyPrimarySellToCatalogItem($item->fresh(['sellOptions']));

        return redirect()
            ->route('hotel.bar.index', ['tab' => 'items'])
            ->with('success', 'Bar item updated.');
    }

    public function destroyItem(HotelPmsServiceItem $item): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->authorizeBarItem($hotel, $item);
        $item->delete();

        return redirect()
            ->route('hotel.bar.index', ['tab' => 'items'])
            ->with('success', 'Bar item removed.');
    }

    public function createPurchaseList(): View|RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $service = $this->barServiceOrRedirect($hotel);
        if ($service instanceof RedirectResponse) {
            return $service;
        }

        if (! $this->purchaseLists->hasRecipients($hotel)) {
            return redirect()
                ->route('hotel.settings.index', ['tab' => 'bar'])
                ->with('error', 'Configure purchase list recipients under Settings → Bar first.');
        }

        $this->hr->ensureDefaults($hotel);
        $this->purchaseLists->ensureBarDepartment($hotel);
        $this->bar->ensureCatalogReady($hotel, $service);
        $service->load(['serviceCategories', 'servicePackages']);

        $packages = $service->servicePackages->where('is_active', true);
        $departments = $this->hr->departments($hotel)->where('is_active', true);
        $defaultDepartmentId = $this->purchaseLists->resolveDepartmentId($hotel, null);
        $catalogItems = $this->items->itemsForService($service);
        if ($defaultDepartmentId) {
            $catalogItems = $this->storeMasterItems->filterServiceCatalogForDepartment(
                $hotel,
                $service,
                $catalogItems,
                (int) $defaultDepartmentId,
                'bar',
            );
        }

        return view('hotel.erp.bar.purchase-list-create', [
            'hotel' => $hotel,
            'service' => $service,
            'items' => $catalogItems,
            'packages' => $packages,
            'departments' => $departments,
            'defaultDepartmentId' => $defaultDepartmentId,
            'lowStockItems' => $defaultDepartmentId
                ? $this->storeMasterItems->filterServiceCatalogForDepartment(
                    $hotel,
                    $service,
                    $this->bar->lowStockItems($service),
                    (int) $defaultDepartmentId,
                    'bar',
                )
                : $this->bar->lowStockItems($service),
            'ui' => $this->bar->uiConfig(),
        ]);
    }

    public function storePurchaseList(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $service = $this->barServiceOrRedirect($hotel);
        if ($service instanceof RedirectResponse) {
            return $service;
        }

        $itemIdRule = Rule::exists('hotel_pms_service_items', 'id')
            ->where('hotel_id', $hotel->id)
            ->where('hotel_pms_service_id', $service->id)
            ->where('is_active', true);
        $packageRule = Rule::exists('hotel_pms_service_packages', 'id')
            ->where('hotel_pms_service_id', $service->id);

        $departmentRule = Rule::exists('hotel_departments', 'id')->where('hotel_id', $hotel->id);

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
                ->route('hotel.bar.purchase-lists.create')
                ->withInput()
                ->with('error', 'Add at least one item to the purchase list.');
        }

        try {
            $list = $this->purchaseLists->createAndSend(
                $hotel,
                auth()->user(),
                $validated['notes'] ?? null,
                $lines,
                (int) $validated['hotel_department_id'],
            );
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('hotel.settings.index', ['tab' => 'bar'])
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('hotel.bar.purchase-lists.show', $list)
            ->with(
                $list->status === HotelBarPurchaseList::STATUS_SENT ? 'success' : 'warning',
                $list->status === HotelBarPurchaseList::STATUS_SENT
                    ? 'Purchase list sent to '.count($list->sent_to ?? []).' recipient(s).'
                    : 'List saved in the system. Email could not be sent — share the recipient link below until mail is configured.'
            );
    }

    public function showPurchaseList(HotelBarPurchaseList $purchaseList): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $purchaseList->hotel_id === (int) $hotel->id, 404);

        $purchaseList->load(['lines', 'creator', 'hotel', 'department', 'purchaseOrder.vendor']);

        return view('hotel.erp.bar.purchase-list-show', [
            'hotel' => $hotel,
            'purchaseList' => $purchaseList,
            'ui' => $this->bar->uiConfig(),
        ]);
    }

    public function printPurchaseList(HotelBarPurchaseList $purchaseList): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $purchaseList->hotel_id === (int) $hotel->id, 404);

        $purchaseList->load(['lines', 'creator', 'hotel']);

        return view('hotel.erp.bar.purchase-list-print', [
            'hotel' => $hotel,
            'purchaseList' => $purchaseList,
            'sharedView' => false,
        ]);
    }

    public function sharedPurchaseList(Request $request, HotelBarPurchaseList $purchaseList): View
    {
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
        return $this->processRecipientSubmit(
            $request,
            $purchaseList,
            fn (HotelBarPurchaseList $list, bool $submitted) => redirect()->to(
                $this->purchaseLists->sharedViewUrl($list).($submitted ? '?submitted=1' : '?saved=1')
            ),
        );
    }

    public function showReceivedPurchaseList(HotelBarPurchaseList $purchaseList): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->authorizePurchaseListRecipient($hotel, $purchaseList);

        $purchaseList->load(['lines', 'creator', 'hotel', 'department']);

        return view('hotel.erp.bar.purchase-list-received-show', [
            'hotel' => $hotel,
            'purchaseList' => $purchaseList,
            'ui' => $this->bar->uiConfig(),
        ]);
    }

    public function respondPurchaseList(HotelBarPurchaseList $purchaseList): View|RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->authorizePurchaseListRecipient($hotel, $purchaseList);

        $purchaseList->load(['lines', 'creator', 'hotel', 'department', 'vendor']);
        $purchaseList = $this->purchaseLists->markReceived($purchaseList);
        $purchaseList = $this->purchaseLists->syncBudgetCurrencyIfEditable($purchaseList);

        return $this->recipientRespondView(
            $purchaseList,
            sharedView: false,
            submitUrl: route('hotel.bar.purchase-lists.respond.submit', $purchaseList),
            saved: session('saved') === true,
            submitted: session('submitted') === true,
        );
    }

    public function submitRespondPurchaseList(Request $request, HotelBarPurchaseList $purchaseList): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->authorizePurchaseListRecipient($hotel, $purchaseList);

        return $this->processRecipientSubmit(
            $request,
            $purchaseList,
            function (HotelBarPurchaseList $list, bool $submitted) {
                if ($submitted) {
                    return redirect()
                        ->route('hotel.bar.purchase-lists.respond', $list)
                        ->with('submitted', true);
                }

                return redirect()
                    ->route('hotel.bar.purchase-lists.respond', $list)
                    ->with('saved', true);
            },
        );
    }

    private function recipientRespondView(
        HotelBarPurchaseList $purchaseList,
        bool $sharedView,
        string $submitUrl,
        bool $saved = false,
        bool $submitted = false,
    ): View {
        $view = $sharedView
            ? 'hotel.erp.bar.purchase-list-respond-shared'
            : 'hotel.erp.bar.purchase-list-respond';

        $packages = collect();
        $catalogItems = collect();
        $barService = $this->bar->barServiceForHotel($purchaseList->hotel);
        if ($barService) {
            $barService->load('servicePackages');
            $packages = $barService->servicePackages->where('is_active', true)->values();
            $catalogItems = $this->items->itemsForService($barService)
                ->where('is_active', true)
                ->sortBy('item_name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();
        }

        $budgetCurrency = $this->purchaseLists->resolvedBudgetCurrency($purchaseList);

        $vendors = $this->accounts->vendorsQuery($purchaseList->hotel_id, ['search' => ''])->get();

        return view($view, [
            'hotel' => $purchaseList->hotel,
            'purchaseList' => $purchaseList,
            'sharedView' => $sharedView,
            'submitUrl' => $submitUrl,
            'expectedPrices' => $this->purchaseLists->expectedPricesByItemName($purchaseList->hotel),
            'packages' => $packages,
            'catalogItems' => $catalogItems,
            'budgetCurrency' => $budgetCurrency,
            'locked' => $purchaseList->recipientBudgetLocked(),
            'saved' => $saved,
            'submitted' => $submitted,
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

    /** @param  array<string, mixed>  $validated */
    private function normalizeItemStock(array $validated): array
    {
        foreach (['stock_on_hand', 'reorder_level'] as $key) {
            if (! array_key_exists($key, $validated) || $validated[$key] === '' || $validated[$key] === null) {
                $validated[$key] = null;
            } else {
                $validated[$key] = (float) $validated[$key];
            }
        }

        return $validated;
    }

    /** @return array<string, mixed> */
    private function validateItems(Request $request, $hotel, HotelPmsService $service): array
    {
        $categoryRule = Rule::exists('hotel_pms_service_categories', 'id')
            ->where('hotel_pms_service_id', $service->id);
        $packageRule = Rule::exists('hotel_pms_service_packages', 'id')
            ->where('hotel_pms_service_id', $service->id);

        return $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:120'],
            'items.*.brand' => ['nullable', 'string', 'max:120'],
            'items.*.category_id' => ['nullable', 'integer', $categoryRule],
            'items.*.package_id' => ['required', 'integer', $packageRule],
            'items.*.pieces_per_receive_unit' => ['required', 'numeric', 'min:0.01'],
            'items.*.selling_options' => ['required', 'array', 'min:1'],
            'items.*.selling_options.*.package_id' => ['required', 'integer', $packageRule],
            'items.*.selling_options.*.pieces_per_sell_unit' => ['required', 'numeric', 'min:0.01'],
            'items.*.selling_options.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ], [], [
            'items' => 'items',
            'items.*.item_name' => 'item name',
            'items.*.brand' => 'brand / manufacturer',
            'items.*.category_id' => 'category',
            'items.*.package_id' => 'receiving unit',
            'items.*.pieces_per_receive_unit' => 'pieces per receiving unit',
            'items.*.selling_options' => 'selling methods',
            'items.*.selling_options.*.package_id' => 'sale unit',
            'items.*.selling_options.*.pieces_per_sell_unit' => 'pieces per sale unit',
        ]);
    }

    /** @param  array<string, mixed>  $row */
    private function createCatalogItemFromRow(HotelPmsService $service, $hotel, array $row): HotelPmsServiceItem
    {
        $item = $service->catalogItems()->create([
            'hotel_id' => $hotel->id,
            'hotel_pms_service_category_id' => $row['category_id'] ?? null,
            'item_name' => trim((string) $row['item_name']),
            'brand' => trim((string) ($row['brand'] ?? '')) ?: null,
            'hotel_pms_service_package_id' => $row['package_id'] ?? null,
            'pieces_per_receive_unit' => $row['pieces_per_receive_unit'],
            'unit_price' => 0,
            'currency' => $this->purchaseLists->budgetCurrencyForHotel($hotel),
            'is_active' => true,
        ]);

        $this->syncItemSellOptions($item, $row['selling_options'] ?? []);
        $this->applyPrimarySellToCatalogItem($item);

        return $item;
    }

    /** @return array<string, mixed> */
    private function validateItem(Request $request, $hotel, HotelPmsService $service, ?HotelPmsServiceItem $item = null): array
    {
        $categoryRule = Rule::exists('hotel_pms_service_categories', 'id')
            ->where('hotel_pms_service_id', $service->id);
        $packageRule = Rule::exists('hotel_pms_service_packages', 'id')
            ->where('hotel_pms_service_id', $service->id);

        return $request->validate([
            'item_name' => ['required', 'string', 'max:120'],
            'brand' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', $categoryRule],
            'package_id' => ['required', 'integer', $packageRule],
            'pieces_per_receive_unit' => ['required', 'numeric', 'min:0.01'],
            'is_active' => ['nullable', 'boolean'],
            'stock_on_hand' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'expected_unit_price' => ['nullable', 'numeric', 'min:0'],
            'selling_options' => ['required', 'array', 'min:1'],
            'selling_options.*.package_id' => ['required', 'integer', $packageRule],
            'selling_options.*.pieces_per_sell_unit' => ['required', 'numeric', 'min:0.01'],
            'selling_options.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ], [], [
            'item_name' => 'item name',
            'brand' => 'brand / manufacturer',
            'category_id' => 'category',
            'package_id' => 'receiving package',
            'pieces_per_receive_unit' => 'pieces per receiving unit',
            'stock_on_hand' => 'stock on hand',
            'reorder_level' => 'reorder level',
            'expected_unit_price' => 'expected unit price',
            'selling_options' => 'selling methods',
            'selling_options.*.package_id' => 'sale unit',
            'selling_options.*.pieces_per_sell_unit' => 'pieces per sale unit',
            'selling_options.*.unit_price' => 'sell price',
        ]);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function syncItemSellOptions(HotelPmsServiceItem $item, array $rows): void
    {
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
            $piecesPerSell = max(0.01, (float) ($row['pieces_per_sell_unit'] ?? 1));

            $option = HotelPmsServiceItemSellOption::query()->updateOrCreate(
                [
                    'hotel_pms_service_item_id' => $item->id,
                    'hotel_pms_service_package_id' => $packageId,
                ],
                [
                    'pieces_per_sell_unit' => $piecesPerSell,
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

    private function applyPrimarySellToCatalogItem(HotelPmsServiceItem $item): void
    {
        $item->loadMissing('sellOptions');
        $primary = $item->sellOptions->first();
        if (! $primary) {
            return;
        }

        $updates = [
            'selling_hotel_pms_service_package_id' => $primary->hotel_pms_service_package_id,
        ];
        if ($primary->unit_price !== null) {
            $updates['unit_price'] = $primary->unit_price;
        }
        $item->update($updates);
    }

    private function normalizeExpectedPrice(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $price = (float) $value;

        return $price > 0 ? $price : null;
    }

    private function authorizePurchaseListRecipient($hotel, HotelBarPurchaseList $purchaseList): void
    {
        abort_unless((int) $purchaseList->hotel_id === (int) $hotel->id, 404);
        abort_unless(
            $this->purchaseLists->userCanViewReceivedPurchaseList($hotel, auth()->user(), $purchaseList),
            403,
            'You are not configured as a purchase list recipient.'
        );
    }

    /** @return array<string, mixed> */
    private function barItemFormViewData($hotel, HotelPmsService $service): array
    {
        return [
            'hotel' => $hotel,
            'service' => $service,
            'categories' => $service->serviceCategories->where('is_active', true),
            'packages' => $service->servicePackages->where('is_active', true),
            'defaultPackageId' => $this->items->defaultPackageId($service),
            'nameSuggestions' => collect($service->menuItemSettings()['items'] ?? [])->pluck('name')->filter()->values(),
            'ui' => $this->bar->uiConfig(),
        ];
    }

    /** @return array<string, mixed> */
    private function deskPaymentViewOptions($hotel, HotelPmsService $service): array
    {
        $walkInModes = $this->deskPayments->walkInPaymentModes($hotel);

        return array_merge($this->deskPayments->optionsForHotel($hotel), [
            'currency' => $this->purchaseLists->budgetCurrencyForHotel($hotel),
            'walk_in_payment_modes' => $walkInModes,
            'default_payment_mode' => $walkInModes[0] ?? 'Cash',
        ]);
    }

    private function barServiceOrRedirect($hotel): HotelPmsService|RedirectResponse
    {
        $service = $this->bar->barServiceForHotel($hotel);

        if (! $service) {
            return redirect()
                ->route('hotel.services.index')
                ->with('error', 'Add the Bar service under PMS → Services first.');
        }

        return $service;
    }

    private function barSaleReturnUrl(Request $request, HotelPmsService $service): string
    {
        if ($request->input('_return') === 'sales.create') {
            return route('sales.create');
        }

        $hotel = auth()->user()->hotel()->first();
        $posDept = $hotel
            ? app(\App\Services\Pos\PosOrderService::class)->posDepartmentForService($hotel, $service->id)
            : null;

        return route('hotel.pos.index', array_filter([
            'department' => $posDept?->id,
            'outlet' => $posDept ? null : $service->id,
        ]));
    }

    private function authorizeBarItem($hotel, HotelPmsServiceItem $item): HotelPmsService
    {
        abort_unless((int) $item->hotel_id === (int) $hotel->id, 404);

        $service = $item->service;
        abort_unless($service && $service->isBar(), 404);

        return $service;
    }
}
