<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_departments', function (Blueprint $table) {
            $table->string('contact_person', 120)->nullable()->after('name');
            $table->decimal('amount_issued', 14, 2)->default(0)->after('contact_person');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_departments', function (Blueprint $table) {
            $table->dropColumn(['contact_person', 'amount_issued']);
        });
    }
};
