<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_bar_purchase_lists', function (Blueprint $table) {
            $table->decimal('market_budget_return_amount', 12, 2)->nullable()->after('market_budget_amount');
            $table->foreignId('market_budget_return_expense_id')
                ->nullable()
                ->after('market_budget_return_amount')
                ->constrained('hotel_expenses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hotel_bar_purchase_lists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('market_budget_return_expense_id');
            $table->dropColumn('market_budget_return_amount');
        });
    }
};
