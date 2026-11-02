<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_pms_service_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('template_key', 80)->nullable();
            $table->string('name');
            $table->string('abbreviation', 20)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hotel_id', 'template_key'], 'svc_pkg_hotel_template_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_pms_service_packages');
    }
};
