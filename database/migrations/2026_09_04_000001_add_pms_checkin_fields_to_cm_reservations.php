<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cm_reservations', function (Blueprint $table) {
            $table->string('pms_status', 20)->default('reserved')->after('status');
            $table->foreignId('room_unit_id')->nullable()->after('pms_status')->constrained('hotel_room_units')->nullOnDelete();
            $table->timestamp('checked_in_at')->nullable()->after('room_unit_id');
            $table->timestamp('checked_out_at')->nullable()->after('checked_in_at');
        });

        DB::table('cm_reservations')
            ->whereNotNull('payload')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $payload = json_decode($row->payload, true);

                    if (! is_array($payload)) {
                        continue;
                    }

                    $unitId = $payload['room_unit_id'] ?? null;

                    if (! $unitId) {
                        continue;
                    }

                    DB::table('cm_reservations')
                        ->where('id', $row->id)
                        ->update([
                            'room_unit_id' => $unitId,
                            'pms_status' => 'assigned',
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('cm_reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_unit_id');
            $table->dropColumn(['pms_status', 'checked_in_at', 'checked_out_at']);
        });
    }
};
