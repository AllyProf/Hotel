<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_departments', function (Blueprint $table) {
            $table->boolean('show_in_pos')->default(false)->after('is_active');
            $table->foreignId('hotel_pms_service_id')
                ->nullable()
                ->after('show_in_pos')
                ->constrained('hotel_pms_services')
                ->nullOnDelete();
        });

        if (! Schema::hasColumn('hotel_pms_services', 'show_in_pos')) {
            return;
        }

        $services = DB::table('hotel_pms_services')
            ->where('show_in_pos', true)
            ->where('is_active', true)
            ->get(['id', 'hotel_id', 'template_key', 'name']);

        foreach ($services as $service) {
            $departmentId = $this->guessDepartmentId((int) $service->hotel_id, (string) ($service->template_key ?? ''));
            if (! $departmentId) {
                continue;
            }

            DB::table('hotel_departments')
                ->where('id', $departmentId)
                ->update([
                    'show_in_pos' => true,
                    'hotel_pms_service_id' => $service->id,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('hotel_departments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hotel_pms_service_id');
            $table->dropColumn('show_in_pos');
        });
    }

    private function guessDepartmentId(int $hotelId, string $templateKey): ?int
    {
        $workflowKey = null;
        foreach (config('hotel_department_procurement.workflows', []) as $key => $def) {
            if (($def['template_key'] ?? null) === $templateKey) {
                $workflowKey = $key;
                break;
            }
        }

        $query = DB::table('hotel_departments')
            ->where('hotel_id', $hotelId)
            ->where('is_active', true);

        if ($workflowKey) {
            $patterns = config('hotel_department_procurement.workflows.'.$workflowKey.'.department_name_patterns', []);
            if ($patterns !== []) {
                $query->where(function ($q) use ($patterns) {
                    foreach ($patterns as $pattern) {
                        $q->orWhere('name', 'like', $pattern);
                    }
                });

                $id = $query->orderBy('name')->value('id');
                if ($id) {
                    return (int) $id;
                }
            }
        }

        $fallback = DB::table('hotel_departments')
            ->where('hotel_id', $hotelId)
            ->where('is_active', true)
            ->orderBy('name')
            ->value('id');

        return $fallback ? (int) $fallback : null;
    }
};
