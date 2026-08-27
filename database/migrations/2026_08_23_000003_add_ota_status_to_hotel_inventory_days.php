<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_inventory_days', function (Blueprint $table) {
            $table->json('ota_status')->nullable()->after('is_open');
        });

        DB::table('hotel_inventory_days')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $slugs = array_column(config('otas', []), 'slug');
                $status = [];
                foreach ($slugs as $slug) {
                    $status[$slug] = (bool) $row->is_open;
                }

                DB::table('hotel_inventory_days')
                    ->where('id', $row->id)
                    ->update(['ota_status' => json_encode($status)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('hotel_inventory_days', function (Blueprint $table) {
            $table->dropColumn('ota_status');
        });
    }
};
