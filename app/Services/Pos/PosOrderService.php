<?php

namespace App\Services\Pos;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelCompany;
use App\Models\HotelDepartment;
use App\Models\HotelPosOrder;
use App\Models\HotelPosOrderLine;
use App\Models\HotelPmsService;
use App\Models\HotelPmsServiceItem;
use App\Models\User;
use App\Services\Bar\BarPurchaseListService;
use App\Services\Bar\BarSaleService;
use App\Services\FrontDeskPaymentService;
use App\Services\HotelSettingsService;
use App\Services\PmsServiceItemDataService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PosOrderService
{
    public function __construct(
        private PmsServiceItemDataService $items,
        private BarSaleService $barSales,
        private BarPurchaseListService $purchaseLists,
        private FrontDeskPaymentService $deskPayments,
    ) {}

    /** @return Collection<int, HotelDepartment> */
    public function posDepartmentsForHotel(Hotel $hotel): Collection
    {
        return HotelDepartment::query()
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->where('show_in_pos', true)
            ->whereNotNull('hotel_pms_service_id')
            ->with('posCatalogService')
            ->orderBy('name')
            ->get()
            ->filter(function (HotelDepartment $department) {
                $service = $department->posCatalogService;

                return $service && $service->is_active && $service->showsCatalog();
            })
            ->values();
    }

    public function resolvePosDepartment(Hotel $hotel, ?int $departmentId, ?int $legacyOutletId = null): ?HotelDepartment
    {
        $departments = $this->posDepartmentsForHotel($hotel);
        if ($departments->isEmpty()) {
            return null;
        }

        if ($departmentId) {
            $match = $departments->firstWhere('id', $departmentId);
            if ($match) {
                return $match;
            }
        }

        if ($legacyOutletId) {
            $match = $departments->firstWhere('hotel_pms_service_id', $legacyOutletId);
            if ($match) {
                return $match;
            }
        }

        return $departments->first();
    }

    public function serviceForPosDepartment(HotelDepartment $department): ?HotelPmsService
    {
        $service = $department->posCatalogService;
        if (! $service || ! $service->is_active || ! $service->showsCatalog()) {
            return null;
        }

        return $service;
    }

    public function resolveServiceForPos(Hotel $hotel, ?int $departmentId = null, ?int $legacyOutletId = null): ?HotelPmsService
    {
        $department = $this->resolvePosDepartment($hotel, $departmentId, $legacyOutletId);

        return $department ? $this->serviceForPosDepartment($department) : null;
    }

    /** @deprecated Use posDepartmentsForHotel / resolveServiceForPos */
    public function resolveOutlet(Hotel $hotel, ?int $serviceOrLegacyOutletId): ?HotelPmsService
    {
        return $this->resolveServiceForPos($hotel, null, $serviceOrLegacyOutletId);
    }

    public function posDepartmentForService(Hotel $hotel, int $serviceId): ?HotelDepartment
    {
        $match = $this->posDepartmentsForHotel($hotel)->firstWhere('hotel_pms_service_id', $serviceId);
        if ($match) {
            return $match;
        }

        return HotelDepartment::query()
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->where('show_in_pos', true)
            ->where('hotel_pms_service_id', $serviceId)
            ->first();
    }

    /** @return Collection<int, HotelPmsService> */
    public function catalogServicesForPosLink(Hotel $hotel): Collection
    {
        return $hotel->pmsServices()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (HotelPmsService $service) => $service->showsCatalog())
            ->values();
    }

    public function outletCode(HotelPmsService $service): string
    {
        $key = (string) ($service->template_key ?? '');
        $codes = config('hotel_pos.outlet_codes', []);

        return $codes[$key] ?? strtoupper(substr(preg_replace('/[^a-z]/i', '', $service->name) ?: 'POS', 0, 4));
    }

    public function fiscalYearLabel(): string
    {
        $year = (int) now()->format('y');
        $month = (int) now()->format('n');
        if ($month >= 4) {
            return sprintf('%02d-%02d', $year, $year + 1);
        }

        return sprintf('%02d-%02d', $year - 1, $year);
    }

    public function taxPercentForService(HotelPmsService $service): float
    {
        $settings = is_array($service->settings) ? $service->settings : [];
        $fromService = $settings['pos_tax_percent'] ?? $settings['tax_percent'] ?? null;
        if ($fromService !== null && $fromService !== '') {
            return max(0, (float) $fromService);
        }

        if (! $service->hasTax()) {
            return 0.0;
        }

        $category = trim((string) ($service->tax_category ?? ''));
        if (preg_match('/(\d+(?:\.\d+)?)\s*%/', $category, $matches)) {
            return max(0, min(100, (float) $matches[1]));
        }
        if (preg_match('/vat\s*(\d+(?:\.\d+)?)/i', $category, $matches)) {
            return max(0, min(100, (float) $matches[1]));
        }

        $service->loadMissing('hotel');
        if ($service->hotel && stripos($category, 'vat') !== false) {
            return app(HotelSettingsService::class)->vatRate($service->hotel);
        }

        return max(0, (float) config('hotel_pos.default_tax_percent', 0));
    }

    public function pricesTaxInclusiveForService(HotelPmsService $service): bool
    {
        return $service->hasTax() && $service->tax_inclusive;
    }

    public function currencyForHotel(Hotel $hotel, HotelPmsService $service): string
    {
        $settings = is_array($service->settings) ? $service->settings : [];
        $fromPos = strtoupper(trim((string) ($settings['pos_currency'] ?? '')));
        if ($fromPos !== '') {
            return $fromPos;
        }

        $workflowKey = $this->procurementWorkflowForService($service);
        if ($workflowKey !== null) {
            $plSettings = $this->purchaseLists->purchaseListSettings($hotel, $workflowKey);
            $fromBudget = strtoupper(trim((string) ($plSettings['budget_currency'] ?? '')));
            if ($fromBudget !== '') {
                return $fromBudget;
            }
        }

        $localFromRates = $hotel->ratePlans()
            ->whereNotNull('local_currency')
            ->where('local_currency', '!=', '')
            ->distinct()
            ->pluck('local_currency')
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter()
            ->first();
        if (is_string($localFromRates) && $localFromRates !== '') {
            return $localFromRates;
        }

        $hotelCurrency = strtoupper(trim((string) ($hotel->currency ?? '')));
        if ($hotelCurrency !== '') {
            return $hotelCurrency;
        }

        $fromBarConfig = strtoupper(trim((string) config('hotel_bar.fallback_budget_currency', '')));
        if ($fromBarConfig !== '') {
            return $fromBarConfig;
        }

        return strtoupper(trim((string) config('hotel_pos.fallback_currency', 'TZS'))) ?: 'TZS';
    }

    /** @return array{code: string, prefix: ?string, decimals: int} */
    public function currencyDisplayMeta(string $currency): array
    {
        $code = strtoupper(trim($currency));
        $symbols = config('hotel_pos.currency_symbols', []);
        $prefix = is_array($symbols) ? ($symbols[$code] ?? null) : null;

        return [
            'code' => $code,
            'prefix' => is_string($prefix) && $prefix !== '' ? $prefix : null,
            'decimals' => $this->currencyDecimalPlaces($currency),
        ];
    }

    public function currencyDecimalPlaces(string $currency): int
    {
        $code = strtoupper(trim($currency));
        $map = config('hotel_pos.currency_decimals', []);
        if (is_array($map) && array_key_exists($code, $map)) {
            return max(0, (int) $map[$code]);
        }

        return max(0, (int) ($map['default'] ?? 2));
    }

    public function roundForCurrency(float $amount, string $currency): float
    {
        return round($amount, $this->currencyDecimalPlaces($currency));
    }

    public function isPaidInFull(HotelPosOrder $order, ?string $currency = null): bool
    {
        $balance = (float) $order->balance;
        if ($balance <= 0.009) {
            return true;
        }

        $code = strtoupper(trim($currency ?? (string) ($order->currency ?: '')));
        if ($code === '') {
            return false;
        }

        return $this->roundForCurrency($balance, $code) <= 0;
    }

    public function procurementWorkflowForService(HotelPmsService $service): ?string
    {
        $templateKey = (string) ($service->template_key ?? '');
        if ($templateKey === '') {
            return null;
        }

        foreach (config('hotel_department_procurement.workflows', []) as $workflowKey => $def) {
            if (($def['template_key'] ?? '') === $templateKey) {
                return (string) $workflowKey;
            }
        }

        return null;
    }

    public function nextOrderNo(Hotel $hotel, HotelPmsService $service): string
    {
        $fy = $this->fiscalYearLabel();
        $code = $this->outletCode($service);
        $prefix = $fy.'-'.$code.'-OR-';
        $last = HotelPosOrder::query()
            ->where('hotel_id', $hotel->id)
            ->where('order_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('order_no');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function nextInvoiceNo(Hotel $hotel, HotelPmsService $service): string
    {
        $fy = $this->fiscalYearLabel();
        $code = $this->outletCode($service);
        $prefix = $fy.'-'.$code.'-';
        $last = HotelPosOrder::query()
            ->where('hotel_id', $hotel->id)
            ->where('hotel_pms_service_id', $service->id)
            ->whereNotNull('invoice_no')
            ->where('invoice_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('invoice_no');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /** @return Builder<HotelPosOrder> */
    public function ordersQuery(
        Hotel $hotel,
        HotelPmsService $service,
        string $status,
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null,
    ): Builder {
        $query = HotelPosOrder::query()
            ->where('hotel_id', $hotel->id)
            ->where('hotel_pms_service_id', $service->id)
            ->where('status', $status)
            ->with(['lines', 'creator', 'reservation.roomUnit']);

        if ($from) {
            $query->where('ordered_at', '>=', $from->format('Y-m-d 00:00:00'));
        }
        if ($to) {
            $query->where('ordered_at', '<=', $to->format('Y-m-d 23:59:59'));
        }

        return $query->orderByDesc('ordered_at')->orderByDesc('id');
    }

    /**
     * @return array{
     *     sales: float,
     *     mtd_sales: float,
     *     order_count: int,
     *     average: float,
     *     currency: string,
     *     period_label: string,
     *     mtd_period_label: string
     * }
     */
    public function dashboardStats(
        Hotel $hotel,
        HotelPmsService $service,
        ?\DateTimeInterface $from,
        ?\DateTimeInterface $to,
    ): array {
        $currency = $this->currencyForHotel($hotel, $service);
        $billed = HotelPosOrder::query()
            ->where('hotel_id', $hotel->id)
            ->where('hotel_pms_service_id', $service->id)
            ->where('status', HotelPosOrder::STATUS_BILLED);

        if ($from) {
            $billed->where('billed_at', '>=', $from->format('Y-m-d 00:00:00'));
        }
        if ($to) {
            $billed->where('billed_at', '<=', $to->format('Y-m-d 23:59:59'));
        }

        $sales = (float) (clone $billed)->sum('total');
        $count = (int) (clone $billed)->count();

        $mtdStart = now()->startOfMonth();
        $mtdEnd = now();
        $mtdSales = (float) HotelPosOrder::query()
            ->where('hotel_id', $hotel->id)
            ->where('hotel_pms_service_id', $service->id)
            ->where('status', HotelPosOrder::STATUS_BILLED)
            ->where('billed_at', '>=', $mtdStart)
            ->sum('total');

        $periodFrom = $from ? \Illuminate\Support\Carbon::parse($from) : now()->startOfDay();
        $periodTo = $to ? \Illuminate\Support\Carbon::parse($to) : now()->endOfDay();

        return [
            'sales' => round($sales, 2),
            'mtd_sales' => round($mtdSales, 2),
            'order_count' => $count,
            'average' => $count > 0 ? round($sales / $count, 2) : 0.0,
            'currency' => $currency,
            'period_label' => $this->formatDateRangeLabel($periodFrom, $periodTo),
            'mtd_period_label' => $this->formatDateRangeLabel($mtdStart, $mtdEnd),
        ];
    }

    /** @return list<array{label: string, value: string, subtitle: string, alert: string}> */
    public function dashboardKpiCards(array $stats): array
    {
        $definitions = config('hotel_pos.dashboard_kpis', []);
        $cards = [];

        foreach ($definitions as $def) {
            $key = $def['key'] ?? '';
            if ($key === '') {
                continue;
            }

            $raw = $stats[$key] ?? 0;
            $format = $def['format'] ?? 'money';
            $currency = (string) ($stats['currency'] ?? '');

            $value = match ($format) {
                'count' => (string) (int) $raw,
                default => $this->formatDisplayAmount((float) $raw, $currency),
            };

            $subtitleKey = $def['subtitle'] ?? null;
            $subtitle = match ($subtitleKey) {
                'period' => (string) ($stats['period_label'] ?? ''),
                'mtd' => (string) ($stats['mtd_period_label'] ?? ''),
                default => '',
            };

            $cards[] = [
                'label' => (string) ($def['label'] ?? $key),
                'value' => $value,
                'subtitle' => $subtitle,
                'alert' => (string) ($def['alert'] ?? 'info'),
            ];
        }

        return $cards;
    }

    public function formatDisplayAmount(float $amount, string $currency): string
    {
        $code = strtoupper(trim($currency));
        $decimals = $this->currencyDecimalPlaces($currency);
        $formatted = number_format($amount, $decimals, '.', ',');
        if ($code === '') {
            return $formatted;
        }

        $symbols = config('hotel_pos.currency_symbols', []);
        $prefix = is_array($symbols) ? ($symbols[$code] ?? null) : null;
        if (is_string($prefix) && $prefix !== '') {
            return $prefix.$formatted;
        }

        return $formatted.' '.$code;
    }

    private function formatDateRangeLabel(\DateTimeInterface $from, \DateTimeInterface $to): string
    {
        $start = \Illuminate\Support\Carbon::parse($from);
        $end = \Illuminate\Support\Carbon::parse($to);

        if ($start->isSameDay($end)) {
            return $start->format('M j, Y');
        }

        if ($start->year === $end->year) {
            return $start->format('M j').' – '.$end->format('M j, Y');
        }

        return $start->format('M j, Y').' – '.$end->format('M j, Y');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lines
     */
    public function createOrder(
        Hotel $hotel,
        HotelPmsService $service,
        array $data,
        array $lines,
        User $user,
    ): HotelPosOrder {
        if ($lines === []) {
            throw new InvalidArgumentException('Add at least one service line to the order.');
        }

        $complimentary = ! empty($data['is_complimentary']);
        $taxPercent = $this->taxPercentForService($service);
        $currency = $this->currencyForHotel($hotel, $service);
        $priced = $this->buildLines($service, $lines, $taxPercent, $complimentary);

        $subtotal = round(collect($priced)->sum('line_amount'), 2);
        $taxTotal = round(collect($priced)->sum('tax_amount'), 2);
        $total = round(collect($priced)->sum('line_total'), 2);

        $guest = $this->guestContextForOrder($hotel, $data);

        $waiterUserId = $this->resolveWaiterUserId($hotel, $data);

        return DB::transaction(function () use ($hotel, $service, $data, $user, $priced, $subtotal, $taxTotal, $total, $currency, $complimentary, $guest, $waiterUserId) {
            $order = HotelPosOrder::query()->create([
                'hotel_id' => $hotel->id,
                'hotel_pms_service_id' => $service->id,
                'status' => HotelPosOrder::STATUS_OPEN,
                'is_complimentary' => $complimentary,
                'order_no' => $this->nextOrderNo($hotel, $service),
                'details' => $guest['details'],
                'cm_reservation_id' => $guest['cm_reservation_id'],
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'total' => $total,
                'currency' => $currency,
                'amount_paid' => 0,
                'balance' => $total,
                'ordered_at' => now(),
                'created_by_user_id' => $user->id,
                'waiter_user_id' => $waiterUserId,
            ]);

            foreach ($priced as $index => $line) {
                unset($line['catalog_item']);
                $line['sort_order'] = $index;
                $order->lines()->create($line);
            }

            return $order->fresh(['lines', 'service', 'creator']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lines
     */
    public function updateOpenOrder(
        Hotel $hotel,
        HotelPosOrder $order,
        HotelPmsService $service,
        array $data,
        array $lines,
    ): HotelPosOrder {
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);
        abort_unless((int) $order->hotel_pms_service_id === (int) $service->id, 404);
        if (! $order->isOpen()) {
            throw new InvalidArgumentException('Only current orders can be edited.');
        }
        if ($lines === []) {
            throw new InvalidArgumentException('Add at least one service line to the order.');
        }

        $complimentary = ! empty($data['is_complimentary']);
        $taxPercent = $this->taxPercentForService($service);
        $priced = $this->buildLines($service, $lines, $taxPercent, $complimentary);

        $subtotal = round(collect($priced)->sum('line_amount'), 2);
        $taxTotal = round(collect($priced)->sum('tax_amount'), 2);
        $total = round(collect($priced)->sum('line_total'), 2);
        $paid = round((float) $order->amount_paid, 2);

        $guest = $this->guestContextForOrder($hotel, $data);
        $waiterUserId = $this->resolveWaiterUserId($hotel, $data);

        return DB::transaction(function () use ($order, $data, $priced, $subtotal, $taxTotal, $total, $paid, $complimentary, $guest, $waiterUserId) {
            $order->lines()->delete();

            foreach ($priced as $index => $line) {
                unset($line['catalog_item']);
                $line['sort_order'] = $index;
                $order->lines()->create($line);
            }

            $order->update([
                'details' => $guest['details'],
                'cm_reservation_id' => $guest['cm_reservation_id'],
                'is_complimentary' => $complimentary,
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'total' => $total,
                'balance' => max(0, round($total - $paid, 2)),
                'waiter_user_id' => $waiterUserId,
            ]);

            return $order->fresh(['lines', 'service', 'creator']);
        });
    }

    /** @return array<string, mixed> */
    public function orderViewPayload(Hotel $hotel, HotelPosOrder $order): array
    {
        $order->loadMissing(['lines', 'service', 'company', 'waiter']);

        $addressParts = array_filter([
            trim((string) ($hotel->address ?? '')),
            trim((string) ($hotel->city ?? '')),
            trim((string) ($hotel->pin_code ?? '')),
        ]);
        $taxId = trim((string) ($hotel->tin ?? ''));
        $outletName = trim((string) ($order->service?->name ?? 'POS'));
        $hotelLabel = trim((string) ($hotel->display_name ?: $hotel->name));
        $currency = trim((string) ($order->currency ?? ''));
        if ($currency === '') {
            $currency = $this->currencyForHotel($hotel, $order->service);
        }

        $sortedLines = $order->lines->sortBy('sort_order')->values();
        $lineRows = $sortedLines->map(fn ($line) => [
            'service' => (string) $line->item_name,
            'rate' => $this->formatDisplayAmount((float) $line->unit_price, $currency),
            'qty' => (float) $line->quantity,
            'amount' => $this->formatDisplayAmount((float) $line->line_amount, $currency),
            'tax' => $this->formatDisplayAmount((float) $line->tax_amount, $currency),
            'total' => $this->formatDisplayAmount((float) $line->line_total, $currency),
        ])->all();

        $paymentComments = trim((string) ($order->payment_notes ?? ''));
        if ($paymentComments === '' && $order->company) {
            $paymentComments = (string) $order->company->name;
        }
        if ($paymentComments === '' && $order->details) {
            $paymentComments = (string) $order->details;
        }

        $paidAmount = $this->roundForCurrency((float) $order->amount_paid, $currency);
        $decimals = $this->currencyDecimalPlaces($currency);
        $paymentAmountPlain = number_format($paidAmount, $decimals, '.', '');

        $invoicePrefix = (string) config('hotel_pos.ui.invoice_title_prefix', 'Invoice');
        $invoiceNo = $order->invoice_no ? (string) $order->invoice_no : null;

        return [
            'id' => (int) $order->id,
            'is_billed' => $order->isBilled(),
            'heading' => $hotelLabel !== '' ? $outletName.' ('.$hotelLabel.')' : $outletName,
            'address' => implode(' ', $addressParts),
            'tax_id' => $taxId !== '' ? $taxId : null,
            'tax_id_label' => (string) config('hotel_pos.ui.tax_id_label', 'GST'),
            'order_no' => (string) $order->order_no,
            'guest_summary' => $this->orderGuestSummary($order),
            'waiter_summary' => $this->orderWaiterSummary($order),
            'can_edit' => $order->isOpen(),
            'invoice_no' => $invoiceNo,
            'invoice_title' => $invoiceNo !== null ? $invoicePrefix.' - '.$invoiceNo : null,
            'details' => $order->details ? (string) $order->details : null,
            'date_label' => ($order->billed_at ?? $order->ordered_at)?->format('d M g:i A'),
            'lines' => $lineRows,
            'line_totals' => [
                'qty' => (float) $sortedLines->sum(fn ($line) => (float) $line->quantity),
                'amount' => $this->formatDisplayAmount((float) $order->subtotal, $currency),
                'tax' => $this->formatDisplayAmount((float) $order->tax_total, $currency),
                'total' => $this->formatDisplayAmount((float) $order->total, $currency),
            ],
            'payment' => [
                'type' => trim((string) ($order->payment_mode ?? '')) ?: '—',
                'comments' => $paymentComments !== '' ? $paymentComments : '—',
                'amount' => $this->formatDisplayAmount($paidAmount, $currency),
                'amount_plain' => $paymentAmountPlain,
            ],
            'add_service_url' => $order->service
                ? route('hotel.services.show', $order->service)
                : null,
            'print_url' => route('hotel.pos.orders.show', $order),
        ];
    }

    /** @return array<string, mixed> */
    public function orderEditPayload(HotelPosOrder $order): array
    {
        $order->loadMissing('lines');

        return [
            'id' => (int) $order->id,
            'order_no' => (string) $order->order_no,
            'details' => (string) ($order->details ?? ''),
            'guest_type' => $order->cm_reservation_id ? 'in_house' : 'walk_in',
            'cm_reservation_id' => $order->cm_reservation_id ? (int) $order->cm_reservation_id : null,
            'is_complimentary' => (bool) $order->is_complimentary,
            'waiter_user_id' => $order->waiter_user_id ? (int) $order->waiter_user_id : null,
            'lines' => $order->lines->sortBy('sort_order')->values()->map(fn ($line) => [
                'item_id' => $line->hotel_pms_service_item_id ? (int) $line->hotel_pms_service_item_id : null,
                'item_name' => (string) $line->item_name,
                'category_id' => $line->hotel_pms_service_category_id ? (int) $line->hotel_pms_service_category_id : null,
                'category_name' => (string) ($line->category_name ?? ''),
                'package_id' => $line->hotel_pms_service_package_id ? (int) $line->hotel_pms_service_package_id : null,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'tax_rate' => (float) $line->tax_rate,
            ])->all(),
        ];
    }

    public function billOrder(Hotel $hotel, HotelPosOrder $order, ?User $user = null): HotelPosOrder
    {
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);
        if (! $order->isOpen()) {
            throw new InvalidArgumentException('Only current orders can be billed.');
        }

        if (! $this->isPaidInFull($order)) {
            throw new InvalidArgumentException('Collect full payment on this order before billing.');
        }

        return DB::transaction(function () use ($order, $hotel) {
            $order->load(['lines.item', 'service']);
            if (! $order->stock_deducted && $order->service?->template_key === 'bar') {
                foreach ($order->lines as $line) {
                    $item = $line->item;
                    if (! $item) {
                        continue;
                    }
                    $this->barSales->deductStockForPosLine(
                        $item,
                        (float) $line->quantity,
                        (float) $line->pieces_per_sell_unit,
                        $line->sell_unit_label,
                        $line->hotel_pms_service_package_id ? (int) $line->hotel_pms_service_package_id : null,
                    );
                }
                $order->stock_deducted = true;
            }

            $order->update([
                'status' => HotelPosOrder::STATUS_BILLED,
                'invoice_no' => $this->nextInvoiceNo($hotel, $order->service),
                'billed_at' => now(),
                'balance' => max(0, round((float) $order->total - (float) $order->amount_paid, 2)),
            ]);

            return $order->fresh(['lines', 'service', 'creator', 'reservation.roomUnit']);
        });
    }

    /** @param  array<string, mixed>  $data */
    public function applyPayment(Hotel $hotel, HotelPosOrder $order, array $data, User $user): HotelPosOrder
    {
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);
        if (! $order->isOpen() && ! $order->isBilled()) {
            throw new InvalidArgumentException('This order cannot accept payment.');
        }

        $type = trim((string) ($data['payment_type'] ?? 'cash'));
        $order->loadMissing('service');
        $currency = trim((string) ($order->currency ?? ''));
        if ($currency === '') {
            $currency = $this->currencyForHotel($hotel, $order->service);
        }
        $balance = round((float) $order->balance, 2);
        $requested = round(max(0, (float) ($data['amount'] ?? $order->balance)), 2);
        if ($requested <= 0 && $balance > 0) {
            throw new InvalidArgumentException('Enter a payment amount.');
        }
        $displayBalance = $this->roundForCurrency($balance, $currency);
        if ($displayBalance > 0 && $requested >= $displayBalance) {
            $amount = $balance;
        } else {
            $amount = min($requested, $balance);
        }

        $reservation = null;
        if ($type === 'room_bill') {
            $resId = (int) ($data['cm_reservation_id'] ?? 0);
            if ($resId <= 0) {
                throw new InvalidArgumentException('Select a room for room bill posting.');
            }
            $reservation = CmReservation::query()
                ->where('hotel_id', $hotel->id)
                ->whereKey($resId)
                ->firstOrFail();
        }

        $company = null;
        if ($type === 'bill_to_company') {
            $companyId = (int) ($data['hotel_company_id'] ?? 0);
            if ($companyId <= 0) {
                throw new InvalidArgumentException('Select a company to bill.');
            }
            $company = HotelCompany::query()
                ->where('hotel_id', $hotel->id)
                ->whereKey($companyId)
                ->first();
            if ($company === null) {
                throw new InvalidArgumentException('Selected company is not valid.');
            }
        }

        $modeLabel = config('hotel_pos.payment_types.'.$type, ucfirst(str_replace('_', ' ', $type)));
        $notes = trim((string) ($data['comments'] ?? ''));
        if ($company !== null) {
            $companyNote = 'Company: '.$company->name;
            $notes = $notes !== '' ? $notes.' · '.$companyNote : $companyNote;
        }

        return DB::transaction(function () use ($order, $amount, $modeLabel, $notes, $reservation, $company, $type, $data, $currency) {
            $newPaid = round((float) $order->amount_paid + $amount, 2);
            $newBalance = max(0, round((float) $order->total - $newPaid, 2));
            if ($this->roundForCurrency($newBalance, $currency) <= 0) {
                $newPaid = round((float) $order->total, 2);
                $newBalance = 0.0;
            }

            $updates = [
                'amount_paid' => $newPaid,
                'balance' => $newBalance,
                'payment_mode' => $modeLabel,
                'payment_notes' => $notes !== '' ? $notes : $order->payment_notes,
            ];

            if ($type === 'room_bill' && $reservation) {
                $updates['cm_reservation_id'] = $reservation->id;
                $this->postOrderToFolio($order, $reservation);
            }

            if ($type === 'bill_to_company' && $company) {
                $updates['hotel_company_id'] = $company->id;
            }

            if ($type === 'credit_card' && ! empty($data['payment_provider'])) {
                $updates['payment_provider'] = trim((string) $data['payment_provider']);
            }

            $order->update($updates);

            return $order->fresh(['lines', 'service', 'reservation.roomUnit', 'company', 'creator']);
        });
    }

    /** @return Collection<int, HotelCompany> */
    public function companiesForHotel(Hotel $hotel): Collection
    {
        return HotelCompany::query()
            ->where('hotel_id', $hotel->id)
            ->orderBy('name')
            ->get(['id', 'name', 'contact_person']);
    }

    public function cancelOpenOrder(Hotel $hotel, HotelPosOrder $order): void
    {
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);
        if (! $order->isOpen()) {
            throw new InvalidArgumentException('Only current orders can be deleted.');
        }

        $order->update(['status' => HotelPosOrder::STATUS_CANCELLED]);
    }

    /** @return Collection<int, CmReservation> */
    public function inHouseReservations(Hotel $hotel): Collection
    {
        return $this->barSales->inHouseReservations($hotel);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{cm_reservation_id: ?int, details: ?string}
     */
    public function guestContextForOrder(Hotel $hotel, array $data): array
    {
        $guestType = (string) ($data['guest_type'] ?? 'walk_in');
        $details = trim((string) ($data['details'] ?? ''));

        if ($guestType === 'in_house') {
            $resId = (int) ($data['cm_reservation_id'] ?? 0);
            if ($resId <= 0) {
                throw new InvalidArgumentException('Select an in-house guest.');
            }

            $reservation = $this->inHouseReservations($hotel)->firstWhere('id', $resId);
            if (! $reservation) {
                throw new InvalidArgumentException('That guest is not checked in.');
            }

            $room = trim((string) ($reservation->roomUnit?->room_number ?? $reservation->room_no ?? ''));
            $guestName = trim((string) $reservation->guestName());
            $guestLabel = $room !== '' ? 'Room '.$room.' — '.$guestName : $guestName;
            if ($details === '') {
                $details = $guestLabel;
            }

            return [
                'cm_reservation_id' => (int) $reservation->id,
                'details' => $details !== '' ? $details : null,
            ];
        }

        $walkLabel = trim((string) ($data['walk_in_label'] ?? ''));
        if ($walkLabel !== '' && $details === '') {
            $details = 'Walk-in: '.$walkLabel;
        }

        return [
            'cm_reservation_id' => null,
            'details' => $details !== '' ? $details : null,
        ];
    }

    public function orderItemsLabel(HotelPosOrder $order, int $limit = 3): string
    {
        $order->loadMissing('lines');
        $names = $order->lines
            ->sortBy('sort_order')
            ->pluck('item_name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values();

        if ($names->isEmpty()) {
            return '—';
        }

        if ($names->count() <= $limit) {
            return $names->join(', ');
        }

        $extra = $names->count() - $limit;

        return $names->take($limit)->join(', ').' +'.$extra.' more';
    }

    public function orderGuestSummary(HotelPosOrder $order): ?string
    {
        $order->loadMissing(['reservation.roomUnit']);
        if ($order->cm_reservation_id && $order->reservation) {
            $room = trim((string) ($order->reservation->roomUnit?->room_number ?? $order->reservation->room_no ?? ''));
            $guest = trim((string) $order->reservation->guestName());

            return $room !== '' ? 'In-house · Room '.$room.' — '.$guest : 'In-house · '.$guest;
        }

        if ($order->details) {
            return (string) $order->details;
        }

        return 'Walk-in';
    }

    /** @return Collection<int, User> */
    public function waitersForHotel(Hotel $hotel): Collection
    {
        return User::query()
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->whereIn('role', [User::ROLE_HOTEL_STAFF, User::ROLE_HOTEL_ADMIN])
            ->with('department')
            ->orderBy('name')
            ->get(['id', 'name', 'hotel_department_id', 'role']);
    }

    /** @param  Collection<int, User>  $waiters */
    public function defaultWaiterUserIdForPos(Hotel $hotel, User $actor, Collection $waiters): ?int
    {
        if ($waiters->isEmpty()) {
            return null;
        }

        $actorId = (int) $actor->id;
        if ($waiters->contains(fn (User $user) => (int) $user->id === $actorId)) {
            return $actorId;
        }

        return (int) $waiters->first()->id;
    }

    public function waiterOptionLabel(User $user): string
    {
        $name = trim((string) $user->name);
        if ($name === '') {
            $name = 'User #'.$user->id;
        }
        $dept = trim((string) ($user->department?->name ?? ''));
        if ($dept !== '') {
            return $name.' · '.$dept;
        }

        return $name;
    }

    public function orderWaiterSummary(HotelPosOrder $order): ?string
    {
        $order->loadMissing('waiter');
        $waiter = $order->waiter;
        if (! $waiter) {
            return null;
        }

        return $this->waiterOptionLabel($waiter);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function resolveWaiterUserId(Hotel $hotel, array $data): ?int
    {
        $require = (bool) config('hotel_pos.require_waiter_on_order', false);
        $raw = $data['waiter_user_id'] ?? null;
        if ($raw === null || $raw === '') {
            if ($require) {
                throw new InvalidArgumentException('Select the waiter who took this order.');
            }

            return null;
        }

        $waiterId = (int) $raw;
        if ($waiterId <= 0) {
            if ($require) {
                throw new InvalidArgumentException('Select the waiter who took this order.');
            }

            return null;
        }

        $valid = $this->waitersForHotel($hotel)->contains(fn (User $user) => (int) $user->id === $waiterId);
        if (! $valid) {
            throw new InvalidArgumentException('Selected waiter is not active on this hotel team.');
        }

        return $waiterId;
    }

    /** @return array{categories: list<array{id: int, name: string}>, items: list<array<string, mixed>>} */
    public function catalogPayload(HotelPmsService $service, ?HotelDepartment $department = null): array
    {
        $service->load(['serviceCategories', 'servicePackages']);
        // POS sells from the PMS register (categories + items on the linked service).
        // Store/indent department scoping applies to procurement, not the sell catalog.
        $items = $this->items->itemsForService($service)->where('is_active', true)->values();
        if ($service->isBar()) {
            $items = $items->filter(
                fn (HotelPmsServiceItem $item) => $item->stock_on_hand !== null && (float) $item->stock_on_hand > 0
            )->values();
        }

        $itemRows = [];
        foreach ($items as $item) {
            $item->loadMissing(['category']);

            if ($item->category && ! $item->category->is_active) {
                continue;
            }

            $stockOnHand = $item->stock_on_hand !== null ? (float) $item->stock_on_hand : null;

            $sellBlocks = $this->barSales->sellCatalogFromItems(collect([$item]));
            if ($sellBlocks === []) {
                continue;
            }

            $block = $sellBlocks[0];
            $methods = $block['methods'] ?? [];
            $multi = count($methods) > 1;

            foreach ($methods as $method) {
                $price = (float) ($method['unit_price'] ?? 0);
                if ($price <= 0) {
                    continue;
                }

                $label = (string) ($method['label'] ?? 'Unit');
                $displayName = $multi
                    ? $block['name'].' ('.$label.')'
                    : (string) $block['name'];

                $packageId = (int) ($method['package_id'] ?? 0);

                $itemRows[] = [
                    'id' => (int) $block['id'],
                    'catalog_key' => (int) $block['id'].'-'.$packageId,
                    'name' => $displayName,
                    'category_id' => $item->hotel_pms_service_category_id ? (int) $item->hotel_pms_service_category_id : null,
                    'category_name' => $item->category?->name,
                    'unit_price' => $price,
                    'package_id' => $packageId,
                    'unit_label' => $label,
                    'stock_on_hand' => $stockOnHand,
                ];
            }
        }

        $registerCategoryIds = $items
            ->pluck('hotel_pms_service_category_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $pricedCategoryIds = collect($itemRows)
            ->pluck('category_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $categoryIdsForPos = array_values(array_unique(array_merge($registerCategoryIds, $pricedCategoryIds)));

        $categories = $service->serviceCategories
            ->where('is_active', true)
            ->whereIn('id', $categoryIdsForPos)
            ->sortBy('sort_order')
            ->map(fn ($c) => ['id' => (int) $c->id, 'name' => (string) $c->name])
            ->values()
            ->all();

        return ['categories' => $categories, 'items' => $itemRows];
    }

    /** @return list<array{item_name: string, package_label: ?string, qty: float, qty_display: string, amount: float, tax: float, total: float}> */
    public function itemConsumptionReport(
        Hotel $hotel,
        HotelPmsService $service,
        ?\DateTimeInterface $from,
        ?\DateTimeInterface $to,
    ): array {
        return $this->aggregateItemConsumption(
            $this->billedPosOrdersQuery($hotel, [
                'from_date' => $from?->format('Y-m-d'),
                'to_date' => $to?->format('Y-m-d'),
                'guest_search' => '',
            ])->where('hotel_pms_service_id', $service->id)->with(['lines.package'])->get(),
            includeOutlet: false,
        );
    }

    /** @param  array<string, mixed>  $filters
     * @return array{rows: list<array<string, string>>, summary: array<string, mixed>|null}
     */
    public function posReportBundle(Hotel $hotel, array $filters): array
    {
        $orders = $this->billedPosOrdersQuery($hotel, $filters)
            ->with(['service', 'company'])
            ->orderBy('billed_at')
            ->orderBy('id')
            ->get();

        $rows = [];
        /** @var array<string, array{orders: int, subtotal: float, tax: float, total: float, collected: float}> $totalsByCurrency */
        $totalsByCurrency = [];

        foreach ($orders as $order) {
            $currency = trim((string) ($order->currency ?? ''));
            if ($currency === '') {
                $currency = $this->currencyForHotel($hotel, $order->service);
            }

            if (! isset($totalsByCurrency[$currency])) {
                $totalsByCurrency[$currency] = [
                    'orders' => 0,
                    'subtotal' => 0.0,
                    'tax' => 0.0,
                    'total' => 0.0,
                    'collected' => 0.0,
                ];
            }

            $totalsByCurrency[$currency]['orders']++;
            $totalsByCurrency[$currency]['subtotal'] += (float) $order->subtotal;
            $totalsByCurrency[$currency]['tax'] += (float) $order->tax_total;
            $totalsByCurrency[$currency]['total'] += (float) $order->total;
            $totalsByCurrency[$currency]['collected'] += (float) $order->amount_paid;

            $rows[] = [
                'Date' => $order->billed_at?->format('d/m/Y H:i') ?? $order->ordered_at?->format('d/m/Y H:i') ?? '—',
                'POS Outlet' => trim((string) ($order->service?->name ?? '')) ?: '—',
                'Invoice No' => $order->invoice_no ?: '—',
                'Order No' => (string) $order->order_no,
                'Details' => $order->details ?: '—',
                'Subtotal' => $this->formatDisplayAmount((float) $order->subtotal, $currency),
                'Tax' => $this->formatDisplayAmount((float) $order->tax_total, $currency),
                'Total' => $this->formatDisplayAmount((float) $order->total, $currency),
                'Payment' => $this->posReportPaymentCell($order),
                'Status' => $this->isPaidInFull($order, $currency)
                    ? (string) config('hotel_pos.ui.status_paid', 'Paid')
                    : (string) config('hotel_pos.ui.status_unpaid', 'Unpaid'),
            ];
        }

        return [
            'rows' => $rows,
            'summary' => $this->posRevenueSummary($totalsByCurrency),
        ];
    }

    /** @param  array<string, mixed>  $filters
     * @return array{rows: list<array<string, string>>, summary: null}
     */
    public function itemConsumptionReportBundle(Hotel $hotel, array $filters): array
    {
        $orders = $this->billedPosOrdersQuery($hotel, $filters)
            ->with(['lines.package', 'service'])
            ->orderBy('billed_at')
            ->get();

        $aggregated = $this->aggregateItemConsumption($orders, includeOutlet: true);

        $rows = [];
        foreach ($aggregated as $row) {
            $currency = trim((string) ($row['currency'] ?? ''));
            if ($currency === '') {
                $currency = strtoupper(trim((string) config('hotel_pos.fallback_currency', 'TZS')));
            }

            $cells = [
                'Service Name' => (string) $row['item_name'],
                'Qty' => (string) $row['qty_display'],
                'Amount' => $this->formatDisplayAmount((float) $row['amount'], $currency),
                'Tax' => $this->formatDisplayAmount((float) $row['tax'], $currency),
                'Total' => $this->formatDisplayAmount((float) $row['total'], $currency),
            ];

            if ($row['outlet'] ?? null) {
                $cells = ['POS Outlet' => (string) $row['outlet']] + $cells;
            }

            $rows[] = $cells;
        }

        return ['rows' => $rows, 'summary' => null];
    }

    /** @param  array<string, mixed>  $filters */
    private function billedPosOrdersQuery(Hotel $hotel, array $filters): Builder
    {
        $query = HotelPosOrder::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', HotelPosOrder::STATUS_BILLED);

        if (! empty($filters['from_date'])) {
            $query->whereDate('billed_at', '>=', Carbon::parse($filters['from_date'])->format('Y-m-d'));
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('billed_at', '<=', Carbon::parse($filters['to_date'])->format('Y-m-d'));
        }

        $search = trim((string) ($filters['guest_search'] ?? ''));
        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(function (Builder $inner) use ($needle) {
                $inner->where('order_no', 'like', $needle)
                    ->orWhere('invoice_no', 'like', $needle)
                    ->orWhere('details', 'like', $needle)
                    ->orWhere('payment_mode', 'like', $needle)
                    ->orWhere('payment_notes', 'like', $needle);
            });
        }

        return $query;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, HotelPosOrder>  $orders
     * @return list<array<string, mixed>>
     */
    private function aggregateItemConsumption(Collection $orders, bool $includeOutlet): array
    {
        $map = [];
        foreach ($orders as $order) {
            $outlet = trim((string) ($order->service?->name ?? ''));
            $currency = trim((string) ($order->currency ?? ''));

            foreach ($order->lines as $line) {
                $packageLabel = $this->consumptionPackageLabel($line);
                $key = sprintf(
                    '%s:%d:%d:%s',
                    $includeOutlet ? (string) $order->hotel_pms_service_id : 'all',
                    (int) ($line->hotel_pms_service_item_id ?? 0),
                    (int) ($line->hotel_pms_service_package_id ?? 0),
                    strtolower(trim($packageLabel ?? '')),
                );
                if ((int) ($line->hotel_pms_service_item_id ?? 0) === 0
                    && (int) ($line->hotel_pms_service_package_id ?? 0) === 0
                    && trim($packageLabel ?? '') === '') {
                    $key .= ':name:'.strtolower(trim((string) $line->item_name));
                }

                if (! isset($map[$key])) {
                    $map[$key] = [
                        'item_name' => (string) $line->item_name,
                        'outlet' => $includeOutlet ? ($outlet !== '' ? $outlet : '—') : null,
                        'currency' => $currency,
                        'package_label' => $packageLabel,
                        'qty' => 0.0,
                        'amount' => 0.0,
                        'tax' => 0.0,
                        'total' => 0.0,
                    ];
                }

                $map[$key]['qty'] += (float) $line->quantity;
                $map[$key]['amount'] += (float) $line->line_amount;
                $map[$key]['tax'] += (float) $line->tax_amount;
                $map[$key]['total'] += (float) $line->line_total;
            }
        }

        $rows = array_values($map);
        foreach ($rows as &$row) {
            $row['qty_display'] = $this->formatConsumptionQtyWithPackage((float) $row['qty'], $row['package_label']);
        }
        unset($row);

        usort($rows, function ($a, $b) {
            $outlet = strcasecmp((string) ($a['outlet'] ?? ''), (string) ($b['outlet'] ?? ''));
            if ($outlet !== 0) {
                return $outlet;
            }

            return strcasecmp((string) $a['item_name'], (string) $b['item_name']);
        });

        return $rows;
    }

    private function posReportPaymentCell(HotelPosOrder $order): string
    {
        if ($order->posted_to_folio || $order->cm_reservation_id) {
            return 'Room folio';
        }

        $parts = array_filter([
            trim((string) ($order->payment_mode ?? '')),
            trim((string) ($order->payment_provider ?? '')),
            $order->company ? 'Company: '.$order->company->name : null,
        ]);

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    /** @param  array<string, array{orders: int, subtotal: float, tax: float, total: float, collected: float}>  $totalsByCurrency */
    private function posRevenueSummary(array $totalsByCurrency): ?array
    {
        if ($totalsByCurrency === []) {
            return null;
        }

        $blocks = [];
        foreach ($totalsByCurrency as $currency => $totals) {
            $blocks[] = [
                'currency' => $currency,
                'items' => [
                    ['label' => 'Billed orders', 'value' => (string) $totals['orders']],
                    ['label' => 'Subtotal', 'value' => $this->formatDisplayAmount($totals['subtotal'], $currency)],
                    ['label' => 'Tax', 'value' => $this->formatDisplayAmount($totals['tax'], $currency)],
                    ['label' => 'Total sales', 'value' => $this->formatDisplayAmount($totals['total'], $currency)],
                    ['label' => 'Collected', 'value' => $this->formatDisplayAmount($totals['collected'], $currency)],
                ],
            ];
        }

        return ['revenue_totals' => $blocks];
    }

    private function consumptionPackageLabel(HotelPosOrderLine $line): ?string
    {
        $label = trim((string) ($line->sell_unit_label ?? ''));
        if ($label !== '') {
            return $label;
        }

        $packageName = trim((string) ($line->package?->name ?? ''));
        if ($packageName !== '') {
            return $packageName;
        }

        return null;
    }

    private function formatConsumptionQtyWithPackage(float $qty, ?string $packageLabel): string
    {
        $qtyText = abs($qty - round($qty)) < 0.0001
            ? (string) (int) round($qty)
            : number_format($qty, 2, '.', '');

        $label = trim((string) ($packageLabel ?? ''));
        if ($label === '') {
            return $qtyText;
        }

        return $qtyText.' '.$label;
    }

    /** @return list<array{type: string, amount: float}> */
    public function paymentsReport(
        Hotel $hotel,
        HotelPmsService $service,
        ?\DateTimeInterface $from,
        ?\DateTimeInterface $to,
    ): array {
        $orders = HotelPosOrder::query()
            ->where('hotel_id', $hotel->id)
            ->where('hotel_pms_service_id', $service->id)
            ->where('status', HotelPosOrder::STATUS_BILLED)
            ->where('amount_paid', '>', 0)
            ->when($from, fn ($q) => $q->where('billed_at', '>=', $from->format('Y-m-d 00:00:00')))
            ->when($to, fn ($q) => $q->where('billed_at', '<=', $to->format('Y-m-d 23:59:59')))
            ->get(['payment_mode', 'amount_paid']);

        $map = [];
        foreach ($orders as $order) {
            $type = $order->payment_mode ?: 'Other';
            $map[$type] = ($map[$type] ?? 0) + (float) $order->amount_paid;
        }

        $rows = [];
        foreach ($map as $type => $amount) {
            $rows[] = ['type' => $type, 'amount' => round($amount, 2)];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function buildLines(HotelPmsService $service, array $lines, float $defaultTaxPercent, bool $complimentary): array
    {
        $out = [];
        foreach ($lines as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            $qty = max(0.01, (float) ($row['quantity'] ?? 1));
            $unitPrice = max(0, (float) ($row['unit_price'] ?? 0));
            $taxRate = isset($row['tax_rate']) ? (float) $row['tax_rate'] : $defaultTaxPercent;

            $item = null;
            if ($itemId > 0) {
                $item = HotelPmsServiceItem::query()
                    ->where('hotel_pms_service_id', $service->id)
                    ->whereKey($itemId)
                    ->where('is_active', true)
                    ->with(['category', 'sellOptions.package', 'package', 'sellingPackage'])
                    ->first();
            }

            $name = trim((string) ($row['item_name'] ?? ''));
            if ($name === '' && $item) {
                $name = $item->item_name;
            }
            if ($name === '') {
                continue;
            }

            if ($item && $unitPrice <= 0) {
                $unitPrice = (float) ($item->unit_price ?? 0);
            }

            if ($complimentary) {
                $unitPrice = 0;
                $taxRate = 0;
            }

            $gross = round($qty * $unitPrice, 2);
            $taxInclusive = $this->pricesTaxInclusiveForService($service);
            if ($taxInclusive && $taxRate > 0) {
                $lineAmount = round($gross / (1 + $taxRate / 100), 2);
                $taxAmount = round($gross - $lineAmount, 2);
                $lineTotal = $gross;
            } else {
                $lineAmount = $gross;
                $taxAmount = round($lineAmount * $taxRate / 100, 2);
                $lineTotal = round($lineAmount + $taxAmount, 2);
            }

            $categoryId = (int) ($row['category_id'] ?? 0) ?: ($item?->hotel_pms_service_category_id);
            $categoryName = trim((string) ($row['category_name'] ?? '')) ?: ($item?->category?->name);
            $packageId = (int) ($row['package_id'] ?? 0) ?: (int) ($item?->selling_hotel_pms_service_package_id ?: $item?->hotel_pms_service_package_id);

            $out[] = [
                'hotel_pms_service_category_id' => $categoryId ?: null,
                'category_name' => $categoryName,
                'hotel_pms_service_item_id' => $item?->id,
                'item_name' => $name,
                'hotel_pms_service_package_id' => $packageId ?: null,
                'sell_unit_label' => trim((string) ($row['unit_label'] ?? '')) ?: null,
                'quantity' => $qty,
                'pieces_per_sell_unit' => 1.0,
                'unit_price' => $unitPrice,
                'line_amount' => $lineAmount,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
                'catalog_item' => $item,
            ];
        }

        if ($out === []) {
            throw new InvalidArgumentException('Add at least one valid service line.');
        }

        return $out;
    }

    private function postOrderToFolio(HotelPosOrder $order, CmReservation $reservation): void
    {
        if ($order->posted_to_folio || $order->total <= 0) {
            return;
        }

        $payload = is_array($reservation->payload) ? $reservation->payload : [];
        $charges = is_array($payload['pos_charges'] ?? null) ? $payload['pos_charges'] : [];
        $charges[] = [
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'invoice_no' => $order->invoice_no,
            'amount' => (float) $order->total,
            'currency' => $order->currency,
            'posted_at' => now()->toIso8601String(),
        ];
        $payload['pos_charges'] = $charges;

        $reservation->update([
            'amount_after_tax' => round((float) ($reservation->amount_after_tax ?? 0) + (float) $order->total, 2),
            'payload' => $payload,
        ]);

        $order->update(['posted_to_folio' => true]);
    }
}
