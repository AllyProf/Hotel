<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_settings', function (Blueprint $table) {
            $table->json('custom_amenities')->nullable()->after('reservation');
        });

        Schema::table('hotel_rate_plans', function (Blueprint $table) {
            $table->string('local_currency', 3)->nullable()->after('local_base_rate');
            $table->string('international_currency', 3)->nullable()->after('base_rate');
        });

        Schema::create('hotel_room_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_room_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('caption')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_room_photos');

        Schema::table('hotel_rate_plans', function (Blueprint $table) {
            $table->dropColumn(['local_currency', 'international_currency']);
        });

        Schema::table('hotel_settings', function (Blueprint $table) {
            $table->dropColumn('custom_amenities');
        });
    }
};
