<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_purchase_orders', function (Blueprint $table) {
            $table->decimal('budget_return_amount', 12, 2)->nullable()->after('hotel_expense_id');
            $table->foreignId('budget_return_expense_id')
                ->nullable()
                ->after('budget_return_amount')
                ->constrained('hotel_expenses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hotel_purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('budget_return_expense_id');
            $table->dropColumn('budget_return_amount');
        });
    }
};
