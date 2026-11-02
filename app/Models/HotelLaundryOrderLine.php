<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelLaundryOrderLine extends Model
{
    protected $fillable = [
        'hotel_laundry_order_id',
        'hotel_pms_service_category_id',
        'item_name',
        'quantity',
        'unit_price',
        'line_total',
        'is_complimentary',
        'item_notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'is_complimentary' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(HotelLaundryOrder::class, 'hotel_laundry_order_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HotelPmsServiceCategory::class, 'hotel_pms_service_category_id');
    }
}
