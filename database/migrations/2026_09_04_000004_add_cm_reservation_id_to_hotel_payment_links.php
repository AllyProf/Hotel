<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_payment_links', function (Blueprint $table) {
            $table->foreignId('cm_reservation_id')
                ->nullable()
                ->after('hotel_id')
                ->constrained('cm_reservations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hotel_payment_links', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cm_reservation_id');
        });
    }
};
