<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelBarSaleLine extends Model
{
    protected $fillable = [
        'hotel_bar_sale_id',
        'hotel_pms_service_item_id',
        'item_name',
        'hotel_pms_service_package_id',
        'sell_unit_label',
        'quantity',
        'pieces_per_sell_unit',
        'unit_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'pieces_per_sell_unit' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(HotelBarSale::class, 'hotel_bar_sale_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(HotelPmsServiceItem::class, 'hotel_pms_service_item_id');
    }

    public function sellPackage(): BelongsTo
    {
        return $this->belongsTo(HotelPmsServicePackage::class, 'hotel_pms_service_package_id');
    }
}
