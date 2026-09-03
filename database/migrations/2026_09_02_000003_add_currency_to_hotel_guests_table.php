<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_guests', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->after('total_value');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_guests', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
