<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_store_master_item_sell_options', function (Blueprint $table) {
            $table->decimal('unit_price', 12, 2)->nullable()->after('pieces_per_sell_unit');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_store_master_item_sell_options', function (Blueprint $table) {
            $table->dropColumn('unit_price');
        });
    }
};
