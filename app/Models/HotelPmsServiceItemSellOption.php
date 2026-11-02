<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelPmsServiceItemSellOption extends Model
{
    protected $fillable = [
        'hotel_pms_service_item_id',
        'hotel_pms_service_package_id',
        'pieces_per_sell_unit',
        'unit_price',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'pieces_per_sell_unit' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(HotelPmsServiceItem::class, 'hotel_pms_service_item_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(HotelPmsServicePackage::class, 'hotel_pms_service_package_id');
    }
}
