<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelExpense extends Model
{
    public const ENTRY_EXPENSE = 'expense';

    public const ENTRY_DEPOSIT = 'deposit';

    public const PAID_IN = 'paid_in';

    public const PAID_OUT = 'paid_out';

    /** @var list<string> */
    public const PAYMENT_TYPES = [
        'cash',
        'credit',
        'upi',
        'bank_transfer',
        'bill_to_company',
        'prepaid',
    ];

    protected $fillable = [
        'hotel_id',
        'entry_type',
        'paid_type',
        'payment_type',
        'amount',
        'category',
        'expense_date',
        'invoice_no',
        'vendor',
        'comments',
        'details_path',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function paymentTypeLabel(): string
    {
        return self::paymentTypeLabels()[$this->payment_type] ?? ucfirst(str_replace('_', ' ', $this->payment_type));
    }

    public function paidTypeLabel(): string
    {
        return match ($this->paid_type) {
            self::PAID_IN => 'Paid In',
            self::PAID_OUT => 'Paid Out',
            default => '—',
        };
    }

    public function entryTypeLabel(): string
    {
        return $this->entry_type === self::ENTRY_DEPOSIT ? 'Deposit' : 'Expense';
    }

    /** @return array<string, string> */
    public static function paymentTypeLabels(): array
    {
        return [
            'cash' => 'Cash',
            'credit' => 'Credit',
            'upi' => 'UPI',
            'bank_transfer' => 'Bank Transfer',
            'bill_to_company' => 'Bill to Company',
            'prepaid' => 'Prepaid',
        ];
    }

    /** @return array<string, string> */
    public static function paidTypeLabels(): array
    {
        return [
            self::PAID_IN => 'Paid In',
            self::PAID_OUT => 'Paid Out',
        ];
    }
}
