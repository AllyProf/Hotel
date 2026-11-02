<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelStoreConsumptionItem extends Model
{
    protected $fillable = [
        'hotel_store_consumption_id',
        'hotel_store_master_item_id',
        'hotel_room_unit_id',
        'room_no',
        'room_distribution',
        'name',
        'quantity',
        'unit',
        'comments',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'room_distribution' => 'array',
        ];
    }

    public function consumption(): BelongsTo
    {
        return $this->belongsTo(HotelStoreConsumption::class, 'hotel_store_consumption_id');
    }

    public function masterItem(): BelongsTo
    {
        return $this->belongsTo(HotelStoreMasterItem::class, 'hotel_store_master_item_id');
    }

    public function roomUnit(): BelongsTo
    {
        return $this->belongsTo(HotelRoomUnit::class, 'hotel_room_unit_id');
    }

    public function displayUnit(): ?string
    {
        if ($this->hotel_store_master_item_id) {
            $this->loadMissing(['masterItem.sellOptions.storePackage', 'masterItem.storePackage']);
            if ($this->masterItem) {
                return $this->masterItem->consumptionUnitLabel();
            }
        }

        $unit = trim((string) ($this->unit ?? ''));

        return $unit !== '' ? $unit : null;
    }

    public function roomLabel(): ?string
    {
        $labels = $this->distributedRoomLabels();

        return $labels !== [] ? implode(', ', $labels) : null;
    }

    /** @return list<string> */
    public function distributedRoomLabels(): array
    {
        $dist = $this->room_distribution;
        if (is_array($dist) && ! empty($dist['all'])) {
            return ['All rooms'];
        }

        if (is_array($dist) && ! empty($dist['unit_ids']) && is_array($dist['unit_ids'])) {
            $ids = array_values(array_filter(array_map('intval', $dist['unit_ids'])));
            if ($ids === []) {
                return [];
            }

            $units = HotelRoomUnit::query()->whereIn('id', $ids)->with('roomType')->get();

            return $units->sortBy(fn (HotelRoomUnit $u) => $u->room_number)
                ->map(fn (HotelRoomUnit $u) => $u->consumptionLabel())
                ->values()
                ->all();
        }

        if ($this->relationLoaded('roomUnit') && $this->roomUnit) {
            return [$this->roomUnit->consumptionLabel()];
        }

        if ($this->hotel_room_unit_id) {
            $unit = HotelRoomUnit::query()->with('roomType')->find($this->hotel_room_unit_id);
            if ($unit) {
                return [$unit->consumptionLabel()];
            }
        }

        $roomNo = trim((string) ($this->room_no ?? ''));

        return $roomNo !== '' ? [$roomNo] : [];
    }
}
