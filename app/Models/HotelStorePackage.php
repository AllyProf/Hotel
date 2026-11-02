<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelStorePackage extends Model
{
    protected $fillable = [
        'hotel_id',
        'hotel_pms_service_package_id',
        'name',
        'abbreviation',
        'sort_order',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function displayLabel(): string
    {
        $abbr = trim((string) $this->abbreviation);
        $name = trim((string) $this->name);

        if ($abbr !== '' && $name !== '' && strcasecmp($abbr, $name) !== 0) {
            return $name.' ('.$abbr.')';
        }

        return $abbr !== '' ? $abbr : $name;
    }

    public function unitLabel(): string
    {
        $abbr = trim((string) $this->abbreviation);

        return $abbr !== '' ? $abbr : trim((string) $this->name);
    }

    /** @return HasMany<HotelStoreMasterItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(HotelStoreMasterItem::class);
    }
}
