<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_room_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('room_number', 20)->nullable();
            $table->string('label')->nullable();
            $table->string('status', 20)->default('available');
            $table->timestamps();

            $table->unique(['hotel_id', 'room_number']);
        });

        Schema::table('hotel_rate_plans', function (Blueprint $table) {
            $table->decimal('local_base_rate', 12, 2)->nullable()->after('base_rate');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_rate_plans', function (Blueprint $table) {
            $table->dropColumn('local_base_rate');
        });

        Schema::dropIfExists('hotel_room_units');
    }
};
