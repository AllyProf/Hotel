<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_lost_found_guest_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hotel_id');
            $table->unsignedBigInteger('hotel_lost_found_item_id');
            $table->unsignedBigInteger('cm_reservation_id')->nullable();
            $table->string('email_to');
            $table->string('guest_name')->nullable();
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->timestamps();

            $table->foreign('hotel_id', 'lf_guest_notify_hotel_fk')
                ->references('id')->on('hotels')->cascadeOnDelete();
            $table->foreign('hotel_lost_found_item_id', 'lf_guest_notify_item_fk')
                ->references('id')->on('hotel_lost_found_items')->cascadeOnDelete();
            $table->foreign('cm_reservation_id', 'lf_guest_notify_res_fk')
                ->references('id')->on('cm_reservations')->nullOnDelete();
            $table->foreign('sent_by', 'lf_guest_notify_user_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->index(['hotel_lost_found_item_id', 'cm_reservation_id'], 'lf_notify_item_res_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_lost_found_guest_notifications');
    }
};
