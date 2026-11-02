<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelStoreIndent extends Model
{
    public const STATUS_APPLIED = 'applied';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_RECEIVED = 'received';

    protected $fillable = [
        'hotel_id',
        'hotel_department_id',
        'hotel_store_category_id',
        'created_by',
        'indent_number',
        'pre_tax',
        'status',
        'received_at',
        'received_by',
    ];

    protected function casts(): array
    {
        return [
            'pre_tax' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HotelDepartment::class, 'hotel_department_id');
    }

    public function storeCategory(): BelongsTo
    {
        return $this->belongsTo(HotelStoreCategory::class, 'hotel_store_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(HotelStoreIndentItem::class);
    }

    public function isApplied(): bool
    {
        return $this->status === self::STATUS_APPLIED;
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function isReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APPLIED => 'Applied',
            self::STATUS_ISSUED => 'Awaiting department',
            self::STATUS_RECEIVED => 'Received',
            default => ucfirst((string) $this->status),
        };
    }

    public function dateTimeLabel(): string
    {
        return $this->created_at?->format('d M h:i A') ?? '—';
    }
}
