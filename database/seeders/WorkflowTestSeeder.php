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

class WorkflowTestSeeder extends Seeder
{
    public function run(): void
    {
        $plan = Plan::query()->where('slug', 'enterprise')->first()
            ?? Plan::query()->where('slug', 'professional')->first()
            ?? Plan::query()->first();

        if ($plan === null) {
            $this->command?->error('No plan found. Run: php artisan db:seed --class=PlanSeeder');

            return;
        }

        $demoHotel = $this->ensureDemoHotel($plan);
        $this->ensureDemoRooms($demoHotel);

        $seeded = 0;

        Hotel::query()->orderBy('id')->each(function (Hotel $hotel) use (&$seeded) {
            $this->seedFrontDeskSettings($hotel);

            $room = $this->primaryBookableRoom($hotel);

            if ($room === null) {
                $this->command?->warn("Skipped {$hotel->name}: no room units configured.");

                return;
            }

            $booking = $this->seedNewBooking($hotel, $room);
            $seeded++;

            $this->command?->info("{$hotel->name}: {$booking->booking_id} — {$booking->guestName()} (check-in {$booking->checkin?->format('Y-m-d')})");
        });

        $this->command?->newLine();

        if ($seeded === 0) {
            $this->command?->error('No bookings created. Add room numbers under Settings → Rooms for your hotel.');

            return;
        }

        $this->command?->info("Workflow test ready — {$seeded} arrival(s) for today.");
        $this->command?->info('Demo login: demo@grandhotel.com / password');
        $this->command?->info('Or use your hotel login — each hotel gets its own WF-{id}-001 booking.');
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

    private function ensureDemoRooms(Hotel $hotel): void
    {
        $room = HotelRoom::updateOrCreate(
            ['hotel_id' => $hotel->id, 'name' => 'standard-room'],
            [
                'is_enabled' => true,
                'rank' => 0,
                'display_name' => 'Standard Room',
                'description' => 'Standard Room',
                'room_count' => 5,
                'min_occupancy' => 1,
                'max_occupancy' => 2,
                'amenities' => ['wifi', 'ac', 'tv'],
            ]
        );

        foreach (['101', '102', '103', '104', '105'] as $number) {
            HotelRoomUnit::updateOrCreate(
                ['hotel_id' => $hotel->id, 'room_number' => $number],
                [
                    'hotel_room_id' => $room->id,
                    'label' => $number,
                    'status' => HotelRoomUnit::STATUS_AVAILABLE,
                ]
            );
        }
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

    private function primaryBookableRoom(Hotel $hotel): ?HotelRoom
    {
        return $hotel->rooms()
            ->where('is_enabled', true)
            ->with(['units' => fn ($query) => $query->orderBy('room_number')])
            ->orderBy('rank')
            ->orderBy('id')
            ->get()
            ->first(fn (HotelRoom $room) => $room->units->isNotEmpty());
    }

    private function seedNewBooking(Hotel $hotel, HotelRoom $room): CmReservation
    {
        $today = Carbon::today();
        $codes = app(ChannelManagerCodeResolver::class);
        $hotelCode = $codes->hotelCode($hotel);
        $roomCode = $codes->roomCode($hotel, $room);
        $bookingId = 'WF-'.$hotel->id.'-001';
        $amount = 240.0;

        return CmReservation::query()->create([
            'hotel_id' => $hotel->id,
            'hotel_code' => $hotelCode,
            'booking_id' => $bookingId,
            'channel' => 'Booking.com',
            'action' => 'book',
            'status' => CmReservation::STATUS_CONFIRMED,
            'pms_status' => CmReservation::PMS_RESERVED,
            'room_unit_id' => null,
            'checkin' => $today->toDateString(),
            'checkout' => $today->copy()->addDays(2)->toDateString(),
            'guest_first_name' => 'Sarah',
            'guest_last_name' => 'Johnson',
            'amount_after_tax' => $amount,
            'amount_paid' => 0,
            'amount_before_tax' => round($amount * 0.85, 2),
            'tax' => round($amount * 0.15, 2),
            'currency' => $hotel->currency ?? 'USD',
            'rooms' => [[
                'roomCode' => $roomCode,
                'roomName' => $room->display_name ?: $room->name,
                'rateplanCode' => $roomCode.'-s-ep',
                'mealPlan' => 'EP',
                'numberOfRooms' => 1,
                'occupancy' => ['adults' => 2, 'children' => 0],
            ]],
            'payload' => [
                'source' => 'workflow_test',
                'pah' => true,
                'guest' => [
                    'email' => 'sarah.johnson@example.com',
                    'phone' => '+255700222001',
                ],
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
