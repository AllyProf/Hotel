<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_bar_purchase_lists', function (Blueprint $table) {
            $table->string('market_stock_destination', 32)->nullable()->after('market_budget_amount');
            $table->timestamp('market_stock_posted_at')->nullable()->after('market_stock_destination');
            $table->foreignId('market_stock_posted_by_user_id')->nullable()->after('market_stock_posted_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('hotel_pms_service_items', function (Blueprint $table) {
            $table->decimal('store_stock_on_hand', 12, 2)->nullable()->after('stock_on_hand');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_pms_service_items', function (Blueprint $table) {
            $table->dropColumn('store_stock_on_hand');
        });

        Schema::table('hotel_bar_purchase_lists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('market_stock_posted_by_user_id');
            $table->dropColumn(['market_stock_destination', 'market_stock_posted_at']);
        });
    }
};
