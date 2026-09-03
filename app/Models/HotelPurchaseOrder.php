<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelPurchaseOrder extends Model
{
    protected $fillable = [
        'hotel_id',
        'hotel_vendor_id',
        'created_by',
        'po_number',
        'image_path',
        'pre_tax',
        'tax',
        'total',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'pre_tax' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(HotelVendor::class, 'hotel_vendor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(HotelPurchaseOrderItem::class);
    }

    public function dateLabel(): string
    {
        return $this->created_at?->format('d/m/Y') ?? '—';
    }
}
