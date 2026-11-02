<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelDepartment extends Model
{
    protected $fillable = [
        'hotel_id',
        'name',
        'contact_person',
        'amount_issued',
        'is_active',
        'show_in_pos',
        'hotel_pms_service_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_in_pos' => 'boolean',
            'amount_issued' => 'decimal:2',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(User::class, 'hotel_department_id');
    }

    public function posCatalogService(): BelongsTo
    {
        return $this->belongsTo(HotelPmsService::class, 'hotel_pms_service_id');
    }
}
