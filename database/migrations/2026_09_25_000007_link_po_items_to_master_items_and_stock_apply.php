<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_purchase_order_items', function (Blueprint $table) {
            $table->foreignId('hotel_store_master_item_id')
                ->nullable()
                ->after('hotel_purchase_order_id')
                ->constrained('hotel_store_master_items')
                ->nullOnDelete();
            $table->timestamp('stock_applied_at')->nullable()->after('receive_notes');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['hotel_store_master_item_id']);
            $table->dropColumn(['hotel_store_master_item_id', 'stock_applied_at']);
        });
    }
};
