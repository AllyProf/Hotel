<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelPmsConferenceRate extends Model
{
    protected $fillable = [
        'hotel_id',
        'hotel_pms_service_id',
        'hotel_pms_service_category_id',
        'label',
        'capacity',
        'pricing_unit',
        'unit_price',
        'currency',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'capacity' => 'integer',
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

    public function pricingUnitLabel(): string
    {
        $options = config('hotel_pms_service_templates.conference_pricing_units', []);

        return $options[$this->pricing_unit] ?? ucfirst(str_replace('_', ' ', (string) $this->pricing_unit));
    }

    public function displayTitle(): string
    {
        if ($this->label) {
            return $this->label;
        }

        return $this->category?->name ?? 'Conference rate';
    }

    public function formattedUnitPrice(?Hotel $hotel = null): string
    {
        $hotel ??= $this->hotel;
        $currency = strtoupper($this->currency ?: $this->service?->displayCurrency($hotel) ?: 'TZS');
        $decimals = in_array($currency, ['TZS'], true) ? 0 : 2;

        return number_format((float) $this->unit_price, $decimals).' '.$currency;
    }
}
