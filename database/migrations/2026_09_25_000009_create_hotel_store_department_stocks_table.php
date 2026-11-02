<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_store_department_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_department_id')->constrained('hotel_departments')->cascadeOnDelete();
            $table->foreignId('hotel_store_master_item_id')->constrained('hotel_store_master_items')->cascadeOnDelete();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['hotel_department_id', 'hotel_store_master_item_id'], 'hotel_store_dept_stock_unique');
            $table->index(['hotel_id', 'hotel_department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_store_department_stocks');
    }
};
