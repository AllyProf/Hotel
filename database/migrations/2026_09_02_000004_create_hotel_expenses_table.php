<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('entry_type', 20)->default('expense');
            $table->string('paid_type', 20)->nullable();
            $table->string('payment_type', 40);
            $table->decimal('amount', 14, 2);
            $table->string('category')->nullable();
            $table->date('expense_date')->nullable();
            $table->string('invoice_no')->nullable();
            $table->string('vendor')->nullable();
            $table->text('comments')->nullable();
            $table->string('details_path')->nullable();
            $table->timestamps();

            $table->index(['hotel_id', 'expense_date']);
            $table->index(['hotel_id', 'entry_type']);
            $table->index(['hotel_id', 'payment_type']);
            $table->index(['hotel_id', 'paid_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_expenses');
    }
};
