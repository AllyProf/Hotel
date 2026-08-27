<?php

use App\Models\Branch;
use App\Models\Hotel;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Hotel::query()->whereDoesntHave('branches')->each(function (Hotel $hotel) {
            $hotel->ensureMainBranch();
        });
    }

    public function down(): void
    {
        Branch::query()->where('is_headquarters', true)->delete();
    }
};
