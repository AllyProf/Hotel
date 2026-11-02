<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelLostFoundGuestNotification extends Model
{
    protected $fillable = [
        'hotel_id',
        'hotel_lost_found_item_id',
        'cm_reservation_id',
        'email_to',
        'guest_name',
        'sent_by',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(HotelLostFoundItem::class, 'hotel_lost_found_item_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(CmReservation::class, 'cm_reservation_id');
    }
}
