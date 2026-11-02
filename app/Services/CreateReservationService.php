<?php

namespace App\Services;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelCompany;
use App\Models\HotelRatePlan;
use App\Models\HotelRoom;
use App\Services\ChannelManager\ChannelManagerCodeResolver;
use App\Services\ChannelManager\ReservationInventoryService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateReservationService
{
    public function __construct(
        private HotelSettingsService $settings,
        private ChannelManagerCodeResolver $codes,
        private ReservationInventoryService $inventorySync,
        private RoomInventoryService $roomInventory,
    ) {}

    /** @return array<string, mixed> */
    public function formOptions(Hotel $hotel): array
    {
        $this->settings->ensureDefaults($hotel);
        $settings = $hotel->settings()->first();
        $reservation = is_array($settings?->reservation) ? $settings->reservation : [];

        $rooms = $hotel->rooms()
            ->where('is_enabled', true)
            ->with(['ratePlans' => fn ($q) => $q->orderByDesc('is_master')->orderBy('code'), 'units' => fn ($q) => $q->orderBy('room_number')])
            ->orderBy('rank')
            ->orderBy('id')
            ->get();

        $roomOptions = $rooms->map(fn (HotelRoom $room) => [
            'id' => $room->id,
            'name' => $room->display_name ?: $room->name,
            'max_occupancy' => (int) $room->max_occupancy,
            'rate_plans' => $room->ratePlans->map(fn (HotelRatePlan $plan) => [
                'id' => $plan->id,
                'label' => $plan->meal_plan ?: $plan->code,
                'code' => $plan->code,
                'meal_plan' => $plan->meal_plan,
                'base_rate' => (float) $plan->rateForGuestType('international'),
                'local_rate' => (float) $plan->rateForGuestType('local'),
                'international_currency' => strtoupper($plan->international_currency ?: $hotel->currency ?: 'USD'),
                'local_currency' => strtoupper($plan->local_currency ?: $hotel->currency ?: 'USD'),
            ])->values()->all(),
            'units' => $room->units->map(fn ($unit) => [
                'id' => $unit->id,
                'label' => $unit->displayLabel(),
            ])->values()->all(),
        ])->values()->all();

        $firstRoom = $rooms->first();
        $firstPlan = $firstRoom?->ratePlans->first();

        $contractedRateFields = [];

        foreach ($rooms as $room) {
            foreach ($room->ratePlans as $plan) {
                $contractedRateFields[] = [
                    'key' => (string) $plan->id,
                    'label' => trim(($room->display_name ?: $room->name).', '.$plan->code.', '.$plan->meal_plan, ' ,'),
                ];
            }
        }

        return [
            'segments' => $reservation['segments'] ?? ['Leisure', 'Corporate', 'Event', 'Walkin', 'OTA'],
            'payment_modes' => $reservation['payment_modes'] ?? ['Cash', 'Credit Card', 'UPI', 'Bank Transfer', 'Bill to Company', 'Prepaid'],
            'identity_types' => $reservation['identity_types'] ?? ['Passport', 'Drivers License', 'VoterID'],
            'genders' => config('hotel_pms.create_reservation.genders', ['Male', 'Female', 'Other']),
            'rooms' => $roomOptions,
            'contracted_rate_fields' => $contractedRateFields,
            'currency' => strtoupper($hotel->currency ?: 'USD'),
            'local_currency' => strtoupper($firstPlan?->local_currency ?: $hotel->currency ?: 'USD'),
            'default_country' => $hotel->country ?: 'Tanzania',
            'default_country_code' => $hotel->country_code ?: 'TZ',
            'default_phone_code' => $hotel->phone_country_code ?: '+255',
            'default_guest_type' => old('guest_type', 'local'),
            'default_checkin' => now()->format('Y-m-d'),
            'default_checkout' => now()->addDay()->format('Y-m-d'),
            'default_room_id' => $firstRoom?->id,
            'default_rate_plan_id' => $firstPlan?->id,
            'default_rate' => $firstPlan ? (float) $firstPlan->rateForGuestType('international') : 0,
            'default_local_rate' => $firstPlan ? (float) $firstPlan->rateForGuestType('local') : 0,
            'default_segment' => ($reservation['segments'][0] ?? 'Leisure'),
            'default_payment_mode' => ($reservation['payment_modes'][0] ?? 'Cash'),
            'channels' => $reservation['channels'] ?? ['PMS', 'Direct', 'Phone', 'Walk-in'],
            'vat_rate' => $this->settings->vatRate($hotel),
            'payment_provider_config' => $this->settings->paymentProviderConfig($hotel),
        ];
    }

    /**
     * @return array{
     *     amount_after_tax: float,
     *     amount_before_tax: float,
     *     tax: float,
     *     daily_tax: float,
     *     vat_rate: float
     * }
     */
    public function calculateRoomCharges(
        float $dailyRate,
        ?float $dailyTax,
        bool $taxInclusive,
        float $vatRate,
        int $nights,
        int $roomCount,
    ): array {
        $nights = max(1, $nights);
        $roomCount = max(1, $roomCount);
        $multiplier = $nights * $roomCount;
        $vatRate = max(0, min(100, $vatRate));

        if ($taxInclusive) {
            $grossPerNight = max(0, $dailyRate);
            $taxPerNight = $vatRate > 0
                ? round($grossPerNight * ($vatRate / (100 + $vatRate)), 2)
                : 0.0;
            $netPerNight = round($grossPerNight - $taxPerNight, 2);
        } else {
            $netPerNight = max(0, $dailyRate);
            $taxPerNight = $dailyTax !== null && $dailyTax >= 0
                ? round($dailyTax, 2)
                : ($vatRate > 0 ? round($netPerNight * $vatRate / 100, 2) : 0.0);
        }

        $amountBeforeTax = round($netPerNight * $multiplier, 2);
        $tax = round($taxPerNight * $multiplier, 2);
        $amountAfterTax = round($amountBeforeTax + $tax, 2);

        return [
            'amount_after_tax' => $amountAfterTax,
            'amount_before_tax' => $amountBeforeTax,
            'tax' => $tax,
            'daily_tax' => $taxPerNight,
            'vat_rate' => $vatRate,
        ];
    }

    /** @param  array<string, mixed>  $data */
    public function storeGroup(Hotel $hotel, array $data): CmReservation
    {
        $hotelCode = $this->codes->hotelCode($hotel);
        $checkin = Carbon::parse($data['checkin'])->startOfDay();
        $checkout = Carbon::parse($data['checkout'])->startOfDay();
        $nights = max(1, $checkin->diffInDays($checkout));
        $taxInclusive = ! empty($data['tax_inclusive']);
        $discountType = in_array($data['discount_type'] ?? '', ['percent', 'amount'], true)
            ? $data['discount_type']
            : 'percent';
        $discountValue = max(0, (float) ($data['discount_value'] ?? $data['discount_percent'] ?? 0));
        $discountPercent = null;
        $discountAmount = null;

        $availabilityError = $this->validateGroupLinesAvailability($hotel, $data['lines'], $checkin, $checkout);
        if ($availabilityError !== null) {
            throw ValidationException::withMessages([
                'lines' => $availabilityError,
            ]);
        }

        $roomPayload = [];
        $subtotal = 0.0;
        $guestTotal = 0;

        foreach ($data['lines'] as $line) {
            $room = HotelRoom::query()->where('hotel_id', $hotel->id)->findOrFail($line['hotel_room_id']);
            $plan = HotelRatePlan::query()
                ->where('hotel_id', $hotel->id)
                ->where('hotel_room_id', $room->id)
                ->findOrFail($line['hotel_rate_plan_id']);

            $roomCount = max(1, (int) $line['room_count']);
            $guestCount = max(1, (int) $line['guest_count']);
            $dailyRate = (float) $line['daily_rate'];
            $lineTotal = round($dailyRate * $nights * $roomCount, 2);
            $subtotal += $lineTotal;
            $guestTotal += $guestCount * $roomCount;

            $roomPayload[] = [
                'roomCode' => $this->codes->roomCode($hotel, $room),
                'roomName' => $room->display_name ?: $room->name,
                'rateplanCode' => $this->codes->rateplanCode($hotel, $plan),
                'mealPlan' => $plan->meal_plan ?: null,
                'numberOfRooms' => $roomCount,
                'dailyRate' => $dailyRate,
                'occupancy' => [
                    'adults' => $guestCount,
                    'children' => 0,
                ],
            ];
        }

        if ($discountType === 'amount') {
            $discountAmount = $discountValue;
            if ($discountAmount > 0) {
                $subtotal = max(0, round($subtotal - $discountAmount, 2));
            }
        } else {
            $discountPercent = min(100, $discountValue);
            if ($discountPercent > 0) {
                $subtotal = round($subtotal * (1 - ($discountPercent / 100)), 2);
            }
        }

        $total = $subtotal;
        $amountPaid = max(0, (float) ($data['advance_amount'] ?? 0));
        $amountRemaining = max(0, round($total - $amountPaid, 2));
        [$firstName, $lastName] = $this->splitGuestName((string) $data['guest_name']);
        $currency = strtoupper($hotel->currency ?: 'USD');
        $paymentMode = trim((string) ($data['payment_mode'] ?? ''));
        $paymentProvider = $this->assertPaymentProvider($hotel, $paymentMode, $data['payment_provider'] ?? null);
        $sendInvoiceToCompany = ! empty($data['send_invoice_to_company']);
        $invoiceSendTo = null;

        if ($sendInvoiceToCompany && ! empty($data['bill_to_company_id'])) {
            $company = HotelCompany::query()
                ->where('hotel_id', $hotel->id)
                ->find($data['bill_to_company_id']);
            $invoiceSendTo = trim((string) ($company?->email ?? '')) ?: null;
        }

        $advance = array_filter([
            'amount' => $amountPaid > 0 ? $amountPaid : null,
            'remaining' => $amountPaid > 0 && $amountRemaining > 0 ? $amountRemaining : null,
            'comments' => $data['advance_comments'] ?? null,
        ], fn ($value) => $value !== null && $value !== '' && $value !== 0.0);

        $payload = array_filter([
            'source' => 'pms',
            'booking_type' => 'group',
            'group_name' => $data['group_name'] ?? null,
            'category' => 'Group',
            'channel' => 'PMS',
            'booked_by' => $data['booked_by'] ?? null,
            'segment' => $data['segment'] ?? null,
            'bill_to' => $data['bill_to'] ?? null,
            'bill_to_company_id' => $data['bill_to_company_id'] ?? null,
            'paymentType' => $paymentMode !== '' ? $paymentMode : null,
            'payment_provider' => $paymentProvider !== '' ? $paymentProvider : null,
            'tax_inclusive' => $taxInclusive,
            'discount_type' => $discountType,
            'discount_percent' => $discountPercent !== null && $discountPercent > 0 ? $discountPercent : null,
            'discount_amount' => $discountAmount !== null && $discountAmount > 0 ? $discountAmount : null,
            'nights' => $nights,
            'advance_payment' => $advance !== [] ? $advance : null,
            'send_invoice_to_company' => $sendInvoiceToCompany ? true : null,
            'invoice_send_to' => $invoiceSendTo,
            'amount_remaining' => $amountPaid > 0 && $amountRemaining > 0 ? $amountRemaining : null,
            'guest' => array_filter([
                'email' => $data['guest_email'] ?? null,
                'phone' => $data['guest_phone'] ?? null,
                'country' => $data['guest_country'] ?? null,
                'country_code' => $data['guest_country_code'] ?? null,
                'city' => $data['guest_city'] ?? null,
                'special_request' => $data['special_request'] ?? null,
            ]),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);

        return tap(CmReservation::query()->create([
            'hotel_id' => $hotel->id,
            'hotel_code' => $hotelCode,
            'booking_id' => $this->generateGroupBookingId($hotel),
            'channel' => 'PMS',
            'action' => 'book',
            'status' => CmReservation::STATUS_CONFIRMED,
            'checkin' => $checkin->format('Y-m-d'),
            'checkout' => $checkout->format('Y-m-d'),
            'guest_first_name' => $firstName,
            'guest_last_name' => $lastName,
            'amount_after_tax' => $total,
            'amount_before_tax' => $total,
            'amount_paid' => $amountPaid,
            'tax' => 0,
            'currency' => $currency,
            'rooms' => $roomPayload,
            'payload' => $payload,
        ]), function (CmReservation $reservation) use ($hotel) {
            $this->inventorySync->syncAfterManualBooking($hotel, $reservation);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function groupAvailabilityError(Hotel $hotel, array $lines, Carbon $checkin, Carbon $checkout): ?string
    {
        return $this->validateGroupLinesAvailability($hotel, $lines, $checkin, $checkout);
    }

    /** @return array{available: int, requested: int, ok: bool, message: string, room_name: string} */
    public function lineAvailability(Hotel $hotel, int $roomId, string $checkin, string $checkout, int $requested): array
    {
        return $this->roomInventory->checkStayAvailability(
            $hotel,
            $roomId,
            Carbon::parse($checkin)->startOfDay(),
            Carbon::parse($checkout)->startOfDay(),
            $requested
        );
    }

    /** @param  array<string, mixed>  $data @return list<CmReservation> */
    public function storeMulti(Hotel $hotel, array $data): array
    {
        $bookings = $data['bookings'] ?? [];

        $availabilityError = $this->validateMultiBookingsAvailability($hotel, $bookings);
        if ($availabilityError !== null) {
            throw ValidationException::withMessages([
                'bookings' => $availabilityError,
            ]);
        }

        $batchId = 'MB-'.strtoupper(Str::random(8));
        $created = [];

        foreach ($bookings as $index => $booking) {
            $this->assertPaymentProvider(
                $hotel,
                (string) ($booking['payment_mode'] ?? ''),
                $booking['payment_provider'] ?? null,
                "bookings.{$index}.payment_provider",
            );

            $created[] = $this->createMultiBooking(
                $hotel,
                $booking,
                $batchId,
                $this->multiBookingFinancial($hotel, $booking),
            );
        }

        return $created;
    }

    /** @param  array<string, mixed>  $data */
    public function storeComplimentary(Hotel $hotel, array $data): CmReservation
    {
        $hotelCode = $this->codes->hotelCode($hotel);
        $room = HotelRoom::query()->where('hotel_id', $hotel->id)->findOrFail($data['hotel_room_id']);
        $plan = HotelRatePlan::query()
            ->where('hotel_id', $hotel->id)
            ->where('hotel_room_id', $room->id)
            ->findOrFail($data['hotel_rate_plan_id']);

        $checkin = Carbon::parse($data['checkin'])->startOfDay();
        $checkout = Carbon::parse($data['checkout'])->startOfDay();
        $nights = max(1, $checkin->diffInDays($checkout));
        $roomCount = max(1, (int) $data['room_count']);
        $guestCount = max(1, (int) $data['guest_count']);

        $availability = $this->roomInventory->checkStayAvailability(
            $hotel,
            $room->id,
            $checkin,
            $checkout,
            $roomCount,
        );

        if (! $availability['ok']) {
            throw ValidationException::withMessages([
                'checkin' => $availability['message'],
            ]);
        }

        [$firstName, $lastName] = $this->splitGuestName((string) $data['guest_name']);
        $referenceRate = (float) $plan->rateForGuestType('international');
        $currency = strtoupper($plan->international_currency ?: $hotel->currency ?: 'USD');
        $reason = trim((string) ($data['complimentary_reason'] ?? ''));
        $reasons = config('hotel_pms.complimentary.reasons', []);
        $reasonLabel = $reasons[$reason] ?? $reason;

        $payload = array_filter([
            'source' => 'pms',
            'booking_type' => 'complimentary',
            'category' => 'Complimentary',
            'complimentary_reason' => $reason !== '' ? $reason : null,
            'complimentary_reason_label' => $reasonLabel !== '' ? $reasonLabel : null,
            'reference_daily_rate' => $referenceRate > 0 ? $referenceRate : null,
            'meal_plan' => $plan->meal_plan ?: null,
            'paymentType' => 'Complimentary',
            'pah' => true,
            'nights' => $nights,
            'guest' => array_filter([
                'email' => $data['guest_email'] ?? null,
                'phone' => $data['guest_phone'] ?? null,
                'country' => $data['guest_country'] ?? null,
                'country_code' => $data['guest_country_code'] ?? null,
                'city' => $data['guest_city'] ?? null,
                'special_request' => $data['special_request'] ?? null,
            ]),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);

        return tap(CmReservation::query()->create([
            'hotel_id' => $hotel->id,
            'hotel_code' => $hotelCode,
            'booking_id' => $this->generateBookingId($hotel),
            'channel' => 'PMS',
            'action' => 'book',
            'status' => CmReservation::STATUS_CONFIRMED,
            'checkin' => $checkin->format('Y-m-d'),
            'checkout' => $checkout->format('Y-m-d'),
            'guest_first_name' => $firstName,
            'guest_last_name' => $lastName,
            'amount_after_tax' => 0,
            'amount_before_tax' => 0,
            'tax' => 0,
            'amount_paid' => 0,
            'currency' => $currency,
            'rooms' => [[
                'roomCode' => $this->codes->roomCode($hotel, $room),
                'roomName' => $room->display_name ?: $room->name,
                'rateplanCode' => $this->codes->rateplanCode($hotel, $plan),
                'mealPlan' => $plan->meal_plan ?: null,
                'numberOfRooms' => $roomCount,
                'occupancy' => [
                    'adults' => $guestCount,
                    'children' => 0,
                ],
            ]],
            'payload' => $payload,
        ]), function (CmReservation $reservation) use ($hotel) {
            $this->inventorySync->syncAfterManualBooking($hotel, $reservation);
        });
    }

    /** @param  array<string, mixed>  $data */
    public function storeCompany(Hotel $hotel, array $data): CmReservation
    {
        $billToName = trim((string) ($data['bill_to'] ?? ''));

        if ($billToName === '') {
            throw ValidationException::withMessages([
                'bill_to' => 'Please enter or import a company name.',
            ]);
        }

        $company = null;

        if (! empty($data['bill_to_company_id'])) {
            $company = HotelCompany::query()
                ->where('hotel_id', $hotel->id)
                ->find($data['bill_to_company_id']);
        }

        $lines = $data['lines'] ?? [];
        $checkin = Carbon::parse($data['checkin'])->startOfDay();
        $checkout = Carbon::parse($data['checkout'])->startOfDay();
        $nights = max(1, $checkin->diffInDays($checkout));
        $taxInclusive = ! empty($data['tax_inclusive']);

        $availabilityError = $this->validateGroupLinesAvailability($hotel, $lines, $checkin, $checkout);
        if ($availabilityError !== null) {
            throw ValidationException::withMessages([
                'lines' => $availabilityError,
            ]);
        }

        $hotelCode = $this->codes->hotelCode($hotel);
        $financial = $this->companyBookingFinancial($hotel, $company, $data);
        $chargeTotals = $this->companyLinesChargeTotals($hotel, $company, $lines, $checkin, $checkout, $taxInclusive);
        [$firstName, $lastName] = $this->splitGuestName((string) $data['guest_name']);
        $total = (float) $financial['amount_after_tax'];
        $amountPaid = max(0, (float) ($financial['amount_paid'] ?? 0));
        $amountRemaining = max(0, round($total - $amountPaid, 2));
        $sendInvoiceToCompany = ! empty($data['send_invoice_to_company']);
        $invoiceEmail = trim((string) ($data['company_contact_email'] ?? ''));

        if ($invoiceEmail === '' && $company !== null) {
            $invoiceEmail = trim((string) ($company->email ?? ''));
        }

        $invoiceSendTo = $sendInvoiceToCompany && $invoiceEmail !== '' ? $invoiceEmail : null;
        $companyContactPhone = trim(
            ($data['company_contact_phone_country_code'] ?? '').ltrim((string) ($data['company_contact_phone'] ?? ''), '0')
        ) ?: null;

        $discountType = $financial['discount_type'] ?? null;
        $discountValue = isset($financial['discount_value']) ? (float) $financial['discount_value'] : null;
        $discountPercent = null;
        $discountAmount = null;

        if ($discountType === 'amount' && $discountValue > 0) {
            $discountAmount = $discountValue;
        } elseif ($discountType === 'percent' && $discountValue > 0) {
            $discountPercent = min(100, $discountValue);
        }

        $advance = array_filter([
            'amount' => $amountPaid > 0 ? $amountPaid : null,
            'remaining' => $amountPaid > 0 && $amountRemaining > 0 ? $amountRemaining : null,
            'comments' => $financial['advance_comments'] ?? null,
        ], fn ($value) => $value !== null && $value !== '' && $value !== 0.0);

        $ratio = $chargeTotals['amount_after_tax'] > 0 ? ($total / $chargeTotals['amount_after_tax']) : 1;
        $roomPayload = [];
        $currency = strtoupper($hotel->currency ?: 'USD');
        $firstPlan = null;

        foreach ($lines as $line) {
            $room = HotelRoom::query()->where('hotel_id', $hotel->id)->findOrFail($line['hotel_room_id']);
            $plan = HotelRatePlan::query()
                ->where('hotel_id', $hotel->id)
                ->where('hotel_room_id', $room->id)
                ->findOrFail($line['hotel_rate_plan_id']);

            if ($firstPlan === null) {
                $firstPlan = $plan;
                $currency = strtoupper($plan->international_currency ?: $hotel->currency ?: 'USD');
            }

            $roomCount = max(1, (int) $line['room_count']);
            $guestCount = max(1, (int) $line['guest_count']);
            $dailyRate = $this->companyLineDailyRate($hotel, $company, $line);

            $roomPayload[] = [
                'roomCode' => $this->codes->roomCode($hotel, $room),
                'roomName' => $room->display_name ?: $room->name,
                'rateplanCode' => $this->codes->rateplanCode($hotel, $plan),
                'mealPlan' => $plan->meal_plan ?: null,
                'numberOfRooms' => $roomCount,
                'dailyRate' => $dailyRate,
                'occupancy' => [
                    'adults' => $guestCount,
                    'children' => 0,
                ],
            ];
        }

        $payload = array_filter([
            'source' => 'pms',
            'booking_type' => 'company',
            'category' => 'Company',
            'segment' => $data['segment'] ?? 'Corporate',
            'bill_to' => $billToName,
            'bill_to_company_id' => $company?->id,
            'company_contact' => array_filter([
                'name' => trim((string) ($data['company_contact_person'] ?? '')) ?: null,
                'email' => trim((string) ($data['company_contact_email'] ?? '')) ?: null,
                'phone' => $companyContactPhone,
            ]),
            'meal_plan' => $firstPlan?->meal_plan ?: null,
            'paymentType' => 'Bill to Company',
            'pah' => false,
            'tax_inclusive' => $taxInclusive,
            'vat_rate' => $chargeTotals['vat_rate'],
            'nights' => $nights,
            'discount_type' => $discountType,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'advance_payment' => $advance !== [] ? $advance : null,
            'amount_remaining' => $amountPaid > 0 && $amountRemaining > 0 ? $amountRemaining : null,
            'send_invoice_to_company' => $sendInvoiceToCompany ? true : null,
            'invoice_send_to' => $invoiceSendTo ?: null,
            'guest' => array_filter([
                'email' => $data['guest_email'] ?? null,
                'phone' => $data['guest_phone'] ?? null,
                'country' => $data['guest_country'] ?? null,
                'country_code' => $data['guest_country_code'] ?? null,
                'city' => $data['guest_city'] ?? null,
                'special_request' => $data['special_request'] ?? null,
            ]),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);

        return tap(CmReservation::query()->create([
            'hotel_id' => $hotel->id,
            'hotel_code' => $hotelCode,
            'booking_id' => $this->generateBookingId($hotel),
            'channel' => 'PMS',
            'action' => 'book',
            'status' => CmReservation::STATUS_CONFIRMED,
            'checkin' => $checkin->format('Y-m-d'),
            'checkout' => $checkout->format('Y-m-d'),
            'guest_first_name' => $firstName,
            'guest_last_name' => $lastName,
            'amount_after_tax' => $total,
            'amount_before_tax' => round($chargeTotals['amount_before_tax'] * $ratio, 2),
            'tax' => round($chargeTotals['tax'] * $ratio, 2),
            'amount_paid' => $amountPaid > 0 ? $amountPaid : null,
            'currency' => $currency,
            'rooms' => $roomPayload,
            'payload' => $payload,
        ]), function (CmReservation $reservation) use ($hotel) {
            $this->inventorySync->syncAfterManualBooking($hotel, $reservation);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{amount_after_tax: float, amount_before_tax: float, tax: float, vat_rate: float}
     */
    private function companyLinesChargeTotals(
        Hotel $hotel,
        ?HotelCompany $company,
        array $lines,
        Carbon $checkin,
        Carbon $checkout,
        bool $taxInclusive,
    ): array {
        $nights = max(1, $checkin->diffInDays($checkout));
        $vatRate = $this->settings->vatRate($hotel);
        $amountAfterTax = 0.0;
        $amountBeforeTax = 0.0;
        $tax = 0.0;

        foreach ($lines as $line) {
            $roomCount = max(1, (int) $line['room_count']);
            $dailyRate = $this->companyLineDailyRate($hotel, $company, $line);
            $charges = $this->calculateRoomCharges(
                $dailyRate,
                null,
                $taxInclusive,
                $vatRate,
                $nights,
                $roomCount,
            );
            $amountAfterTax += $charges['amount_after_tax'];
            $amountBeforeTax += $charges['amount_before_tax'];
            $tax += $charges['tax'];
        }

        return [
            'amount_after_tax' => round($amountAfterTax, 2),
            'amount_before_tax' => round($amountBeforeTax, 2),
            'tax' => round($tax, 2),
            'vat_rate' => $vatRate,
        ];
    }

    /** @param  array<string, mixed>  $line */
    private function companyLineDailyRate(Hotel $hotel, ?HotelCompany $company, array $line): float
    {
        if ($company !== null) {
            $planId = (string) ($line['hotel_rate_plan_id'] ?? '');
            $rates = is_array($company->contracted_rates) ? $company->contracted_rates : [];

            if ($planId !== '' && isset($rates[$planId]) && (float) $rates[$planId] > 0) {
                return (float) $rates[$planId];
            }
        }

        return $this->multiBookingDailyRate($hotel, $line);
    }

    /** @param  array<string, mixed>  $data @return array<string, mixed> */
    private function companyBookingFinancial(Hotel $hotel, ?HotelCompany $company, array $data): array
    {
        $checkin = Carbon::parse($data['checkin'])->startOfDay();
        $checkout = Carbon::parse($data['checkout'])->startOfDay();
        $taxInclusive = ! empty($data['tax_inclusive']);
        $grossTotal = $this->companyLinesChargeTotals(
            $hotel,
            $company,
            $data['lines'] ?? [],
            $checkin,
            $checkout,
            $taxInclusive,
        )['amount_after_tax'];

        $discountType = in_array($data['discount_type'] ?? '', ['percent', 'amount'], true)
            ? $data['discount_type']
            : 'percent';
        $discountValue = max(0, (float) ($data['discount_value'] ?? $data['discount_percent'] ?? 0));
        $discountAmount = 0.0;

        if ($discountType === 'amount') {
            $discountAmount = min($discountValue, $grossTotal);
        } elseif ($discountValue > 0) {
            $discountAmount = $grossTotal * (min(100, $discountValue) / 100);
        }

        return [
            'amount_after_tax' => max(0, round($grossTotal - $discountAmount, 2)),
            'amount_paid' => max(0, (float) ($data['advance_amount'] ?? 0)),
            'discount_type' => $discountType,
            'discount_value' => $discountValue > 0 ? $discountValue : null,
            'advance_comments' => trim((string) ($data['advance_comments'] ?? '')) ?: null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $allBookings
     * @param  list<array{checkin: string, checkout: string, hotel_room_id: int, room_count: int}>  $otherBookings
     */
    public function multiLineAvailability(
        Hotel $hotel,
        string $checkin,
        string $checkout,
        int $roomId,
        int $roomCount,
        array $otherBookings = []
    ): array {
        $checkinDate = Carbon::parse($checkin)->startOfDay();
        $checkoutDate = Carbon::parse($checkout)->startOfDay();
        $requested = max(1, $roomCount);

        foreach ($otherBookings as $other) {
            if ((int) ($other['hotel_room_id'] ?? 0) !== $roomId) {
                continue;
            }

            $otherCheckin = Carbon::parse($other['checkin'])->startOfDay();
            $otherCheckout = Carbon::parse($other['checkout'])->startOfDay();

            if ($this->dateRangesOverlap($checkinDate, $checkoutDate, $otherCheckin, $otherCheckout)) {
                $requested += max(1, (int) ($other['room_count'] ?? 1));
            }
        }

        return $this->roomInventory->checkStayAvailability($hotel, $roomId, $checkinDate, $checkoutDate, $requested);
    }

    /** @param  array<string, mixed>  $data @param  array<string, mixed>  $financial */
    private function createMultiBooking(Hotel $hotel, array $data, string $batchId, array $financial = []): CmReservation
    {
        $hotelCode = $this->codes->hotelCode($hotel);
        $room = HotelRoom::query()->where('hotel_id', $hotel->id)->findOrFail($data['hotel_room_id']);
        $plan = HotelRatePlan::query()
            ->where('hotel_id', $hotel->id)
            ->where('hotel_room_id', $room->id)
            ->findOrFail($data['hotel_rate_plan_id']);

        $checkin = Carbon::parse($data['checkin'])->startOfDay();
        $checkout = Carbon::parse($data['checkout'])->startOfDay();
        $nights = max(1, $checkin->diffInDays($checkout));
        $roomCount = max(1, (int) $data['room_count']);
        $guestCount = max(1, (int) $data['guest_count']);
        $dailyRate = $this->multiBookingDailyRate($hotel, $data);
        $taxInclusive = ! empty($data['tax_inclusive']);
        $vatRate = $this->settings->vatRate($hotel);
        $charges = $this->calculateRoomCharges($dailyRate, null, $taxInclusive, $vatRate, $nights, $roomCount);
        $grossTotal = $charges['amount_after_tax'];
        $total = isset($financial['amount_after_tax'])
            ? (float) $financial['amount_after_tax']
            : $grossTotal;
        $ratio = $grossTotal > 0 ? ($total / $grossTotal) : 1;
        $amountBeforeTax = round($charges['amount_before_tax'] * $ratio, 2);
        $tax = round($charges['tax'] * $ratio, 2);
        $amountPaid = max(0, (float) ($financial['amount_paid'] ?? 0));
        $amountRemaining = max(0, round($total - $amountPaid, 2));

        [$firstName, $lastName] = $this->splitGuestName((string) $data['guest_name']);
        $paymentMode = trim((string) ($data['payment_mode'] ?? ''));
        $paymentProvider = trim((string) ($data['payment_provider'] ?? ''));
        $currency = strtoupper($plan->international_currency ?: $hotel->currency ?: 'USD');
        $discountType = $financial['discount_type'] ?? null;
        $discountValue = isset($financial['discount_value']) ? (float) $financial['discount_value'] : null;
        $discountPercent = null;
        $discountAmount = null;

        if ($discountType === 'amount' && $discountValue > 0) {
            $discountAmount = $discountValue;
        } elseif ($discountType === 'percent' && $discountValue > 0) {
            $discountPercent = min(100, $discountValue);
        }

        $advance = array_filter([
            'amount' => $amountPaid > 0 ? $amountPaid : null,
            'remaining' => $amountPaid > 0 && $amountRemaining > 0 ? $amountRemaining : null,
            'comments' => $financial['advance_comments'] ?? null,
        ], fn ($value) => $value !== null && $value !== '' && $value !== 0.0);

        $payload = array_filter([
            'source' => 'pms',
            'booking_type' => 'multi',
            'multi_batch_id' => $batchId,
            'category' => 'Multi',
            'channel' => 'PMS',
            'meal_plan' => $plan->meal_plan ?: null,
            'paymentType' => $paymentMode !== '' ? $paymentMode : null,
            'payment_provider' => $paymentProvider !== '' ? $paymentProvider : null,
            'pah' => $paymentMode === 'Cash',
            'tax_inclusive' => $taxInclusive,
            'vat_rate' => $charges['vat_rate'],
            'daily_rate' => $dailyRate,
            'daily_tax' => $charges['daily_tax'],
            'nights' => $nights,
            'discount_type' => $discountType,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'multi_discount_total' => $financial['multi_discount_total'] ?? null,
            'multi_gross_total' => $financial['multi_gross_total'] ?? null,
            'advance_payment' => $advance !== [] ? $advance : null,
            'amount_remaining' => $amountPaid > 0 && $amountRemaining > 0 ? $amountRemaining : null,
            'guest' => array_filter([
                'email' => $data['guest_email'] ?? null,
                'phone' => $data['guest_phone'] ?? null,
                'country' => $data['guest_country'] ?? null,
                'country_code' => $data['guest_country_code'] ?? null,
                'city' => $data['guest_city'] ?? null,
            ]),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);

        return tap(CmReservation::query()->create([
            'hotel_id' => $hotel->id,
            'hotel_code' => $hotelCode,
            'booking_id' => $this->generateBookingId($hotel),
            'channel' => 'PMS',
            'action' => 'book',
            'status' => CmReservation::STATUS_CONFIRMED,
            'checkin' => $checkin->format('Y-m-d'),
            'checkout' => $checkout->format('Y-m-d'),
            'guest_first_name' => $firstName,
            'guest_last_name' => $lastName,
            'amount_after_tax' => $total,
            'amount_before_tax' => $amountBeforeTax,
            'tax' => $tax,
            'amount_paid' => $amountPaid > 0 ? $amountPaid : null,
            'currency' => $currency,
            'rooms' => [[
                'roomCode' => $this->codes->roomCode($hotel, $room),
                'roomName' => $room->display_name ?: $room->name,
                'rateplanCode' => $this->codes->rateplanCode($hotel, $plan),
                'mealPlan' => $plan->meal_plan ?: null,
                'numberOfRooms' => $roomCount,
                'occupancy' => [
                    'adults' => $guestCount,
                    'children' => 0,
                ],
            ]],
            'payload' => $payload,
        ]), function (CmReservation $reservation) use ($hotel) {
            $this->inventorySync->syncAfterManualBooking($hotel, $reservation);
        });
    }

    /** @param  array<string, mixed>  $data @return array<string, mixed> */
    private function multiBookingFinancial(Hotel $hotel, array $data): array
    {
        $grossTotal = $this->multiBookingGrossTotal($hotel, $data);
        $discountType = in_array($data['discount_type'] ?? '', ['percent', 'amount'], true)
            ? $data['discount_type']
            : 'percent';
        $discountValue = max(0, (float) ($data['discount_value'] ?? $data['discount_percent'] ?? 0));
        $discountAmount = 0.0;

        if ($discountType === 'amount') {
            $discountAmount = min($discountValue, $grossTotal);
        } elseif ($discountValue > 0) {
            $discountAmount = $grossTotal * (min(100, $discountValue) / 100);
        }

        return [
            'amount_after_tax' => max(0, round($grossTotal - $discountAmount, 2)),
            'amount_paid' => max(0, (float) ($data['advance_amount'] ?? 0)),
            'discount_type' => $discountType,
            'discount_value' => $discountValue > 0 ? $discountValue : null,
            'advance_comments' => trim((string) ($data['advance_comments'] ?? '')) ?: null,
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function multiBookingDailyRate(Hotel $hotel, array $data): float
    {
        $room = HotelRoom::query()->where('hotel_id', $hotel->id)->findOrFail($data['hotel_room_id']);
        $plan = HotelRatePlan::query()
            ->where('hotel_id', $hotel->id)
            ->where('hotel_room_id', $room->id)
            ->findOrFail($data['hotel_rate_plan_id']);

        return (float) $plan->rateForGuestType('international');
    }

    /** @param  array<string, mixed>  $data */
    private function multiBookingGrossTotal(Hotel $hotel, array $data): float
    {
        $checkin = Carbon::parse($data['checkin'])->startOfDay();
        $checkout = Carbon::parse($data['checkout'])->startOfDay();
        $nights = max(1, $checkin->diffInDays($checkout));
        $roomCount = max(1, (int) $data['room_count']);
        $dailyRate = $this->multiBookingDailyRate($hotel, $data);
        $taxInclusive = ! empty($data['tax_inclusive']);
        $vatRate = $this->settings->vatRate($hotel);

        return $this->calculateRoomCharges(
            $dailyRate,
            null,
            $taxInclusive,
            $vatRate,
            $nights,
            $roomCount,
        )['amount_after_tax'];
    }

    private function assertPaymentProvider(
        Hotel $hotel,
        string $paymentMode,
        mixed $paymentProvider,
        string $errorKey = 'payment_provider',
    ): string {
        $paymentProvider = trim((string) ($paymentProvider ?? ''));
        $providerOptions = $this->settings->paymentProviderOptions($hotel, $paymentMode);

        if ($providerOptions !== [] && $paymentProvider === '') {
            throw ValidationException::withMessages([
                $errorKey => 'Please select a '.$this->settings->paymentProviderLabel($paymentMode).'.',
            ]);
        }

        if ($paymentProvider !== '' && ! in_array($paymentProvider, $providerOptions, true)) {
            throw ValidationException::withMessages([
                $errorKey => 'Selected payment provider is not valid.',
            ]);
        }

        return $paymentProvider;
    }

    /** @param  array<string, mixed>  $data */
    public function store(Hotel $hotel, array $data): CmReservation
    {
        $hotelCode = $this->codes->hotelCode($hotel);
        $room = HotelRoom::query()->where('hotel_id', $hotel->id)->findOrFail($data['hotel_room_id']);
        $plan = HotelRatePlan::query()
            ->where('hotel_id', $hotel->id)
            ->where('hotel_room_id', $room->id)
            ->findOrFail($data['hotel_rate_plan_id']);

        $checkin = Carbon::parse($data['checkin'])->startOfDay();
        $checkout = Carbon::parse($data['checkout'])->startOfDay();
        $nights = max(1, $checkin->diffInDays($checkout));
        $roomCount = max(1, (int) $data['room_count']);
        $guestCount = max(1, (int) $data['guest_count']);
        $dailyRate = (float) $data['daily_rate'];
        $dailyTax = array_key_exists('daily_tax', $data) ? (float) $data['daily_tax'] : null;
        $taxInclusive = ! empty($data['tax_inclusive']);
        $vatRate = $this->settings->vatRate($hotel);
        $charges = $this->calculateRoomCharges($dailyRate, $dailyTax, $taxInclusive, $vatRate, $nights, $roomCount);
        $total = $charges['amount_after_tax'];
        $taxTotal = $charges['tax'];

        [$firstName, $lastName] = $this->splitGuestName((string) $data['guest_name']);
        $paymentMode = trim((string) ($data['payment_mode'] ?? ''));
        $paymentProvider = $this->assertPaymentProvider($hotel, $paymentMode, $data['payment_provider'] ?? null);

        $guestType = ($data['guest_type'] ?? 'international') === 'local' ? 'local' : 'international';
        $currency = $guestType === 'local'
            ? strtoupper($plan->local_currency ?: $hotel->currency ?: 'USD')
            : strtoupper($plan->international_currency ?: $hotel->currency ?: 'USD');

        $payload = array_filter([
            'source' => 'pms',
            'meal_plan' => $plan->meal_plan ?: null,
            'guest_type' => $guestType,
            'booked_by' => $data['booked_by'] ?? null,
            'segment' => $data['segment'] ?? null,
            'bill_to' => $data['bill_to'] ?? null,
            'bill_to_company_id' => $data['bill_to_company_id'] ?? null,
            'paymentType' => $paymentMode !== '' ? $paymentMode : null,
            'payment_provider' => $paymentProvider !== '' ? $paymentProvider : null,
            'pah' => $paymentMode === 'Cash',
            'tax_inclusive' => $taxInclusive,
            'vat_rate' => $charges['vat_rate'],
            'daily_rate' => $dailyRate,
            'daily_tax' => $charges['daily_tax'],
            'nights' => $nights,
            'room_unit_id' => $data['room_unit_id'] ?? null,
            'guest' => array_filter([
                'email' => $data['guest_email'] ?? null,
                'phone' => $data['guest_phone'] ?? null,
                'gender' => $data['guest_gender'] ?? null,
                'vip' => ! empty($data['guest_vip']),
                'country' => $data['guest_country'] ?? null,
                'country_code' => $data['guest_country_code'] ?? null,
                'city' => $data['guest_city'] ?? null,
                'address' => $data['guest_address'] ?? null,
                'special_request' => $data['special_request'] ?? null,
                'identity_type' => $data['identity_type'] ?? null,
                'identity_detail' => $data['identity_detail'] ?? null,
            ]),
            'send_payment_link' => ! empty($data['send_payment_link']),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);

        return tap(CmReservation::query()->create([
            'hotel_id' => $hotel->id,
            'hotel_code' => $hotelCode,
            'booking_id' => $this->generateBookingId($hotel),
            'channel' => 'Direct',
            'action' => 'book',
            'status' => CmReservation::STATUS_CONFIRMED,
            'checkin' => $checkin->format('Y-m-d'),
            'checkout' => $checkout->format('Y-m-d'),
            'guest_first_name' => $firstName,
            'guest_last_name' => $lastName,
            'amount_after_tax' => $total,
            'amount_before_tax' => $charges['amount_before_tax'],
            'tax' => $taxTotal,
            'currency' => $currency,
            'rooms' => [[
                'roomCode' => $this->codes->roomCode($hotel, $room),
                'roomName' => $room->display_name ?: $room->name,
                'rateplanCode' => $this->codes->rateplanCode($hotel, $plan),
                'mealPlan' => $plan->meal_plan ?: null,
                'numberOfRooms' => $roomCount,
                'occupancy' => [
                    'adults' => $guestCount,
                    'children' => 0,
                ],
            ]],
            'payload' => $payload,
        ]), function (CmReservation $reservation) use ($hotel) {
            $this->inventorySync->syncAfterManualBooking($hotel, $reservation);
        });
    }

    /** @return array{0: string, 1: string} */
    private function splitGuestName(string $name): array
    {
        $name = trim($name);

        if ($name === '') {
            return ['Guest', ''];
        }

        $parts = preg_split('/\s+/', $name, 2) ?: [];

        return [
            $parts[0],
            $parts[1] ?? '',
        ];
    }

    private function generateBookingId(Hotel $hotel): string
    {
        $prefix = 'PMS-'.now()->format('Ymd').'-';

        do {
            $bookingId = $prefix.strtoupper(Str::random(6));
        } while (CmReservation::query()->where('booking_id', $bookingId)->exists());

        return $bookingId;
    }

    private function generateGroupBookingId(Hotel $hotel): string
    {
        $prefix = 'GRP-'.now()->format('Ymd').'-';

        do {
            $bookingId = $prefix.strtoupper(Str::random(6));
        } while (CmReservation::query()->where('booking_id', $bookingId)->exists());

        return $bookingId;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function validateGroupLinesAvailability(Hotel $hotel, array $lines, Carbon $checkin, Carbon $checkout): ?string
    {
        $requestedByRoom = [];

        foreach ($lines as $line) {
            $roomId = (int) ($line['hotel_room_id'] ?? 0);
            $requestedByRoom[$roomId] = ($requestedByRoom[$roomId] ?? 0) + max(1, (int) ($line['room_count'] ?? 1));
        }

        foreach ($requestedByRoom as $roomId => $requested) {
            $result = $this->roomInventory->checkStayAvailability($hotel, $roomId, $checkin, $checkout, $requested);

            if (! $result['ok']) {
                return $result['message'];
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $bookings
     */
    private function validateMultiBookingsAvailability(Hotel $hotel, array $bookings): ?string
    {
        foreach ($bookings as $index => $booking) {
            $roomId = (int) ($booking['hotel_room_id'] ?? 0);
            $checkin = Carbon::parse($booking['checkin'])->startOfDay();
            $checkout = Carbon::parse($booking['checkout'])->startOfDay();
            $requested = max(1, (int) ($booking['room_count'] ?? 1));

            foreach ($bookings as $otherIndex => $other) {
                if ($otherIndex === $index) {
                    continue;
                }

                if ((int) ($other['hotel_room_id'] ?? 0) !== $roomId) {
                    continue;
                }

                $otherCheckin = Carbon::parse($other['checkin'])->startOfDay();
                $otherCheckout = Carbon::parse($other['checkout'])->startOfDay();

                if ($this->dateRangesOverlap($checkin, $checkout, $otherCheckin, $otherCheckout)) {
                    $requested += max(1, (int) ($other['room_count'] ?? 1));
                }
            }

            $result = $this->roomInventory->checkStayAvailability($hotel, $roomId, $checkin, $checkout, $requested);

            if (! $result['ok']) {
                return 'Booking '.($index + 1).': '.$result['message'];
            }
        }

        return null;
    }

    private function dateRangesOverlap(Carbon $aStart, Carbon $aEnd, Carbon $bStart, Carbon $bEnd): bool
    {
        return $aStart->lt($bEnd) && $bStart->lt($aEnd);
    }
}
