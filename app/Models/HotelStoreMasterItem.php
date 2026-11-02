<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelStoreMasterItem extends Model
{
    protected $fillable = [
        'hotel_id',
        'hotel_store_category_id',
        'hotel_vendor_id',
        'hotel_store_sub_category_id',
        'hotel_pms_service_category_id',
        'name',
        'rate',
        'tax_group',
        'hotel_store_tax_group_id',
        'tax_inclusive',
        'inventoried',
        'capital_item',
        'stock',
        'unit',
        'hotel_store_package_id',
        'pieces_per_receive_unit',
        'usage_type',
    ];

    public const USAGE_SALE = 'sale';

    public const USAGE_INTERNAL = 'internal';

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'stock' => 'decimal:2',
            'pieces_per_receive_unit' => 'decimal:2',
            'tax_inclusive' => 'boolean',
            'inventoried' => 'boolean',
            'capital_item' => 'boolean',
        ];
    }

    public function lineValue(): float
    {
        return round((float) $this->rate * (float) $this->stock, 2);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HotelStoreCategory::class, 'hotel_store_category_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(HotelVendor::class, 'hotel_vendor_id');
    }

    public function taxGroup(): BelongsTo
    {
        return $this->belongsTo(HotelStoreTaxGroup::class, 'hotel_store_tax_group_id');
    }

    public function storePackage(): BelongsTo
    {
        return $this->belongsTo(HotelStorePackage::class, 'hotel_store_package_id');
    }

    public function sellOptions(): HasMany
    {
        return $this->hasMany(HotelStoreMasterItemSellOption::class, 'hotel_store_master_item_id')
            ->orderBy('sort_order');
    }

    public function isForSale(): bool
    {
        return $this->usage_type === self::USAGE_SALE;
    }

    /** Unit shown when issuing / consuming (sell option), not receive stock unit. */
    public function consumptionUnitLabel(): string
    {
        $this->loadMissing(['sellOptions.storePackage', 'storePackage']);

        $sellOption = $this->sellOptions->sortBy('sort_order')->first();
        if ($sellOption?->storePackage) {
            return $sellOption->storePackage->unitLabel();
        }

        $unit = trim((string) ($this->unit ?? ''));
        if ($unit !== '') {
            return $unit;
        }

        if ($this->storePackage) {
            return $this->storePackage->unitLabel();
        }

        return (string) config('hotel_stores.po_line_qty_unit', 'Pkts');
    }

    /**
     * Receive + usage units configured on the item (for indents / department issue).
     *
     * @return list<array{value: string, unit: string, label: string}>
     */
    public function indentUnitOptions(): array
    {
        $this->loadMissing(['sellOptions.storePackage', 'storePackage']);

        $options = [];

        if ($this->storePackage) {
            $receiveUnit = $this->storePackage->unitLabel();
            $options[] = [
                'value' => 'receive',
                'unit' => $receiveUnit,
                'label' => $this->storePackage->displayLabel(),
                'kind' => 'receive',
                'pieces_per_sell_unit' => 1.0,
            ];
        }

        foreach ($this->sellOptions as $sellOption) {
            if (! $sellOption->storePackage) {
                continue;
            }
            $options[] = [
                'value' => 'usage:'.$sellOption->id,
                'unit' => $sellOption->storePackage->unitLabel(),
                'label' => $sellOption->storePackage->displayLabel(),
                'kind' => 'usage',
                'pieces_per_sell_unit' => max(0.01, (float) $sellOption->pieces_per_sell_unit),
            ];
        }

        if ($options === []) {
            $fallback = trim((string) ($this->unit ?? ''));
            if ($fallback === '' && $this->storePackage) {
                $fallback = $this->storePackage->unitLabel();
            }
            if ($fallback === '') {
                $fallback = (string) config('hotel_stores.po_line_qty_unit', 'Pkts');
            }
            $options[] = [
                'value' => 'default',
                'unit' => $fallback,
                'label' => $fallback,
                'kind' => 'receive',
                'pieces_per_sell_unit' => 1.0,
            ];
        }

        return $options;
    }

    public function defaultIndentUnit(): string
    {
        $options = $this->indentUnitOptions();
        foreach ($options as $option) {
            if (str_starts_with($option['value'], 'usage:')) {
                return $option['unit'];
            }
        }

        return $options[0]['unit'];
    }

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(HotelStoreSubCategory::class, 'hotel_store_sub_category_id');
    }

    public function pmsServiceCategory(): BelongsTo
    {
        return $this->belongsTo(HotelPmsServiceCategory::class, 'hotel_pms_service_category_id');
    }
}
