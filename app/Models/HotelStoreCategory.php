<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelStoreCategory extends Model
{
    protected $fillable = [
        'hotel_id',
        'hotel_pms_service_id',
        'hotel_department_id',
        'name',
        'sort_order',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function pmsService(): BelongsTo
    {
        return $this->belongsTo(HotelPmsService::class, 'hotel_pms_service_id');
    }

    public function hotelDepartment(): BelongsTo
    {
        return $this->belongsTo(HotelDepartment::class, 'hotel_department_id');
    }

    /** @return HasMany<HotelStoreSubCategory, $this> */
    public function subCategories(): HasMany
    {
        return $this->hasMany(HotelStoreSubCategory::class)->orderBy('sort_order')->orderBy('name');
    }

    /** @return HasMany<HotelStoreMasterItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(HotelStoreMasterItem::class);
    }
}
