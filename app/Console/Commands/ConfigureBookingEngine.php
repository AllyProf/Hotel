<?php

namespace App\Console\Commands;

use App\Models\Hotel;
use App\Services\BookingEngineService;
use Illuminate\Console\Command;

class ConfigureBookingEngine extends Command
{
    protected $signature = 'hotel:configure-booking-engine {hotel? : Hotel ID (defaults to first hotel)}';

    protected $description = 'Enable the public booking engine and ensure rooms/rates exist for testing';

    public function handle(BookingEngineService $bookingEngine): int
    {
        $hotelId = $this->argument('hotel');

        $hotel = $hotelId
            ? Hotel::query()->with('plan')->find($hotelId)
            : Hotel::query()->with('plan')->orderBy('id')->first();

        if (! $hotel) {
            $this->error('No hotel found.');

            return self::FAILURE;
        }

        if (! $hotel->hasFeature('booking_engine_website')) {
            $this->warn('Plan does not include booking_engine_website — public page may still 404 until the plan is updated.');
        }

        $result = $bookingEngine->configureForTesting($hotel);

        $this->info('Booking engine configured for: '.$hotel->name);
        $this->line('  Slug: '.$result['slug']);
        $this->line('  URL:  '.$result['url']);
        $this->line('  Rooms: '.$result['rooms'].' · Rate plans: '.$result['rate_plans']);

        return self::SUCCESS;
    }
}
