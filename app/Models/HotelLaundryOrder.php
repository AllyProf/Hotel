<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelLaundryOrder extends Model
{
    public const STATUS_RECEIVED = 'received';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_READY = 'ready';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'hotel_id',
        'hotel_pms_service_id',
        'cm_reservation_id',
        'ticket_no',
        'room_no',
        'guest_name',
        'status',
        'is_express',
        'complimentary_pieces',
        'subtotal',
        'surcharge_amount',
        'total',
        'currency',
        'payment_mode',
        'payment_provider',
        'amount_paid',
        'payment_notes',
        'posted_to_folio',
        'notes',
        'received_at',
        'ready_at',
        'delivered_at',
        'created_by',
        'completed_by',
    ];

    protected function casts(): array
    {
        return [
            'is_express' => 'boolean',
            'posted_to_folio' => 'boolean',
            'subtotal' => 'decimal:2',
            'surcharge_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'received_at' => 'datetime',
            'ready_at' => 'datetime',
            'delivered_at' => 'datetime',
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

    public function lines(): HasMany
    {
        return $this->hasMany(HotelLaundryOrderLine::class)->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function statusLabel(): string
    {
        return config('hotel_pms.laundry.statuses.'.$this->status, ucfirst(str_replace('_', ' ', $this->status)));
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, [self::STATUS_DELIVERED, self::STATUS_CANCELLED], true);
    }

    public function displayCurrency(?Hotel $hotel = null): string
    {
        $this->loadMissing(['service', 'hotel']);
        $hotel ??= $this->hotel;

        if ($this->service) {
            return $this->service->displayCurrency($hotel);
        }

        $code = strtoupper(trim((string) ($this->currency ?? '')));

        return $code !== '' ? $code : strtoupper($hotel?->currency ?? 'TZS');
    }

    public function formattedAmount(float $amount, ?Hotel $hotel = null): string
    {
        return number_format($amount, 2).' '.$this->displayCurrency($hotel);
    }

    public function formattedTotal(?Hotel $hotel = null): string
    {
        return $this->formattedAmount((float) $this->total, $hotel);
    }
}
