<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_bar_purchase_lists', function (Blueprint $table) {
            $table->foreignId('hotel_department_id')
                ->nullable()
                ->after('hotel_id')
                ->constrained('hotel_departments')
                ->nullOnDelete();
        });

        Schema::table('hotel_pms_service_items', function (Blueprint $table) {
            $table->decimal('expected_unit_price', 12, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_pms_service_items', function (Blueprint $table) {
            $table->dropColumn('expected_unit_price');
        });

        Schema::table('hotel_bar_purchase_lists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hotel_department_id');
        });
    }
};
