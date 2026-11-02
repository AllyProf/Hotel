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

    public const PMS_RESERVED = 'reserved';

    public const PMS_ASSIGNED = 'assigned';

    public const PMS_CHECKED_IN = 'checked_in';

    public const PMS_CHECKED_OUT = 'checked_out';

    protected $fillable = [
        'hotel_id',
        'hotel_code',
        'booking_id',
        'channel',
        'action',
        'status',
        'pms_status',
        'room_unit_id',
        'checked_in_at',
        'checked_out_at',
        'late_checkout_fee',
        'checkin',
        'checkout',
        'guest_first_name',
        'guest_last_name',
        'amount_after_tax',
        'amount_paid',
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
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'late_checkout_fee' => 'decimal:2',
            'amount_after_tax' => 'decimal:2',
            'amount_paid' => 'decimal:2',
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

    public function roomUnit(): BelongsTo
    {
        return $this->belongsTo(HotelRoomUnit::class, 'room_unit_id');
    }

    public function isCheckedIn(): bool
    {
        return $this->pms_status === self::PMS_CHECKED_IN;
    }

    public function isCheckedOut(): bool
    {
        return $this->pms_status === self::PMS_CHECKED_OUT;
    }

    public function isMultiRoomBooking(): bool
    {
        return $this->roomCount() > 1;
    }

    /** @return list<int> */
    public function assignedRoomUnitIds(): array
    {
        $payload = is_array($this->payload) ? $this->payload : [];
        $ids = $payload['room_unit_ids'] ?? [];

        if (! is_array($ids)) {
            $ids = [];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === [] && $this->room_unit_id) {
            return [(int) $this->room_unit_id];
        }

        return $ids;
    }

    public function assignedRoomCount(): int
    {
        return count($this->assignedRoomUnitIds());
    }

    public function isRoomAssigned(): bool
    {
        return $this->assignedRoomCount() > 0;
    }

    public function isFullyAssigned(): bool
    {
        $required = max(1, $this->roomCount());

        return $this->assignedRoomCount() >= $required;
    }

    public function assignedRoomLabel(): string
    {
        $ids = $this->assignedRoomUnitIds();

        if ($ids === []) {
            return '—';
        }

        $labels = HotelRoomUnit::query()
            ->whereIn('id', $ids)
            ->orderBy('room_number')
            ->orderBy('id')
            ->get()
            ->map(fn (HotelRoomUnit $unit) => $unit->displayLabel())
            ->all();

        return $labels !== [] ? implode(', ', $labels) : '—';
    }

    public function pmsStatusLabel(): string
    {
        return match ($this->pms_status) {
            self::PMS_ASSIGNED => 'Assigned',
            self::PMS_CHECKED_IN => 'Checked in',
            self::PMS_CHECKED_OUT => 'Checked out',
            default => 'Reserved',
        };
    }

    public function pmsStatusBadgeClass(): string
    {
        return match ($this->pms_status) {
            self::PMS_ASSIGNED => 'badge-info',
            self::PMS_CHECKED_IN => 'badge-success',
            self::PMS_CHECKED_OUT => 'badge-secondary',
            default => 'badge-warning',
        };
    }

    public function guestName(): string
    {
        return trim(($this->guest_first_name ?? '').' '.($this->guest_last_name ?? '')) ?: '—';
    }

    public function guestEmail(): ?string
    {
        $payload = is_array($this->payload) ? $this->payload : [];
        $guest = is_array($payload['guest'] ?? null) ? $payload['guest'] : [];

        foreach (['email', 'guestEmail', 'guest_email'] as $key) {
            $value = trim((string) ($guest[$key] ?? $payload[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    public function guestPhone(): ?string
    {
        $payload = is_array($this->payload) ? $this->payload : [];
        $guest = is_array($payload['guest'] ?? null) ? $payload['guest'] : [];

        foreach (['phone', 'mobile', 'guestPhone', 'guest_phone'] as $key) {
            $value = trim((string) ($guest[$key] ?? $payload[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isArrivalToday(): bool
    {
        return ! $this->isCancelled() && (bool) $this->checkin?->isToday();
    }

    public function isCheckoutToday(): bool
    {
        return ! $this->isCancelled() && (bool) $this->checkout?->isToday();
    }

    public function rowHighlightClass(): string
    {
        if ($this->isCancelled()) {
            return 'is-cancelled';
        }

        if ($this->isCheckoutToday()) {
            return 'is-checkout-today';
        }

        if ($this->isArrivalToday()) {
            return 'is-arrival-today';
        }

        return '';
    }

    public function canManageStay(): bool
    {
        return ! $this->isCancelled() && ! $this->isCheckedOut();
    }

    /** @return array<int, array<string, mixed>> */
    public function invoiceSendHistory(): array
    {
        $payload = is_array($this->payload) ? $this->payload : [];
        $history = $payload['invoice_send_history'] ?? [];

        return is_array($history) ? $history : [];
    }

    /** @return array<int, array<string, mixed>> */
    public function stayChangeHistory(): array
    {
        $payload = is_array($this->payload) ? $this->payload : [];
        $history = $payload['stay_change_history'] ?? [];

        return is_array($history) ? $history : [];
    }

    public function invoiceSentAtLabel(): ?string
    {
        $payload = is_array($this->payload) ? $this->payload : [];
        $sentAt = $payload['invoice_sent_at'] ?? null;

        return $sentAt ? \Carbon\Carbon::parse($sentAt)->format('d/m/Y H:i') : null;
    }

    public function invoiceSentToLabel(): ?string
    {
        $payload = is_array($this->payload) ? $this->payload : [];
        $email = trim((string) ($payload['invoice_send_to'] ?? ''));

        return $email !== '' ? $email : null;
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

    public function nightsRemaining(?\Carbon\Carbon $asOf = null): ?int
    {
        if ($this->checkout === null) {
            return null;
        }

        $asOf = ($asOf ?? now())->copy()->startOfDay();
        $checkout = $this->checkout->copy()->startOfDay();

        if ($checkout->lte($asOf)) {
            return 0;
        }

        return (int) $asOf->diffInDays($checkout);
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

    public function isComplimentary(): bool
    {
        $payload = is_array($this->payload) ? $this->payload : [];

        if (($payload['booking_type'] ?? '') === 'complimentary') {
            return true;
        }

        return strcasecmp($this->categoryLabel(), 'Complimentary') === 0;
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

    public function isPrepaid(): bool
    {
        $label = strtolower($this->paymentLabel());

        return str_contains($label, 'prepaid') || str_contains($label, 'paid');
    }

    public function totalDue(?float $pendingLateFee = null): float
    {
        $lateFee = max(0, (float) ($pendingLateFee ?? 0));
        $roomDue = $this->isPrepaid() ? 0.0 : (float) ($this->amount_after_tax ?? 0);

        return round($roomDue + $lateFee, 2);
    }

    public function effectiveAmountPaid(): float
    {
        return round(max(0, (float) ($this->amount_paid ?? 0)), 2);
    }

    public function balanceDue(?float $pendingLateFee = null): float
    {
        return round(max(0, $this->totalDue($pendingLateFee) - $this->effectiveAmountPaid()), 2);
    }

    public function isPaymentCleared(?float $additionalLateFee = null): bool
    {
        return $this->balanceDue($additionalLateFee) <= 0.009;
    }

    public function balanceLabel(?float $additionalLateFee = null): string
    {
        $balance = $this->balanceDue($additionalLateFee);

        if ($balance <= 0.009) {
            return 'Paid';
        }

        return number_format($balance, 2).' '.($this->currency ?? '');
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

        $payload = is_array($this->payload) ? $this->payload : [];
        $guest = is_array($payload['guest'] ?? null) ? $payload['guest'] : [];

        return [
            'summary' => array_filter([
                'Booking ID' => $this->booking_id,
                'Status' => $this->statusLabel(),
                'PMS Status' => $this->pmsStatusLabel(),
                'Channel' => $this->channel,
                'Source' => $this->sourceDisplayLabel() !== '—' ? $this->sourceDisplayLabel() : null,
                'Guest' => $this->guestName(),
                'Email' => $guest['email'] ?? null,
                'Phone' => $guest['phone'] ?? $guest['mobile'] ?? null,
                'Payment' => $this->paymentLabel(),
                'Booked on' => $this->bookedOnLabel(),
                'Booked by' => $payload['booked_by'] ?? null,
                'Check-in' => $this->checkinLabel(),
                'Check-out' => $this->checkoutLabel(),
                'Assigned room' => $this->assignedRoomLabel() !== '—' ? $this->assignedRoomLabel() : null,
                'Room type' => $this->roomLabel(),
                'Room nights' => $this->roomNightCount(),
                'Rooms count' => $this->roomCount() ?: null,
                'Guests' => $this->guestCount() ?: null,
                'Meal plan' => $this->mealPlanLabel() !== '—' ? $this->mealPlanLabel() : null,
                'Category' => $this->categoryLabel() !== '—' ? $this->categoryLabel() : null,
                'Segment' => $payload['segment'] ?? null,
                'Bill to' => $payload['bill_to'] ?? null,
                'Price' => $this->priceLabel() !== '—' ? $this->priceLabel() : null,
                'Amount paid' => $this->effectiveAmountPaid() > 0 ? number_format($this->effectiveAmountPaid(), 2).' '.($this->currency ?? '') : null,
                'Balance due' => $this->balanceDue() > 0.009 ? $this->balanceLabel() : null,
                'Invoice sent to' => $this->invoiceSentToLabel(),
                'Invoice sent at' => $this->invoiceSentAtLabel(),
                'Last stay change' => ! empty($payload['last_stay_change_at'])
                    ? \Carbon\Carbon::parse($payload['last_stay_change_at'])->format('d/m/Y H:i')
                        .(! empty($payload['last_stay_change_reason']) ? ' — '.$payload['last_stay_change_reason'] : '')
                    : null,
                'Hotel code' => $this->hotel_code,
            ], fn ($value) => $value !== null && $value !== '' && $value !== '—'),
            'raw' => $raw,
        ];
    }
}
