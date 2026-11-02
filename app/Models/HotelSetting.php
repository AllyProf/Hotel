<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelSetting extends Model
{
    protected $fillable = [
        'hotel_id', 'pms', 'be', 'rateplan', 'whatsapp', 'laundry', 'reservation', 'hr', 'bar',
        'hk_procurement', 'laundry_procurement', 'reception_procurement',
        'integrations', 'custom_amenities',
    ];

    protected function casts(): array
    {
        return [
            'pms' => 'array',
            'be' => 'array',
            'rateplan' => 'array',
            'whatsapp' => 'array',
            'laundry' => 'array',
            'reservation' => 'array',
            'hr' => 'array',
            'bar' => 'array',
            'hk_procurement' => 'array',
            'laundry_procurement' => 'array',
            'reception_procurement' => 'array',
            'integrations' => 'array',
            'custom_amenities' => 'array',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
