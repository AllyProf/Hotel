<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_bar_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_pms_service_id')->constrained('hotel_pms_services')->cascadeOnDelete();
            $table->foreignId('cm_reservation_id')->nullable()->constrained('cm_reservations')->nullOnDelete();
            $table->string('sale_no', 40);
            $table->string('room_no', 40)->nullable();
            $table->string('guest_name', 200)->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 8)->default('TZS');
            $table->string('payment_mode', 80)->nullable();
            $table->string('payment_provider', 80)->nullable();
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->text('payment_notes')->nullable();
            $table->boolean('posted_to_folio')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('sold_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hotel_id', 'sale_no']);
            $table->index(['hotel_id', 'sold_at']);
        });

        Schema::create('hotel_bar_sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_bar_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_pms_service_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name');
            $table->foreignId('hotel_pms_service_package_id')->nullable()->constrained('hotel_pms_service_packages')->nullOnDelete();
            $table->string('sell_unit_label', 80)->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('pieces_per_sell_unit', 12, 4)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_bar_sale_lines');
        Schema::dropIfExists('hotel_bar_sales');
    }
};
