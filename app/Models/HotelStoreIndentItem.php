<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelStoreIndentItem extends Model
{
    protected $fillable = [
        'hotel_store_indent_id',
        'hotel_store_master_item_id',
        'name',
        'quantity',
        'unit',
        'pre_tax',
        'comments',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'pre_tax' => 'decimal:2',
        ];
    }

    public function indent(): BelongsTo
    {
        return $this->belongsTo(HotelStoreIndent::class, 'hotel_store_indent_id');
    }

    public function masterItem(): BelongsTo
    {
        return $this->belongsTo(HotelStoreMasterItem::class, 'hotel_store_master_item_id');
    }
}
