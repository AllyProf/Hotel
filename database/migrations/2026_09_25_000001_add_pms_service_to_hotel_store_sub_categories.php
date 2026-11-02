<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_store_sub_categories', function (Blueprint $table) {
            $table->foreignId('hotel_pms_service_id')
                ->nullable()
                ->after('hotel_store_category_id')
                ->constrained('hotel_pms_services')
                ->nullOnDelete();

            $table->unique(['hotel_store_category_id', 'hotel_pms_service_id'], 'store_subcat_pms_service_uniq');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_store_sub_categories', function (Blueprint $table) {
            $table->dropUnique('store_subcat_pms_service_uniq');
            $table->dropConstrainedForeignId('hotel_pms_service_id');
        });
    }
};
