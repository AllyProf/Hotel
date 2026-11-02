<?php

namespace App\Services\Conference;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelConferenceBooking;
use App\Models\HotelPmsConferenceRate;
use App\Models\HotelPmsService;
use App\Models\User;
use App\Services\FrontDeskPaymentService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ConferenceBookingService
{
    public function __construct(
        private ConferenceRateDataService $rates,
        private FrontDeskPaymentService $deskPayments,
    ) {}

    /** @return array<string, mixed> */
    public function uiConfig(): array
    {
        return config('hotel_pms.conference', []);
    }

    public function conferenceServiceForHotel(Hotel $hotel): ?HotelPmsService
    {
        return $hotel->pmsServices()
            ->where('template_key', 'conference')
            ->where('is_active', true)
            ->first();
    }

    public function nextBookingNo(Hotel $hotel): string
    {
        $year = now()->format('Y');
        $prefix = 'CONF-'.$year.'-';
        $last = HotelConferenceBooking::query()
            ->where('hotel_id', $hotel->id)
            ->where('booking_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('booking_no');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    /** @return Builder<HotelConferenceBooking> */
    public function bookingsQuery(Hotel $hotel, ?string $tab = null): Builder
    {
        $query = HotelConferenceBooking::query()
            ->where('hotel_id', $hotel->id)
            ->with(['category', 'conferenceRate', 'reservation.roomUnit', 'service', 'creator'])
            ->orderByDesc('starts_at');

        if ($tab === 'upcoming') {
            $query->where('starts_at', '>=', now()->startOfDay())
                ->whereNotIn('status', [HotelConferenceBooking::STATUS_COMPLETED, HotelConferenceBooking::STATUS_CANCELLED]);
        } elseif ($tab === 'past') {
            $query->where(function ($q) {
                $q->where('ends_at', '<', now())
                    ->orWhere('status', HotelConferenceBooking::STATUS_COMPLETED);
            });
        } elseif ($tab === 'cancelled') {
            $query->where('status', HotelConferenceBooking::STATUS_CANCELLED);
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
    public function createBooking(
        Hotel $hotel,
        HotelPmsService $service,
        array $data,
        ?User $user = null,
    ): HotelConferenceBooking {
        $settings = $service->conferenceSettings();
        $rateId = (int) ($data['hotel_pms_conference_rate_id'] ?? 0);
        $rate = $this->rates->findRateForService($service, $rateId);

        if (! $rate) {
            throw new InvalidArgumentException('Select a valid conference rate / package.');
        }

        $startsAt = Carbon::parse($data['starts_at'] ?? now());
        $endsAt = Carbon::parse($data['ends_at'] ?? $startsAt);
        if ($endsAt->lt($startsAt)) {
            throw new InvalidArgumentException('End date/time must be after start.');
        }

        $delegates = max(1, (int) ($data['delegates'] ?? 1));
        $eventName = trim((string) ($data['event_name'] ?? ''));
        if ($eventName === '') {
            throw new InvalidArgumentException('Event name is required.');
        }

        $reservation = null;
        if (! empty($data['cm_reservation_id'])) {
            $reservation = CmReservation::query()
                ->where('hotel_id', $hotel->id)
                ->whereKey((int) $data['cm_reservation_id'])
                ->first();
        }

        if (! $reservation && empty($settings['allow_external_booking'])) {
            throw new InvalidArgumentException('External bookings are disabled in conference settings.');
        }

        $organizer = trim((string) ($data['organizer_name'] ?? ''));
        $company = trim((string) ($data['company_name'] ?? ''));
        $phone = $this->normalizeContactPhone(
            (string) ($data['contact_phone_country_code'] ?? ''),
            (string) ($data['contact_phone'] ?? ''),
        );
        $email = trim((string) ($data['contact_email'] ?? ''));

        if (! $reservation && ! empty($settings['require_contact_for_external']) && $phone === '') {
            throw new InvalidArgumentException('Contact phone is required for external bookings.');
        }

        $guestName = trim((string) ($data['guest_name'] ?? ''));
        if ($reservation) {
            $guestName = $reservation->guestName();
        }

        $roomNo = trim((string) ($data['room_no'] ?? ''));
        if ($reservation && $reservation->isRoomAssigned()) {
            $roomNo = $reservation->assignedRoomLabel();
        }

        $billingQty = $this->resolveBillingQuantity($rate, $startsAt, $endsAt, $delegates, (float) ($data['billing_quantity'] ?? 0));
        $subtotal = $this->computeSubtotal($rate, $billingQty);
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
                $discountAmount = $applied['discount_amount'];
                $total = $applied['total'];
            }
        }

        $currency = $this->rates->normalizeCurrency((string) ($rate->currency ?: $service->displayCurrency($hotel)));
        $walkIn = $reservation === null;
        $payment = $this->deskPayments->parsePayment($hotel, $data, $total, $walkIn);

        $status = in_array($data['status'] ?? '', array_keys(config('hotel_pms.conference.statuses', [])), true)
            ? $data['status']
            : ($settings['default_booking_status'] ?? HotelConferenceBooking::STATUS_CONFIRMED);

        $booking = HotelConferenceBooking::query()->create([
            'hotel_id' => $hotel->id,
            'hotel_pms_service_id' => $service->id,
            'cm_reservation_id' => $reservation?->id,
            'hotel_pms_service_category_id' => $rate->hotel_pms_service_category_id,
            'hotel_pms_conference_rate_id' => $rate->id,
            'booking_no' => $this->nextBookingNo($hotel),
            'event_name' => $eventName,
            'organizer_name' => $organizer !== '' ? $organizer : null,
            'company_name' => $company !== '' ? $company : null,
            'contact_phone' => $phone !== '' ? $phone : null,
            'contact_email' => $email !== '' ? $email : null,
            'room_no' => $roomNo !== '' ? $roomNo : null,
            'guest_name' => $guestName !== '' ? $guestName : null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'delegates' => $delegates,
            'billing_quantity' => $billingQty,
            'rate_label' => $rate->displayTitle(),
            'pricing_unit' => $rate->pricing_unit,
            'unit_price' => (float) $rate->unit_price,
            'subtotal' => $subtotal,
            'discount_type' => $discountType,
            'discount_value' => $discountType ? $discountValue : null,
            'discount_amount' => $discountAmount,
            'total' => $total,
            'currency' => $currency,
            'payment_mode' => $payment['payment_mode'],
            'payment_provider' => $payment['payment_provider'],
            'amount_paid' => $payment['amount_paid'],
            'payment_notes' => $payment['payment_notes'],
            'status' => $status,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'created_by' => $user?->id,
        ]);

        if (! empty($settings['post_to_folio']) && $reservation && $total > 0) {
            $this->postToFolio($booking, $reservation);
        }

        return $booking->fresh(['category', 'conferenceRate', 'reservation.roomUnit', 'creator']);
    }

    public function postToFolio(HotelConferenceBooking $booking, ?CmReservation $reservation = null): void
    {
        if ($booking->posted_to_folio || (float) $booking->total <= 0) {
            return;
        }

        $reservation ??= $booking->reservation;
        if (! $reservation) {
            throw new InvalidArgumentException('Link this booking to a reservation to post to folio.');
        }

        $payload = is_array($reservation->payload) ? $reservation->payload : [];
        $charges = is_array($payload['conference_charges'] ?? null) ? $payload['conference_charges'] : [];

        $charges[] = [
            'booking_id' => $booking->id,
            'booking_no' => $booking->booking_no,
            'event_name' => $booking->event_name,
            'amount' => (float) $booking->total,
            'currency' => $booking->currency,
            'posted_at' => now()->toIso8601String(),
        ];
        $payload['conference_charges'] = $charges;

        $reservation->update([
            'amount_after_tax' => round((float) ($reservation->amount_after_tax ?? 0) + (float) $booking->total, 2),
            'payload' => $payload,
        ]);

        $booking->update(['posted_to_folio' => true]);
    }

    private function resolveBillingQuantity(
        HotelPmsConferenceRate $rate,
        Carbon $startsAt,
        Carbon $endsAt,
        int $delegates,
        float $manualQty,
    ): float {
        return match ($rate->pricing_unit) {
            'per_person' => (float) max(1, $delegates),
            'per_half_day' => $manualQty > 0 ? $manualQty : max(1, (float) ceil($startsAt->diffInMinutes($endsAt) / 720)),
            'per_day' => max(1, (float) ($startsAt->copy()->startOfDay()->diffInDays($endsAt->copy()->startOfDay()) + 1)),
            'fixed' => 1,
            default => max(1, $manualQty > 0 ? $manualQty : 1),
        };
    }

    private function computeSubtotal(HotelPmsConferenceRate $rate, float $billingQty): float
    {
        $unit = (float) $rate->unit_price;
        $qty = max(0, $billingQty);

        return match ($rate->pricing_unit) {
            'fixed' => round($unit, 2),
            default => round($unit * ($qty > 0 ? $qty : 1), 2),
        };
    }

    private function normalizeContactPhone(string $countryCode, string $localNumber): string
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

    /** @return array{discount_amount: float, total: float} */
    private function applyDiscount(float $subtotal, string $type, float $value): array
    {
        $subtotal = max(0, round($subtotal, 2));
        $discountAmount = 0.0;

        if ($type === 'percent') {
            $discountAmount = round($subtotal * (min(100, max(0, $value)) / 100), 2);
        } elseif ($type === 'amount') {
            $discountAmount = round(min(max(0, $value), $subtotal), 2);
        }

        return [
            'discount_amount' => $discountAmount,
            'total' => round(max(0, $subtotal - $discountAmount), 2),
        ];
    }
}
