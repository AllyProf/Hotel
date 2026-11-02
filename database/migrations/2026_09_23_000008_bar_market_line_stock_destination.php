<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_bar_purchase_list_lines', function (Blueprint $table) {
            $table->string('market_stock_destination', 32)->nullable()->after('market_sell_option_prices');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_bar_purchase_list_lines', function (Blueprint $table) {
            $table->dropColumn('market_stock_destination');
        });
    }
};
