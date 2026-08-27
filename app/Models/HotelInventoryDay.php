<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelInventoryDay extends Model
{
    protected $fillable = [
        'hotel_id',
        'date',
        'is_open',
        'ota_status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_open' => 'boolean',
            'ota_status' => 'array',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
