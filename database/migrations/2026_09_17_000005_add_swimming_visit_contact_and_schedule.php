<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_swimming_visits', function (Blueprint $table) {
            $table->string('guest_phone', 40)->nullable()->after('guest_name');
            $table->string('guest_email', 160)->nullable()->after('guest_phone');
            $table->date('scheduled_date')->nullable()->after('guest_email');
            $table->boolean('is_complimentary')->default(false)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_swimming_visits', function (Blueprint $table) {
            $table->dropColumn(['guest_phone', 'guest_email', 'scheduled_date', 'is_complimentary']);
        });
    }
};
