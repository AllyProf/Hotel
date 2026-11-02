<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_store_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['hotel_id', 'name']);
        });

        Schema::create('hotel_store_sub_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_store_category_id')->constrained('hotel_store_categories')->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['hotel_store_category_id', 'name'], 'store_subcat_cat_name_uniq');
        });

        Schema::create('hotel_store_master_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_store_category_id')->constrained('hotel_store_categories')->cascadeOnDelete();
            $table->foreignId('hotel_store_sub_category_id')->nullable()->constrained('hotel_store_sub_categories')->nullOnDelete();
            $table->string('name', 160);
            $table->decimal('rate', 12, 2)->default(0);
            $table->string('tax_group', 40)->nullable();
            $table->boolean('tax_inclusive')->default(false);
            $table->boolean('inventoried')->default(true);
            $table->boolean('capital_item')->default(false);
            $table->decimal('stock', 12, 2)->default(0);
            $table->string('unit', 40)->nullable();
            $table->timestamps();

            $table->index(['hotel_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_store_master_items');
        Schema::dropIfExists('hotel_store_sub_categories');
        Schema::dropIfExists('hotel_store_categories');
    }
};
