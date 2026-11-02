<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_pms_services', function (Blueprint $table) {
            $table->boolean('show_in_pos')->default(false)->after('visible_on_be');
        });

        $outletKeys = config('hotel_pos.outlet_template_keys', []);
        if ($outletKeys !== []) {
            DB::table('hotel_pms_services')
                ->whereIn('template_key', $outletKeys)
                ->update(['show_in_pos' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('hotel_pms_services', function (Blueprint $table) {
            $table->dropColumn('show_in_pos');
        });
    }
};
