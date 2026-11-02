<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_bar_purchase_lists', function (Blueprint $table) {
            $table->string('market_payment_type', 40)->nullable()->after('market_completed_by_user_id');
            $table->text('market_completion_notes')->nullable()->after('market_payment_type');
        });

        Schema::table('hotel_bar_purchase_list_lines', function (Blueprint $table) {
            $table->decimal('quantity_purchased', 12, 2)->nullable()->after('line_budget');
            $table->decimal('purchase_unit_price', 12, 2)->nullable()->after('quantity_purchased');
            $table->decimal('selling_unit_price', 12, 2)->nullable()->after('purchase_unit_price');
            $table->string('market_line_notes', 500)->nullable()->after('selling_unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_bar_purchase_list_lines', function (Blueprint $table) {
            $table->dropColumn([
                'quantity_purchased',
                'purchase_unit_price',
                'selling_unit_price',
                'market_line_notes',
            ]);
        });

        Schema::table('hotel_bar_purchase_lists', function (Blueprint $table) {
            $table->dropColumn(['market_payment_type', 'market_completion_notes']);
        });
    }
};
