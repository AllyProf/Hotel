<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelStoreTaxGroup extends Model
{
    protected $fillable = [
        'hotel_id',
        'name',
        'rate_percent',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'rate_percent' => 'decimal:2',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /** @return HasMany<HotelStoreMasterItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(HotelStoreMasterItem::class);
    }
}
