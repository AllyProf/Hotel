<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_bar_purchase_lists', function (Blueprint $table) {
            $table->string('workflow_key', 32)->default('bar')->after('hotel_id');
            $table->index(['hotel_id', 'workflow_key'], 'bar_pl_hotel_workflow_idx');
        });

        DB::table('hotel_bar_purchase_lists')->whereNull('workflow_key')->update(['workflow_key' => 'bar']);

        Schema::table('hotel_settings', function (Blueprint $table) {
            $table->json('hk_procurement')->nullable()->after('bar');
            $table->json('laundry_procurement')->nullable()->after('hk_procurement');
            $table->json('reception_procurement')->nullable()->after('laundry_procurement');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_settings', function (Blueprint $table) {
            $table->dropColumn(['hk_procurement', 'laundry_procurement', 'reception_procurement']);
        });

        Schema::table('hotel_bar_purchase_lists', function (Blueprint $table) {
            $table->dropIndex('bar_pl_hotel_workflow_idx');
            $table->dropColumn('workflow_key');
        });
    }
};
