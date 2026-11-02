<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_settings', function (Blueprint $table) {
            $table->json('bar')->nullable()->after('hr');
        });

        Schema::create('hotel_bar_purchase_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('reference_no', 48);
            $table->string('status', 24)->default('sent');
            $table->text('notes')->nullable();
            $table->json('sent_to')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['hotel_id', 'reference_no'], 'bar_pl_hotel_ref_unique');
        });

        Schema::create('hotel_bar_purchase_list_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_bar_purchase_list_id')->constrained('hotel_bar_purchase_lists')->cascadeOnDelete();
            $table->string('item_name', 160);
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit', 40)->nullable();
            $table->string('line_notes', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_bar_purchase_list_lines');
        Schema::dropIfExists('hotel_bar_purchase_lists');

        Schema::table('hotel_settings', function (Blueprint $table) {
            $table->dropColumn('bar');
        });
    }
};
