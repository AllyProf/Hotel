<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_pms_service_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_pms_service_id')->constrained('hotel_pms_services')->cascadeOnDelete();
            $table->foreignId('hotel_pms_service_category_id')->nullable()->constrained('hotel_pms_service_categories')->nullOnDelete();
            $table->string('item_name', 120);
            $table->foreignId('hotel_pms_service_package_id')->nullable()->constrained('hotel_pms_service_packages')->nullOnDelete();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->string('currency', 3)->default('TZS');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['hotel_pms_service_id', 'hotel_pms_service_category_id', 'item_name'],
                'pms_service_item_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_pms_service_items');
    }
};
