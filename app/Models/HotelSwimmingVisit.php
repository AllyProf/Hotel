<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelSwimmingVisit extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_IN_POOL = 'in_pool';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'hotel_id',
        'hotel_pms_service_id',
        'cm_reservation_id',
        'hotel_pms_service_category_id',
        'hotel_pms_swimming_rate_id',
        'visit_no',
        'room_no',
        'guest_name',
        'guest_phone',
        'guest_email',
        'scheduled_date',
        'persons',
        'pricing_unit',
        'audience',
        'billing_quantity',
        'adults',
        'children',
        'rate_label',
        'unit_price',
        'subtotal',
        'discount_type',
        'discount_value',
        'discount_amount',
        'total',
        'is_complimentary',
        'currency',
        'payment_mode',
        'payment_provider',
        'amount_paid',
        'payment_notes',
        'status',
        'posted_to_folio',
        'notes',
        'entered_at',
        'exited_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'persons' => 'integer',
            'adults' => 'integer',
            'children' => 'integer',
            'billing_quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'is_complimentary' => 'boolean',
            'scheduled_date' => 'date',
            'posted_to_folio' => 'boolean',
            'entered_at' => 'datetime',
            'exited_at' => 'datetime',
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

    public function swimmingRate(): BelongsTo
    {
        return $this->belongsTo(HotelPmsSwimmingRate::class, 'hotel_pms_swimming_rate_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        $labels = config('hotel_pms.swimming.statuses', []);

        return $labels[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    public function formattedTotal(?Hotel $hotel = null): string
    {
        if ($this->is_complimentary || (float) $this->total <= 0.009) {
            return 'Complimentary';
        }

        $hotel ??= $this->hotel;
        $currency = strtoupper($this->currency ?: $this->service?->displayCurrency($hotel) ?: 'TZS');
        $decimals = in_array($currency, ['TZS', 'UGX', 'RWF', 'BIF'], true) ? 0 : 2;

        return number_format((float) $this->total, $decimals).' '.$currency;
    }
}
