<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HotelPurchaseOrder extends Model
{
    public const STATUS_APPLIED = 'applied';

    public const STATUS_OPEN = 'open';

    public const STATUS_INVOICED = 'invoiced';

    public const STATUS_PAID = 'paid';

    public const ACCOUNTS_PENDING = 'pending';

    public const ACCOUNTS_APPROVED = 'approved';

    public const ACCOUNTS_REJECTED = 'rejected';

    protected $fillable = [
        'hotel_id',
        'hotel_vendor_id',
        'hotel_store_category_id',
        'created_by',
        'po_number',
        'image_path',
        'pre_tax',
        'tax',
        'total',
        'status',
        'accounts_status',
        'accounts_submitted_at',
        'accounts_reviewed_at',
        'accounts_reviewed_by_user_id',
        'accounts_notes',
        'invoice_number',
        'invoiced_at',
        'goods_received',
        'goods_received_at',
        'goods_received_by',
        'goods_receive_notes',
        'paid_at',
        'hotel_expense_id',
        'budget_return_amount',
        'budget_return_expense_id',
    ];

    protected function casts(): array
    {
        return [
            'pre_tax' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
            'invoiced_at' => 'datetime',
            'goods_received' => 'boolean',
            'goods_received_at' => 'datetime',
            'accounts_submitted_at' => 'datetime',
            'accounts_reviewed_at' => 'datetime',
            'budget_return_amount' => 'decimal:2',
        ];
    }

    public function accountsReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accounts_reviewed_by_user_id');
    }

    public function goodsReceivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'goods_received_by');
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(HotelVendor::class, 'hotel_vendor_id');
    }

    public function storeDepartment(): BelongsTo
    {
        return $this->belongsTo(HotelStoreCategory::class, 'hotel_store_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(HotelPurchaseOrderItem::class);
    }

    public function paymentExpense(): BelongsTo
    {
        return $this->belongsTo(HotelExpense::class, 'hotel_expense_id');
    }

    public function budgetReturnExpense(): BelongsTo
    {
        return $this->belongsTo(HotelExpense::class, 'budget_return_expense_id');
    }

    public function hasBudgetReturn(): bool
    {
        return $this->budget_return_amount !== null
            && (float) $this->budget_return_amount > 0;
    }

    public function barPurchaseList(): HasOne
    {
        return $this->hasOne(HotelBarPurchaseList::class, 'hotel_purchase_order_id');
    }

    /**
     * Reason Payables cannot mark this open PO paid yet, or null when payment is allowed.
     */
    public function payablesPaymentBlockReason(?HotelBarPurchaseList $linkedBarList = null): ?string
    {
        if (! $this->isOpen()) {
            return null;
        }

        if ($this->isStoreDeskPurchaseOrder()) {
            if ($this->accounts_status !== self::ACCOUNTS_APPROVED) {
                return 'Approve this store PO on Purchase budgets before payment.';
            }

            return null;
        }

        return null;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isApplied(): bool
    {
        return $this->status === self::STATUS_APPLIED;
    }

    public function isInvoiced(): bool
    {
        return $this->status === self::STATUS_INVOICED;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function canBeEdited(): bool
    {
        if ($this->isStoreDeskPurchaseOrder()) {
            return $this->canStoreDeskEditBuyingPrices();
        }

        return in_array($this->status, [self::STATUS_APPLIED, self::STATUS_OPEN], true);
    }

    public function canStoreDeskEditBuyingPrices(): bool
    {
        if (! $this->isStoreDeskPurchaseOrder() || $this->status !== self::STATUS_APPLIED) {
            return false;
        }

        return $this->accounts_status === null || $this->accounts_status === self::ACCOUNTS_REJECTED;
    }

    public function canSubmitStoreDeskToAccounts(): bool
    {
        if (! $this->isStoreDeskPurchaseOrder() || $this->status !== self::STATUS_APPLIED) {
            return false;
        }

        if ($this->accounts_status === self::ACCOUNTS_PENDING || $this->accounts_status === self::ACCOUNTS_APPROVED) {
            return false;
        }

        $this->loadMissing('items');
        if ($this->items->isEmpty()) {
            return false;
        }

        foreach ($this->items as $line) {
            if ((float) $line->rate <= 0) {
                return false;
            }
        }

        return (float) $this->total > 0;
    }

    public function canInvoiceFromStoreDesk(): bool
    {
        if (! $this->isStoreDeskPurchaseOrder()) {
            return $this->isOpen();
        }

        return $this->isOpen() && $this->accounts_status === self::ACCOUNTS_APPROVED;
    }

    public function canReceiveStoreDeskGoods(): bool
    {
        if (! $this->isStoreDeskPurchaseOrder()) {
            return false;
        }

        if ($this->accounts_status !== self::ACCOUNTS_APPROVED || $this->goods_received) {
            return false;
        }

        return $this->isPaid();
    }

    /** Accountant approved — store keeper can print and go to market / vendor. */
    public function canPrintStoreDeskPurchaseList(): bool
    {
        return $this->isStoreDeskPurchaseOrder()
            && $this->accounts_status === self::ACCOUNTS_APPROVED;
    }

    /** Whether the stores PO queue row should show the ⋮ actions menu. */
    public function storeDeskHasRowMenuActions(array $permissions): bool
    {
        if (! $this->isStoreDeskPurchaseOrder()) {
            return false;
        }

        if (($permissions['edit'] ?? false) && $this->canStoreDeskEditBuyingPrices()) {
            return true;
        }

        if ($this->canSubmitStoreDeskToAccounts()) {
            return true;
        }

        if ($this->canPrintStoreDeskPurchaseList()) {
            return true;
        }

        if (($permissions['invoice'] ?? false) && $this->canReceiveStoreDeskGoods()) {
            return true;
        }

        return ($permissions['delete'] ?? false)
            && ! $this->isPaid()
            && $this->canStoreDeskEditBuyingPrices();
    }

    public function accountsStatusLabel(): string
    {
        return match ($this->accounts_status) {
            self::ACCOUNTS_PENDING => 'Awaiting accountant',
            self::ACCOUNTS_APPROVED => 'Approved for payment',
            self::ACCOUNTS_REJECTED => 'Rejected by accountant',
            default => 'After store',
        };
    }

    /** Accounts column on Stores → Purchase orders (includes Payables payment). */
    public function storeDeskAccountsDisplayLabel(): string
    {
        if ($this->isPaid()) {
            return 'Paid';
        }

        return $this->accountsStatusLabel();
    }

    public function storeDeskReviewLabel(): string
    {
        if ($this->accounts_status === self::ACCOUNTS_REJECTED) {
            return 'Revise buying prices';
        }
        if ($this->accounts_status === self::ACCOUNTS_PENDING) {
            return 'Budget submitted';
        }
        if ($this->accounts_status === self::ACCOUNTS_APPROVED) {
            if ($this->goods_received) {
                return 'Goods received';
            }
            if ($this->isPaid()) {
                return 'Paid — ready to receive';
            }

            return 'Awaiting payment';
        }
        if ($this->canSubmitStoreDeskToAccounts()) {
            return 'Ready for Accounts';
        }

        return 'Enter buying prices';
    }

    /** @return array{line_count: int, priced_count: int, all_priced: bool} */
    public function storeDeskBuyingPriceProgress(): array
    {
        $this->loadMissing('items');
        $lineCount = $this->items->count();
        $pricedCount = $this->items->filter(fn ($line) => (float) $line->rate > 0)->count();

        return [
            'line_count' => $lineCount,
            'priced_count' => $pricedCount,
            'all_priced' => $lineCount > 0 && $pricedCount === $lineCount,
        ];
    }

    public function storeDeskBuyingPriceStatusLabel(): string
    {
        if (! $this->isStoreDeskPurchaseOrder()) {
            return '—';
        }

        if ($this->accounts_status === self::ACCOUNTS_PENDING) {
            return 'Submitted';
        }

        if ($this->accounts_status === self::ACCOUNTS_APPROVED) {
            return 'Edited';
        }

        if ($this->accounts_status === self::ACCOUNTS_REJECTED) {
            $progress = $this->storeDeskBuyingPriceProgress();

            return $progress['all_priced'] ? 'Edited' : $this->buyingPricePartialLabel($progress);
        }

        $progress = $this->storeDeskBuyingPriceProgress();
        if ($progress['line_count'] === 0) {
            return 'Not edited';
        }
        if ($progress['all_priced']) {
            return 'Edited';
        }
        if ($progress['priced_count'] === 0) {
            return 'Not edited';
        }

        return $this->buyingPricePartialLabel($progress);
    }

    /** @param  array{line_count: int, priced_count: int, all_priced: bool}  $progress */
    private function buyingPricePartialLabel(array $progress): string
    {
        return 'Partial ('.$progress['priced_count'].'/'.$progress['line_count'].')';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APPLIED => 'Applied',
            self::STATUS_INVOICED => 'Invoiced',
            self::STATUS_PAID => 'Paid',
            self::STATUS_OPEN => 'Open',
            default => ucfirst((string) $this->status),
        };
    }

    public function invoiceDateLabel(): string
    {
        return $this->invoiced_at?->format('d M') ?? '—';
    }

    public function dateLabel(): string
    {
        return $this->created_at?->format('d/m/Y') ?? '—';
    }

    public function dateTimeLabel(): string
    {
        return $this->created_at?->format('d M h:i A') ?? '—';
    }

    public function sentAtLabel(): string
    {
        return $this->created_at?->format('d M Y H:i') ?? '—';
    }

    public function totalQuantityLabel(): string
    {
        $this->loadMissing(['items.masterItem.storePackage']);
        $total = round((float) $this->items->sum('quantity'), 2);
        $formatted = self::formatStoreDeskQuantityAmount($total);

        $units = $this->items
            ->map(function (HotelPurchaseOrderItem $item) {
                $master = $item->masterItem;
                if (! $master) {
                    return '';
                }
                $pkgName = trim((string) ($master->storePackage?->name ?? ''));
                if ($pkgName !== '') {
                    return $pkgName;
                }

                return trim((string) ($master->unit ?? ''));
            })
            ->filter(fn (string $unit) => $unit !== '')
            ->unique()
            ->values();

        if ($units->count() === 1) {
            return $formatted.' '.$units->first();
        }

        return $formatted;
    }

    private static function formatStoreDeskQuantityAmount(float $total): string
    {
        if ($total <= 0) {
            return '0';
        }

        if (fmod($total, 1.0) === 0.0) {
            return (string) (int) $total;
        }

        return rtrim(rtrim(number_format($total, 2, '.', ''), '0'), '.');
    }

    public function isStoreDeskPurchaseOrder(): bool
    {
        return str_starts_with((string) $this->po_number, 'ORD-');
    }
}
