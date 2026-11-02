<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_pms_service_items', function (Blueprint $table) {
            $table->decimal('pieces_per_receive_unit', 12, 2)->nullable()->after('hotel_pms_service_package_id');
        });

        Schema::create('hotel_pms_service_item_sell_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hotel_pms_service_item_id');
            $table->unsignedBigInteger('hotel_pms_service_package_id');
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('hotel_pms_service_item_id', 'pms_item_sell_opt_item_fk')
                ->references('id')
                ->on('hotel_pms_service_items')
                ->cascadeOnDelete();
            $table->foreign('hotel_pms_service_package_id', 'pms_item_sell_opt_pkg_fk')
                ->references('id')
                ->on('hotel_pms_service_packages')
                ->cascadeOnDelete();
            $table->unique(['hotel_pms_service_item_id', 'hotel_pms_service_package_id'], 'pms_item_sell_opt_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_pms_service_item_sell_options');

        Schema::table('hotel_pms_service_items', function (Blueprint $table) {
            $table->dropColumn('pieces_per_receive_unit');
        });
    }
};
