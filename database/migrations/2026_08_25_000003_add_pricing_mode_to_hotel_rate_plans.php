<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_rate_plans', function (Blueprint $table) {
            $table->string('pricing_mode', 20)->default('both')->after('international_currency');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_rate_plans', function (Blueprint $table) {
            $table->dropColumn('pricing_mode');
        });
    }
};
