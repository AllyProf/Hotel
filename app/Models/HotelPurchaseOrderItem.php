<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelPurchaseOrderItem extends Model
{
    protected $fillable = [
        'hotel_purchase_order_id',
        'name',
        'quantity',
        'rate',
        'pre_tax',
        'tax',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'rate' => 'decimal:2',
            'pre_tax' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(HotelPurchaseOrder::class, 'hotel_purchase_order_id');
    }
}
