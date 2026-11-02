<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hotel_id', 'name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('hotel_department_id')->nullable()->after('hotel_role_id');
            $table->foreign('hotel_department_id', 'users_hotel_dept_fk')
                ->references('id')->on('hotel_departments')->nullOnDelete();
        });

        Schema::create('hotel_attendance_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->string('status', 16)->default('present');
            $table->string('notes', 255)->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hotel_id', 'user_id', 'work_date'], 'hr_attendance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_attendance_entries');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('users_hotel_dept_fk');
            $table->dropColumn('hotel_department_id');
        });

        Schema::dropIfExists('hotel_departments');
    }
};
