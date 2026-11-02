<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_pms_laundry_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_pms_service_id')->constrained('hotel_pms_services')->cascadeOnDelete();
            $table->foreignId('hotel_pms_service_category_id')->nullable()->constrained('hotel_pms_service_categories')->nullOnDelete();
            $table->string('item_name', 120);
            $table->foreignId('hotel_pms_service_package_id')->nullable()->constrained('hotel_pms_service_packages')->nullOnDelete();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->string('currency', 3)->default('TZS');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['hotel_pms_service_id', 'hotel_pms_service_category_id', 'item_name'],
                'laundry_price_item_unique'
            );
        });

        Schema::create('hotel_laundry_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_pms_service_id')->constrained('hotel_pms_services')->cascadeOnDelete();
            $table->foreignId('cm_reservation_id')->nullable()->constrained('cm_reservations')->nullOnDelete();
            $table->string('ticket_no', 40);
            $table->string('room_no', 20)->nullable();
            $table->string('guest_name')->nullable();
            $table->string('status', 24)->default('received');
            $table->boolean('is_express')->default(false);
            $table->unsignedSmallInteger('complimentary_pieces')->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('surcharge_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 3)->default('TZS');
            $table->boolean('posted_to_folio')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hotel_id', 'ticket_no']);
        });

        Schema::create('hotel_laundry_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_laundry_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_pms_service_category_id')->nullable()->constrained('hotel_pms_service_categories')->nullOnDelete();
            $table->string('item_name', 120);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->boolean('is_complimentary')->default(false);
            $table->string('item_notes', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_laundry_order_lines');
        Schema::dropIfExists('hotel_laundry_orders');
        Schema::dropIfExists('hotel_pms_laundry_prices');
    }
};
