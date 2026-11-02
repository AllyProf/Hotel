<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelHousekeepingTask extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'hotel_id',
        'hotel_room_unit_id',
        'room_no',
        'room_type_name',
        'task_type',
        'status',
        'priority',
        'assigned_to',
        'notes',
        'due_at',
        'started_at',
        'completed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'started_at' => 'datetime',
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
        return config('hotel_erp_hk.housekeeping.statuses.'.$this->status, ucfirst(str_replace('_', ' ', $this->status)));
    }

    public function taskTypeLabel(): string
    {
        return config('hotel_erp_hk.housekeeping.task_types.'.$this->task_type, ucfirst(str_replace('_', ' ', $this->task_type)));
    }

    public function priorityLabel(): string
    {
        return config('hotel_erp_hk.housekeeping.priorities.'.$this->priority, ucfirst($this->priority));
    }

    public function isOpen(): bool
    {
        return $this->status !== self::STATUS_COMPLETED;
    }
}
