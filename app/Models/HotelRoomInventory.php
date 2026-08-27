<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelRoomInventory extends Model
{
    protected $fillable = [
        'hotel_id',
        'hotel_room_id',
        'date',
        'available_count',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(HotelRoom::class, 'hotel_room_id');
    }
}
