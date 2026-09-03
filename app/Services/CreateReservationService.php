<?php

namespace App\Services;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelRatePlan;
use App\Models\HotelRoom;
use App\Services\ChannelManager\ChannelManagerCodeResolver;
use App\Services\ChannelManager\ReservationInventoryService;
use Carbon\Carbon;
use Illuminate\Support\Str;

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
        $discountPercent = max(0, min(100, (float) ($data['discount_percent'] ?? 0)));

        $availabilityError = $this->validateGroupLinesAvailability($hotel, $data['lines'], $checkin, $checkout);
        if ($availabilityError !== null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
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

        if ($discountPercent > 0) {
            $subtotal = round($subtotal * (1 - ($discountPercent / 100)), 2);
        }

        $total = $subtotal;
        [$firstName, $lastName] = $this->splitGuestName((string) $data['guest_name']);
        $currency = strtoupper($hotel->currency ?: 'USD');

        $advance = array_filter([
            'amount' => isset($data['advance_amount']) ? (float) $data['advance_amount'] : null,
            'payment_mode' => $data['advance_payment_mode'] ?? null,
            'comments' => $data['advance_comments'] ?? null,
            'attachment_path' => $data['advance_attachment_path'] ?? null,
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
            'paymentType' => $data['payment_mode'] ?? null,
            'tax_inclusive' => $taxInclusive,
            'discount_percent' => $discountPercent > 0 ? $discountPercent : null,
            'nights' => $nights,
            'advance_payment' => $advance !== [] ? $advance : null,
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
            throw \Illuminate\Validation\ValidationException::withMessages([
                'bookings' => $availabilityError,
            ]);
        }

        $batchId = 'MB-'.strtoupper(Str::random(8));
        $created = [];

        foreach ($bookings as $booking) {
            $created[] = $this->createMultiBooking($hotel, $booking, $batchId);
        }

        return $created;
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

    /** @param  array<string, mixed>  $data */
    private function createMultiBooking(Hotel $hotel, array $data, string $batchId): CmReservation
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
        $taxInclusive = ! empty($data['tax_inclusive']);
        $total = round($dailyRate * $nights * $roomCount, 2);

        [$firstName, $lastName] = $this->splitGuestName((string) $data['guest_name']);
        $currency = strtoupper($plan->international_currency ?: $hotel->currency ?: 'USD');

        $payload = array_filter([
            'source' => 'pms',
            'booking_type' => 'multi',
            'multi_batch_id' => $batchId,
            'category' => 'Multi',
            'channel' => 'PMS',
            'meal_plan' => $plan->meal_plan ?: null,
            'paymentType' => $data['payment_mode'] ?? null,
            'pah' => ($data['payment_mode'] ?? '') === 'Cash',
            'tax_inclusive' => $taxInclusive,
            'daily_rate' => $dailyRate,
            'nights' => $nights,
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
            'amount_before_tax' => $total,
            'tax' => 0,
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
        $dailyTax = (float) ($data['daily_tax'] ?? 0);
        $taxInclusive = ! empty($data['tax_inclusive']);
        $lineRate = $taxInclusive ? $dailyRate : ($dailyRate + $dailyTax);
        $total = round($lineRate * $nights * $roomCount, 2);
        $taxTotal = $taxInclusive ? 0 : round($dailyTax * $nights * $roomCount, 2);

        [$firstName, $lastName] = $this->splitGuestName((string) $data['guest_name']);

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
            'paymentType' => $data['payment_mode'] ?? null,
            'pah' => ($data['payment_mode'] ?? '') === 'Cash',
            'tax_inclusive' => $taxInclusive,
            'daily_rate' => $dailyRate,
            'daily_tax' => $dailyTax,
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
                'zip' => $data['guest_zip'] ?? null,
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
            'amount_before_tax' => $taxInclusive ? $total : max(0, $total - $taxTotal),
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
