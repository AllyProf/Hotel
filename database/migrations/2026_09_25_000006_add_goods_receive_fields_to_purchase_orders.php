<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_purchase_orders', function (Blueprint $table) {
            $table->timestamp('goods_received_at')->nullable()->after('goods_received');
            $table->foreignId('goods_received_by')->nullable()->after('goods_received_at')->constrained('users')->nullOnDelete();
            $table->text('goods_receive_notes')->nullable()->after('goods_received_by');
        });

        Schema::table('hotel_purchase_order_items', function (Blueprint $table) {
            $table->decimal('received_quantity', 10, 2)->nullable()->after('quantity');
            $table->string('receive_status', 20)->nullable()->after('received_quantity');
            $table->text('receive_notes')->nullable()->after('receive_status');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['received_quantity', 'receive_status', 'receive_notes']);
        });

        Schema::table('hotel_purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['goods_received_by']);
            $table->dropColumn(['goods_received_at', 'goods_received_by', 'goods_receive_notes']);
        });
    }
};
