<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $allFeatures = array_keys(config('plan_features', []));

        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'price' => 49000,
                'billing_cycle' => 'monthly',
                'max_rooms' => 20,
                'max_users' => 5,
                'max_branches' => 1,
                'description' => 'For small hotels getting started on the platform.',
                'features' => [
                    'property_management_system',
                    'booking_engine_website',
                    'housekeeping_maintenance_system',
                ],
                'sort_order' => 1,
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'price' => 149000,
                'billing_cycle' => 'monthly',
                'max_rooms' => 100,
                'max_users' => 20,
                'max_branches' => 5,
                'description' => 'For growing hotels that need more capacity and modules.',
                'features' => [
                    'property_management_system',
                    'channel_manager',
                    'revenue_management_software',
                    'booking_engine_website',
                    'point_of_sale_system',
                    'reviews_reputation_management',
                    'housekeeping_maintenance_system',
                    'crm_leads_management_system',
                ],
                'sort_order' => 2,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'price' => 349000,
                'billing_cycle' => 'monthly',
                'max_rooms' => 0,
                'max_users' => 0,
                'max_branches' => 0,
                'description' => 'Full platform access for large hotel groups and chains.',
                'features' => $allFeatures,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
