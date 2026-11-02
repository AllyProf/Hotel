<?php

namespace Database\Seeders;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\HotelRoomUnit;
use App\Models\Plan;
use App\Models\User;
use App\Services\ChannelManager\ChannelManagerCodeResolver;
use App\Services\HotelSettingsService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FrontDeskDemoSeeder extends Seeder
{
    public function run(): void
    {
        $plan = Plan::query()->where('slug', 'enterprise')->first()
            ?? Plan::query()->where('slug', 'professional')->first()
            ?? Plan::query()->first();

        if ($plan === null) {
            $this->command?->warn('No plan found. Run PlanSeeder first.');

            return;
        }

        $demoHotel = $this->ensureDemoHotel($plan);
        $this->seedFrontDeskSettings($demoHotel);
        $this->seedBookingsForHotel($demoHotel, createRoomsIfMissing: true);

        Hotel::query()->whereKeyNot($demoHotel->id)->each(function (Hotel $hotel) {
            $this->seedFrontDeskSettings($hotel);
            $this->seedBookingsForHotel($hotel, createRoomsIfMissing: false);
        });

        $this->command?->info('Front desk demo data seeded for all hotels.');
        $this->command?->info('Dedicated demo login: demo@grandhotel.com / password');
        $this->command?->info('Or use your existing hotel login — today\'s arrivals are included.');
    }

    private function ensureDemoHotel(Plan $plan): Hotel
    {
        $hotel = Hotel::updateOrCreate(
            ['email' => 'demo@grandhotel.com'],
            [
                'name' => 'Demo Grand Hotel',
                'display_name' => 'Demo Grand Hotel',
                'phone' => '+255700000001',
                'address' => '123 Ocean View Road',
                'city' => 'Dar es Salaam',
                'state' => 'Dar es Salaam',
                'country' => 'Tanzania',
                'country_code' => 'TZ',
                'currency' => 'USD',
                'timezone' => 'Africa/Dar_es_Salaam',
                'status' => Hotel::STATUS_ACTIVE,
                'plan_id' => $plan->id,
            ]
        );

        User::updateOrCreate(
            ['email' => 'demo@grandhotel.com'],
            [
                'name' => 'Demo Hotel Admin',
                'password' => 'password',
                'role' => User::ROLE_HOTEL_ADMIN,
                'hotel_id' => $hotel->id,
            ]
        );

        return $hotel;
    }

    private function seedFrontDeskSettings(Hotel $hotel): void
    {
        $settings = app(HotelSettingsService::class)->ensureDefaults($hotel);
        $pms = is_array($settings->pms) ? $settings->pms : [];

        $settings->update([
            'pms' => array_merge($pms, [
                'front_desk_checkin_time' => $pms['front_desk_checkin_time'] ?? '14:00',
                'front_desk_checkout_time' => $pms['front_desk_checkout_time'] ?? '11:00',
                'late_checkout_amount' => $pms['late_checkout_amount'] ?? 50,
            ]),
        ]);
    }

    private function seedBookingsForHotel(Hotel $hotel, bool $createRoomsIfMissing): void
    {
        if ($createRoomsIfMissing) {
            $standardRoom = $this->ensureRoom($hotel, 'standard-room', 'Standard Room', 0);
            $deluxeRoom = $this->ensureRoom($hotel, 'deluxe-room', 'Deluxe Room', 1);
            $this->ensureUnits($hotel, $standardRoom, ['101', '102', '103', '104', '105']);
            $this->ensureUnits($hotel, $deluxeRoom, ['201', '202', '203']);
        }

        $rooms = $hotel->rooms()
            ->where('is_enabled', true)
            ->with(['units' => fn ($q) => $q->orderBy('room_number')])
            ->orderBy('rank')
            ->orderBy('id')
            ->get();

        $roomsWithUnits = $rooms->filter(fn (HotelRoom $room) => $room->units->isNotEmpty())->values();

        if ($roomsWithUnits->isEmpty()) {
            if ($createRoomsIfMissing) {
                $this->command?->warn("Skipped {$hotel->name}: room units were not created.");

                return;
            }

            $this->command?->warn("Skipped {$hotel->name}: add room numbers under Settings → Rooms.");

            return;
        }

        $primaryRoom = $roomsWithUnits->first();
        $secondaryRoom = $roomsWithUnits->get(1) ?? $primaryRoom;
        $primaryUnits = $primaryRoom->units->values();
        $secondaryUnits = $secondaryRoom->units->values();

        $unitA = $primaryUnits->get(0);
        $unitB = $primaryUnits->get(1) ?? $unitA;
        $unitC = $primaryUnits->get(2) ?? $unitA;
        $unitD = $secondaryUnits->get(0) ?? $unitA;

        $today = Carbon::today();
        $codes = app(ChannelManagerCodeResolver::class);
        $hotelCode = $codes->hotelCode($hotel);
        $prefix = 'FD-'.$hotel->id;

        $rows = [
            [
                'booking_id' => "{$prefix}-ARR-001",
                'channel' => 'Booking.com',
                'guest_first_name' => 'Sarah',
                'guest_last_name' => 'Johnson',
                'checkin' => $today,
                'checkout' => $today->copy()->addDays(2),
                'room' => $primaryRoom,
                'pms_status' => CmReservation::PMS_RESERVED,
                'room_unit_id' => null,
                'amount' => 240,
            ],
            [
                'booking_id' => "{$prefix}-ARR-002",
                'channel' => 'Expedia',
                'guest_first_name' => 'Michael',
                'guest_last_name' => 'Chen',
                'checkin' => $today,
                'checkout' => $today->copy()->addDay(),
                'room' => $secondaryRoom,
                'pms_status' => CmReservation::PMS_RESERVED,
                'room_unit_id' => null,
                'amount' => 180,
            ],
            [
                'booking_id' => "{$prefix}-ARR-003",
                'channel' => 'Direct',
                'guest_first_name' => 'Amina',
                'guest_last_name' => 'Hassan',
                'checkin' => $today,
                'checkout' => $today->copy()->addDays(3),
                'room' => $primaryRoom,
                'pms_status' => CmReservation::PMS_ASSIGNED,
                'room_unit_id' => $unitC?->id,
                'amount' => 300,
            ],
            [
                'booking_id' => "{$prefix}-DEP-001",
                'channel' => 'Direct',
                'guest_first_name' => 'James',
                'guest_last_name' => 'Wilson',
                'checkin' => $today->copy()->subDays(2),
                'checkout' => $today,
                'room' => $primaryRoom,
                'pms_status' => CmReservation::PMS_CHECKED_IN,
                'room_unit_id' => $unitA?->id,
                'amount' => 220,
                'amount_paid' => 0,
                'checked_in_at' => $today->copy()->subDays(2)->setTime(15, 30),
                'guest' => [
                    'email' => 'james.wilson@example.com',
                    'phone' => '+255700111001',
                ],
            ],
            [
                'booking_id' => "{$prefix}-DEP-002",
                'channel' => 'Booking.com',
                'guest_first_name' => 'Priya',
                'guest_last_name' => 'Sharma',
                'checkin' => $today->copy()->subDay(),
                'checkout' => $today,
                'room' => $secondaryRoom,
                'pms_status' => CmReservation::PMS_CHECKED_IN,
                'room_unit_id' => $unitD?->id,
                'amount' => 195,
                'amount_paid' => 0,
                'checked_in_at' => $today->copy()->subDay()->setTime(14, 10),
                'guest' => [
                    'email' => 'priya.sharma@example.com',
                    'phone' => '+255700111002',
                ],
            ],
            [
                'booking_id' => "{$prefix}-IN-001",
                'channel' => 'Airbnb',
                'guest_first_name' => 'David',
                'guest_last_name' => 'Okonkwo',
                'checkin' => $today->copy()->subDay(),
                'checkout' => $today->copy()->addDays(2),
                'room' => $primaryRoom,
                'pms_status' => CmReservation::PMS_CHECKED_IN,
                'room_unit_id' => $unitB?->id,
                'amount' => 260,
                'checked_in_at' => $today->copy()->subDay()->setTime(16, 0),
            ],
        ];

        foreach ($rows as $row) {
            if ($row['room_unit_id'] === null && in_array($row['pms_status'], [CmReservation::PMS_ASSIGNED, CmReservation::PMS_CHECKED_IN], true)) {
                continue;
            }

            $this->upsertBooking($hotel, $hotelCode, $codes, $row);
        }

        $arrivalCount = CmReservation::query()
            ->where('hotel_id', $hotel->id)
            ->whereDate('checkin', $today)
            ->whereNotIn('pms_status', [CmReservation::PMS_CHECKED_IN, CmReservation::PMS_CHECKED_OUT])
            ->count();

        $this->command?->info("{$hotel->name}: {$arrivalCount} arrival(s) for {$today->toDateString()}");
    }

    /** @param  array<string, mixed>  $row */
    private function upsertBooking(Hotel $hotel, string $hotelCode, ChannelManagerCodeResolver $codes, array $row): void
    {
        /** @var HotelRoom $room */
        $room = $row['room'];
        $roomCode = $codes->roomCode($hotel, $room);
        $unitId = $row['room_unit_id'] ?? null;

        CmReservation::updateOrCreate(
            ['hotel_code' => $hotelCode, 'booking_id' => $row['booking_id']],
            [
                'hotel_id' => $hotel->id,
                'channel' => $row['channel'],
                'action' => 'book',
                'status' => CmReservation::STATUS_CONFIRMED,
                'pms_status' => $row['pms_status'],
                'room_unit_id' => $unitId,
                'checked_in_at' => $row['checked_in_at'] ?? null,
                'checkin' => $row['checkin']->toDateString(),
                'checkout' => $row['checkout']->toDateString(),
                'guest_first_name' => $row['guest_first_name'],
                'guest_last_name' => $row['guest_last_name'],
                'amount_after_tax' => $row['amount'],
                'amount_paid' => array_key_exists('amount_paid', $row) ? $row['amount_paid'] : 0,
                'amount_before_tax' => round($row['amount'] * 0.85, 2),
                'tax' => round($row['amount'] * 0.15, 2),
                'currency' => $hotel->currency ?? 'USD',
                'rooms' => [[
                    'roomCode' => $roomCode,
                    'roomName' => $room->display_name ?: $room->name,
                    'rateplanCode' => $roomCode.'-s-ep',
                    'mealPlan' => 'EP',
                    'numberOfRooms' => 1,
                    'occupancy' => ['adults' => 2, 'children' => 0],
                ]],
                'payload' => array_filter([
                    'source' => 'demo',
                    'room_unit_id' => $unitId,
                    'pah' => in_array($row['channel'], ['Direct', 'Booking.com'], true),
                    'guest' => $row['guest'] ?? null,
                ]),
            ]
        );
    }

    private function ensureRoom(Hotel $hotel, string $name, string $displayName, int $rank): HotelRoom
    {
        return HotelRoom::updateOrCreate(
            ['hotel_id' => $hotel->id, 'name' => $name],
            [
                'is_enabled' => true,
                'rank' => $rank,
                'display_name' => $displayName,
                'description' => $displayName,
                'room_count' => 5,
                'min_occupancy' => 1,
                'max_occupancy' => 2,
                'amenities' => ['wifi', 'ac', 'tv'],
            ]
        );
    }

    /** @return array<string, HotelRoomUnit> */
    private function ensureUnits(Hotel $hotel, HotelRoom $room, array $numbers): array
    {
        $units = [];

        foreach ($numbers as $number) {
            $units[$number] = HotelRoomUnit::updateOrCreate(
                ['hotel_id' => $hotel->id, 'room_number' => $number],
                [
                    'hotel_room_id' => $room->id,
                    'label' => $number,
                    'status' => HotelRoomUnit::STATUS_AVAILABLE,
                ]
            );
        }

        return $units;
    }
}
