<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_store_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_department_id')->constrained('hotel_departments')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('consumption_number', 40);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['hotel_id', 'consumption_number']);
            $table->index(['hotel_id', 'created_at']);
        });

        Schema::create('hotel_store_consumption_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_store_consumption_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_store_master_item_id')->nullable()->constrained('hotel_store_master_items')->nullOnDelete();
            $table->string('name');
            $table->decimal('quantity', 10, 2)->default(0);
            $table->string('unit', 40)->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_store_consumption_items');
        Schema::dropIfExists('hotel_store_consumptions');
    }
};
