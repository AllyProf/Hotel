<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_pms_conference_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_pms_service_id')->constrained('hotel_pms_services')->cascadeOnDelete();
            $table->foreignId('hotel_pms_service_category_id')->constrained('hotel_pms_service_categories')->cascadeOnDelete();
            $table->string('label', 120)->nullable();
            $table->unsignedSmallInteger('capacity')->default(0);
            $table->string('pricing_unit', 24)->default('per_day');
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->string('currency', 3)->default('TZS');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('hotel_conference_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_pms_service_id')->constrained('hotel_pms_services')->cascadeOnDelete();
            $table->foreignId('cm_reservation_id')->nullable()->constrained('cm_reservations')->nullOnDelete();
            $table->foreignId('hotel_pms_service_category_id')->nullable()->constrained('hotel_pms_service_categories')->nullOnDelete();
            $table->foreignId('hotel_pms_conference_rate_id')->nullable()->constrained('hotel_pms_conference_rates')->nullOnDelete();
            $table->string('booking_no', 40);
            $table->string('event_name')->nullable();
            $table->string('organizer_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('contact_email', 160)->nullable();
            $table->string('room_no', 20)->nullable();
            $table->string('guest_name')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('delegates')->default(1);
            $table->decimal('billing_quantity', 10, 2)->default(1);
            $table->string('rate_label', 120)->nullable();
            $table->string('pricing_unit', 24)->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->string('discount_type', 16)->nullable();
            $table->decimal('discount_value', 12, 2)->nullable();
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 3)->default('TZS');
            $table->string('payment_mode', 80)->nullable();
            $table->string('payment_provider', 120)->nullable();
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('payment_notes', 500)->nullable();
            $table->string('status', 24)->default('confirmed');
            $table->boolean('posted_to_folio')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hotel_id', 'booking_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_conference_bookings');
        Schema::dropIfExists('hotel_pms_conference_rates');
    }
};
