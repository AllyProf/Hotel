<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_pms_service_items', function (Blueprint $table) {
            $table->decimal('stock_on_hand', 12, 2)->nullable()->after('is_active');
            $table->decimal('reorder_level', 12, 2)->nullable()->after('stock_on_hand');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_pms_service_items', function (Blueprint $table) {
            $table->dropColumn(['stock_on_hand', 'reorder_level']);
        });
    }
};
