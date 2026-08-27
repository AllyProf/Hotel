<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelRatePlan extends Model
{
    public const PRICING_BOTH = 'both';

    public const PRICING_LOCAL = 'local';

    public const PRICING_INTERNATIONAL = 'international';

    /** @return list<string> */
    public static function mealPlanCodes(): array
    {
        return array_column(config('channel_manager_integration.overview.meal_plans', []), 'code');
    }

    public static function mealsForPlan(string $mealPlan): int
    {
        return match (strtoupper($mealPlan)) {
            'CP' => 1,
            'MAP' => 2,
            'AP' => 3,
            default => 0,
        };
    }

    protected $fillable = [
        'hotel_id', 'hotel_room_id', 'code', 'occupancy', 'meal_plan', 'colour_code',
        'meals', 'is_master', 'base_rate', 'local_base_rate', 'local_currency', 'international_currency',
        'pricing_mode', 'ratio', 'be_ratio', 'extra_adult',
        'extra_child', 'description', 'policy', 'amenities',
    ];

    protected function casts(): array
    {
        return [
            'is_master' => 'boolean',
            'base_rate' => 'decimal:2',
            'local_base_rate' => 'decimal:2',
            'ratio' => 'decimal:4',
            'be_ratio' => 'decimal:4',
            'extra_adult' => 'decimal:2',
            'extra_child' => 'decimal:2',
            'amenities' => 'array',
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

    public function displayLabel(): string
    {
        return $this->room?->name.' / '.$this->code.' / '.$this->meal_plan;
    }

    public function rateForGuestType(string $guestType = 'international'): float
    {
        if ($this->pricing_mode === self::PRICING_INTERNATIONAL) {
            return (float) $this->base_rate;
        }

        if ($this->pricing_mode === self::PRICING_LOCAL) {
            return (float) ($this->local_base_rate ?? $this->base_rate);
        }

        if ($guestType === 'local' && $this->local_base_rate !== null) {
            return (float) $this->local_base_rate;
        }

        return (float) $this->base_rate;
    }
}
