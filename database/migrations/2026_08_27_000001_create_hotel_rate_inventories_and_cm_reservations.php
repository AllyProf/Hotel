<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_rate_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_rate_plan_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('local_rate', 12, 2)->nullable();
            $table->decimal('international_rate', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['hotel_rate_plan_id', 'date']);
        });

        Schema::create('cm_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('hotel_code');
            $table->string('booking_id');
            $table->string('channel');
            $table->string('action');
            $table->string('status')->default('confirmed');
            $table->date('checkin')->nullable();
            $table->date('checkout')->nullable();
            $table->string('guest_first_name')->nullable();
            $table->string('guest_last_name')->nullable();
            $table->decimal('amount_after_tax', 12, 2)->nullable();
            $table->decimal('amount_before_tax', 12, 2)->nullable();
            $table->decimal('tax', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->json('rooms')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['hotel_code', 'booking_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cm_reservations');
        Schema::dropIfExists('hotel_rate_inventories');
    }
};
