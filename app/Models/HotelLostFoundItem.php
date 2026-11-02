<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelLostFoundItem extends Model
{
    public const TYPE_LOST = 'lost';

    public const TYPE_FOUND = 'found';

    public const STATUS_STORED = 'stored';

    public const STATUS_CLAIMED = 'claimed';

    public const STATUS_DISPOSED = 'disposed';

    protected $fillable = [
        'hotel_id',
        'reference_no',
        'entry_type',
        'item_name',
        'category',
        'hotel_room_unit_id',
        'room_no',
        'location',
        'storage_location',
        'guest_name',
        'guest_phone',
        'guest_email',
        'description',
        'photo_path',
        'status',
        'claimed_by_name',
        'closure_notes',
        'logged_at',
        'claimed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'logged_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomUnit(): BelongsTo
    {
        return $this->belongsTo(HotelRoomUnit::class, 'hotel_room_unit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function entryTypeLabel(): string
    {
        return config('hotel_erp_hk.lost_found.entry_types.'.$this->entry_type, ucfirst($this->entry_type));
    }

    public function categoryLabel(): string
    {
        return config('hotel_erp_hk.lost_found.categories.'.$this->category, ucfirst($this->category));
    }

    public function statusLabel(): string
    {
        return config('hotel_erp_hk.lost_found.statuses.'.$this->status, ucfirst($this->status));
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_STORED;
    }

    public function photoUrl(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return asset('storage/'.$this->photo_path);
    }
}
