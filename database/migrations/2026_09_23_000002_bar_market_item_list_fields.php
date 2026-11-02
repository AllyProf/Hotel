<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_bar_purchase_lists', function (Blueprint $table) {
            $table->decimal('market_budget_amount', 14, 2)->nullable()->after('market_completion_notes');
        });

        Schema::table('hotel_bar_purchase_list_lines', function (Blueprint $table) {
            $table->boolean('market_item_found')->nullable()->after('market_line_notes');
            $table->decimal('purchase_line_total', 14, 2)->nullable()->after('market_item_found');
            $table->date('expiry_date')->nullable()->after('purchase_line_total');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_bar_purchase_list_lines', function (Blueprint $table) {
            $table->dropColumn(['market_item_found', 'purchase_line_total', 'expiry_date']);
        });

        Schema::table('hotel_bar_purchase_lists', function (Blueprint $table) {
            $table->dropColumn('market_budget_amount');
        });
    }
};
