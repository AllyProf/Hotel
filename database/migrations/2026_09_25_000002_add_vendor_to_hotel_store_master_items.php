<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_store_master_items', function (Blueprint $table) {
            $table->foreignId('hotel_vendor_id')
                ->nullable()
                ->after('hotel_store_category_id')
                ->constrained('hotel_vendors')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hotel_store_master_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hotel_vendor_id');
        });
    }
};
