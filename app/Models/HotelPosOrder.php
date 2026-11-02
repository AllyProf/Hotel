<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelPosOrder extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_BILLED = 'billed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'hotel_id',
        'hotel_pms_service_id',
        'status',
        'is_complimentary',
        'order_no',
        'invoice_no',
        'details',
        'subtotal',
        'tax_total',
        'discount',
        'total',
        'currency',
        'amount_paid',
        'balance',
        'payment_mode',
        'payment_provider',
        'payment_notes',
        'cm_reservation_id',
        'hotel_company_id',
        'posted_to_folio',
        'stock_deducted',
        'ordered_at',
        'billed_at',
        'created_by_user_id',
        'waiter_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_complimentary' => 'boolean',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance' => 'decimal:2',
            'posted_to_folio' => 'boolean',
            'stock_deducted' => 'boolean',
            'ordered_at' => 'datetime',
            'billed_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isBilled(): bool
    {
        return $this->status === self::STATUS_BILLED;
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(HotelPmsService::class, 'hotel_pms_service_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(CmReservation::class, 'cm_reservation_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(HotelCompany::class, 'hotel_company_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_user_id');
    }

    /** @return HasMany<HotelPosOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(HotelPosOrderLine::class)->orderBy('sort_order')->orderBy('id');
    }
}
