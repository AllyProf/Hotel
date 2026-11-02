<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_housekeeping_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_room_unit_id')->constrained()->cascadeOnDelete();
            $table->string('room_no', 64);
            $table->string('room_type_name')->nullable();
            $table->string('task_type', 32)->default('checkout_clean');
            $table->string('status', 32)->default('pending');
            $table->string('priority', 16)->default('normal');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hotel_id', 'status']);
        });

        Schema::create('hotel_maintenance_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_room_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('room_no', 64)->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 32)->default('general');
            $table->string('status', 32)->default('open');
            $table->string('priority', 16)->default('normal');
            $table->boolean('blocks_room')->default(false);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hotel_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_maintenance_orders');
        Schema::dropIfExists('hotel_housekeeping_tasks');
    }
};
