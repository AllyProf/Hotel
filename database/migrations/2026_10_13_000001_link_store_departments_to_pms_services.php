<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_store_categories', function (Blueprint $table) {
            $table->foreignId('hotel_pms_service_id')
                ->nullable()
                ->after('hotel_id')
                ->constrained('hotel_pms_services')
                ->nullOnDelete();
        });

        Schema::table('hotel_store_packages', function (Blueprint $table) {
            $table->foreignId('hotel_pms_service_package_id')
                ->nullable()
                ->after('hotel_id')
                ->constrained('hotel_pms_service_packages')
                ->nullOnDelete();

            $table->unique(['hotel_id', 'hotel_pms_service_package_id'], 'store_pkg_pms_pkg_uniq');
        });

        Schema::table('hotel_store_master_items', function (Blueprint $table) {
            $table->foreignId('hotel_pms_service_category_id')
                ->nullable()
                ->after('hotel_store_sub_category_id')
                ->constrained('hotel_pms_service_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hotel_store_master_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hotel_pms_service_category_id');
        });

        Schema::table('hotel_store_packages', function (Blueprint $table) {
            $table->dropUnique('store_pkg_pms_pkg_uniq');
            $table->dropConstrainedForeignId('hotel_pms_service_package_id');
        });

        Schema::table('hotel_store_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hotel_pms_service_id');
        });
    }
};
