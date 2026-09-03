<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_vendor_id')->constrained('hotel_vendors')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('po_number');
            $table->string('image_path')->nullable();
            $table->decimal('pre_tax', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('status', 20)->default('open');
            $table->timestamps();

            $table->unique(['hotel_id', 'po_number']);
            $table->index(['hotel_id', 'created_at']);
        });

        Schema::create('hotel_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_purchase_order_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('quantity', 10, 2)->default(0);
            $table->decimal('rate', 12, 2)->default(0);
            $table->decimal('pre_tax', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_purchase_order_items');
        Schema::dropIfExists('hotel_purchase_orders');
    }
};
