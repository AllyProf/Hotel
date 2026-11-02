<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_pms_swimming_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_pms_service_id')->constrained('hotel_pms_services')->cascadeOnDelete();
            $table->foreignId('hotel_pms_service_category_id')->constrained('hotel_pms_service_categories')->cascadeOnDelete();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->string('currency', 3)->default('TZS');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hotel_pms_service_id', 'hotel_pms_service_category_id'], 'swim_rate_category_unique');
        });

        Schema::create('hotel_swimming_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_pms_service_id')->constrained('hotel_pms_services')->cascadeOnDelete();
            $table->foreignId('cm_reservation_id')->nullable()->constrained('cm_reservations')->nullOnDelete();
            $table->foreignId('hotel_pms_service_category_id')->nullable()->constrained('hotel_pms_service_categories')->nullOnDelete();
            $table->string('visit_no', 40);
            $table->string('room_no', 20)->nullable();
            $table->string('guest_name')->nullable();
            $table->unsignedSmallInteger('persons')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 3)->default('TZS');
            $table->string('status', 24)->default('in_pool');
            $table->boolean('posted_to_folio')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('entered_at')->nullable();
            $table->timestamp('exited_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hotel_id', 'visit_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_swimming_visits');
        Schema::dropIfExists('hotel_pms_swimming_rates');
    }
};
