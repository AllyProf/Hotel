<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_pms_service_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_pms_service_id')->constrained('hotel_pms_services')->cascadeOnDelete();
            $table->string('template_key', 80)->nullable();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hotel_pms_service_id', 'template_key'], 'svc_cat_service_template_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_pms_service_categories');
    }
};
