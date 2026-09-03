<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('gst_vat')->nullable();
            $table->json('contracted_rates')->nullable();
            $table->timestamps();

            $table->index(['hotel_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_companies');
    }
};
