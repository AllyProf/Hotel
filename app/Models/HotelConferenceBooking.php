<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelConferenceBooking extends Model
{
    public const STATUS_INQUIRY = 'inquiry';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'hotel_id',
        'hotel_pms_service_id',
        'cm_reservation_id',
        'hotel_pms_service_category_id',
        'hotel_pms_conference_rate_id',
        'booking_no',
        'event_name',
        'organizer_name',
        'company_name',
        'contact_phone',
        'contact_email',
        'room_no',
        'guest_name',
        'starts_at',
        'ends_at',
        'delegates',
        'billing_quantity',
        'rate_label',
        'pricing_unit',
        'unit_price',
        'subtotal',
        'discount_type',
        'discount_value',
        'discount_amount',
        'total',
        'currency',
        'payment_mode',
        'payment_provider',
        'amount_paid',
        'payment_notes',
        'status',
        'posted_to_folio',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'delegates' => 'integer',
            'billing_quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'posted_to_folio' => 'boolean',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(HotelPmsServiceCategory::class, 'hotel_pms_service_category_id');
    }

    public function conferenceRate(): BelongsTo
    {
        return $this->belongsTo(HotelPmsConferenceRate::class, 'hotel_pms_conference_rate_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return config('hotel_pms.conference.statuses.'.$this->status, ucfirst(str_replace('_', ' ', $this->status)));
    }

    public function formattedTotal(?Hotel $hotel = null): string
    {
        if ((float) $this->total <= 0.009) {
            return 'Complimentary';
        }

        $hotel ??= $this->hotel;
        $currency = strtoupper($this->currency ?: 'TZS');
        $decimals = in_array($currency, ['TZS'], true) ? 0 : 2;

        return number_format((float) $this->total, $decimals).' '.$currency;
    }
}
