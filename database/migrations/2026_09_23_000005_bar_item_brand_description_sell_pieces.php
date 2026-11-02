<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_pms_service_items', function (Blueprint $table) {
            $table->string('brand', 120)->nullable()->after('item_name');
            $table->text('description')->nullable()->after('brand');
        });

        Schema::table('hotel_pms_service_item_sell_options', function (Blueprint $table) {
            $table->decimal('pieces_per_sell_unit', 12, 2)->nullable()->after('hotel_pms_service_package_id');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_pms_service_item_sell_options', function (Blueprint $table) {
            $table->dropColumn('pieces_per_sell_unit');
        });

        Schema::table('hotel_pms_service_items', function (Blueprint $table) {
            $table->dropColumn(['brand', 'description']);
        });
    }
};
