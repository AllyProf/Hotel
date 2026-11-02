<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelStoreMasterItemSellOption extends Model
{
    protected $fillable = [
        'hotel_store_master_item_id',
        'hotel_store_package_id',
        'pieces_per_sell_unit',
        'unit_price',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'pieces_per_sell_unit' => 'decimal:2',
            'unit_price' => 'decimal:2',
        ];
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
