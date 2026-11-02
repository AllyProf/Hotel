<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelPmsSwimmingRate extends Model
{
    protected $fillable = [
        'hotel_id',
        'hotel_pms_service_id',
        'hotel_pms_service_category_id',
        'audience',
        'pricing_unit',
        'label',
        'unit_price',
        'currency',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(HotelPmsService::class, 'hotel_pms_service_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HotelPmsServiceCategory::class, 'hotel_pms_service_category_id');
    }

    public function audienceLabel(): string
    {
        $options = config('hotel_pms_service_templates.swimming_audiences', []);

        return $options[$this->audience] ?? ucfirst((string) $this->audience);
    }

    public function pricingUnitLabel(): string
    {
        $options = config('hotel_pms_service_templates.swimming_pricing_units', []);

        return $options[$this->pricing_unit] ?? ucfirst(str_replace('_', ' ', (string) $this->pricing_unit));
    }

    public function displayTitle(): string
    {
        if ($this->label) {
            return $this->label;
        }

        $parts = array_filter([
            $this->category?->name,
            $this->audience !== 'guest' ? $this->audienceLabel() : null,
            $this->pricingUnitLabel(),
        ]);

        return $parts !== [] ? implode(' · ', $parts) : 'Pool rate';
    }

    public function formattedUnitPrice(?Hotel $hotel = null): string
    {
        $hotel ??= $this->hotel;
        $currency = strtoupper($this->currency ?: $this->service?->displayCurrency($hotel) ?: 'TZS');
        $decimals = in_array($currency, ['TZS'], true) ? 0 : 2;

        return number_format((float) $this->unit_price, $decimals).' '.$currency;
    }

    public function calculateTotal(float $quantity): float
    {
        $unit = (float) $this->unit_price;
        $qty = max(0, $quantity);

        return match ($this->pricing_unit) {
            'fixed' => round($unit, 2),
            default => round($unit * ($qty > 0 ? $qty : 1), 2),
        };
    }
}
