<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_laundry_orders', function (Blueprint $table) {
            $table->string('payment_mode', 80)->nullable()->after('currency');
            $table->string('payment_provider', 120)->nullable()->after('payment_mode');
            $table->decimal('amount_paid', 12, 2)->default(0)->after('payment_provider');
            $table->string('payment_notes', 500)->nullable()->after('amount_paid');
        });

        Schema::table('hotel_swimming_visits', function (Blueprint $table) {
            $table->string('payment_mode', 80)->nullable()->after('currency');
            $table->string('payment_provider', 120)->nullable()->after('payment_mode');
            $table->decimal('amount_paid', 12, 2)->default(0)->after('payment_provider');
            $table->string('payment_notes', 500)->nullable()->after('amount_paid');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_laundry_orders', function (Blueprint $table) {
            $table->dropColumn(['payment_mode', 'payment_provider', 'amount_paid', 'payment_notes']);
        });

        Schema::table('hotel_swimming_visits', function (Blueprint $table) {
            $table->dropColumn(['payment_mode', 'payment_provider', 'amount_paid', 'payment_notes']);
        });
    }
};
