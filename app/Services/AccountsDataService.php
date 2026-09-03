<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelPurchaseOrder;
use App\Models\HotelPurchaseOrderItem;
use App\Models\HotelVendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class AccountsDataService
{
    public function __construct(
        private CompanyDataService $companies,
        private ExpenseDataService $expenses,
    ) {}

    /** @return array<string, mixed> */
    public function uiConfig(): array
    {
        return config('hotel_accounts', []);
    }

    /** @return array<string, mixed> */
    public function filtersFromRequest(Request $request): array
    {
        $tab = $request->input('tab', 'receivables');
        $tabs = array_keys($this->uiConfig()['tabs'] ?? []);

        if (! in_array($tab, $tabs, true)) {
            $tab = 'receivables';
        }

        $fromDate = $request->input('from_date', now()->startOfYear()->format('Y-m-d'));
        $toDate = $request->input('to_date', now()->format('Y-m-d'));

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $taxType = $request->input('tax_type', 'sales');
        if (! array_key_exists($taxType, $this->uiConfig()['taxes']['types'] ?? [])) {
            $taxType = 'sales';
        }

        $paymentMode = trim((string) $request->input('payment_mode', 'cash'));
        if ($paymentMode === '') {
            $paymentMode = 'cash';
        }

        return [
            'tab' => $tab,
            'search' => trim((string) $request->input('search', '')),
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'tax_type' => $taxType,
            'tax_generated' => $request->boolean('tax_generated'),
            'payment_mode' => $paymentMode,
            'reconciliation_submitted' => $request->boolean('submitted'),
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function vendorsQuery(int $hotelId, array $filters): Builder
    {
        $query = HotelVendor::query()
            ->where('hotel_id', $hotelId)
            ->orderBy('name');

        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(function (Builder $builder) use ($term) {
                $builder->where('name', 'like', $term)
                    ->orWhere('contact_person', 'like', $term)
                    ->orWhere('gst_num', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('state', 'like', $term);
            });
        }

        return $query;
    }

    /** @param  array<string, mixed>  $filters */
    public function purchaseOrdersQuery(int $hotelId, array $filters): Builder
    {
        $query = HotelPurchaseOrder::query()
            ->with('vendor')
            ->where('hotel_id', $hotelId)
            ->orderByDesc('created_at');

        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(function (Builder $builder) use ($term) {
                $builder->where('po_number', 'like', $term)
                    ->orWhereHas('vendor', fn (Builder $vendor) => $vendor->where('name', 'like', $term));
            });
        }

        return $query;
    }

    /** @param  array<string, mixed>  $data */
    public function createVendor(Hotel $hotel, array $data): HotelVendor
    {
        return HotelVendor::query()->create([
            'hotel_id' => $hotel->id,
            'name' => $data['name'],
            'contact_person' => $data['contact_person'] ?? null,
            'gst_num' => $data['gst_num'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'state' => $data['state'] ?? null,
            'address' => $data['address'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{name: string, quantity: float, rate: float, pre_tax: float, tax: float, total: float}>  $items
     */
    public function createPurchaseOrder(Hotel $hotel, array $data, array $items, ?UploadedFile $image = null): HotelPurchaseOrder
    {
        return DB::transaction(function () use ($hotel, $data, $items, $image) {
            $poNumber = 'PO-'.now()->format('YmdHis');

            $order = HotelPurchaseOrder::query()->create([
                'hotel_id' => $hotel->id,
                'hotel_vendor_id' => $data['hotel_vendor_id'],
                'created_by' => auth()->id(),
                'po_number' => $poNumber,
                'image_path' => $image ? $image->store('hotel-purchase-orders/'.$hotel->id, 'public') : null,
                'pre_tax' => $data['pre_tax'],
                'tax' => $data['tax'],
                'total' => $data['total'],
                'status' => 'open',
            ]);

            foreach ($items as $item) {
                HotelPurchaseOrderItem::query()->create([
                    'hotel_purchase_order_id' => $order->id,
                    ...$item,
                ]);
            }

            return $order->load(['vendor', 'items']);
        });
    }

    /** @param  array<string, mixed>  $filters @return list<array{tax: string, total: float}> */
    public function taxReport(Hotel $hotel, string $hotelCode, array $filters): array
    {
        if ($filters['tax_type'] === 'purchases') {
            return $this->purchaseTaxReport($hotel, $filters);
        }

        return $this->salesTaxReport($hotel, $hotelCode, $filters);
    }

    /** @param  array<string, mixed>  $filters @return list<array{tax: string, total: float}> */
    private function salesTaxReport(Hotel $hotel, string $hotelCode, array $filters): array
    {
        $rows = \App\Models\CmReservation::query()
            ->where(function (Builder $query) use ($hotel, $hotelCode) {
                $query->where('hotel_id', $hotel->id)
                    ->orWhere('hotel_code', $hotelCode);
            })
            ->where('status', '!=', \App\Models\CmReservation::STATUS_CANCELLED)
            ->whereBetween('checkin', [$filters['from_date'], $filters['to_date']])
            ->get();

        $groups = [];

        foreach ($rows as $row) {
            $taxAmount = (float) ($row->tax ?? 0);

            if ($taxAmount <= 0) {
                continue;
            }

            $label = strtoupper((string) ($row->currency ?: $hotel->currency ?: 'Tax')).' Tax';

            if (! isset($groups[$label])) {
                $groups[$label] = 0.0;
            }

            $groups[$label] += $taxAmount;
        }

        return collect($groups)
            ->map(fn (float $total, string $tax) => ['tax' => $tax, 'total' => $total])
            ->values()
            ->all();
    }

    /** @param  array<string, mixed>  $filters @return list<array{tax: string, total: float}> */
    private function purchaseTaxReport(Hotel $hotel, array $filters): array
    {
        $orders = HotelPurchaseOrder::query()
            ->where('hotel_id', $hotel->id)
            ->whereBetween('created_at', [
                $filters['from_date'].' 00:00:00',
                $filters['to_date'].' 23:59:59',
            ])
            ->get();

        $total = $orders->sum(fn (HotelPurchaseOrder $order) => (float) $order->tax);

        if ($total <= 0) {
            return [];
        }

        return [[
            'tax' => 'Purchase Tax',
            'total' => (float) $total,
        ]];
    }

    /** @param  array<string, mixed>  $filters @return array{rows: \Illuminate\Support\Collection, totals: array{paid_in: float, paid_out: float}} */
    public function reconciliation(Hotel $hotel, array $filters): array
    {
        $expenseFilters = [
            'from_date' => $filters['from_date'],
            'to_date' => $filters['to_date'],
            'payment_type' => $filters['payment_mode'],
            'paid_type' => '',
        ];

        $rows = $this->expenses->query($hotel->id, $expenseFilters)->get();

        $mapped = $rows->map(function ($row) {
            $paidIn = $row->paid_type === \App\Models\HotelExpense::PAID_IN ? (float) $row->amount : 0.0;
            $paidOut = $row->paid_type === \App\Models\HotelExpense::PAID_OUT ? (float) $row->amount : 0.0;

            return [
                'date' => $row->expense_date?->format('d/m/Y') ?? '—',
                'pos_name' => config('hotel_accounts.reconciliation.default_pos_name', 'Front Desk'),
                'invoice_no' => $row->invoice_no ?: '—',
                'party' => $row->vendor ?: '—',
                'comments' => $row->comments ?: '—',
                'image_url' => $row->details_path ? asset('storage/'.$row->details_path) : null,
                'user' => auth()->user()?->name ?? '—',
                'paid_in' => $paidIn,
                'paid_out' => $paidOut,
            ];
        });

        return [
            'rows' => $mapped,
            'totals' => [
                'paid_in' => $mapped->sum('paid_in'),
                'paid_out' => $mapped->sum('paid_out'),
            ],
        ];
    }

    public function companyService(): CompanyDataService
    {
        return $this->companies;
    }
}
