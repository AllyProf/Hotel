<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelStoreSubCategory extends Model
{
    protected $fillable = [
        'hotel_id',
        'hotel_store_category_id',
        'name',
        'sort_order',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HotelStoreCategory::class, 'hotel_store_category_id');
    }

    /** @return HasMany<HotelStoreMasterItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(HotelStoreMasterItem::class);
    }
}
