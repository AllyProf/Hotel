<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_pms_services', function (Blueprint $table) {
            $table->string('template_key', 80)->nullable()->after('hotel_id');
            $table->string('category', 40)->default('other')->after('name');
            $table->string('pricing_unit', 20)->default('fixed')->after('amount');
            $table->boolean('is_active')->default(true)->after('amount_editable');

            $table->unique(['hotel_id', 'template_key']);
        });
    }

    public function down(): void
    {
        Schema::table('hotel_pms_services', function (Blueprint $table) {
            $table->dropUnique(['hotel_id', 'template_key']);
            $table->dropColumn(['template_key', 'category', 'pricing_unit', 'is_active']);
        });
    }
};
