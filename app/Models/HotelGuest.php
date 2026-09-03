<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class HotelGuest extends Model
{
    protected $fillable = [
        'hotel_id',
        'name',
        'phone',
        'email',
        'photo_path',
        'total_value',
        'currency',
        'previous_stays',
    ];

    protected function casts(): array
    {
        return [
            'total_value' => 'decimal:2',
            'previous_stays' => 'integer',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function photoUrl(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->photo_path);
    }

    public function totalValueLabel(?string $fallbackCurrency = null): string
    {
        $currency = strtoupper($this->currency ?: $fallbackCurrency ?: 'USD');

        return number_format((float) $this->total_value, 0).' '.$currency;
    }
}
