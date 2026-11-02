<?php

namespace App\Services;

use App\Mail\BookingInvoiceMail;
use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelCompany;
use App\Models\HotelRoom;
use App\Models\HotelRoomUnit;
use App\Services\ChannelManager\ChannelManagerCodeResolver;
use App\Services\ChannelManager\ReservationInventoryService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ReservationManageService
{
    public function __construct(
        private ReservationInventoryService $inventorySync,
        private RoomInventoryService $roomInventory,
        private ChannelManagerCodeResolver $codes,
        private CheckInService $checkIn,
        private CreateReservationService $createReservation,
        private HotelSettingsService $settings,
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     errors: list<string>,
     *     warnings: list<string>,
     *     nights: array{old: int, new: int},
     *     pricing: array<string, mixed>,
     *     rooms: list<array<string, mixed>>,
     *     assigned_room: ?string
     * }
     */
    public function previewStayChange(Hotel $hotel, CmReservation $reservation, string $checkin, string $checkout): array
    {
        $errors = [];
        $warnings = [];

        try {
            $this->assertStayEditable($reservation);
        } catch (ValidationException $exception) {
            return $this->previewStayFailure(collect($exception->errors())->flatten()->all());
        }

        [$newCheckin, $newCheckout, $dateErrors] = $this->parseStayDates($checkin, $checkout);
        if ($dateErrors !== []) {
            return $this->previewStayFailure($dateErrors);
        }

        if ($reservation->isCheckedIn() && $reservation->checkin !== null
            && $newCheckin->ne($reservation->checkin->copy()->startOfDay())) {
            $errors[] = 'Cannot change check-in date after the guest has checked in.';
        }

        $warnings = array_merge($warnings, $this->checkedInStayWarnings($reservation, $newCheckin, $newCheckout));

        $availabilityError = $this->availabilityError($hotel, $reservation, $newCheckin, $newCheckout);
        if ($availabilityError !== null) {
            $errors[] = $availabilityError;
        }

        $pricing = $this->calculateStayPricing($hotel, $reservation, $newCheckin, $newCheckout);
        $rooms = $this->roomAvailabilitySummary($hotel, $reservation, $newCheckin, $newCheckout);

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'nights' => [
                'old' => $pricing['old_nights'],
                'new' => $pricing['new_nights'],
            ],
            'pricing' => $pricing,
            'rooms' => $rooms,
            'assigned_room' => $reservation->isRoomAssigned() ? $reservation->assignedRoomLabel() : null,
        ];
    }

    /**
     * @param  array{
     *     reason?: string,
     *     recalculate_total?: bool,
     *     resend_invoice?: bool,
     *     resend_invoice_email?: string|null
     * }  $options
     * @return array{reservation: CmReservation, invoice_sent: bool, pricing: array<string, mixed>}
     */
    public function updateStayDates(
        Hotel $hotel,
        CmReservation $reservation,
        string $checkin,
        string $checkout,
        array $options = []
    ): array {
        $this->assertStayEditable($reservation);

        [$newCheckin, $newCheckout, $dateErrors] = $this->parseStayDates($checkin, $checkout);
        if ($dateErrors !== []) {
            throw ValidationException::withMessages([
                'checkout' => $dateErrors[0],
            ]);
        }

        if ($reservation->isCheckedIn() && $reservation->checkin !== null
            && $newCheckin->ne($reservation->checkin->copy()->startOfDay())) {
            throw ValidationException::withMessages([
                'checkin' => 'Cannot change check-in date after the guest has checked in.',
            ]);
        }

        $reason = trim((string) ($options['reason'] ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Please enter a reason for the date change.',
            ]);
        }

        $resendInvoice = ! empty($options['resend_invoice']);
        $recalculateTotal = ! empty($options['recalculate_total']) || $resendInvoice;
        $resendEmail = trim((string) ($options['resend_invoice_email'] ?? ''));

        if ($resendInvoice && ($resendEmail === '' || ! filter_var($resendEmail, FILTER_VALIDATE_EMAIL))) {
            throw ValidationException::withMessages([
                'resend_invoice_email' => 'Enter a valid email to resend the invoice.',
            ]);
        }

        $pricingBefore = $this->calculateStayPricing($hotel, $reservation, $newCheckin, $newCheckout);

        $updated = DB::transaction(function () use ($hotel, $reservation, $newCheckin, $newCheckout, $reason, $recalculateTotal, $pricingBefore) {
            $before = $this->snapshotReservation($reservation);
            $oldCheckin = $before->checkin?->format('Y-m-d');
            $oldCheckout = $before->checkout?->format('Y-m-d');

            if (! $before->isCancelled()) {
                $this->inventorySync->restoreReservation($hotel, $before);
            }

            $availabilityError = $this->availabilityError($hotel, $reservation, $newCheckin, $newCheckout, creditOwnHold: false);
            if ($availabilityError !== null) {
                if (! $before->isCancelled()) {
                    $this->inventorySync->consumeReservation($hotel, $before);
                }

                throw ValidationException::withMessages([
                    'checkin' => $availabilityError,
                ]);
            }

            $updateData = [
                'checkin' => $newCheckin->format('Y-m-d'),
                'checkout' => $newCheckout->format('Y-m-d'),
                'status' => CmReservation::STATUS_MODIFIED,
            ];

            $payload = is_array($reservation->payload) ? $reservation->payload : [];
            $history = is_array($payload['stay_change_history'] ?? null) ? $payload['stay_change_history'] : [];
            $history[] = array_filter([
                'from_checkin' => $oldCheckin,
                'from_checkout' => $oldCheckout,
                'to_checkin' => $newCheckin->format('Y-m-d'),
                'to_checkout' => $newCheckout->format('Y-m-d'),
                'reason' => $reason,
                'changed_at' => now()->toIso8601String(),
                'changed_by' => auth()->user()?->name,
                'recalculated' => $recalculateTotal,
                'old_total' => (float) ($before->amount_after_tax ?? 0),
                'new_total' => $recalculateTotal ? $pricingBefore['new_total'] : null,
            ], fn ($value) => $value !== null && $value !== '');

            $payload['stay_change_history'] = $history;
            $payload['nights'] = $pricingBefore['new_nights'];
            $payload['last_stay_change_reason'] = $reason;
            $payload['last_stay_change_at'] = now()->toIso8601String();

            if ($recalculateTotal) {
                $updateData['amount_after_tax'] = $pricingBefore['new_total'];
                $updateData['amount_before_tax'] = $pricingBefore['amount_before_tax'];
                $updateData['tax'] = $pricingBefore['tax'];
                $payload['amount_remaining'] = $pricingBefore['balance_due'] > 0.009 ? $pricingBefore['balance_due'] : null;

                if (is_array($payload['advance_payment'] ?? null)) {
                    $payload['advance_payment']['remaining'] = $pricingBefore['balance_due'] > 0.009
                        ? $pricingBefore['balance_due']
                        : null;
                }
            }

            $updateData['payload'] = $payload;

            $reservation->update($updateData);

            $fresh = $reservation->fresh();

            if ($fresh && ! $fresh->isCancelled()) {
                $this->inventorySync->consumeReservation($hotel, $fresh);
                $this->inventorySync->pushInventoryForReservationChange($hotel, $before, $fresh);
            }

            return $fresh ?? $reservation;
        });

        $invoiceSent = false;
        if ($resendInvoice) {
            $this->sendInvoice($hotel, $updated, $resendEmail);
            $invoiceSent = true;
        }

        return [
            'reservation' => $updated,
            'invoice_sent' => $invoiceSent,
            'pricing' => $pricingBefore,
        ];
    }

    public function sendInvoice(Hotel $hotel, CmReservation $reservation, string $email): CmReservation
    {
        if ($reservation->isCancelled()) {
            throw ValidationException::withMessages([
                'invoice_email' => 'Cancelled bookings cannot receive invoices.',
            ]);
        }

        $email = trim($email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'invoice_email' => 'Enter a valid email address.',
            ]);
        }

        try {
            Mail::to($email)->send(new BookingInvoiceMail($hotel, $reservation));
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'invoice_email' => 'Could not send the invoice email. Check mail settings and try again.',
            ]);
        }

        $payload = is_array($reservation->payload) ? $reservation->payload : [];
        $history = is_array($payload['invoice_send_history'] ?? null) ? $payload['invoice_send_history'] : [];
        $history[] = [
            'email' => $email,
            'sent_at' => now()->toIso8601String(),
            'sent_by' => auth()->user()?->name,
        ];

        $payload['invoice_sent_at'] = now()->toIso8601String();
        $payload['invoice_send_to'] = $email;
        $payload['send_invoice_to_company'] = true;
        $payload['invoice_send_history'] = $history;

        $reservation->update(['payload' => $payload]);

        return $reservation->fresh();
    }

    public function defaultInvoiceEmail(CmReservation $reservation): string
    {
        $payload = is_array($reservation->payload) ? $reservation->payload : [];

        foreach ([
            $payload['invoice_send_to'] ?? null,
            is_array($payload['guest'] ?? null) ? ($payload['guest']['email'] ?? null) : null,
        ] as $candidate) {
            $email = trim((string) $candidate);
            if ($email !== '') {
                return $email;
            }
        }

        $companyId = (int) ($payload['bill_to_company_id'] ?? 0);
        if ($companyId > 0) {
            $companyEmail = HotelCompany::query()->find($companyId)?->email;
            $email = trim((string) $companyEmail);
            if ($email !== '') {
                return $email;
            }
        }

        return '';
    }

    /**
     * @return array{
     *     old_nights: int,
     *     new_nights: int,
     *     old_total: float,
     *     new_total: float,
     *     difference: float,
     *     amount_before_tax: float,
     *     tax: float,
     *     amount_paid: float,
     *     balance_due: float,
     *     currency: string
     * }
     */
    public function calculateStayPricing(
        Hotel $hotel,
        CmReservation $reservation,
        Carbon $checkin,
        Carbon $checkout
    ): array {
        $oldNights = max(1, (int) ($reservation->checkin?->diffInDays($reservation->checkout) ?? 1));
        $newNights = max(1, (int) $checkin->diffInDays($checkout));
        $payload = is_array($reservation->payload) ? $reservation->payload : [];
        $taxInclusive = ! empty($payload['tax_inclusive']);
        $vatRate = (float) ($payload['vat_rate'] ?? $this->settings->vatRate($hotel));
        $dailyTax = array_key_exists('daily_tax', $payload) ? (float) $payload['daily_tax'] : null;
        $bookingType = (string) ($payload['booking_type'] ?? '');

        $newTotal = 0.0;
        $amountBeforeTax = 0.0;
        $taxTotal = 0.0;
        $hasLineRates = false;

        foreach ($reservation->roomLines() as $line) {
            $roomCount = max(1, (int) ($line['numberOfRooms'] ?? $line['roomCount'] ?? $line['quantity'] ?? 1));
            $dailyRate = (float) ($line['dailyRate'] ?? $line['daily_rate'] ?? 0);

            if ($dailyRate <= 0 && isset($payload['daily_rate'])) {
                $dailyRate = (float) $payload['daily_rate'];
            }

            if ($dailyRate <= 0) {
                continue;
            }

            $hasLineRates = true;

            if ($bookingType === 'group' || ($taxInclusive && $dailyTax === null)) {
                $lineTotal = round($dailyRate * $newNights * $roomCount, 2);
                $newTotal += $lineTotal;
                $amountBeforeTax += $lineTotal;
            } else {
                $charges = $this->createReservation->calculateRoomCharges(
                    $dailyRate,
                    $dailyTax,
                    $taxInclusive,
                    $vatRate,
                    $newNights,
                    $roomCount
                );
                $newTotal += $charges['amount_after_tax'];
                $amountBeforeTax += $charges['amount_before_tax'];
                $taxTotal += $charges['tax'];
            }
        }

        if (! $hasLineRates) {
            $oldTotal = (float) ($reservation->amount_after_tax ?? 0);
            $ratio = $newNights / $oldNights;
            $newTotal = round($oldTotal * $ratio, 2);
            $amountBeforeTax = round((float) ($reservation->amount_before_tax ?? $oldTotal) * $ratio, 2);
            $taxTotal = round((float) ($reservation->tax ?? 0) * $ratio, 2);
        }

        $newTotal = $this->applyDiscount($newTotal, $payload);
        if ($taxTotal > 0) {
            $amountBeforeTax = max(0, round($newTotal - $taxTotal, 2));
        } else {
            $amountBeforeTax = $newTotal;
        }

        $amountPaid = $reservation->effectiveAmountPaid();
        $balanceDue = max(0, round($newTotal - $amountPaid, 2));
        $oldTotal = (float) ($reservation->amount_after_tax ?? 0);

        return [
            'old_nights' => $oldNights,
            'new_nights' => $newNights,
            'old_total' => $oldTotal,
            'new_total' => $newTotal,
            'difference' => round($newTotal - $oldTotal, 2),
            'amount_before_tax' => $amountBeforeTax,
            'tax' => $taxTotal,
            'amount_paid' => $amountPaid,
            'balance_due' => $balanceDue,
            'currency' => strtoupper((string) ($reservation->currency ?: $hotel->currency ?: 'USD')),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function applyDiscount(float $subtotal, array $payload): float
    {
        $discountType = (string) ($payload['discount_type'] ?? 'percent');

        if ($discountType === 'amount' && ! empty($payload['discount_amount'])) {
            return max(0, round($subtotal - (float) $payload['discount_amount'], 2));
        }

        if (! empty($payload['discount_percent'])) {
            $percent = min(100, (float) $payload['discount_percent']);

            return round($subtotal * (1 - ($percent / 100)), 2);
        }

        return round($subtotal, 2);
    }

    /** @return array{0: Carbon, 1: Carbon, 2: list<string>} */
    private function parseStayDates(string $checkin, string $checkout): array
    {
        $newCheckin = Carbon::parse($checkin)->startOfDay();
        $newCheckout = Carbon::parse($checkout)->startOfDay();
        $errors = [];

        if ($newCheckout->lte($newCheckin)) {
            $errors[] = 'Check-out must be after check-in.';
        }

        return [$newCheckin, $newCheckout, $errors];
    }

    /** @param list<string> $errors */
    public function previewStayFailure(array $errors): array
    {
        return [
            'ok' => false,
            'errors' => $errors,
            'warnings' => [],
            'nights' => ['old' => 0, 'new' => 0],
            'pricing' => [],
            'rooms' => [],
            'assigned_room' => null,
        ];
    }

    /** @return list<string> */
    private function checkedInStayWarnings(CmReservation $reservation, Carbon $newCheckin, Carbon $newCheckout): array
    {
        if (! $reservation->isCheckedIn()) {
            return [];
        }

        $warnings = [];
        $oldCheckout = $reservation->checkout?->copy()->startOfDay();

        if ($oldCheckout !== null && $newCheckout->gt($oldCheckout)) {
            $extraNights = $oldCheckout->diffInDays($newCheckout);
            $warnings[] = 'Guest is checked in — extending stay by '.$extraNights.' night'.($extraNights === 1 ? '' : 's').'.';
        }

        if ($oldCheckout !== null && $newCheckout->lt($oldCheckout)) {
            $warnings[] = 'Guest is checked in — shortening the stay will free the room from the new checkout date.';
        }

        $nextGuest = $this->nextGuestInUnit($reservation, $newCheckout);
        if ($nextGuest !== null) {
            $warnings[] = 'Another guest ('.$nextGuest->guestName().', booking '.$nextGuest->booking_id.') is scheduled for this room from '
                .$nextGuest->checkin?->format('d/m/Y').'.';
        }

        return $warnings;
    }

    private function nextGuestInUnit(CmReservation $reservation, Carbon $newCheckout): ?CmReservation
    {
        if (! $reservation->room_unit_id || $reservation->checkout === null) {
            return null;
        }

        return CmReservation::query()
            ->where('hotel_id', $reservation->hotel_id)
            ->whereKeyNot($reservation->id)
            ->where('room_unit_id', $reservation->room_unit_id)
            ->where('status', '!=', CmReservation::STATUS_CANCELLED)
            ->where('pms_status', '!=', CmReservation::PMS_CHECKED_OUT)
            ->whereDate('checkin', '<', $newCheckout->format('Y-m-d'))
            ->whereDate('checkout', '>', $reservation->checkout->format('Y-m-d'))
            ->whereDate('checkin', '>=', $reservation->checkout->format('Y-m-d'))
            ->orderBy('checkin')
            ->first();
    }

    /** @return list<array<string, mixed>> */
    private function roomAvailabilitySummary(
        Hotel $hotel,
        CmReservation $reservation,
        Carbon $checkin,
        Carbon $checkout
    ): array {
        $requestedByRoom = $this->requestedRoomsById($hotel, $reservation);
        $inventoryCredits = $this->inventoryCreditsByRoom($hotel, $reservation);
        $summary = [];

        foreach ($requestedByRoom as $roomId => $requested) {
            $room = HotelRoom::query()->find($roomId);
            if ($room === null) {
                continue;
            }

            $result = $this->roomInventory->checkStayAvailabilityWithCredits(
                $hotel,
                (int) $roomId,
                $checkin,
                $checkout,
                (int) $requested,
                $inventoryCredits[$roomId] ?? []
            );
            $bookedByOthers = 0;

            foreach (CarbonPeriod::create($checkin->copy()->startOfDay(), '1 day', $checkout->copy()->subDay()) as $day) {
                $night = Carbon::parse($day)->format('Y-m-d');
                $nightBooked = 0;

                foreach ($this->overlappingReservations($hotel, $reservation, $checkin, $checkout) as $other) {
                    $nightBooked += $this->roomCountForTypeOnNight($hotel, $other, (int) $roomId, $night);
                }

                $bookedByOthers = max($bookedByOthers, $nightBooked);
            }

            $summary[] = [
                'room_id' => $roomId,
                'room_name' => $room->display_name ?: $room->name,
                'requested' => $requested,
                'capacity' => max(1, (int) $room->room_count),
                'booked_by_others' => $bookedByOthers,
                'inventory_available' => $result['available'],
                'ok' => $result['ok'] && ($bookedByOthers + $requested <= max(1, (int) $room->room_count)),
                'message' => $result['message'],
            ];
        }

        return $summary;
    }

    private function assertStayEditable(CmReservation $reservation): void
    {
        if ($reservation->isCancelled()) {
            throw ValidationException::withMessages([
                'checkin' => 'Cancelled bookings cannot be updated.',
            ]);
        }

        if ($reservation->isCheckedOut()) {
            throw ValidationException::withMessages([
                'checkin' => 'Checked-out bookings cannot be updated.',
            ]);
        }
    }

    private function snapshotReservation(CmReservation $reservation): CmReservation
    {
        $snapshot = $reservation->replicate();
        $snapshot->exists = true;
        $snapshot->id = $reservation->id;

        return $snapshot;
    }

    private function availabilityError(
        Hotel $hotel,
        CmReservation $reservation,
        Carbon $checkin,
        Carbon $checkout,
        bool $creditOwnHold = true
    ): ?string {
        $assignedError = $this->assignedUnitConflictError($reservation, $checkin, $checkout);
        if ($assignedError !== null) {
            return $assignedError;
        }

        $requestedByRoom = $this->requestedRoomsById($hotel, $reservation);
        if ($requestedByRoom === []) {
            return null;
        }

        $overlapError = $this->reservationOverlapError($hotel, $reservation, $checkin, $checkout, $requestedByRoom);
        if ($overlapError !== null) {
            return $overlapError;
        }

        $inventoryCredits = $creditOwnHold ? $this->inventoryCreditsByRoom($hotel, $reservation) : [];

        foreach ($requestedByRoom as $roomId => $requested) {
            $result = $this->roomInventory->checkStayAvailabilityWithCredits(
                $hotel,
                (int) $roomId,
                $checkin,
                $checkout,
                (int) $requested,
                $inventoryCredits[$roomId] ?? []
            );

            if (! $result['ok']) {
                return $result['message'];
            }
        }

        return null;
    }

    /** @return array<int, array<string, int>> */
    private function inventoryCreditsByRoom(Hotel $hotel, CmReservation $reservation): array
    {
        $credits = [];

        foreach ($this->inventorySync->nightlyAllocationsFor($hotel, $reservation) as $allocation) {
            $roomId = (int) $allocation['room_id'];
            $date = (string) $allocation['date'];
            $credits[$roomId][$date] = ($credits[$roomId][$date] ?? 0) + (int) $allocation['rooms'];
        }

        return $credits;
    }

    /** @return array<int, int> */
    private function requestedRoomsById(Hotel $hotel, CmReservation $reservation): array
    {
        $requestedByRoom = [];

        foreach ($reservation->roomLines() as $line) {
            $roomCode = trim((string) ($line['roomCode'] ?? $line['room_code'] ?? ''));
            $room = $this->codes->resolveRoomByCode($hotel, $roomCode);

            if ($room === null) {
                continue;
            }

            $qty = max(1, (int) ($line['numberOfRooms'] ?? $line['roomCount'] ?? $line['quantity'] ?? 1));
            $requestedByRoom[$room->id] = ($requestedByRoom[$room->id] ?? 0) + $qty;
        }

        if ($requestedByRoom === []) {
            $room = $hotel->rooms()->where('is_enabled', true)->orderBy('rank')->first();
            if ($room === null) {
                return [];
            }

            $requestedByRoom[$room->id] = max(1, $reservation->roomCount());
        }

        return $requestedByRoom;
    }

    private function assignedUnitConflictError(CmReservation $reservation, Carbon $checkin, Carbon $checkout): ?string
    {
        if (! $reservation->room_unit_id) {
            return null;
        }

        $unit = HotelRoomUnit::query()->find($reservation->room_unit_id);
        if ($unit === null) {
            return null;
        }

        $probe = $reservation->replicate();
        $probe->checkin = $checkin;
        $probe->checkout = $checkout;

        if ($this->checkIn->isUnitAvailable($unit, $probe, $reservation->id)) {
            return null;
        }

        if ($reservation->isCheckedIn()) {
            return 'Cannot extend stay — assigned room '.$unit->displayLabel().' is already booked for another guest on the selected dates.';
        }

        return 'Assigned room '.$unit->displayLabel().' is not available for the selected dates. Another guest may already be booked in that room.';
    }

    /** @param array<int, int> $requestedByRoom */
    private function reservationOverlapError(
        Hotel $hotel,
        CmReservation $reservation,
        Carbon $checkin,
        Carbon $checkout,
        array $requestedByRoom
    ): ?string {
        $others = $this->overlappingReservations($hotel, $reservation, $checkin, $checkout);

        foreach (CarbonPeriod::create($checkin->copy()->startOfDay(), '1 day', $checkout->copy()->subDay()) as $day) {
            $night = Carbon::parse($day)->format('Y-m-d');

            foreach ($requestedByRoom as $roomId => $requested) {
                $room = HotelRoom::query()->find($roomId);
                if ($room === null) {
                    continue;
                }

                $bookedByOthers = 0;
                $conflictBooking = null;

                foreach ($others as $other) {
                    $otherQty = $this->roomCountForTypeOnNight($hotel, $other, (int) $roomId, $night);
                    if ($otherQty <= 0) {
                        continue;
                    }

                    $bookedByOthers += $otherQty;
                    $conflictBooking = $other;
                }

                $capacity = max(1, (int) $room->room_count);
                if ($bookedByOthers + $requested > $capacity) {
                    $roomName = $room->display_name ?: $room->name;
                    $nightLabel = Carbon::parse($night)->format('d/m/Y');
                    $suffix = $conflictBooking !== null
                        ? ' Conflicts with booking '.$conflictBooking->booking_id.' ('.$conflictBooking->guestName().').'
                        : '';

                    return 'Not enough '.$roomName.' rooms on '.$nightLabel.'. '
                        .$bookedByOthers.' already booked by other reservations, this booking needs '.$requested.'.'
                        .$suffix;
                }
            }
        }

        return null;
    }

    /** @return \Illuminate\Support\Collection<int, CmReservation> */
    private function overlappingReservations(
        Hotel $hotel,
        CmReservation $reservation,
        Carbon $checkin,
        Carbon $checkout
    ) {
        return CmReservation::query()
            ->where('hotel_id', $hotel->id)
            ->whereKeyNot($reservation->id)
            ->where('status', '!=', CmReservation::STATUS_CANCELLED)
            ->where('pms_status', '!=', CmReservation::PMS_CHECKED_OUT)
            ->whereDate('checkout', '>', $checkin->format('Y-m-d'))
            ->whereDate('checkin', '<', $checkout->format('Y-m-d'))
            ->get();
    }

    private function roomCountForTypeOnNight(Hotel $hotel, CmReservation $reservation, int $roomId, string $night): int
    {
        if ($reservation->checkin === null || $reservation->checkout === null) {
            return 0;
        }

        $checkin = $reservation->checkin->format('Y-m-d');
        $checkout = $reservation->checkout->format('Y-m-d');

        if ($night < $checkin || $night >= $checkout) {
            return 0;
        }

        $total = 0;

        foreach ($reservation->roomLines() as $line) {
            $roomCode = trim((string) ($line['roomCode'] ?? $line['room_code'] ?? ''));
            $room = $this->codes->resolveRoomByCode($hotel, $roomCode);

            if ($room?->id !== $roomId) {
                continue;
            }

            $total += max(1, (int) ($line['numberOfRooms'] ?? $line['roomCount'] ?? $line['quantity'] ?? 1));
        }

        if ($total === 0 && $reservation->roomLines() === []) {
            $fallbackRoom = $hotel->rooms()->where('is_enabled', true)->orderBy('rank')->first();
            if ($fallbackRoom?->id === $roomId) {
                $total = max(1, $reservation->roomCount());
            }
        }

        return $total;
    }
}
