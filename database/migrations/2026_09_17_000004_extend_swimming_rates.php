<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_pms_swimming_rates', function (Blueprint $table) {
            $table->index(
                ['hotel_pms_service_id', 'hotel_pms_service_category_id'],
                'swim_service_category_idx'
            );
        });

        Schema::table('hotel_pms_swimming_rates', function (Blueprint $table) {
            $table->dropUnique('swim_rate_category_unique');
        });

        Schema::table('hotel_pms_swimming_rates', function (Blueprint $table) {
            $table->string('audience', 24)->default('adult')->after('hotel_pms_service_category_id');
            $table->string('pricing_unit', 24)->default('per_person')->after('audience');
            $table->string('label', 120)->nullable()->after('pricing_unit');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_active');
        });

        Schema::table('hotel_swimming_visits', function (Blueprint $table) {
            $table->foreignId('hotel_pms_swimming_rate_id')->nullable()->after('hotel_pms_service_category_id')
                ->constrained('hotel_pms_swimming_rates')->nullOnDelete();
            $table->string('pricing_unit', 24)->nullable()->after('persons');
            $table->string('audience', 24)->nullable()->after('pricing_unit');
            $table->decimal('billing_quantity', 10, 2)->default(1)->after('audience');
            $table->unsignedSmallInteger('adults')->default(0)->after('billing_quantity');
            $table->unsignedSmallInteger('children')->default(0)->after('adults');
            $table->string('rate_label', 160)->nullable()->after('children');
        });

        DB::table('hotel_pms_swimming_rates')->update([
            'audience' => 'adult',
            'pricing_unit' => 'per_person',
        ]);
    }

    public function down(): void
    {
        Schema::table('hotel_swimming_visits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hotel_pms_swimming_rate_id');
            $table->dropColumn(['pricing_unit', 'audience', 'billing_quantity', 'adults', 'children', 'rate_label']);
        });

        Schema::table('hotel_pms_swimming_rates', function (Blueprint $table) {
            $table->dropColumn(['audience', 'pricing_unit', 'label', 'sort_order']);
            $table->unique(['hotel_pms_service_id', 'hotel_pms_service_category_id'], 'swim_rate_category_unique');
        });
    }
};
