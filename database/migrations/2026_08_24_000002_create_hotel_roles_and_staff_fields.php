<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['hotel_id', 'slug']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('hotel_role_id')->nullable()->after('hotel_id')->constrained('hotel_roles')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('hotel_role_id')->constrained()->nullOnDelete();
            $table->string('phone', 30)->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hotel_role_id');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn(['phone', 'is_active']);
        });

        Schema::dropIfExists('hotel_roles');
    }
};
