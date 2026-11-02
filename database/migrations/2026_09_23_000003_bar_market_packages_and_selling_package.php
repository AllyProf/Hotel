<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_pms_service_items', function (Blueprint $table) {
            $table->unsignedBigInteger('selling_hotel_pms_service_package_id')->nullable()->after('hotel_pms_service_package_id');
            $table->foreign('selling_hotel_pms_service_package_id', 'hotel_pms_items_selling_pkg_fk')
                ->references('id')
                ->on('hotel_pms_service_packages')
                ->nullOnDelete();
        });

        Schema::table('hotel_bar_purchase_list_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('receiving_package_id')->nullable()->after('expiry_date');
            $table->unsignedBigInteger('selling_package_id')->nullable()->after('receiving_package_id');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_bar_purchase_list_lines', function (Blueprint $table) {
            $table->dropColumn(['receiving_package_id', 'selling_package_id']);
        });

        Schema::table('hotel_pms_service_items', function (Blueprint $table) {
            $table->dropForeign('hotel_pms_items_selling_pkg_fk');
            $table->dropColumn('selling_hotel_pms_service_package_id');
        });
    }
};
