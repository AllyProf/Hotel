<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelMaintenanceOrder extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_WAITING_PARTS = 'waiting_parts';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'hotel_id',
        'hotel_room_unit_id',
        'room_no',
        'title',
        'description',
        'category',
        'status',
        'priority',
        'blocks_room',
        'assigned_to',
        'opened_at',
        'completed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'blocks_room' => 'boolean',
            'opened_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return config('hotel_erp_hk.maintenance.statuses.'.$this->status, ucfirst(str_replace('_', ' ', $this->status)));
    }

    public function categoryLabel(): string
    {
        return config('hotel_erp_hk.maintenance.categories.'.$this->category, ucfirst($this->category));
    }

    public function priorityLabel(): string
    {
        return config('hotel_erp_hk.maintenance.priorities.'.$this->priority, ucfirst($this->priority));
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true);
    }
}
