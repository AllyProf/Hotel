<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_payment_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('guest_name')->nullable();
            $table->string('invoice_id')->nullable();
            $table->string('payment_link')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['hotel_id', 'status']);
            $table->index(['hotel_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_payment_links');
    }
};
