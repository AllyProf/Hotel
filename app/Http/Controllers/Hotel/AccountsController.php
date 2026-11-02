<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelBarPurchaseList;
use App\Models\HotelExpense;
use App\Models\HotelPurchaseOrder;
use App\Services\AccountsDataService;
use App\Services\Bar\BarPurchaseListService;
use App\Services\ChannelManager\ChannelManagerCodeResolver;
use App\Services\CompanyDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AccountsController extends Controller
{
    public function __construct(
        private AccountsDataService $accounts,
        private CompanyDataService $companies,
        private ChannelManagerCodeResolver $codes,
        private BarPurchaseListService $barPurchaseLists,
    ) {}

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $hotelCode = $this->codes->hotelCode($hotel);
        $filters = $this->accounts->filtersFromRequest($request);
        $ui = $this->accounts->uiConfig();

        $receivables = null;
        $payables = null;
        $vendors = null;
        $taxRows = [];
        $reconciliation = null;

        if ($filters['tab'] === 'receivables') {
            $companyFilters = ['search' => $filters['search'], 'per_page' => 20];
            $receivables = $this->companies->query($hotel->id, $companyFilters)
                ->paginate(20)
                ->withQueryString();
        }

        $barPurchaseListsByPoId = collect();
        if ($filters['tab'] === 'payables') {
            $payables = $this->accounts->purchaseOrdersQuery($hotel->id, $filters)
                ->paginate(20)
                ->withQueryString();
            $vendors = $this->accounts->vendorsQuery($hotel->id, $filters)
                ->limit(100)
                ->get();
            $poIds = $payables->getCollection()->pluck('id')->filter()->all();
            if ($poIds !== []) {
                $barPurchaseListsByPoId = HotelBarPurchaseList::query()
                    ->where('hotel_id', $hotel->id)
                    ->whereIn('hotel_purchase_order_id', $poIds)
                    ->get()
                    ->keyBy('hotel_purchase_order_id');
            }
        }

        if ($filters['tab'] === 'taxes' && $filters['tax_generated']) {
            $taxRows = $this->accounts->taxReport($hotel, $hotelCode, $filters);
        }

        if ($filters['tab'] === 'reconciliation' && $filters['reconciliation_submitted']) {
            $reconciliation = $this->accounts->reconciliation($hotel, $filters);
        }

        $barBudgetsPending = collect();
        $barBudgetsRejected = collect();
        $storeDeskPosPending = collect();
        $storeDeskPosRejected = collect();
        $isBarAccountant = false;
        $payablesVendors = collect();
        if ($filters['tab'] === 'bar_budgets') {
            $user = auth()->user();
            $isBarAccountant = collect(\App\Support\DepartmentProcurement::workflowKeys())
                ->contains(fn (string $workflow) => $this->barPurchaseLists->userIsProcurementAccountant($hotel, $user, $workflow));
            $barBudgetsPending = $this->barPurchaseLists->listsPendingAccounting($hotel);
            $barBudgetsRejected = $this->barPurchaseLists->listsRejectedAccounting($hotel);
            $storeDeskPosPending = $this->accounts->storeDeskPurchaseOrdersPendingAccounting($hotel);
            $storeDeskPosRejected = $this->accounts->storeDeskPurchaseOrdersRejectedAccounting($hotel);
            $payablesVendors = $this->accounts->vendorsQuery($hotel->id, ['search' => ''])->get();
            if ($payablesVendors->isEmpty()) {
                $this->accounts->ensureDefaultMarketVendor($hotel);
                $payablesVendors = $this->accounts->vendorsQuery($hotel->id, ['search' => ''])->get();
            }
        }

        $defaultBarVendorId = (int) ($this->barPurchaseLists->purchaseListSettings($hotel)['default_vendor_id'] ?? 0);

        $accountsTabBadges = [
            'bar_budgets' => $this->barPurchaseLists->pendingAccountingCount($hotel)
                + $this->accounts->storeDeskPurchaseOrdersPendingAccountingCount($hotel),
        ];

        return view('hotel.erp.accounts.index', [
            'hotel' => $hotel,
            'ui' => $ui,
            'filters' => $filters,
            'billingStats' => $this->companies->billingStatsByCompany($hotel, $hotelCode),
            'receivables' => $receivables,
            'payables' => $payables,
            'barPurchaseListsByPoId' => $barPurchaseListsByPoId,
            'vendors' => $vendors,
            'taxRows' => $taxRows,
            'reconciliation' => $reconciliation,
            'barBudgetsPending' => $barBudgetsPending,
            'barBudgetsRejected' => $barBudgetsRejected,
            'storeDeskPosPending' => $storeDeskPosPending,
            'storeDeskPosRejected' => $storeDeskPosRejected,
            'isBarAccountant' => $isBarAccountant,
            'payablesVendors' => $payablesVendors,
            'defaultBarVendorId' => $defaultBarVendorId,
            'accountsTabBadges' => $accountsTabBadges,
            'paymentModes' => \App\Models\HotelExpense::paymentTypeLabels(),
        ]);
    }

    public function storeCompany(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'gst_vat' => ['nullable', 'string', 'max:80'],
        ]);

        $company = $this->companies->createCompany($hotel, $validated);

        return redirect()
            ->route('hotel.accounts.index', ['tab' => 'receivables'])
            ->with('success', 'Company '.$company->name.' added successfully.');
    }

    public function storeVendor(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'phone_country_code' => ['nullable', 'string', 'max:8'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'state' => ['nullable', 'string', 'max:80', Rule::in(config('tanzania_regions', []))],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $phoneLocal = ltrim(trim((string) ($validated['phone'] ?? '')), '0');
        $phoneCode = trim((string) ($validated['phone_country_code'] ?? ''));
        $validated['phone'] = $phoneLocal !== ''
            ? trim($phoneCode.$phoneLocal)
            : null;
        unset($validated['phone_country_code']);

        $vendor = $this->accounts->createVendor($hotel, $validated);

        return redirect()
            ->route('hotel.accounts.index', ['tab' => 'payables'])
            ->with('success', 'Vendor '.$vendor->name.' added successfully.');
    }

    public function createPurchaseOrder(): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $vendors = $this->accounts->vendorsQuery($hotel->id, ['search' => ''])->get();

        return view('hotel.erp.accounts.purchase-orders.create', [
            'hotel' => $hotel,
            'vendors' => $vendors,
        ]);
    }

    public function storePurchaseOrder(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'hotel_vendor_id' => ['required', 'integer'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:160'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.pre_tax' => ['required', 'numeric', 'min:0'],
            'items.*.tax' => ['required', 'numeric', 'min:0'],
            'items.*.total' => ['required', 'numeric', 'min:0'],
        ]);

        $vendor = \App\Models\HotelVendor::query()
            ->where('hotel_id', $hotel->id)
            ->where('id', $validated['hotel_vendor_id'])
            ->firstOrFail();

        $items = collect($validated['items'])->map(fn (array $item) => [
            'name' => $item['name'],
            'quantity' => (float) $item['quantity'],
            'rate' => (float) $item['rate'],
            'pre_tax' => (float) $item['pre_tax'],
            'tax' => (float) $item['tax'],
            'total' => (float) $item['total'],
        ])->all();

        $totals = [
            'pre_tax' => collect($items)->sum('pre_tax'),
            'tax' => collect($items)->sum('tax'),
            'total' => collect($items)->sum('total'),
        ];

        $order = $this->accounts->createPurchaseOrder(
            $hotel,
            [
                'hotel_vendor_id' => $vendor->id,
                ...$totals,
            ],
            $items,
            $request->file('image')
        );

        return redirect()
            ->route('hotel.accounts.index', ['tab' => 'payables'])
            ->with('success', 'Purchase order '.$order->po_number.' created.');
    }

    public function payPurchaseOrder(Request $request, HotelPurchaseOrder $order): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $data = $request->validate([
            'payment_type' => ['required', 'string', 'in:'.implode(',', HotelExpense::PAYMENT_TYPES)],
            'expense_date' => ['required', 'date'],
        ]);

        try {
            $this->accounts->payPurchaseOrder(
                $hotel,
                $order,
                $data['payment_type'],
                $data['expense_date'],
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return redirect()
            ->route('hotel.accounts.index', ['tab' => 'payables'])
            ->with('success', 'PO '.$order->po_number.' marked paid and posted to Expenses.');
    }

    public function approveBarPurchaseList(Request $request, HotelBarPurchaseList $purchaseList): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $purchaseList->hotel_id === (int) $hotel->id, 404);
        abort_unless($this->barPurchaseLists->userIsProcurementAccountant($hotel, auth()->user(), $purchaseList->workflowKey()), 403);

        $validated = $request->validate([
            'accounting_notes' => ['nullable', 'string', 'max:2000'],
            'hotel_vendor_id' => [
                'nullable',
                'integer',
                Rule::exists('hotel_vendors', 'id')->where('hotel_id', $hotel->id),
            ],
        ]);

        try {
            $list = $this->barPurchaseLists->approveAccounting(
                $purchaseList,
                auth()->user(),
                $validated['accounting_notes'] ?? null,
                isset($validated['hotel_vendor_id']) ? (int) $validated['hotel_vendor_id'] : null,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return redirect()
            ->route('hotel.accounts.index', ['tab' => 'bar_budgets'])
            ->with('success', 'Budget approved.');
    }

    public function rejectBarPurchaseList(Request $request, HotelBarPurchaseList $purchaseList): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $purchaseList->hotel_id === (int) $hotel->id, 404);
        abort_unless($this->barPurchaseLists->userIsProcurementAccountant($hotel, auth()->user(), $purchaseList->workflowKey()), 403);

        $validated = $request->validate([
            'accounting_notes' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $this->barPurchaseLists->rejectAccounting(
                $purchaseList,
                auth()->user(),
                $validated['accounting_notes'],
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return redirect()
            ->route('hotel.accounts.index', ['tab' => 'bar_budgets'])
            ->with('success', 'Budget rejected.');
    }

    public function approveStoreDeskPurchaseOrder(Request $request, HotelPurchaseOrder $order): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);
        abort_unless(
            collect(\App\Support\DepartmentProcurement::workflowKeys())
                ->contains(fn (string $workflow) => $this->barPurchaseLists->userIsProcurementAccountant($hotel, auth()->user(), $workflow)),
            403
        );

        $validated = $request->validate([
            'accounting_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $order = $this->accounts->approveStoreDeskPurchaseOrderAccounts(
                $hotel,
                $order,
                auth()->user(),
                $validated['accounting_notes'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return redirect()
            ->route('hotel.accounts.index', ['tab' => 'bar_budgets'])
            ->with('success', 'Purchase order approved.');
    }

    public function rejectStoreDeskPurchaseOrder(Request $request, HotelPurchaseOrder $order): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);
        abort_unless(
            collect(\App\Support\DepartmentProcurement::workflowKeys())
                ->contains(fn (string $workflow) => $this->barPurchaseLists->userIsProcurementAccountant($hotel, auth()->user(), $workflow)),
            403
        );

        $validated = $request->validate([
            'accounting_notes' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $this->accounts->rejectStoreDeskPurchaseOrderAccounts(
                $hotel,
                $order,
                auth()->user(),
                $validated['accounting_notes'],
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return redirect()
            ->route('hotel.accounts.index', ['tab' => 'bar_budgets'])
            ->with('success', 'Purchase order rejected.');
    }

    public function exportReconciliation(Request $request): BinaryFileResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $filters = $this->accounts->filtersFromRequest($request);
        $filters['reconciliation_submitted'] = true;
        $filters['tab'] = 'reconciliation';

        if (! $request->boolean('submitted')) {
            abort(422, 'Run reconciliation first.');
        }

        $export = $this->accounts->reconciliationExport($hotel, $filters);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([$export['headers']]);

        $rowOffset = 2;
        foreach ($export['rows'] as $row) {
            $sheet->fromArray([$row], null, 'A'.$rowOffset);
            $rowOffset++;
        }

        foreach (range('A', $sheet->getHighestColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'reconciliation-'.$filters['from_date'].'-'.$filters['to_date'].'.xlsx';
        $path = storage_path('app/temp/'.$filename);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }
}
