<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_store_indents', function (Blueprint $table) {
            $table->foreignId('hotel_store_category_id')
                ->nullable()
                ->after('hotel_department_id')
                ->constrained('hotel_store_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hotel_store_indents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hotel_store_category_id');
        });
    }
};
