<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 40)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('photo_path')->nullable();
            $table->decimal('total_value', 14, 2)->default(0);
            $table->unsignedInteger('previous_stays')->default(0);
            $table->timestamps();

            $table->index(['hotel_id', 'email']);
            $table->index(['hotel_id', 'phone']);
            $table->index(['hotel_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_guests');
    }
};
