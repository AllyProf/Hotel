<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelRateInventory extends Model
{
    protected $fillable = [
        'hotel_id',
        'hotel_rate_plan_id',
        'date',
        'local_rate',
        'international_rate',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'local_rate' => 'decimal:2',
            'international_rate' => 'decimal:2',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(HotelRatePlan::class, 'hotel_rate_plan_id');
    }
}
