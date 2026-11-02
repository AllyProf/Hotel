<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelPmsServicePackage extends Model
{
    protected $fillable = [
        'hotel_id',
        'hotel_pms_service_id',
        'template_key',
        'name',
        'abbreviation',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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

    public function isCustom(): bool
    {
        return $this->template_key === null || $this->template_key === '';
    }

    /** @return array<string, mixed> */
    public function toCardRow(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'abbreviation' => $this->abbreviation,
            'template_key' => $this->template_key,
            'is_custom' => $this->isCustom(),
            'is_active' => $this->is_active,
        ];
    }
}
