<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_bar_purchase_lists', function (Blueprint $table) {
            $table->string('recipient_status', 32)->default('awaiting')->after('status');
            $table->text('recipient_notes')->nullable()->after('notes');
            $table->string('budget_currency', 3)->nullable()->after('recipient_notes');
            $table->decimal('budget_total', 14, 2)->nullable()->after('budget_currency');
            $table->timestamp('received_at')->nullable()->after('sent_at');
            $table->timestamp('budget_prepared_at')->nullable()->after('received_at');
            $table->string('budget_prepared_by_name', 120)->nullable()->after('budget_prepared_at');
            $table->string('budget_prepared_by_email', 160)->nullable()->after('budget_prepared_by_name');
        });

        Schema::table('hotel_bar_purchase_list_lines', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 2)->nullable()->after('line_notes');
            $table->decimal('line_budget', 14, 2)->nullable()->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_bar_purchase_list_lines', function (Blueprint $table) {
            $table->dropColumn(['unit_cost', 'line_budget']);
        });

        Schema::table('hotel_bar_purchase_lists', function (Blueprint $table) {
            $table->dropColumn([
                'recipient_status',
                'recipient_notes',
                'budget_currency',
                'budget_total',
                'received_at',
                'budget_prepared_at',
                'budget_prepared_by_name',
                'budget_prepared_by_email',
            ]);
        });
    }
};
