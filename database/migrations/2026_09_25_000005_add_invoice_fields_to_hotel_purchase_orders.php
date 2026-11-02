<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_purchase_orders', function (Blueprint $table) {
            $table->string('invoice_number', 80)->nullable()->after('status');
            $table->timestamp('invoiced_at')->nullable()->after('invoice_number');
            $table->boolean('goods_received')->default(false)->after('invoiced_at');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['invoice_number', 'invoiced_at', 'goods_received']);
        });
    }
};
