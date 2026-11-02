<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_purchase_orders', function (Blueprint $table) {
            $table->string('accounts_status', 20)->nullable()->after('status');
            $table->timestamp('accounts_submitted_at')->nullable()->after('accounts_status');
            $table->timestamp('accounts_reviewed_at')->nullable()->after('accounts_submitted_at');
            $table->foreignId('accounts_reviewed_by_user_id')->nullable()->after('accounts_reviewed_at')
                ->constrained('users')->nullOnDelete();
            $table->text('accounts_notes')->nullable()->after('accounts_reviewed_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accounts_reviewed_by_user_id');
            $table->dropColumn([
                'accounts_status',
                'accounts_submitted_at',
                'accounts_reviewed_at',
                'accounts_notes',
            ]);
        });
    }
};
