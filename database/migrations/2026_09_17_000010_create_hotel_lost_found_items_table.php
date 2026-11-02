<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_lost_found_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('reference_no', 32);
            $table->string('entry_type', 16)->default('found');
            $table->string('item_name');
            $table->string('category', 32)->default('other');
            $table->foreignId('hotel_room_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('room_no', 64)->nullable();
            $table->string('location', 128)->nullable();
            $table->string('storage_location', 128)->nullable();
            $table->string('guest_name')->nullable();
            $table->string('guest_phone', 64)->nullable();
            $table->string('guest_email')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 32)->default('stored');
            $table->string('claimed_by_name')->nullable();
            $table->text('closure_notes')->nullable();
            $table->timestamp('logged_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hotel_id', 'reference_no']);
            $table->index(['hotel_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_lost_found_items');
    }
};
