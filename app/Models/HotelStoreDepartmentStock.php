<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelStoreDepartmentStock extends Model
{
    protected $fillable = [
        'hotel_id',
        'hotel_department_id',
        'hotel_store_master_item_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HotelDepartment::class, 'hotel_department_id');
    }

    public function masterItem(): BelongsTo
    {
        return $this->belongsTo(HotelStoreMasterItem::class, 'hotel_store_master_item_id');
    }
}
