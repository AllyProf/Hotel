<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_pos_orders', function (Blueprint $table) {
            $table->foreignId('hotel_company_id')
                ->nullable()
                ->after('cm_reservation_id')
                ->constrained('hotel_companies')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hotel_pos_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hotel_company_id');
        });
    }
};
