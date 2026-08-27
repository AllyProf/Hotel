<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
            $table->string('pin_code', 20)->nullable()->after('address');
            $table->string('state')->nullable()->after('city');
            $table->string('currency', 3)->default('USD')->after('country_code');
            $table->string('timezone')->default('Africa/Dar_es_Salaam')->after('currency');
            $table->decimal('latitude', 10, 7)->nullable()->after('timezone');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('website')->nullable()->after('email');
            $table->string('google_maps_url')->nullable()->after('website');
            $table->string('google_review_link')->nullable()->after('google_maps_url');
            $table->string('bank_name')->nullable()->after('google_review_link');
            $table->string('bank_account_name')->nullable()->after('bank_name');
            $table->string('bank_account_no')->nullable()->after('bank_account_name');
            $table->string('bank_ifsc')->nullable()->after('bank_account_no');
        });

        Schema::create('hotel_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('pms')->nullable();
            $table->json('be')->nullable();
            $table->json('whatsapp')->nullable();
            $table->json('laundry')->nullable();
            $table->json('reservation')->nullable();
            $table->timestamps();
        });

        Schema::create('hotel_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('rank')->default(0);
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('room_count')->default(1);
            $table->unsignedTinyInteger('min_occupancy')->default(1);
            $table->unsignedTinyInteger('max_occupancy')->default(2);
            $table->boolean('show_ota_breakup')->default(false);
            $table->json('amenities')->nullable();
            $table->timestamps();
        });

        Schema::create('hotel_rate_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_room_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('occupancy')->default('Single (S)');
            $table->string('meal_plan')->default('CP');
            $table->string('colour_code')->nullable();
            $table->unsignedTinyInteger('meals')->default(1);
            $table->boolean('is_master')->default(false);
            $table->decimal('base_rate', 12, 2)->default(0);
            $table->decimal('ratio', 8, 4)->default(1);
            $table->decimal('be_ratio', 8, 4)->default(0.85);
            $table->decimal('extra_adult', 12, 2)->default(0);
            $table->decimal('extra_child', 12, 2)->default(0);
            $table->text('description')->nullable();
            $table->text('policy')->nullable();
            $table->json('amenities')->nullable();
            $table->timestamps();
        });

        Schema::create('hotel_pms_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('tax_category')->default('No Tax');
            $table->string('hsn_code')->nullable();
            $table->boolean('tax_inclusive')->default(true);
            $table->boolean('visible_on_be')->default(false);
            $table->boolean('amount_editable')->default(true);
            $table->string('image')->nullable();
            $table->string('comments')->nullable();
            $table->timestamps();
        });

        Schema::create('hotel_pms_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('service_names')->nullable();
            $table->string('comments')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_pms_categories');
        Schema::dropIfExists('hotel_pms_services');
        Schema::dropIfExists('hotel_rate_plans');
        Schema::dropIfExists('hotel_rooms');
        Schema::dropIfExists('hotel_settings');
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn([
                'display_name', 'pin_code', 'state', 'currency', 'timezone',
                'latitude', 'longitude', 'website', 'google_maps_url', 'google_review_link',
                'bank_name', 'bank_account_name', 'bank_account_no', 'bank_ifsc',
            ]);
        });
    }
};
