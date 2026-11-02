<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_store_master_item_sell_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hotel_store_master_item_id');
            $table->unsignedBigInteger('hotel_store_package_id');
            $table->decimal('pieces_per_sell_unit', 12, 2)->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('hotel_store_master_item_id', 'store_sell_opt_item_fk')
                ->references('id')->on('hotel_store_master_items')->cascadeOnDelete();
            $table->foreign('hotel_store_package_id', 'store_sell_opt_pkg_fk')
                ->references('id')->on('hotel_store_packages')->cascadeOnDelete();
            $table->unique(['hotel_store_master_item_id', 'hotel_store_package_id'], 'store_item_sell_pkg_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_store_master_item_sell_options');
    }
};
