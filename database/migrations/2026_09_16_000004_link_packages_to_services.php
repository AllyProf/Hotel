<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('hotel_pms_service_packages')->delete();

        Schema::table('hotel_pms_service_packages', function (Blueprint $table) {
            $table->index('hotel_id', 'svc_pkg_hotel_id_index');
        });

        Schema::table('hotel_pms_service_packages', function (Blueprint $table) {
            $table->dropUnique('svc_pkg_hotel_template_unique');
            $table->foreignId('hotel_pms_service_id')->after('hotel_id')->constrained('hotel_pms_services')->cascadeOnDelete();
            $table->unique(['hotel_pms_service_id', 'template_key'], 'svc_pkg_service_template_unique');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_pms_service_packages', function (Blueprint $table) {
            $table->dropUnique('svc_pkg_service_template_unique');
            $table->dropConstrainedForeignId('hotel_pms_service_id');
            $table->unique(['hotel_id', 'template_key'], 'svc_pkg_hotel_template_unique');
            $table->dropIndex('svc_pkg_hotel_id_index');
        });
    }
};
