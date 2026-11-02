<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelBarPurchaseList;
use App\Models\HotelExpense;
use App\Models\HotelPurchaseOrder;
use App\Models\HotelPurchaseOrderItem;
use App\Models\HotelVendor;
use App\Models\User;
use App\Support\DepartmentProcurement;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
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
            ->with(['vendor', 'paymentExpense'])
            ->where('hotel_id', $hotelId)
            ->orderByDesc('created_at');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $builder) use ($term) {
                $builder->where('po_number', 'like', $term)
                    ->orWhereHas('vendor', fn (Builder $vendor) => $vendor->where('name', 'like', $term));
            });
        }

        $dateColumn = ($filters['scope'] ?? '') === 'invoices' ? 'invoiced_at' : 'created_at';

        $fromDate = trim((string) ($filters['from_date'] ?? ''));
        if ($fromDate !== '') {
            $query->whereDate($dateColumn, '>=', $fromDate);
        }

        $toDate = trim((string) ($filters['to_date'] ?? ''));
        if ($toDate !== '') {
            $query->whereDate($dateColumn, '<=', $toDate);
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $scope = trim((string) ($filters['scope'] ?? ''));
        if ($scope === 'purchase_orders') {
            $query->where(function (Builder $builder) {
                $builder->whereIn('status', [
                    HotelPurchaseOrder::STATUS_APPLIED,
                    HotelPurchaseOrder::STATUS_OPEN,
                ])->orWhere(function (Builder $pendingReceive) {
                    $pendingReceive
                        ->where('status', HotelPurchaseOrder::STATUS_INVOICED)
                        ->where('goods_received', false)
                        ->where('po_number', 'like', 'ORD-%');
                })->orWhere(function (Builder $paidAwaitingReceive) {
                    $paidAwaitingReceive
                        ->where('status', HotelPurchaseOrder::STATUS_PAID)
                        ->where('goods_received', false)
                        ->where('po_number', 'like', 'ORD-%');
                });
            });
        } elseif ($scope === 'invoices') {
            $query->where('status', HotelPurchaseOrder::STATUS_INVOICED);
        }

        return $query;
    }

    public function suggestStoreInvoiceNumber(Hotel $hotel): string
    {
        return $this->nextStoreInvoiceNumber($hotel);
    }

    /** @param  array<string, mixed>  $data */
    public function saveStorePurchaseOrderInvoice(
        Hotel $hotel,
        HotelPurchaseOrder $order,
        array $data,
        ?UploadedFile $image = null,
    ): HotelPurchaseOrder {
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);

        if ($order->isStoreDeskPurchaseOrder() && ! $order->canInvoiceFromStoreDesk() && $order->status !== HotelPurchaseOrder::STATUS_INVOICED) {
            throw new InvalidArgumentException('Accounts must approve this purchase order before invoicing.');
        }

        $isNew = $order->status === HotelPurchaseOrder::STATUS_OPEN;
        if (! $isNew && $order->status !== HotelPurchaseOrder::STATUS_INVOICED) {
            throw new InvalidArgumentException('Invoice details can only be saved for open or invoiced orders.');
        }

        $invoiceNumber = trim((string) ($data['invoice_number'] ?? ''));
        if ($invoiceNumber === '') {
            $invoiceNumber = $isNew
                ? $this->nextStoreInvoiceNumber($hotel)
                : (string) ($order->invoice_number ?? $this->nextStoreInvoiceNumber($hotel));
        }

        $invoicedAt = isset($data['invoiced_at']) && $data['invoiced_at'] !== ''
            ? Carbon::parse((string) $data['invoiced_at'])
            : ($order->invoiced_at ?? now());

        $payload = [
            'invoice_number' => $invoiceNumber,
            'invoiced_at' => $invoicedAt,
        ];

        if ($isNew) {
            $payload['status'] = HotelPurchaseOrder::STATUS_INVOICED;
        }

        if ($image) {
            if ($order->image_path) {
                Storage::disk('public')->delete($order->image_path);
            }
            $payload['image_path'] = $image->store('hotel-purchase-orders/'.$hotel->id, 'public');
        }

        $order->update($payload);

        return $order->fresh(['vendor', 'items']);
    }

    /** Open store-desk POs must be invoiced before stock receipt; use auto number when receiving from the PO queue. */
    public function ensureStoreDeskInvoicedForGoodsReceive(Hotel $hotel, HotelPurchaseOrder $order): HotelPurchaseOrder
    {
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);

        if (! $order->canReceiveStoreDeskGoods()) {
            throw new InvalidArgumentException('This purchase order is not ready to receive.');
        }

        if ($order->isInvoiced() || $order->isPaid()) {
            return $order->fresh(['vendor', 'items', 'goodsReceivedBy']);
        }

        return $this->saveStorePurchaseOrderInvoice($hotel, $order, []);
    }

    private function nextStoreInvoiceNumber(Hotel $hotel): string
    {
        $start = (int) config('hotel_stores.invoice_number_start', 700);
        $maxNumeric = HotelPurchaseOrder::query()
            ->where('hotel_id', $hotel->id)
            ->whereNotNull('invoice_number')
            ->pluck('invoice_number')
            ->map(fn ($value) => ctype_digit((string) $value) ? (int) $value : 0)
            ->max();

        $next = max($start, (int) $maxNumeric + 1);

        return (string) $next;
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
            'account_name' => $data['account_name'] ?? null,
            'account_no' => $data['account_no'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'ifsc_code' => $data['ifsc_code'] ?? null,
        ]);
    }

    /** @param  array<string, mixed>  $data */
    public function updateVendor(Hotel $hotel, HotelVendor $vendor, array $data): HotelVendor
    {
        abort_unless($vendor->hotel_id === $hotel->id, 404);

        $vendor->update([
            'name' => $data['name'],
            'contact_person' => $data['contact_person'] ?? null,
            'gst_num' => $data['gst_num'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'state' => $data['state'] ?? null,
            'address' => $data['address'] ?? null,
            'account_name' => $data['account_name'] ?? null,
            'account_no' => $data['account_no'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'ifsc_code' => $data['ifsc_code'] ?? null,
        ]);

        return $vendor->fresh();
    }

    public function deleteVendor(Hotel $hotel, HotelVendor $vendor): void
    {
        abort_unless($vendor->hotel_id === $hotel->id, 404);

        if ($vendor->purchaseOrders()->exists()) {
            throw new InvalidArgumentException('This vendor cannot be deleted because purchase orders are linked to them.');
        }

        $vendor->delete();
    }

    /**
     * @return array{code: string, local: string}
     */
    public function splitVendorPhone(?string $phone, string $defaultCode = '+255'): array
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return ['code' => $defaultCode, 'local' => ''];
        }

        $codes = collect(config('hotel_stores.vendor_phone_codes', []))
            ->pluck('code')
            ->sortByDesc(fn ($code) => strlen((string) $code))
            ->values();

        foreach ($codes as $code) {
            $code = (string) $code;
            if (str_starts_with($phone, $code)) {
                return [
                    'code' => $code,
                    'local' => ltrim(substr($phone, strlen($code)), '0'),
                ];
            }
        }

        return ['code' => $defaultCode, 'local' => $phone];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{name: string, quantity: float, rate: float, pre_tax: float, tax: float, total: float}>  $items
     */
    public function ensureDefaultMarketVendor(Hotel $hotel): HotelVendor
    {
        return HotelVendor::query()->firstOrCreate(
            ['hotel_id' => $hotel->id, 'name' => 'Market / cash purchases'],
            ['contact_person' => 'Procurement'],
        );
    }

    public function createPurchaseOrderFromBarList(
        Hotel $hotel,
        HotelBarPurchaseList $list,
        int $vendorId,
    ): HotelPurchaseOrder {
        if ((int) $list->hotel_id !== (int) $hotel->id) {
            throw new InvalidArgumentException('Purchase list not found.');
        }

        if ($list->hotel_purchase_order_id) {
            $existing = HotelPurchaseOrder::query()
                ->where('hotel_id', $hotel->id)
                ->whereKey($list->hotel_purchase_order_id)
                ->first();
            if ($existing) {
                return $existing->load(['vendor', 'items']);
            }
        }

        $vendor = HotelVendor::query()
            ->where('hotel_id', $hotel->id)
            ->whereKey($vendorId)
            ->first();
        if (! $vendor) {
            throw new InvalidArgumentException('Select a valid vendor for the purchase order.');
        }

        $list->loadMissing('lines');

        $items = [];
        foreach ($list->lines as $line) {
            $lineTotal = (float) ($line->line_budget ?? 0);
            if ($lineTotal <= 0 && $line->unit_cost !== null) {
                $lineTotal = round((float) $line->quantity * (float) $line->unit_cost, 2);
            }
            if ($lineTotal <= 0) {
                continue;
            }
            $qty = max(0.01, (float) $line->quantity);
            $rate = round($lineTotal / $qty, 2);

            $items[] = [
                'name' => $line->item_name.($line->unit ? ' ('.$line->unit.')' : ''),
                'quantity' => $qty,
                'rate' => $rate,
                'pre_tax' => $lineTotal,
                'tax' => 0.0,
                'total' => $lineTotal,
            ];
        }

        $total = (float) ($list->budget_total ?? 0);
        if ($items === [] && $total > 0) {
            $items[] = [
                'name' => 'Bar list '.$list->reference_no,
                'quantity' => 1,
                'rate' => $total,
                'pre_tax' => $total,
                'tax' => 0.0,
                'total' => $total,
            ];
        }

        if ($items === []) {
            throw new InvalidArgumentException('Budget lines with amounts are required to create a purchase order.');
        }

        $preTax = round(collect($items)->sum('pre_tax'), 2);
        $tax = round(collect($items)->sum('tax'), 2);
        $orderTotal = round(collect($items)->sum('total'), 2);

        return DB::transaction(function () use ($hotel, $list, $vendor, $items, $preTax, $tax, $orderTotal) {
            $poNumber = 'PO-BAR-'.$list->id;

            $order = HotelPurchaseOrder::query()->create([
                'hotel_id' => $hotel->id,
                'hotel_vendor_id' => $vendor->id,
                'created_by' => auth()->id(),
                'po_number' => $poNumber,
                'pre_tax' => $preTax,
                'tax' => $tax,
                'total' => $orderTotal,
                'status' => HotelPurchaseOrder::STATUS_OPEN,
            ]);

            foreach ($items as $item) {
                HotelPurchaseOrderItem::query()->create([
                    'hotel_purchase_order_id' => $order->id,
                    ...$item,
                ]);
            }

            $list->update(['hotel_purchase_order_id' => $order->id]);

            return $order->load(['vendor', 'items']);
        });
    }

    public function syncBarListPurchaseOrderToActualSpend(Hotel $hotel, HotelBarPurchaseList $list): ?HotelPurchaseOrder
    {
        if ((int) $list->hotel_id !== (int) $hotel->id) {
            throw new InvalidArgumentException('Purchase list not found.');
        }

        $orderId = (int) ($list->hotel_purchase_order_id ?? 0);
        if ($orderId <= 0) {
            return null;
        }

        $order = HotelPurchaseOrder::query()
            ->where('hotel_id', $hotel->id)
            ->whereKey($orderId)
            ->first();
        if (! $order) {
            return null;
        }

        if ($order->isPaid()) {
            return $order;
        }

        $list->loadMissing('lines');

        $items = [];
        foreach ($list->lines as $line) {
            if ($line->market_item_found === false) {
                continue;
            }
            $qty = max(0, (float) ($line->quantity_purchased ?? 0));
            $lineTotal = ($line->purchase_line_total !== null)
                ? max(0, (float) $line->purchase_line_total)
                : 0.0;
            if ($qty <= 0 || $lineTotal <= 0) {
                continue;
            }
            $rate = round($lineTotal / $qty, 2);

            $items[] = [
                'name' => $line->item_name.($line->unit ? ' ('.$line->unit.')' : ''),
                'quantity' => $qty,
                'rate' => $rate,
                'pre_tax' => $lineTotal,
                'tax' => 0.0,
                'total' => $lineTotal,
            ];
        }

        return DB::transaction(function () use ($order, $items, $list) {
            $order->items()->delete();

            if ($items === []) {
                $order->update([
                    'pre_tax' => 0,
                    'tax' => 0,
                    'total' => 0,
                ]);

                return $order->fresh(['vendor', 'items']);
            }

            foreach ($items as $item) {
                HotelPurchaseOrderItem::query()->create([
                    'hotel_purchase_order_id' => $order->id,
                    ...$item,
                ]);
            }

            $preTax = round(collect($items)->sum('pre_tax'), 2);
            $tax = round(collect($items)->sum('tax'), 2);
            $orderTotal = round(collect($items)->sum('total'), 2);

            $order->update([
                'pre_tax' => $preTax,
                'tax' => $tax,
                'total' => $orderTotal,
            ]);

            return $order->fresh(['vendor', 'items']);
        });
    }

    public function createPurchaseOrder(Hotel $hotel, array $data, array $items, ?UploadedFile $image = null): HotelPurchaseOrder
    {
        return DB::transaction(function () use ($hotel, $data, $items, $image) {
            $poNumber = (string) ($data['po_number'] ?? ('PO-'.now()->format('YmdHis')));

            $order = HotelPurchaseOrder::query()->create([
                'hotel_id' => $hotel->id,
                'hotel_vendor_id' => $data['hotel_vendor_id'],
                'hotel_store_category_id' => $data['hotel_store_category_id'] ?? null,
                'created_by' => auth()->id(),
                'po_number' => $poNumber,
                'image_path' => $image ? $image->store('hotel-purchase-orders/'.$hotel->id, 'public') : null,
                'pre_tax' => $data['pre_tax'],
                'tax' => $data['tax'],
                'total' => $data['total'],
                'status' => (string) ($data['status'] ?? HotelPurchaseOrder::STATUS_OPEN),
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

    /** @param  array<int, array<string, mixed>>  $items */
    public function updatePurchaseOrder(Hotel $hotel, HotelPurchaseOrder $order, array $data, array $items): HotelPurchaseOrder
    {
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);

        if (! in_array($order->status, [HotelPurchaseOrder::STATUS_APPLIED, HotelPurchaseOrder::STATUS_OPEN], true)) {
            throw new InvalidArgumentException('This purchase order cannot be edited.');
        }

        return DB::transaction(function () use ($order, $data, $items) {
            $order->update([
                'hotel_vendor_id' => (int) $data['hotel_vendor_id'],
                'hotel_store_category_id' => $data['hotel_store_category_id'] ?? null,
                'pre_tax' => (float) $data['pre_tax'],
                'tax' => (float) $data['tax'],
                'total' => (float) $data['total'],
            ]);

            $order->items()->delete();

            foreach ($items as $item) {
                HotelPurchaseOrderItem::query()->create([
                    'hotel_purchase_order_id' => $order->id,
                    ...$item,
                ]);
            }

            return $order->fresh(['vendor', 'items']);
        });
    }

    public function approveStorePurchaseOrder(Hotel $hotel, HotelPurchaseOrder $order): HotelPurchaseOrder
    {
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);

        if ($order->status !== HotelPurchaseOrder::STATUS_APPLIED) {
            throw new InvalidArgumentException('Only applied purchase orders can be approved.');
        }

        $order->update(['status' => HotelPurchaseOrder::STATUS_OPEN]);

        return $order->fresh(['vendor', 'items']);
    }

    public function deletePurchaseOrder(Hotel $hotel, HotelPurchaseOrder $order): void
    {
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);

        if ($order->status === HotelPurchaseOrder::STATUS_PAID) {
            throw new InvalidArgumentException('Paid purchase orders cannot be deleted.');
        }

        $order->items()->delete();
        $order->delete();
    }

    public function submitStoreDeskPurchaseOrderToAccounts(Hotel $hotel, HotelPurchaseOrder $order): HotelPurchaseOrder
    {
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);

        if (! $order->canSubmitStoreDeskToAccounts()) {
            throw new InvalidArgumentException('Enter a buying price on every line before submitting to Accounts.');
        }

        $order->update([
            'accounts_status' => HotelPurchaseOrder::ACCOUNTS_PENDING,
            'accounts_submitted_at' => now(),
            'accounts_reviewed_at' => null,
            'accounts_reviewed_by_user_id' => null,
            'accounts_notes' => null,
        ]);

        return $order->fresh(['vendor', 'items', 'storeDepartment', 'creator']);
    }

    public function approveStoreDeskPurchaseOrderAccounts(
        Hotel $hotel,
        HotelPurchaseOrder $order,
        User $user,
        ?string $notes = null,
    ): HotelPurchaseOrder {
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);

        if (! $order->isStoreDeskPurchaseOrder() || $order->accounts_status !== HotelPurchaseOrder::ACCOUNTS_PENDING) {
            throw new InvalidArgumentException('This purchase order is not awaiting accountant approval.');
        }

        $order->update([
            'accounts_status' => HotelPurchaseOrder::ACCOUNTS_APPROVED,
            'accounts_reviewed_at' => now(),
            'accounts_reviewed_by_user_id' => $user->id,
            'accounts_notes' => trim((string) $notes) ?: null,
            'status' => HotelPurchaseOrder::STATUS_OPEN,
        ]);

        return $order->fresh(['vendor', 'items', 'storeDepartment', 'creator']);
    }

    public function rejectStoreDeskPurchaseOrderAccounts(
        Hotel $hotel,
        HotelPurchaseOrder $order,
        User $user,
        string $notes,
    ): HotelPurchaseOrder {
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);

        if (! $order->isStoreDeskPurchaseOrder() || $order->accounts_status !== HotelPurchaseOrder::ACCOUNTS_PENDING) {
            throw new InvalidArgumentException('This purchase order is not awaiting accountant approval.');
        }

        $order->update([
            'accounts_status' => HotelPurchaseOrder::ACCOUNTS_REJECTED,
            'accounts_reviewed_at' => now(),
            'accounts_reviewed_by_user_id' => $user->id,
            'accounts_notes' => trim($notes),
            'status' => HotelPurchaseOrder::STATUS_APPLIED,
        ]);

        return $order->fresh(['vendor', 'items', 'storeDepartment', 'creator']);
    }

    /** @return Collection<int, HotelPurchaseOrder> */
    public function storeDeskPurchaseOrdersPendingAccounting(Hotel $hotel, int $limit = 50): Collection
    {
        return HotelPurchaseOrder::query()
            ->where('hotel_id', $hotel->id)
            ->where('po_number', 'like', 'ORD-%')
            ->where('accounts_status', HotelPurchaseOrder::ACCOUNTS_PENDING)
            ->with(['vendor', 'items.masterItem.storePackage', 'storeDepartment', 'creator'])
            ->orderByDesc('accounts_submitted_at')
            ->limit($limit)
            ->get();
    }

    public function storeDeskPurchaseOrdersPendingAccountingCount(Hotel $hotel): int
    {
        return (int) HotelPurchaseOrder::query()
            ->where('hotel_id', $hotel->id)
            ->where('po_number', 'like', 'ORD-%')
            ->where('accounts_status', HotelPurchaseOrder::ACCOUNTS_PENDING)
            ->count();
    }

    /**
     * Currency for store POs — same rules as procurement budgets (Settings → Budget currency, then local rates).
     */
    public function storeDeskCurrency(Hotel $hotel): string
    {
        $fromProcurementSettings = $this->procurementBudgetCurrencyFromSettings($hotel);
        if ($fromProcurementSettings !== '') {
            return $fromProcurementSettings;
        }

        $locals = $hotel->ratePlans()
            ->whereNotNull('local_currency')
            ->where('local_currency', '!=', '')
            ->distinct()
            ->pluck('local_currency')
            ->map(fn ($code) => $this->normalizeCurrencyCode((string) $code))
            ->filter()
            ->unique()
            ->values();

        $localFromRates = $locals->first(
            fn (string $code) => ! in_array($code, ['USD', 'EUR', 'GBP'], true)
        ) ?? $locals->first();
        if ($localFromRates) {
            return $localFromRates;
        }

        $hotelCurrency = $this->normalizeCurrencyCode((string) ($hotel->currency ?? ''));
        if ($hotelCurrency !== '') {
            return $hotelCurrency;
        }

        $fallback = $this->normalizeCurrencyCode((string) config('hotel_bar.fallback_budget_currency', ''));

        return $fallback !== '' ? $fallback : 'TZS';
    }

    private function procurementBudgetCurrencyFromSettings(Hotel $hotel): string
    {
        $settingsService = app(HotelSettingsService::class);
        $defaults = $settingsService->defaultDepartmentProcurement()['purchase_list'];
        $settings = $settingsService->ensureDefaults($hotel);

        foreach (DepartmentProcurement::workflowKeys() as $workflowKey) {
            $workflow = DepartmentProcurement::workflow($workflowKey);
            $settingsKey = (string) $workflow['settings_key'];
            $pl = $defaults;

            if (! empty($workflow['settings_nested'])) {
                $root = is_array($settings->{$settingsKey}) ? $settings->{$settingsKey} : $settingsService->defaultBar();
                if (is_array($root['purchase_list'] ?? null)) {
                    $pl = array_merge($defaults, $root['purchase_list']);
                }
            } else {
                $root = is_array($settings->{$settingsKey})
                    ? $settings->{$settingsKey}
                    : $settingsService->defaultDepartmentProcurement();
                if (is_array($root['purchase_list'] ?? null)) {
                    $pl = array_merge($defaults, $root['purchase_list']);
                }
            }

            $currency = $this->normalizeCurrencyCode((string) ($pl['budget_currency'] ?? ''));
            if ($currency !== '') {
                return $currency;
            }
        }

        return '';
    }

    private function normalizeCurrencyCode(string $code): string
    {
        $code = strtoupper(trim($code));

        return match ($code) {
            'TZ' => 'TZS',
            default => $code,
        };
    }

    /** Amount only (no currency code) — matches Payables column style. */
    public function formatAccountsTableAmount(float $amount, ?string $currencyCode = null): string
    {
        $currency = strtoupper(trim((string) ($currencyCode ?? '')));
        $decimals = in_array($currency, ['TZS', 'UGX', 'RWF', 'BIF', 'KES'], true) ? 0 : 2;

        return number_format($amount, $decimals, '.', ',');
    }

    /** Budget line for modals / confirmations (currency before amount). */
    public function formatAccountsBudgetLabel(float $amount, string $currencyCode): string
    {
        $currency = strtoupper(trim($currencyCode)) ?: 'TZS';
        $formatted = $this->formatAccountsTableAmount($amount, $currency);

        return $currency.' '.$formatted;
    }

    /** @return Collection<int, HotelPurchaseOrder> */
    public function storeDeskPurchaseOrdersRejectedAccounting(Hotel $hotel, int $limit = 50): Collection
    {
        return HotelPurchaseOrder::query()
            ->where('hotel_id', $hotel->id)
            ->where('po_number', 'like', 'ORD-%')
            ->where('accounts_status', HotelPurchaseOrder::ACCOUNTS_REJECTED)
            ->where('status', HotelPurchaseOrder::STATUS_APPLIED)
            ->with(['vendor', 'items', 'storeDepartment', 'creator'])
            ->orderByDesc('accounts_reviewed_at')
            ->limit($limit)
            ->get();
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
                'user' => '—',
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

    public function payPurchaseOrder(
        Hotel $hotel,
        HotelPurchaseOrder $order,
        string $paymentType,
        string $expenseDate,
    ): HotelPurchaseOrder {
        if ((int) $order->hotel_id !== (int) $hotel->id) {
            throw new InvalidArgumentException('Purchase order not found.');
        }

        if (! $order->isOpen()) {
            throw new InvalidArgumentException('This purchase order is already paid.');
        }

        $payBlockReason = $order->payablesPaymentBlockReason(
            HotelBarPurchaseList::query()
                ->where('hotel_id', $hotel->id)
                ->where('hotel_purchase_order_id', $order->id)
                ->first()
        );
        if ($payBlockReason !== null) {
            throw new InvalidArgumentException($payBlockReason);
        }

        if (! in_array($paymentType, HotelExpense::PAYMENT_TYPES, true)) {
            throw new InvalidArgumentException('Invalid payment type.');
        }

        return DB::transaction(function () use ($hotel, $order, $paymentType, $expenseDate) {
            $vendorName = $order->vendor?->name ?: 'Vendor';

            $expense = HotelExpense::query()->create([
                'hotel_id' => $hotel->id,
                'hotel_purchase_order_id' => $order->id,
                'entry_type' => HotelExpense::ENTRY_EXPENSE,
                'paid_type' => HotelExpense::PAID_OUT,
                'payment_type' => $paymentType,
                'amount' => $order->total,
                'category' => 'Purchases',
                'expense_date' => $expenseDate,
                'invoice_no' => $order->po_number,
                'vendor' => $vendorName,
                'comments' => 'PO payment · '.$order->po_number,
            ]);

            $order->update([
                'status' => HotelPurchaseOrder::STATUS_PAID,
                'paid_at' => now(),
                'hotel_expense_id' => $expense->id,
            ]);

            return $order->fresh(['vendor', 'paymentExpense']);
        });
    }

    /** Paid-in when market spend is below the amount already paid on the linked PO. */
    public function postBarListMarketBudgetReturn(
        Hotel $hotel,
        HotelBarPurchaseList $list,
        float $actualSpend,
    ): HotelBarPurchaseList {
        if ((int) $list->hotel_id !== (int) $hotel->id) {
            throw new InvalidArgumentException('Purchase list not found.');
        }

        if ($list->market_budget_return_expense_id) {
            return $list->fresh(['purchaseOrder.paymentExpense', 'marketBudgetReturnExpense']);
        }

        $list->loadMissing('purchaseOrder.paymentExpense');
        $order = $list->purchaseOrder;
        if (! $order || ! $order->isPaid()) {
            return $list;
        }

        $paidOut = round((float) ($order->paymentExpense?->amount ?? $order->total), 2);
        $actualSpend = round(max(0, $actualSpend), 2);
        $returnAmount = round($paidOut - $actualSpend, 2);
        if ($returnAmount < 0.01) {
            return $list;
        }

        $vendorName = $order->vendor?->name ?: 'Vendor';
        $paymentType = $order->paymentExpense?->payment_type ?? HotelExpense::PAYMENT_TYPES[0];

        return DB::transaction(function () use ($hotel, $list, $order, $returnAmount, $vendorName, $paymentType) {
            $expense = HotelExpense::query()->create([
                'hotel_id' => $hotel->id,
                'hotel_purchase_order_id' => $order->id,
                'entry_type' => HotelExpense::ENTRY_EXPENSE,
                'paid_type' => HotelExpense::PAID_IN,
                'payment_type' => $paymentType,
                'amount' => $returnAmount,
                'category' => 'Purchases',
                'expense_date' => now()->toDateString(),
                'invoice_no' => $order->po_number,
                'vendor' => $vendorName,
                'comments' => 'Budget return · '.$order->po_number.' · '.$list->reference_no,
            ]);

            $list->update([
                'market_budget_return_amount' => $returnAmount,
                'market_budget_return_expense_id' => $expense->id,
            ]);

            return $list->fresh(['purchaseOrder.paymentExpense', 'marketBudgetReturnExpense']);
        });
    }

    /** Paid-in when goods received total is below the Payables amount already paid (store ORD-* POs). */
    public function postStoreDeskPurchaseOrderBudgetReturn(
        Hotel $hotel,
        HotelPurchaseOrder $order,
    ): HotelPurchaseOrder {
        if ((int) $order->hotel_id !== (int) $hotel->id) {
            throw new InvalidArgumentException('Purchase order not found.');
        }

        if (! $order->isStoreDeskPurchaseOrder() || ! $order->isPaid() || ! $order->goods_received) {
            return $order;
        }

        if ($order->budget_return_expense_id) {
            return $order->fresh(['vendor', 'paymentExpense', 'budgetReturnExpense']);
        }

        $order->loadMissing('paymentExpense');
        $paidOut = round((float) ($order->paymentExpense?->amount ?? 0), 2);
        $actualSpend = round((float) $order->total, 2);
        $returnAmount = round($paidOut - $actualSpend, 2);
        if ($returnAmount < 0.01) {
            return $order;
        }

        $vendorName = $order->vendor?->name ?: 'Vendor';
        $paymentType = $order->paymentExpense?->payment_type ?? HotelExpense::PAYMENT_TYPES[0];

        return DB::transaction(function () use ($hotel, $order, $returnAmount, $vendorName, $paymentType) {
            $expense = HotelExpense::query()->create([
                'hotel_id' => $hotel->id,
                'hotel_purchase_order_id' => $order->id,
                'entry_type' => HotelExpense::ENTRY_EXPENSE,
                'paid_type' => HotelExpense::PAID_IN,
                'payment_type' => $paymentType,
                'amount' => $returnAmount,
                'category' => 'Purchases',
                'expense_date' => now()->toDateString(),
                'invoice_no' => $order->po_number,
                'vendor' => $vendorName,
                'comments' => 'Budget return · '.$order->po_number.' · after goods receive',
            ]);

            $order->update([
                'budget_return_amount' => $returnAmount,
                'budget_return_expense_id' => $expense->id,
            ]);

            return $order->fresh(['vendor', 'paymentExpense', 'budgetReturnExpense']);
        });
    }

    /** @param  array<string, mixed>  $filters
     * @return array{headers: list<string>, rows: list<list<string|float>>}
     */
    public function reconciliationExport(Hotel $hotel, array $filters): array
    {
        $data = $this->reconciliation($hotel, $filters);
        $headers = array_column($this->uiConfig()['reconciliation']['columns'] ?? [], 'label');

        $rows = $data['rows']->map(fn (array $row) => [
            $row['date'],
            $row['pos_name'],
            $row['invoice_no'],
            $row['party'],
            $row['comments'],
            $row['image_url'] ? 'Yes' : '—',
            $row['user'],
            number_format($row['paid_in'], 2, '.', ''),
            number_format($row['paid_out'], 2, '.', ''),
        ])->values()->all();

        $rows[] = [
            'Total', '', '', '', '', '', '',
            number_format($data['totals']['paid_in'], 2, '.', ''),
            number_format($data['totals']['paid_out'], 2, '.', ''),
        ];

        return ['headers' => $headers, 'rows' => $rows];
    }
}
