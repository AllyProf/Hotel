<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_bar_purchase_lists', function (Blueprint $table) {
            $table->string('accounting_status', 32)->nullable()->after('budget_prepared_by_email');
            $table->text('accounting_notes')->nullable()->after('accounting_status');
            $table->timestamp('accounting_reviewed_at')->nullable()->after('accounting_notes');
            $table->foreignId('accounting_reviewed_by_user_id')->nullable()->after('accounting_reviewed_at')
                ->constrained('users')->nullOnDelete();
            $table->string('accounting_reviewed_by_name', 120)->nullable()->after('accounting_reviewed_by_user_id');

            $table->string('market_status', 32)->nullable()->after('accounting_reviewed_by_name');
            $table->timestamp('market_released_at')->nullable()->after('market_status');
            $table->timestamp('market_completed_at')->nullable()->after('market_released_at');
            $table->foreignId('market_completed_by_user_id')->nullable()->after('market_completed_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hotel_bar_purchase_lists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('market_completed_by_user_id');
            $table->dropColumn([
                'market_completed_at',
                'market_released_at',
                'market_status',
                'accounting_reviewed_by_name',
                'accounting_reviewed_by_user_id',
                'accounting_reviewed_at',
                'accounting_notes',
                'accounting_status',
            ]);
        });
    }
};
