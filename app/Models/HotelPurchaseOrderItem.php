<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelPurchaseOrderItem extends Model
{
    protected $fillable = [
        'hotel_purchase_order_id',
        'hotel_store_master_item_id',
        'hotel_store_package_id',
        'name',
        'quantity',
        'received_quantity',
        'receive_status',
        'receive_notes',
        'stock_applied_at',
        'rate',
        'pre_tax',
        'tax',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'received_quantity' => 'decimal:2',
            'stock_applied_at' => 'datetime',
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

    public function masterItem(): BelongsTo
    {
        return $this->belongsTo(HotelStoreMasterItem::class, 'hotel_store_master_item_id');
    }

    public function storePackage(): BelongsTo
    {
        return $this->belongsTo(HotelStorePackage::class, 'hotel_store_package_id');
    }
}
