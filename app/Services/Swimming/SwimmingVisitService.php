<?php

namespace App\Services\Swimming;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelPmsService;
use App\Models\HotelPmsSwimmingRate;
use App\Models\HotelSwimmingVisit;
use App\Models\User;
use App\Services\FrontDeskPaymentService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class SwimmingVisitService
{
    public function __construct(
        private SwimmingRateDataService $rates,
        private FrontDeskPaymentService $deskPayments,
    ) {}

    /** @return array<string, mixed> */
    public function uiConfig(): array
    {
        return config('hotel_pms.swimming', []);
    }

    public function swimmingServiceForHotel(Hotel $hotel): ?HotelPmsService
    {
        return $hotel->pmsServices()
            ->where('template_key', 'swimming')
            ->where('is_active', true)
            ->first();
    }

    public function nextVisitNo(Hotel $hotel): string
    {
        $year = now()->format('Y');
        $prefix = 'SWIM-'.$year.'-';
        $last = HotelSwimmingVisit::query()
            ->where('hotel_id', $hotel->id)
            ->where('visit_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('visit_no');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    /** @return Builder<HotelSwimmingVisit> */
    public function visitsQuery(Hotel $hotel, ?string $tab = null): Builder
    {
        $query = HotelSwimmingVisit::query()
            ->where('hotel_id', $hotel->id)
            ->with(['category', 'swimmingRate', 'reservation.roomUnit', 'service', 'creator'])
            ->orderByDesc('id');

        if ($tab === 'scheduled') {
            $query->where('status', HotelSwimmingVisit::STATUS_SCHEDULED);
        } elseif ($tab === 'in_pool') {
            $query->where('status', HotelSwimmingVisit::STATUS_IN_POOL);
        } elseif ($tab === 'completed') {
            $query->where('status', HotelSwimmingVisit::STATUS_COMPLETED);
        } elseif ($tab === 'today') {
            $today = Carbon::today();
            $query->where(function (Builder $q) use ($today) {
                $q->whereDate('scheduled_date', $today)
                    ->orWhereDate('entered_at', $today);
            });
        }

        return $query;
    }

    /** @return Collection<int, CmReservation> */
    public function inHouseReservations(Hotel $hotel): Collection
    {
        return CmReservation::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', '!=', CmReservation::STATUS_CANCELLED)
            ->where('pms_status', CmReservation::PMS_CHECKED_IN)
            ->with(['roomUnit'])
            ->orderBy('room_unit_id')
            ->get();
    }

    /** @param  array<string, mixed>  $data */
    public function createVisit(
        Hotel $hotel,
        HotelPmsService $service,
        array $data,
        ?User $user = null,
    ): HotelSwimmingVisit {
        $settings = $service->swimmingSettings();
        $rateId = (int) ($data['hotel_pms_swimming_rate_id'] ?? 0);
        $rate = $this->rates->findRateForService($service, $rateId);

        if (! $rate) {
            throw new InvalidArgumentException('Select a valid pool rate.');
        }

        $adults = max(0, (int) ($data['adults'] ?? 0));
        $children = max(0, (int) ($data['children'] ?? 0));
        $billingQty = max(0, (float) ($data['billing_quantity'] ?? 1));

        if ($rate->pricing_unit === 'per_person' && ($adults + $children) < 1) {
            throw new InvalidArgumentException('Enter at least one adult or child.');
        }

        $maxPersons = (int) ($settings['max_persons_per_entry'] ?? 99);
        if ($rate->pricing_unit === 'per_person' && ($adults + $children) > $maxPersons) {
            throw new InvalidArgumentException("Maximum {$maxPersons} persons allowed per entry.");
        }

        $reservation = null;
        if (! empty($data['cm_reservation_id'])) {
            $reservation = CmReservation::query()
                ->where('hotel_id', $hotel->id)
                ->whereKey((int) $data['cm_reservation_id'])
                ->first();
        }

        $guestName = trim((string) ($data['guest_name'] ?? ''));
        if ($reservation) {
            $guestName = $reservation->guestName();
        }
        if ($guestName === '') {
            throw new InvalidArgumentException('Guest name is required.');
        }

        $guestPhone = $this->normalizeGuestPhone(
            (string) ($data['guest_phone_country_code'] ?? ''),
            (string) ($data['guest_phone'] ?? ''),
        );
        $guestEmail = trim((string) ($data['guest_email'] ?? ''));

        if (! $reservation && ! empty($settings['allow_walk_in'])) {
            if (! empty($settings['require_contact_for_walkin']) && $guestPhone === '') {
                throw new InvalidArgumentException('Phone number is required for walk-in / external guests.');
            }
        }

        if ($guestEmail !== '' && ! filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }

        $roomNo = trim((string) ($data['room_no'] ?? ''));
        if ($reservation && $reservation->isRoomAssigned()) {
            $roomNo = $reservation->assignedRoomLabel();
        }

        if ($reservation && ! empty($settings['require_room_for_inhouse']) && $roomNo === '') {
            throw new InvalidArgumentException('Room number is required for in-house guests.');
        }

        if (! $reservation && empty($settings['allow_walk_in'])) {
            throw new InvalidArgumentException('Walk-in pool access is disabled in swimming settings.');
        }

        $scheduledDate = Carbon::parse($data['scheduled_date'] ?? now()->format('Y-m-d'))->startOfDay();
        if ($scheduledDate->lt(Carbon::today())) {
            throw new InvalidArgumentException('Visit date cannot be in the past.');
        }

        $isFutureVisit = $scheduledDate->isAfter(Carbon::today());

        $unitPrice = (float) $rate->unit_price;
        $subtotal = $this->computeTotal($service, $rate, $adults, $children, $billingQty);
        $discountType = null;
        $discountValue = 0.0;
        $discountAmount = 0.0;
        $total = $subtotal;

        if ($service->amount_editable) {
            $discountType = in_array($data['discount_type'] ?? '', ['percent', 'amount'], true)
                ? $data['discount_type']
                : null;
            $discountValue = max(0, (float) ($data['discount_value'] ?? 0));

            if ($discountType && $discountValue > 0) {
                $applied = $this->applyDiscount($subtotal, $discountType, $discountValue);
                $subtotal = $applied['subtotal'];
                $discountAmount = $applied['discount_amount'];
                $total = $applied['total'];
            }
        }
        $persons = max(1, $adults + $children);
        if ($rate->pricing_unit !== 'per_person') {
            $persons = max(1, (int) ceil($billingQty > 0 ? $billingQty : 1));
        }

        $currency = $this->rates->normalizeCurrency((string) ($rate->currency ?: $service->displayCurrency($hotel)));

        $isComplimentary = $this->resolveComplimentary($settings, $reservation !== null, ! empty($data['apply_complimentary']));
        if ($isComplimentary) {
            $total = 0;
            $subtotal = 0;
            $discountAmount = 0;
            $unitPrice = 0;
        }

        $status = $isFutureVisit ? HotelSwimmingVisit::STATUS_SCHEDULED : HotelSwimmingVisit::STATUS_IN_POOL;
        $enteredAt = $isFutureVisit ? null : now();
        $payment = $this->deskPayments->parsePayment($hotel, $data, $total, $reservation === null);

        $visit = HotelSwimmingVisit::query()->create([
            'hotel_id' => $hotel->id,
            'hotel_pms_service_id' => $service->id,
            'cm_reservation_id' => $reservation?->id,
            'hotel_pms_service_category_id' => $rate->hotel_pms_service_category_id,
            'hotel_pms_swimming_rate_id' => $rate->id,
            'visit_no' => $this->nextVisitNo($hotel),
            'room_no' => $roomNo !== '' ? $roomNo : null,
            'guest_name' => $guestName,
            'guest_phone' => $guestPhone !== '' ? $guestPhone : null,
            'guest_email' => $guestEmail !== '' ? $guestEmail : null,
            'scheduled_date' => $scheduledDate,
            'persons' => min($persons, $maxPersons),
            'pricing_unit' => $rate->pricing_unit,
            'audience' => $rate->audience,
            'billing_quantity' => $billingQty > 0 ? $billingQty : 1,
            'adults' => $adults,
            'children' => $children,
            'rate_label' => $rate->displayTitle(),
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal,
            'discount_type' => $discountType,
            'discount_value' => $discountType ? $discountValue : null,
            'discount_amount' => $discountAmount,
            'total' => $total,
            'is_complimentary' => $isComplimentary,
            'currency' => $currency,
            'payment_mode' => $payment['payment_mode'],
            'payment_provider' => $payment['payment_provider'],
            'amount_paid' => $payment['amount_paid'],
            'payment_notes' => $payment['payment_notes'],
            'status' => $status,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'entered_at' => $enteredAt,
            'created_by' => $user?->id,
        ]);

        if (! $isFutureVisit && ! empty($settings['post_to_folio']) && $reservation && $total > 0) {
            $this->postToFolio($visit, $reservation);
        }

        return $visit->fresh(['category', 'swimmingRate', 'reservation.roomUnit', 'creator']);
    }

    /** @param  array<string, mixed>  $settings */
    private function resolveComplimentary(array $settings, bool $isInHouse, bool $deskOverride): bool
    {
        if (! $isInHouse) {
            return false;
        }

        if (! empty($settings['complimentary_for_inhouse'])) {
            return true;
        }

        return ! empty($settings['allow_desk_complimentary_override']) && $deskOverride;
    }

    public function checkInScheduled(HotelSwimmingVisit $visit, ?User $user = null): HotelSwimmingVisit
    {
        if ($visit->status !== HotelSwimmingVisit::STATUS_SCHEDULED) {
            throw new InvalidArgumentException('Only scheduled visits can be checked in.');
        }

        $visit->update([
            'status' => HotelSwimmingVisit::STATUS_IN_POOL,
            'entered_at' => now(),
        ]);

        $visit->load(['reservation', 'service']);
        $settings = $visit->service?->swimmingSettings() ?? [];

        if (! empty($settings['post_to_folio']) && $visit->reservation && (float) $visit->total > 0 && ! $visit->posted_to_folio) {
            $this->postToFolio($visit, $visit->reservation);
        }

        return $visit->fresh(['category', 'swimmingRate', 'reservation.roomUnit', 'creator']);
    }

    public function computeTotal(
        HotelPmsService $service,
        HotelPmsSwimmingRate $rate,
        int $adults,
        int $children,
        float $billingQty,
    ): float {
        if ($rate->pricing_unit === 'per_person') {
            $total = 0.0;

            if ($rate->audience === 'child') {
                $count = $children > 0 ? $children : max(1, (int) $billingQty);

                return $rate->calculateTotal((float) $count);
            }

            $adultCount = $adults > 0 ? $adults : ($children > 0 ? 0 : max(1, (int) $billingQty));
            if ($adultCount > 0) {
                $total += $rate->calculateTotal((float) $adultCount);
            }

            if ($children > 0) {
                $childRate = $this->childRateFor($service, $rate);
                if ($childRate) {
                    $total += $childRate->calculateTotal((float) $children);
                } elseif ($rate->audience === 'guest' || $adultCount === 0) {
                    $total += $rate->calculateTotal((float) $children);
                }
            }

            return round($total, 2);
        }

        $qty = $billingQty > 0 ? $billingQty : 1;

        return $rate->calculateTotal($qty);
    }

    private function childRateFor(HotelPmsService $service, HotelPmsSwimmingRate $adultRate): ?HotelPmsSwimmingRate
    {
        return HotelPmsSwimmingRate::query()
            ->where('hotel_pms_service_id', $service->id)
            ->where('hotel_pms_service_category_id', $adultRate->hotel_pms_service_category_id)
            ->where('pricing_unit', 'per_person')
            ->where('audience', 'child')
            ->where('is_active', true)
            ->first();
    }

    public function completeVisit(HotelSwimmingVisit $visit, ?User $user = null): HotelSwimmingVisit
    {
        if ($visit->status !== HotelSwimmingVisit::STATUS_IN_POOL) {
            throw new InvalidArgumentException('This visit is already closed.');
        }

        $visit->update([
            'status' => HotelSwimmingVisit::STATUS_COMPLETED,
            'exited_at' => now(),
        ]);

        return $visit->fresh(['category', 'swimmingRate', 'reservation.roomUnit', 'creator']);
    }

    public function postToFolio(HotelSwimmingVisit $visit, ?CmReservation $reservation = null): void
    {
        if ($visit->posted_to_folio || (float) $visit->total <= 0) {
            return;
        }

        $reservation ??= $visit->reservation;
        if (! $reservation) {
            throw new InvalidArgumentException('Link this visit to a reservation to post to folio.');
        }

        $payload = is_array($reservation->payload) ? $reservation->payload : [];
        $charges = is_array($payload['swimming_charges'] ?? null) ? $payload['swimming_charges'] : [];

        $charges[] = [
            'visit_id' => $visit->id,
            'visit_no' => $visit->visit_no,
            'amount' => (float) $visit->total,
            'currency' => $visit->currency,
            'posted_at' => now()->toIso8601String(),
        ];
        $payload['swimming_charges'] = $charges;

        $reservation->update([
            'amount_after_tax' => round((float) ($reservation->amount_after_tax ?? 0) + (float) $visit->total, 2),
            'payload' => $payload,
        ]);

        $visit->update(['posted_to_folio' => true]);
    }

    /** @return array{subtotal: float, discount_amount: float, total: float} */
    public function applyDiscount(float $subtotal, string $type, float $value): array
    {
        $subtotal = max(0, round($subtotal, 2));
        $discountAmount = 0.0;

        if ($type === 'percent') {
            $discountAmount = round($subtotal * (min(100, max(0, $value)) / 100), 2);
        } elseif ($type === 'amount') {
            $discountAmount = round(min(max(0, $value), $subtotal), 2);
        }

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'total' => round(max(0, $subtotal - $discountAmount), 2),
        ];
    }

    private function normalizeGuestPhone(string $countryCode, string $localNumber): string
    {
        $localNumber = preg_replace('/\s+/', '', trim($localNumber)) ?? '';
        $countryCode = trim($countryCode);

        if ($localNumber === '') {
            return '';
        }

        if (str_starts_with($localNumber, '+')) {
            return $localNumber;
        }

        if ($countryCode !== '') {
            return $countryCode.ltrim($localNumber, '0');
        }

        return $localNumber;
    }

    /** @return array<int, array{price: float, currency: string}> */
    public function childRateMap(HotelPmsService $service): array
    {
        $map = [];
        $rates = HotelPmsSwimmingRate::query()
            ->where('hotel_pms_service_id', $service->id)
            ->where('pricing_unit', 'per_person')
            ->where('audience', 'child')
            ->where('is_active', true)
            ->get();

        foreach ($rates as $rate) {
            $map[(int) $rate->hotel_pms_service_category_id] = [
                'price' => (float) $rate->unit_price,
                'currency' => strtoupper($rate->currency ?: 'TZS'),
            ];
        }

        return $map;
    }
}
