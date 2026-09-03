<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('action_type');
            $table->string('category', 40)->default('general');
            $table->string('booking_id')->nullable();
            $table->string('guest_name')->nullable();
            $table->string('folio_no')->nullable();
            $table->string('room_no')->nullable();
            $table->foreignId('hotel_room_id')->nullable()->constrained('hotel_rooms')->nullOnDelete();
            $table->text('details')->nullable();
            $table->string('changed_by')->nullable();
            $table->timestamp('logged_at')->useCurrent();
            $table->timestamps();

            $table->index(['hotel_id', 'logged_at']);
            $table->index(['hotel_id', 'category']);
            $table->index(['hotel_id', 'booking_id']);
            $table->index(['hotel_id', 'folio_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_logs');
    }
};
