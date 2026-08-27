<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmReservation extends Model
{
    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_MODIFIED = 'modified';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'hotel_id',
        'hotel_code',
        'booking_id',
        'channel',
        'action',
        'status',
        'checkin',
        'checkout',
        'guest_first_name',
        'guest_last_name',
        'amount_after_tax',
        'amount_before_tax',
        'tax',
        'currency',
        'rooms',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'checkin' => 'date',
            'checkout' => 'date',
            'amount_after_tax' => 'decimal:2',
            'amount_before_tax' => 'decimal:2',
            'tax' => 'decimal:2',
            'rooms' => 'array',
            'payload' => 'array',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function guestName(): string
    {
        return trim(($this->guest_first_name ?? '').' '.($this->guest_last_name ?? '')) ?: '—';
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /** @return list<array<string, mixed>> */
    public function roomLines(): array
    {
        $rooms = $this->rooms;

        if (! is_array($rooms)) {
            return [];
        }

        if ($rooms === []) {
            return [];
        }

        if (array_is_list($rooms)) {
            return array_values(array_filter($rooms, fn ($row) => is_array($row)));
        }

        return [$rooms];
    }

    public function roomCount(): int
    {
        $total = 0;

        foreach ($this->roomLines() as $line) {
            $qty = (int) ($line['numberOfRooms'] ?? $line['roomCount'] ?? $line['quantity'] ?? 1);

            $total += max(1, $qty);
        }

        return $total > 0 ? $total : ($this->roomLines() !== [] ? count($this->roomLines()) : 0);
    }

    public function roomLabel(): string
    {
        $labels = [];

        foreach ($this->roomLines() as $line) {
            $name = trim((string) ($line['roomName'] ?? $line['room_name'] ?? ''));
            $code = trim((string) ($line['roomCode'] ?? $line['room_code'] ?? ''));

            $labels[] = $name !== '' ? $name : ($code !== '' ? $code : '—');
        }

        $labels = array_values(array_filter(array_unique($labels), fn ($v) => $v !== '—'));

        return $labels !== [] ? implode(', ', $labels) : '—';
    }

    public function mealPlanLabel(): string
    {
        $plans = [];

        foreach ($this->roomLines() as $line) {
            $code = strtolower((string) ($line['rateplanCode'] ?? $line['rateplan_code'] ?? $line['ratePlanCode'] ?? ''));

            if ($code === '') {
                continue;
            }

            if (preg_match('/-([a-z]+)$/i', $code, $matches)) {
                $plans[] = strtoupper($matches[1]);
            }
        }

        $plans = array_values(array_unique($plans));

        if ($plans === []) {
            $payloadPlan = $this->payload['mealPlan'] ?? $this->payload['meal_plan'] ?? null;

            return $payloadPlan ? strtoupper((string) $payloadPlan) : '—';
        }

        return implode(', ', $plans);
    }

    public function roomNightCount(): ?int
    {
        if ($this->checkin === null || $this->checkout === null) {
            return null;
        }

        $nights = $this->checkin->diffInDays($this->checkout);

        return $nights > 0 ? $nights : null;
    }

    public function paymentLabel(): string
    {
        $payload = is_array($this->payload) ? $this->payload : [];

        if (array_key_exists('pah', $payload)) {
            return filter_var($payload['pah'], FILTER_VALIDATE_BOOLEAN) ? 'Pay at Hotel' : 'Prepaid';
        }

        $payment = strtolower((string) ($payload['paymentType'] ?? $payload['payment_type'] ?? $payload['payment'] ?? ''));

        if ($payment === '') {
            return '—';
        }

        if (str_contains($payment, 'hotel') || $payment === 'pah') {
            return 'Pay at Hotel';
        }

        if (str_contains($payment, 'prepaid') || str_contains($payment, 'online') || str_contains($payment, 'paid')) {
            return 'Prepaid';
        }

        return ucfirst($payment);
    }

    public function priceLabel(): string
    {
        if ($this->amount_after_tax === null) {
            return '—';
        }

        return number_format((float) $this->amount_after_tax, 0).' '.($this->currency ?? '');
    }

    public function bookedOnLabel(): string
    {
        return $this->created_at?->format('d/m/Y H:i') ?? '—';
    }

    public function checkinLabel(): string
    {
        return $this->checkin?->format('d/m/Y') ?? '—';
    }

    public function checkoutLabel(): string
    {
        return $this->checkout?->format('d/m/Y') ?? '—';
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_CANCELLED => 'badge-secondary',
            self::STATUS_MODIFIED => 'badge-warning',
            default => 'badge-success',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_MODIFIED => 'Modified',
            default => 'Confirmed',
        };
    }
}
