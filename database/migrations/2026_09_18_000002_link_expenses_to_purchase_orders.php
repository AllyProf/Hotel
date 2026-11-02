<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('hotel_purchase_order_id')->nullable()->after('hotel_id');
            $table->foreign('hotel_purchase_order_id', 'hotel_expenses_po_fk')
                ->references('id')->on('hotel_purchase_orders')->nullOnDelete();
        });

        Schema::table('hotel_purchase_orders', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('status');
            $table->unsignedBigInteger('hotel_expense_id')->nullable()->after('paid_at');
            $table->foreign('hotel_expense_id', 'hotel_po_expense_fk')
                ->references('id')->on('hotel_expenses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hotel_purchase_orders', function (Blueprint $table) {
            $table->dropForeign('hotel_po_expense_fk');
            $table->dropColumn(['paid_at', 'hotel_expense_id']);
        });

        Schema::table('hotel_expenses', function (Blueprint $table) {
            $table->dropForeign('hotel_expenses_po_fk');
            $table->dropColumn('hotel_purchase_order_id');
        });
    }
};
