<?php

namespace App\Models;

use App\Services\OtaLogoService;
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
            $mealPlan = trim((string) ($line['mealPlan'] ?? $line['meal_plan'] ?? ''));

            if ($mealPlan !== '') {
                $plans[] = strtoupper($mealPlan);

                continue;
            }

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

            if ($payloadPlan) {
                return strtoupper((string) $payloadPlan);
            }

            $resolved = $this->resolveMealPlanFromRatePlans();

            return $resolved !== '' ? $resolved : '—';
        }

        return implode(', ', $plans);
    }

    private function resolveMealPlanFromRatePlans(): string
    {
        if (! $this->hotel_id) {
            return '';
        }

        static $cache = [];
        $plans = [];

        foreach ($this->roomLines() as $line) {
            $planCode = trim((string) ($line['rateplanCode'] ?? $line['rateplan_code'] ?? $line['ratePlanCode'] ?? ''));

            if ($planCode === '') {
                continue;
            }

            $roomCode = trim((string) ($line['roomCode'] ?? $line['room_code'] ?? ''));
            $cacheKey = $this->hotel_id.':'.$roomCode.':'.$planCode;

            if (! array_key_exists($cacheKey, $cache)) {
                $query = HotelRatePlan::query()
                    ->where('hotel_id', $this->hotel_id)
                    ->where('code', $planCode);

                if ($roomCode !== '') {
                    $query->whereHas('room', function ($roomQuery) use ($roomCode) {
                        $roomQuery->where('name', $roomCode)->orWhere('display_name', $roomCode);
                    });
                }

                $cache[$cacheKey] = strtoupper((string) ($query->value('meal_plan') ?? ''));
            }

            if ($cache[$cacheKey] !== '') {
                $plans[] = $cache[$cacheKey];
            }
        }

        $plans = array_values(array_unique($plans));

        return $plans !== [] ? implode(', ', $plans) : '';
    }

    public function roomNightCount(): ?int
    {
        if ($this->checkin === null || $this->checkout === null) {
            return null;
        }

        $nights = $this->checkin->diffInDays($this->checkout);

        return $nights > 0 ? $nights : null;
    }

    public function guestCount(): int
    {
        $lines = $this->roomLines();

        if ($lines === []) {
            return 1;
        }

        $total = 0;

        foreach ($lines as $line) {
            $occupancy = is_array($line['occupancy'] ?? null) ? $line['occupancy'] : [];
            $total += (int) ($occupancy['adults'] ?? 1);
            $total += (int) ($occupancy['children'] ?? 0);
        }

        return max(1, $total);
    }

    public function categoryLabel(): string
    {
        $payload = is_array($this->payload) ? $this->payload : [];

        foreach (['category', 'bookingCategory', 'booking_category', 'segment'] as $key) {
            $value = trim((string) ($payload[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '—';
    }

    public function paymentLinkUrl(): ?string
    {
        $payload = is_array($this->payload) ? $this->payload : [];

        foreach (['paymentLink', 'payment_link', 'paymentUrl', 'payment_url'] as $key) {
            $value = trim((string) ($payload[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    public function paymentLinkLabel(): string
    {
        return $this->paymentLinkUrl() ? 'Link' : '—';
    }

    public function sourceLabel(): string
    {
        return $this->sourceDisplayLabel();
    }

    public function sourceDisplayLabel(): string
    {
        return self::sourceMeta($this->channel)['label'];
    }

    public function sourceBadgeClass(): string
    {
        return self::sourceMeta($this->channel)['class'];
    }

    /** @return array{label: string, class: string} */
    private static function sourceMeta(?string $channel): array
    {
        static $cache = [];

        $channel = trim((string) $channel);

        if ($channel === '') {
            return ['label' => '—', 'class' => 'rd-source-neutral'];
        }

        if (! array_key_exists($channel, $cache)) {
            $presentation = app(OtaLogoService::class)->presentationForChannel($channel);

            $cache[$channel] = [
                'label' => (string) ($presentation['name'] ?? $channel),
                'class' => self::sourceClassForSlug($presentation['slug'] ?? null),
            ];
        }

        return $cache[$channel];
    }

    private static function sourceClassForSlug(?string $slug): string
    {
        return match ($slug) {
            'direct' => 'rd-source-direct',
            'booking-com' => 'rd-source-booking',
            'expedia' => 'rd-source-expedia',
            'agoda' => 'rd-source-agoda',
            'airbnb' => 'rd-source-airbnb',
            'goibibo' => 'rd-source-goibibo',
            'makemytrip' => 'rd-source-mmt',
            'trip-com' => 'rd-source-trip',
            'cleartrip' => 'rd-source-cleartrip',
            'hostelworld' => 'rd-source-hostelworld',
            'hotelbeds' => 'rd-source-hotelbeds',
            'traveloka' => 'rd-source-traveloka',
            'yatra' => 'rd-source-yatra',
            default => 'rd-source-ota',
        };
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

    public function commissionAmount(): ?float
    {
        $payload = is_array($this->payload) ? $this->payload : [];
        $amount = is_array($payload['amount'] ?? null) ? $payload['amount'] : [];

        foreach (['commission', 'otaCommission', 'ota_commission'] as $key) {
            if (isset($amount[$key]) && is_numeric($amount[$key])) {
                return (float) $amount[$key];
            }
        }

        return null;
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

    public function isRecentlyReceived(int $minutes = 30): bool
    {
        return $this->created_at !== null && $this->created_at->greaterThan(now()->subMinutes($minutes));
    }

    public function paymentBadgeClass(): string
    {
        $label = strtolower($this->paymentLabel());

        if ($label === '—') {
            return 'rd-pay-neutral';
        }

        if (str_contains($label, 'prepaid') || str_contains($label, 'paid')) {
            return 'rd-pay-prepaid';
        }

        if (str_contains($label, 'hotel')) {
            return 'rd-pay-pah';
        }

        return 'rd-pay-neutral';
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

    /** @return array{summary: array<string, mixed>, raw: array<string, mixed>} */
    public function detailForView(): array
    {
        $raw = is_array($this->payload) ? $this->payload : [];

        if ($raw === []) {
            $raw = [
                'action' => $this->action,
                'hotelCode' => $this->hotel_code,
                'channel' => $this->channel,
                'bookingId' => $this->booking_id,
                'checkin' => $this->checkin?->format('Y-m-d'),
                'checkout' => $this->checkout?->format('Y-m-d'),
                'guest' => array_filter([
                    'firstName' => $this->guest_first_name,
                    'lastName' => $this->guest_last_name,
                ]),
                'amount' => array_filter([
                    'amountAfterTax' => $this->amount_after_tax,
                    'amountBeforeTax' => $this->amount_before_tax,
                    'tax' => $this->tax,
                    'currency' => $this->currency,
                ]),
                'rooms' => $this->rooms,
            ];
        }

        return [
            'summary' => array_filter([
                'Booking ID' => $this->booking_id,
                'Status' => $this->statusLabel(),
                'Channel' => $this->channel,
                'Guest' => $this->guestName(),
                'Payment' => $this->paymentLabel(),
                'Booked on' => $this->bookedOnLabel(),
                'Check-in' => $this->checkinLabel(),
                'Check-out' => $this->checkoutLabel(),
                'Room' => $this->roomLabel(),
                'Room nights' => $this->roomNightCount(),
                'Rooms count' => $this->roomCount() ?: null,
                'Meal plan' => $this->mealPlanLabel() !== '—' ? $this->mealPlanLabel() : null,
                'Price' => $this->priceLabel() !== '—' ? $this->priceLabel() : null,
                'Hotel code' => $this->hotel_code,
            ], fn ($value) => $value !== null && $value !== '' && $value !== '—'),
            'raw' => $raw,
        ];
    }
}
