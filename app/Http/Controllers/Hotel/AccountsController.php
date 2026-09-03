<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Services\AccountsDataService;
use App\Services\ChannelManager\ChannelManagerCodeResolver;
use App\Services\CompanyDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountsController extends Controller
{
    public function __construct(
        private AccountsDataService $accounts,
        private CompanyDataService $companies,
        private ChannelManagerCodeResolver $codes,
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

        if ($filters['tab'] === 'payables') {
            $payables = $this->accounts->purchaseOrdersQuery($hotel->id, $filters)
                ->paginate(20)
                ->withQueryString();
            $vendors = $this->accounts->vendorsQuery($hotel->id, $filters)
                ->limit(100)
                ->get();
        }

        if ($filters['tab'] === 'taxes' && $filters['tax_generated']) {
            $taxRows = $this->accounts->taxReport($hotel, $hotelCode, $filters);
        }

        if ($filters['tab'] === 'reconciliation' && $filters['reconciliation_submitted']) {
            $reconciliation = $this->accounts->reconciliation($hotel, $filters);
        }

        return view('hotel.erp.accounts.index', [
            'hotel' => $hotel,
            'ui' => $ui,
            'filters' => $filters,
            'billingStats' => $this->companies->billingStatsByCompany($hotel, $hotelCode),
            'receivables' => $receivables,
            'payables' => $payables,
            'vendors' => $vendors,
            'taxRows' => $taxRows,
            'reconciliation' => $reconciliation,
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
            'gst_num' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'state' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

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
}
