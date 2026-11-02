<?php

namespace App\Models;

use App\Support\DepartmentProcurement;
use App\Services\Bar\BarPurchaseListService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelBarPurchaseList extends Model
{
    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const RECIPIENT_AWAITING = 'awaiting';

    public const RECIPIENT_IN_PROGRESS = 'in_progress';

    public const RECIPIENT_BUDGET_PREPARED = 'budget_prepared';

    public const RECIPIENT_RETURNED = 'returned_to_department';

    public const ACCOUNTING_PENDING = 'pending';

    public const ACCOUNTING_APPROVED = 'approved';

    public const ACCOUNTING_REJECTED = 'rejected';

    public const MARKET_WAITING = 'waiting';

    public const MARKET_RELEASED = 'released';

    public const MARKET_COMPLETED = 'completed';

    public const STOCK_DEST_STORE = 'store';

    public const STOCK_DEST_BAR = 'bar_department';

    public const STOCK_DEST_DEPARTMENT = 'department';

    protected $fillable = [
        'hotel_id',
        'workflow_key',
        'hotel_department_id',
        'hotel_vendor_id',
        'reference_no',
        'status',
        'recipient_status',
        'notes',
        'recipient_notes',
        'budget_currency',
        'budget_total',
        'sent_to',
        'created_by_user_id',
        'sent_at',
        'received_at',
        'budget_prepared_at',
        'budget_prepared_by_name',
        'budget_prepared_by_email',
        'accounting_status',
        'accounting_notes',
        'accounting_reviewed_at',
        'accounting_reviewed_by_user_id',
        'accounting_reviewed_by_name',
        'market_status',
        'market_released_at',
        'market_completed_at',
        'market_completed_by_user_id',
        'market_payment_type',
        'market_completion_notes',
        'market_budget_amount',
        'market_budget_return_amount',
        'market_budget_return_expense_id',
        'market_stock_destination',
        'market_stock_posted_at',
        'market_stock_posted_by_user_id',
        'hotel_purchase_order_id',
    ];

    protected function casts(): array
    {
        return [
            'sent_to' => 'array',
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
            'budget_prepared_at' => 'datetime',
            'accounting_reviewed_at' => 'datetime',
            'market_released_at' => 'datetime',
            'market_completed_at' => 'datetime',
            'market_stock_posted_at' => 'datetime',
            'budget_total' => 'decimal:2',
            'market_budget_amount' => 'decimal:2',
            'market_budget_return_amount' => 'decimal:2',
        ];
    }

    public function workflowKey(): string
    {
        return $this->workflow_key ?: 'bar';
    }

    public function recipientStatusLabel(): string
    {
        $labels = DepartmentProcurement::statusLabels('recipient_statuses');

        return $labels[$this->recipient_status] ?? ucfirst(str_replace('_', ' ', (string) $this->recipient_status));
    }

    public function resolvedBudgetCurrency(): string
    {
        return app(BarPurchaseListService::class)->resolvedBudgetCurrency($this);
    }

    public function accountingStatusLabel(): string
    {
        $key = $this->effectiveAccountingStatus();

        $labels = DepartmentProcurement::statusLabels('accounting_statuses');

        return $labels[$key] ?? ucfirst(str_replace('_', ' ', (string) $key));
    }

    public function marketStatusLabel(): string
    {
        $key = $this->market_status ?: self::MARKET_WAITING;
        $labels = DepartmentProcurement::statusLabels('market_statuses');

        return $labels[$key] ?? ucfirst(str_replace('_', ' ', (string) $key));
    }

    public function totalRequestedQuantity(): float
    {
        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get();

        return (float) $lines->sum(fn ($line) => max(0, (float) $line->quantity));
    }

    public function totalRequestedQuantityLabel(): string
    {
        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get();
        $lines->loadMissing('receivingPackage');

        $total = (float) $lines->sum(fn ($line) => max(0, (float) $line->quantity));
        $formatted = self::formatQuantityAmount($total);

        $units = $lines
            ->map(fn (HotelBarPurchaseListLine $line) => trim($line->requestedUnitLabel()))
            ->filter(fn (string $unit) => $unit !== '')
            ->unique()
            ->values();

        if ($units->count() === 1) {
            return $formatted.' '.$units->first();
        }

        return $formatted;
    }

    private static function formatQuantityAmount(float $total): string
    {
        if ($total <= 0) {
            return '0';
        }

        $formatted = rtrim(rtrim(number_format($total, 2, '.', ''), '0'), '.');

        return $formatted !== '' ? $formatted : '0';
    }

    public function effectiveAccountingStatus(): ?string
    {
        if ($this->recipient_status !== self::RECIPIENT_BUDGET_PREPARED) {
            return null;
        }

        return $this->accounting_status ?: self::ACCOUNTING_PENDING;
    }

    public function isApprovedForMarket(): bool
    {
        return $this->canStoreMarketReceive();
    }

    public function linkedPurchaseOrderIsPaid(): bool
    {
        $this->loadMissing('purchaseOrder');

        return (bool) $this->purchaseOrder?->isPaid();
    }

    /** Budget approved and Payables PO paid — store keeper may run market receive. */
    public function canStoreMarketReceive(): bool
    {
        return $this->effectiveAccountingStatus() === self::ACCOUNTING_APPROVED
            && $this->market_status === self::MARKET_RELEASED
            && $this->linkedPurchaseOrderIsPaid();
    }

    public function isAwaitingPayablesPaymentForMarket(): bool
    {
        return $this->isMarketPendingPurchase() && ! $this->linkedPurchaseOrderIsPaid();
    }

    public function isAccountingRejected(): bool
    {
        return $this->accounting_status === self::ACCOUNTING_REJECTED;
    }

    /** Budget submitted and locked unless accountant rejected (procurement may revise). */
    public function recipientBudgetLocked(): bool
    {
        return $this->recipient_status === self::RECIPIENT_BUDGET_PREPARED
            && $this->accounting_status !== self::ACCOUNTING_REJECTED;
    }

    public function canQuickSubmitBudgetToAccounts(): bool
    {
        if ($this->recipientBudgetLocked()) {
            return false;
        }

        if ($this->recipient_status === self::RECIPIENT_BUDGET_PREPARED && ! $this->isAccountingRejected()) {
            return false;
        }

        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get();
        if ($lines->isEmpty()) {
            return false;
        }

        if ((int) ($this->hotel_vendor_id ?? 0) <= 0) {
            return false;
        }

        $total = 0.0;
        foreach ($lines as $line) {
            if ($line->unit_cost === null || (float) $line->unit_cost <= 0) {
                return false;
            }
            $qty = max(1, (int) round((float) $line->quantity));
            $total += $qty * (float) $line->unit_cost;
        }

        return $total > 0;
    }

    public function canStoreEditBudget(): bool
    {
        if ($this->isReturnedToDepartment()) {
            return false;
        }

        return ! $this->recipientBudgetLocked();
    }

    /** @return array{line_count: int, priced_count: int, all_priced: bool} */
    public function buyingPriceProgress(): array
    {
        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get();
        $lineCount = $lines->count();
        $pricedCount = $lines->filter(
            fn ($line) => $line->unit_cost !== null && (float) $line->unit_cost > 0
        )->count();

        return [
            'line_count' => $lineCount,
            'priced_count' => $pricedCount,
            'all_priced' => $lineCount > 0 && $pricedCount === $lineCount,
        ];
    }

    public function buyingPriceStatusLabel(): string
    {
        if ($this->recipient_status === self::RECIPIENT_BUDGET_PREPARED && ! $this->isAccountingRejected()) {
            return 'Submitted';
        }

        $progress = $this->buyingPriceProgress();
        if ($progress['line_count'] === 0) {
            return 'Not edited';
        }
        if ($progress['all_priced']) {
            return 'Edited';
        }
        if ($progress['priced_count'] === 0) {
            return 'Not edited';
        }

        return 'Partial ('.$progress['priced_count'].'/'.$progress['line_count'].')';
    }

    public function buyingPriceStatusIsComplete(): bool
    {
        return $this->buyingPriceProgress()['all_priced'];
    }

    public function isReturnedToDepartment(): bool
    {
        if ($this->recipient_status === self::RECIPIENT_RETURNED) {
            return true;
        }

        return $this->recipient_status === self::RECIPIENT_AWAITING
            && $this->storeReturnNotes() !== null;
    }

    public function storeReturnNotes(): ?string
    {
        $notes = trim((string) ($this->recipient_notes ?? ''));
        $prefix = '[Returned by stores]';

        if (! str_starts_with($notes, $prefix)) {
            return null;
        }

        $message = trim(substr($notes, strlen($prefix)));

        return $message !== '' ? $message : null;
    }

    public function marketTabDisplayName(): string
    {
        if ($this->department?->name) {
            return $this->department->name;
        }

        $date = $this->market_released_at ?? $this->sent_at ?? $this->created_at;

        return 'Purchase List - '.$date?->format('F j, Y');
    }

    public function marketTabDate(): ?\Illuminate\Support\Carbon
    {
        return $this->market_released_at ?? $this->sent_at;
    }

    public function marketEstimatedTotal(): float
    {
        if ($this->market_budget_amount !== null) {
            return (float) $this->market_budget_amount;
        }

        return $this->budget_total !== null ? (float) $this->budget_total : 0.0;
    }

    public function marketPlannedBudgetTotal(): float
    {
        return $this->budget_total !== null ? (float) $this->budget_total : 0.0;
    }

    public function marketActualTotal(): float
    {
        if ($this->market_status !== self::MARKET_COMPLETED) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($this->lines as $line) {
            if ($line->market_item_found === false) {
                continue;
            }
            if ($line->purchase_line_total !== null) {
                $total += (float) $line->purchase_line_total;

                continue;
            }
            $qty = $line->quantity_purchased !== null ? (float) $line->quantity_purchased : 0.0;
            $cost = $line->purchase_unit_price !== null ? (float) $line->purchase_unit_price : 0.0;
            $total += $qty * $cost;
        }

        return round($total, 2);
    }

    public function isMarketPendingPurchase(): bool
    {
        return $this->market_status === self::MARKET_RELEASED;
    }

    public function marketStockDestinationLabel(): ?string
    {
        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get();
        $keys = $lines
            ->filter(fn (HotelBarPurchaseListLine $line) => $line->market_item_found !== false
                && (float) ($line->quantity_purchased ?? 0) > 0)
            ->pluck('market_stock_destination')
            ->filter()
            ->unique()
            ->values();

        if ($keys->isEmpty()) {
            $legacy = $this->market_stock_destination;

            $destinations = DepartmentProcurement::marketStockDestinations($this->workflowKey());

            return $legacy
                ? ($destinations[$legacy] ?? $legacy)
                : null;
        }

        if ($keys->count() === 1) {
            $key = (string) $keys->first();
            $destinations = DepartmentProcurement::marketStockDestinations($this->workflowKey());

            return $destinations[$key] ?? $key;
        }

        return 'Multiple destinations';
    }

    public function isMarketStockPending(): bool
    {
        if ($this->market_status !== self::MARKET_COMPLETED || $this->market_stock_posted_at) {
            return false;
        }

        return $this->lines()
            ->where(function ($query) {
                $query->whereNull('market_item_found')->orWhere('market_item_found', true);
            })
            ->where('quantity_purchased', '>', 0)
            ->exists();
    }

    /** Purchased lines (found, qty > 0) for this list. */
    public function marketPurchasedLines(): \Illuminate\Support\Collection
    {
        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get();

        return $lines->filter(fn (HotelBarPurchaseListLine $line) => $line->market_item_found !== false
            && (float) ($line->quantity_purchased ?? 0) > 0);
    }

    public function marketStockLineCountFor(string $destination): int
    {
        return $this->marketPurchasedLines()
            ->filter(fn (HotelBarPurchaseListLine $line) => $line->market_stock_destination === $destination)
            ->count();
    }

    public function hasBarStockToReceive(): bool
    {
        return $this->isMarketStockPending()
            && $this->marketStockLineCountFor(self::STOCK_DEST_BAR) > 0;
    }

    public function marketStockPostedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'market_stock_posted_by_user_id');
    }

    public function accountingReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accounting_reviewed_by_user_id');
    }

    public function marketCompletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'market_completed_by_user_id');
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HotelDepartment::class, 'hotel_department_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(HotelVendor::class, 'hotel_vendor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(HotelBarPurchaseListLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(HotelPurchaseOrder::class, 'hotel_purchase_order_id');
    }

    public function marketBudgetReturnExpense(): BelongsTo
    {
        return $this->belongsTo(HotelExpense::class, 'market_budget_return_expense_id');
    }

    public function hasMarketBudgetReturn(): bool
    {
        return $this->market_budget_return_amount !== null
            && (float) $this->market_budget_return_amount > 0;
    }
}
