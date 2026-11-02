<?php

use App\Models\Hotel;
use App\Models\HotelStoreCategory;
use App\Models\HotelStoreIndent;
use App\Services\Stores\StoreMasterItemService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_store_categories', function (Blueprint $table) {
            $table->foreignId('hotel_department_id')
                ->nullable()
                ->after('hotel_pms_service_id')
                ->constrained('hotel_departments')
                ->nullOnDelete();
        });

        $masterItems = app(StoreMasterItemService::class);

        Hotel::query()->each(function (Hotel $hotel) use ($masterItems) {
            HotelStoreCategory::query()
                ->where('hotel_id', $hotel->id)
                ->orderBy('id')
                ->each(function (HotelStoreCategory $category) use ($hotel, $masterItems) {
                    $department = $masterItems->hotelDepartmentForStoreCategory($hotel, $category);
                    if (! $department) {
                        return;
                    }

                    $updates = ['hotel_department_id' => $department->id];
                    $deptName = trim($department->name);
                    $catName = trim($category->name);
                    if ($deptName !== '' && strcasecmp($catName, $deptName) !== 0) {
                        $nameTaken = HotelStoreCategory::query()
                            ->where('hotel_id', $hotel->id)
                            ->where('name', $deptName)
                            ->whereKeyNot($category->id)
                            ->exists();
                        if (! $nameTaken) {
                            $updates['name'] = $deptName;
                        }
                    }

                    $category->update($updates);
                });

            HotelStoreIndent::query()
                ->where('hotel_id', $hotel->id)
                ->whereNull('hotel_store_category_id')
                ->each(function (HotelStoreIndent $indent) use ($hotel, $masterItems) {
                    $categoryId = $masterItems->storeCategoryIdForHotelDepartment(
                        $hotel,
                        (int) $indent->hotel_department_id
                    );
                    if ($categoryId) {
                        $indent->update(['hotel_store_category_id' => $categoryId]);
                    }
                });
        });
    }

    public function down(): void
    {
        Schema::table('hotel_store_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hotel_department_id');
        });
    }
};
