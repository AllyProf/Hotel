<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_store_consumption_items', function (Blueprint $table) {
            $table->foreignId('hotel_room_unit_id')
                ->nullable()
                ->after('hotel_store_master_item_id')
                ->constrained('hotel_room_units')
                ->nullOnDelete();
            $table->string('room_no', 64)->nullable()->after('hotel_room_unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_store_consumption_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hotel_room_unit_id');
            $table->dropColumn('room_no');
        });
    }
};
