<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_swimming_visits', function (Blueprint $table) {
            $table->decimal('subtotal', 12, 2)->nullable()->after('unit_price');
            $table->string('discount_type', 16)->nullable()->after('subtotal');
            $table->decimal('discount_value', 12, 2)->nullable()->after('discount_type');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('discount_value');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_swimming_visits', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'discount_type', 'discount_value', 'discount_amount']);
        });
    }
};
