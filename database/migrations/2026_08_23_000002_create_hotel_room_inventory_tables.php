<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_inventory_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_open')->default(true);
            $table->timestamps();

            $table->unique(['hotel_id', 'date']);
        });

        Schema::create('hotel_room_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_room_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('available_count')->default(0);
            $table->timestamps();

            $table->unique(['hotel_room_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_room_inventories');
        Schema::dropIfExists('hotel_inventory_days');
    }
};
