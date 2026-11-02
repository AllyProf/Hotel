<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelRoomUnit extends Model
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_DIRTY = 'dirty';

    public const STATUS_CLEANED = 'cleaned';

    public const STATUS_MAINTENANCE = 'maintenance';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'hotel_id',
        'hotel_room_id',
        'branch_id',
        'room_number',
        'label',
        'status',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(HotelRoom::class, 'hotel_room_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function displayLabel(): string
    {
        return $this->label ?: ($this->room_number ?: '—');
    }

    /** e.g. 100-single for HK consumption room picker */
    public function consumptionLabel(): string
    {
        $num = trim((string) ($this->room_number ?? ''));
        if ($num === '') {
            return $this->displayLabel();
        }

        $typeName = $this->roomType?->display_name ?: $this->roomType?->name ?: 'room';
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $typeName)) ?: 'room';

        return $num.'-'.$slug;
    }
}
