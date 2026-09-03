<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_cm_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('channel')->nullable();
            $table->string('booking_id')->nullable();
            $table->string('guest_name')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('direction', 20)->default('inbound');
            $table->timestamp('sent_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['hotel_id', 'external_id']);
            $table->index(['hotel_id', 'sent_at']);
            $table->index(['hotel_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_cm_messages');
    }
};
