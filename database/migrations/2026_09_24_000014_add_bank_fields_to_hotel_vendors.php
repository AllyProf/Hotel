<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_vendors', function (Blueprint $table) {
            $table->string('account_name', 160)->nullable()->after('address');
            $table->string('account_no', 64)->nullable()->after('account_name');
            $table->string('bank_name', 160)->nullable()->after('account_no');
            $table->string('ifsc_code', 32)->nullable()->after('bank_name');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_vendors', function (Blueprint $table) {
            $table->dropColumn(['account_name', 'account_no', 'bank_name', 'ifsc_code']);
        });
    }
};
