<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_bar_purchase_lists', function (Blueprint $table) {
            $table->foreignId('hotel_purchase_order_id')->nullable()->after('market_completed_by_user_id')
                ->constrained('hotel_purchase_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hotel_bar_purchase_lists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hotel_purchase_order_id');
        });
    }
};
