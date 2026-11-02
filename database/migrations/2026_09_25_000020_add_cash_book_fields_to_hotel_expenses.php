<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_expenses', function (Blueprint $table) {
            $table->string('pos_label', 80)->nullable()->after('vendor');
            $table->string('guest_name', 160)->nullable()->after('pos_label');
            $table->string('room_no', 40)->nullable()->after('guest_name');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_expenses', function (Blueprint $table) {
            $table->dropColumn(['pos_label', 'guest_name', 'room_no']);
        });
    }
};
