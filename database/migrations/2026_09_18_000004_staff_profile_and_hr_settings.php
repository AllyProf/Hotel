<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_country_code', 8)->nullable()->after('phone');
        });

        Schema::table('hotel_settings', function (Blueprint $table) {
            $table->json('hr')->nullable()->after('reservation');
        });

        Schema::create('hotel_staff_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('document_key', 64);
            $table->string('label', 160)->nullable();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'document_key'], 'staff_doc_user_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_staff_documents');

        Schema::table('hotel_settings', function (Blueprint $table) {
            $table->dropColumn('hr');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone_country_code');
        });
    }
};
