<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelRoom extends Model
{
    protected $fillable = [
        'hotel_id', 'is_enabled', 'rank', 'name', 'display_name', 'description',
        'room_count', 'min_occupancy', 'max_occupancy', 'show_ota_breakup', 'amenities',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'show_ota_breakup' => 'boolean',
            'amenities' => 'array',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function ratePlans(): HasMany
    {
        return $this->hasMany(HotelRatePlan::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(HotelRoomUnit::class, 'hotel_room_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(HotelRoomPhoto::class, 'hotel_room_id')->orderBy('sort_order');
    }

    /** @return list<string> */
    public function numberedRoomLabels(): array
    {
        return $this->units()
            ->whereNotNull('room_number')
            ->where('room_number', '!=', '')
            ->orderBy('room_number')
            ->pluck('room_number')
            ->all();
    }
}
