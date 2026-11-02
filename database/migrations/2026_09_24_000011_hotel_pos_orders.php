<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_pos_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_pms_service_id')->constrained('hotel_pms_services')->cascadeOnDelete();
            $table->string('status', 24)->default('open');
            $table->boolean('is_complimentary')->default(false);
            $table->string('order_no', 48);
            $table->string('invoice_no', 48)->nullable();
            $table->string('details', 255)->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 8)->default('TZS');
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('payment_mode', 80)->nullable();
            $table->string('payment_provider', 120)->nullable();
            $table->text('payment_notes')->nullable();
            $table->foreignId('cm_reservation_id')->nullable()->constrained('cm_reservations')->nullOnDelete();
            $table->boolean('posted_to_folio')->default(false);
            $table->boolean('stock_deducted')->default(false);
            $table->timestamp('ordered_at');
            $table->timestamp('billed_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hotel_id', 'order_no']);
            $table->index(['hotel_id', 'hotel_pms_service_id', 'status', 'ordered_at'], 'pos_orders_hotel_svc_status_idx');
        });

        Schema::create('hotel_pos_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_pos_order_id')->constrained('hotel_pos_orders')->cascadeOnDelete();
            $table->foreignId('hotel_pms_service_category_id')->nullable()->constrained('hotel_pms_service_categories')->nullOnDelete();
            $table->string('category_name', 120)->nullable();
            $table->foreignId('hotel_pms_service_item_id')->nullable()->constrained('hotel_pms_service_items')->nullOnDelete();
            $table->string('item_name', 160);
            $table->foreignId('hotel_pms_service_package_id')->nullable()->constrained('hotel_pms_service_packages')->nullOnDelete();
            $table->string('sell_unit_label', 80)->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('pieces_per_sell_unit', 12, 4)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_amount', 12, 2)->default(0);
            $table->decimal('tax_rate', 8, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_pos_order_lines');
        Schema::dropIfExists('hotel_pos_orders');
    }
};
