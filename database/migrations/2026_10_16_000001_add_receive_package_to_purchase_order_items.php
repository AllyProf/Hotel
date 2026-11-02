<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_purchase_order_items', function (Blueprint $table) {
            $table->foreignId('hotel_store_package_id')
                ->nullable()
                ->after('hotel_store_master_item_id')
                ->constrained('hotel_store_packages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hotel_purchase_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hotel_store_package_id');
        });
    }
};
