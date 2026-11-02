<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelPmsLaundryPrice extends Model
{
    protected $fillable = [
        'hotel_id',
        'hotel_pms_service_id',
        'hotel_pms_service_category_id',
        'item_name',
        'hotel_pms_service_package_id',
        'unit_price',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(HotelPmsService::class, 'hotel_pms_service_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HotelPmsServiceCategory::class, 'hotel_pms_service_category_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(HotelPmsServicePackage::class, 'hotel_pms_service_package_id');
    }
}
