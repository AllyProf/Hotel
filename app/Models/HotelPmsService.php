<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelPmsService extends Model
{
    public const PRICING_UNITS = ['fixed', 'per_day', 'per_person', 'per_use'];

    protected $fillable = [
        'hotel_id',
        'template_key',
        'name',
        'category',
        'amount',
        'currency',
        'pricing_unit',
        'tax_category',
        'hsn_code',
        'tax_inclusive',
        'visible_on_be',
        'show_in_pos',
        'amount_editable',
        'is_active',
        'image',
        'comments',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tax_inclusive' => 'boolean',
            'visible_on_be' => 'boolean',
            'show_in_pos' => 'boolean',
            'amount_editable' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function serviceCategories(): HasMany
    {
        return $this->hasMany(HotelPmsServiceCategory::class, 'hotel_pms_service_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function servicePackages(): HasMany
    {
        return $this->hasMany(HotelPmsServicePackage::class, 'hotel_pms_service_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function laundryPrices(): HasMany
    {
        return $this->hasMany(HotelPmsLaundryPrice::class, 'hotel_pms_service_id')
            ->orderBy('item_name');
    }

    public function catalogItems(): HasMany
    {
        return $this->hasMany(HotelPmsServiceItem::class, 'hotel_pms_service_id')
            ->orderBy('item_name');
    }

    public function laundryOrders(): HasMany
    {
        return $this->hasMany(HotelLaundryOrder::class, 'hotel_pms_service_id');
    }

    public function swimmingRates(): HasMany
    {
        return $this->hasMany(HotelPmsSwimmingRate::class, 'hotel_pms_service_id');
    }

    public function swimmingVisits(): HasMany
    {
        return $this->hasMany(HotelSwimmingVisit::class, 'hotel_pms_service_id');
    }

    public function conferenceRates(): HasMany
    {
        return $this->hasMany(HotelPmsConferenceRate::class, 'hotel_pms_service_id');
    }

    public function conferenceBookings(): HasMany
    {
        return $this->hasMany(HotelConferenceBooking::class, 'hotel_pms_service_id');
    }

    public function barSales(): HasMany
    {
        return $this->hasMany(HotelBarSale::class, 'hotel_pms_service_id');
    }

    public function isCustom(): bool
    {
        return $this->template_key === null || $this->template_key === '';
    }

    public function isProductBased(): bool
    {
        if ($this->isCustom()) {
            return true;
        }

        $template = config('hotel_pms_service_templates.templates.'.$this->template_key, []);

        return ($template['product_based'] ?? true) === true;
    }

    public function showsSettings(): bool
    {
        return $this->isCustom() || ! $this->isProductBased() || $this->isLaundry() || $this->isConference();
    }

    public function showsCatalog(): bool
    {
        return $this->isCustom() || $this->isProductBased();
    }

    /** Catalog-based services that may appear on ERP → POS (POS Name dropdown). */
    public function canConfigurePosVisibility(): bool
    {
        return $this->showsCatalog();
    }

    public function usesPackages(): bool
    {
        if ($this->isCustom()) {
            return false;
        }

        $template = config('hotel_pms_service_templates.templates.'.$this->template_key, []);

        return ($template['uses_packages'] ?? false) === true;
    }

    public function showsPackages(): bool
    {
        return $this->usesPackages();
    }

    public function displayCurrency(?Hotel $hotel = null): string
    {
        if ($this->currency) {
            return strtoupper($this->currency);
        }

        return strtoupper($hotel?->currency ?? 'TZS');
    }

    public function hasTax(): bool
    {
        $category = strtolower(trim($this->tax_category ?? ''));

        return $category !== '' && $category !== 'no tax';
    }

    public function isParking(): bool
    {
        return $this->template_key === 'parking';
    }

    public function isLaundry(): bool
    {
        return $this->template_key === 'laundry';
    }

    public function isSwimming(): bool
    {
        return $this->template_key === 'swimming';
    }

    public function isConference(): bool
    {
        return $this->template_key === 'conference';
    }

    public function isBar(): bool
    {
        return $this->template_key === 'bar';
    }

    public function usesMenuItems(): bool
    {
        return in_array($this->template_key, ['bar', 'restaurant', 'room_service'], true);
    }

    /** @return array<string, mixed> */
    public function parkingSettings(): array
    {
        $defaults = config('hotel_pms_service_templates.facility_defaults.parking', []);

        return array_merge($defaults, is_array($this->settings) ? $this->settings : []);
    }

    /** @return array<string, mixed> */
    public function swimmingSettings(): array
    {
        $defaults = config('hotel_pms_service_templates.facility_defaults.swimming', []);

        return array_merge($defaults, is_array($this->settings) ? $this->settings : []);
    }

    /** @return array<string, mixed> */
    public function conferenceSettings(): array
    {
        $defaults = config('hotel_pms_service_templates.facility_defaults.conference', []);

        return array_merge($defaults, is_array($this->settings) ? $this->settings : []);
    }

    /** @return array<string, mixed> */
    public function laundrySettings(): array
    {
        $defaults = config('hotel_pms_service_templates.facility_defaults.laundry', []);
        $stored = array_merge($defaults, is_array($this->settings) ? $this->settings : []);

        if (empty($stored['items'])) {
            $this->loadMissing('hotel.settings');
            $legacyItems = $this->hotel?->settings?->laundry['items'] ?? [];
            if ($legacyItems !== []) {
                $stored['items'] = $legacyItems;
            }
        }

        return $stored;
    }

    /** @return array<string, mixed> */
    public function menuItemSettings(): array
    {
        $key = $this->template_key ?: 'bar';
        $defaults = config('hotel_pms_service_templates.facility_defaults.'.$key, []);

        return array_merge($defaults, is_array($this->settings) ? $this->settings : []);
    }

    /** @return array<string, mixed> */
    public function barSettings(): array
    {
        $defaults = config('hotel_pms_service_templates.facility_defaults.bar', []);

        return array_merge($defaults, is_array($this->settings) ? $this->settings : []);
    }

    /** @return list<string> */
    public static function vehicleTypeOptions(): array
    {
        return array_keys(config('hotel_pms_service_templates.vehicle_types', []));
    }

    public function categoryLabel(): string
    {
        $categories = config('hotel_pms_service_templates.categories', []);

        return $categories[$this->category]['label'] ?? ucfirst(str_replace('_', ' ', $this->category));
    }

    public function pricingUnitLabel(): string
    {
        $units = config('hotel_pms_service_templates.pricing_units', []);

        return $units[$this->pricing_unit] ?? ucfirst(str_replace('_', ' ', $this->pricing_unit));
    }

    /** @return array<string, mixed> */
    public function toTableRow(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'category_label' => $this->categoryLabel(),
            'amount' => (float) $this->amount,
            'pricing_unit' => $this->pricing_unit,
            'pricing_unit_label' => $this->pricingUnitLabel(),
            'tax_category' => $this->tax_category,
            'hsn_code' => $this->hsn_code,
            'tax_inclusive' => $this->tax_inclusive,
            'visible_on_be' => $this->visible_on_be,
            'amount_editable' => $this->amount_editable,
            'is_active' => $this->is_active,
            'is_custom' => $this->isCustom(),
            'template_key' => $this->template_key,
            'comments' => $this->comments,
        ];
    }
}
