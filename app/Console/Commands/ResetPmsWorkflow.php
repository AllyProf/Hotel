<?php

namespace App\Console\Commands;

use App\Models\HotelRoomUnit;
use Database\Seeders\WorkflowTestSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetPmsWorkflow extends Command
{
    protected $signature = 'pms:reset-workflow
                            {--force : Skip confirmation prompt}
                            {--full : Also truncate inventory tables}
                            {--keep-guests : Keep hotel_guests records}';

    protected $description = 'Clear PMS booking data and seed one fresh booking for end-to-end workflow testing';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Delete all bookings, payments, and front-desk logs?', true)) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        $this->info('Clearing PMS workflow data…');

        Schema::disableForeignKeyConstraints();

        $tables = [
            'hotel_payment_links',
            'hotel_logs',
            'cm_reservations',
        ];

        if (! $this->option('keep-guests')) {
            $tables[] = 'hotel_guests';
        }

        if ($this->option('full')) {
            $tables[] = 'hotel_room_inventories';
            $tables[] = 'hotel_rate_inventories';
            $tables[] = 'hotel_inventory_days';
        }

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
                $this->line("  Truncated {$table}");
            }
        }

        Schema::enableForeignKeyConstraints();

        HotelRoomUnit::query()->update(['status' => HotelRoomUnit::STATUS_AVAILABLE]);
        $this->line('  Reset all room units to available');

        $this->newLine();
        $this->call('db:seed', ['--class' => WorkflowTestSeeder::class]);

        return self::SUCCESS;
    }
}
