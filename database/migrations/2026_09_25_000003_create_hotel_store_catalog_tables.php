<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_store_tax_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->decimal('rate_percent', 5, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['hotel_id', 'name']);
        });

        Schema::create('hotel_store_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('abbreviation', 20)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['hotel_id', 'name']);
        });

        Schema::table('hotel_store_master_items', function (Blueprint $table) {
            $table->foreignId('hotel_store_tax_group_id')
                ->nullable()
                ->after('tax_group')
                ->constrained('hotel_store_tax_groups')
                ->nullOnDelete();
            $table->foreignId('hotel_store_package_id')
                ->nullable()
                ->after('unit')
                ->constrained('hotel_store_packages')
                ->nullOnDelete();
            $table->decimal('pieces_per_receive_unit', 12, 2)->default(1)->after('hotel_store_package_id');
            $table->string('usage_type', 20)->default('internal')->after('pieces_per_receive_unit');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_store_master_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hotel_store_tax_group_id');
            $table->dropConstrainedForeignId('hotel_store_package_id');
            $table->dropColumn(['pieces_per_receive_unit', 'usage_type']);
        });

        Schema::dropIfExists('hotel_store_packages');
        Schema::dropIfExists('hotel_store_tax_groups');
    }
};
