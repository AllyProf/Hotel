<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PlanSeeder::class);

        User::updateOrCreate(
            ['email' => 'owner@hotelsaas.com'],
            [
                'name' => 'Platform Owner',
                'password' => 'password',
                'role' => User::ROLE_PLATFORM_OWNER,
                'hotel_id' => null,
            ]
        );
    }
}
