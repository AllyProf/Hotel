<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelBarPurchaseListLine extends Model
{
    protected $fillable = [
        'hotel_bar_purchase_list_id',
        'item_name',
        'quantity',
        'unit',
        'line_notes',
        'unit_cost',
        'line_budget',
        'quantity_purchased',
        'purchase_unit_price',
        'selling_unit_price',
        'market_line_notes',
        'market_item_found',
        'purchase_line_total',
        'expiry_date',
        'receiving_package_id',
        'selling_package_id',
        'market_sell_option_prices',
        'market_stock_destination',
        'sort_order',
    ];

    public function marketStockDestinationLabel(): ?string
    {
        if (! $this->market_stock_destination) {
            return null;
        }

        return config('hotel_bar.market_stock_destinations.'.$this->market_stock_destination, $this->market_stock_destination);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'line_budget' => 'decimal:2',
            'quantity_purchased' => 'decimal:2',
            'purchase_unit_price' => 'decimal:2',
            'selling_unit_price' => 'decimal:2',
            'purchase_line_total' => 'decimal:2',
            'market_item_found' => 'boolean',
            'expiry_date' => 'date',
            'market_sell_option_prices' => 'array',
        ];
    }

    public function purchaseList(): BelongsTo
    {
        return $this->belongsTo(HotelBarPurchaseList::class, 'hotel_bar_purchase_list_id');
    }

    public function receivingPackage(): BelongsTo
    {
        return $this->belongsTo(HotelPmsServicePackage::class, 'receiving_package_id');
    }

    public function sellingPackage(): BelongsTo
    {
        return $this->belongsTo(HotelPmsServicePackage::class, 'selling_package_id');
    }

    /** Unit shown on purchase requests (department-entered unit, else receive package). */
    public function requestedUnitLabel(): string
    {
        $unit = trim((string) ($this->unit ?? ''));
        if ($unit !== '') {
            return $unit;
        }

        $label = $this->marketReceivingUnitLabel();

        return $label !== '—' ? $label : '';
    }

    public function marketReceivingUnitLabel(): string
    {
        $pkg = $this->relationLoaded('receivingPackage') ? $this->receivingPackage : null;
        if ($pkg) {
            $name = trim((string) ($pkg->name ?? ''));
            $abbr = trim((string) ($pkg->abbreviation ?? ''));

            return $abbr !== '' ? $abbr : ($name !== '' ? $name : (string) ($this->unit ?: '—'));
        }

        $unit = trim((string) ($this->unit ?? ''));

        return $unit !== '' ? $unit : '—';
    }
}
