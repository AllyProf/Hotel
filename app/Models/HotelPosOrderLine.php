<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelPosOrderLine extends Model
{
    protected $fillable = [
        'hotel_pos_order_id',
        'hotel_pms_service_category_id',
        'category_name',
        'hotel_pms_service_item_id',
        'item_name',
        'hotel_pms_service_package_id',
        'sell_unit_label',
        'quantity',
        'pieces_per_sell_unit',
        'unit_price',
        'line_amount',
        'tax_rate',
        'tax_amount',
        'line_total',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'pieces_per_sell_unit' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'line_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(HotelPosOrder::class, 'hotel_pos_order_id');
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
