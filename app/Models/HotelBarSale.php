<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelBarSale extends Model
{
    protected $fillable = [
        'hotel_id',
        'hotel_pms_service_id',
        'cm_reservation_id',
        'sale_no',
        'room_no',
        'guest_name',
        'subtotal',
        'total',
        'currency',
        'payment_mode',
        'payment_provider',
        'amount_paid',
        'payment_notes',
        'posted_to_folio',
        'notes',
        'sold_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'posted_to_folio' => 'boolean',
            'sold_at' => 'datetime',
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

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(CmReservation::class, 'cm_reservation_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<HotelBarSaleLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(HotelBarSaleLine::class)->orderBy('id');
    }

    public function formattedTotal(?Hotel $hotel = null): string
    {
        $hotel ??= $this->hotel;

        return number_format((float) $this->total, 2).' '.strtoupper($this->currency ?: $hotel?->currency ?: 'TZS');
    }
}
