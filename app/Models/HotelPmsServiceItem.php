<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelPmsServiceItem extends Model
{
    protected $fillable = [
        'hotel_id',
        'hotel_pms_service_id',
        'hotel_pms_service_category_id',
        'item_name',
        'brand',
        'description',
        'hotel_pms_service_package_id',
        'pieces_per_receive_unit',
        'selling_hotel_pms_service_package_id',
        'unit_price',
        'expected_unit_price',
        'currency',
        'is_active',
        'stock_on_hand',
        'store_stock_on_hand',
        'reorder_level',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'expected_unit_price' => 'decimal:2',
            'is_active' => 'boolean',
            'stock_on_hand' => 'decimal:2',
            'store_stock_on_hand' => 'decimal:2',
            'reorder_level' => 'decimal:2',
            'pieces_per_receive_unit' => 'decimal:2',
        ];
    }

    public function isLowStock(): bool
    {
        if ($this->reorder_level === null) {
            return false;
        }

        $onHand = $this->stock_on_hand !== null ? (float) $this->stock_on_hand : 0.0;

        return $onHand <= (float) $this->reorder_level;
    }

    public function barPiecesAvailable(): float
    {
        $receivePieces = $this->pieces_per_receive_unit !== null ? (float) $this->pieces_per_receive_unit : 1.0;
        if ($receivePieces <= 0) {
            $receivePieces = 1.0;
        }
        $stockUnits = $this->stock_on_hand !== null ? (float) $this->stock_on_hand : 0.0;

        return round($stockUnits * $receivePieces, 4);
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

    public function package(): BelongsTo
    {
        return $this->belongsTo(HotelPmsServicePackage::class, 'hotel_pms_service_package_id');
    }

    public function sellingPackage(): BelongsTo
    {
        return $this->belongsTo(HotelPmsServicePackage::class, 'selling_hotel_pms_service_package_id');
    }

    /** @return HasMany<HotelPmsServiceItemSellOption, $this> */
    public function sellOptions(): HasMany
    {
        return $this->hasMany(HotelPmsServiceItemSellOption::class)->orderBy('sort_order')->orderBy('id');
    }
}
