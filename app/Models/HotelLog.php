<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelLog extends Model
{
    public const CATEGORY_GENERAL = 'general';

    public const CATEGORY_PAYMENTS = 'payments';

    public const CATEGORY_OUT_OF_ORDER = 'out_of_order';

    public const CATEGORY_COMPLIMENTARY = 'complimentary';

    protected $fillable = [
        'hotel_id',
        'action_type',
        'category',
        'booking_id',
        'guest_name',
        'folio_no',
        'room_no',
        'hotel_room_id',
        'details',
        'changed_by',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'logged_at' => 'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(HotelRoom::class, 'hotel_room_id');
    }

    public function dateLabel(): string
    {
        return $this->logged_at?->format('j, M, Y g:i A') ?? '—';
    }
}
