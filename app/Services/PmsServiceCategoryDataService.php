<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelPmsService;
use App\Models\HotelPmsServiceCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PmsServiceCategoryDataService
{
    public function queryForService(int $serviceId): Builder
    {
        return HotelPmsServiceCategory::query()
            ->where('hotel_pms_service_id', $serviceId)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /** @return list<string, array<string, mixed>> */
    public function categoryTemplatesForService(HotelPmsService $service): array
    {
        if ($service->template_key === null || $service->template_key === '') {
            return [];
        }

        return config('hotel_pms_service_templates.category_templates.'.$service->template_key, []);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function cardsForService(HotelPmsService $service): Collection
    {
        $existing = $this->queryForService($service->id)->get();
        $byTemplateKey = $existing->filter(fn (HotelPmsServiceCategory $cat) => $cat->template_key !== null)
            ->keyBy('template_key');

        $templateCards = collect($this->categoryTemplatesForService($service))
            ->map(function (array $template, string $key) use ($byTemplateKey) {
                $category = $byTemplateKey->get($key);

                return [
                    'type' => 'template',
                    'template_key' => $key,
                    'category_id' => $category?->id,
                    'name' => $template['name'],
                    'icon' => $template['icon'] ?? 'fa fa-tag',
                    'is_added' => $category !== null,
                    'is_custom' => false,
                    'is_active' => $category?->is_active ?? true,
                ];
            });

        $customCards = $existing
            ->filter(fn (HotelPmsServiceCategory $category) => $category->isCustom())
            ->map(fn (HotelPmsServiceCategory $category) => [
                'type' => 'custom',
                'template_key' => null,
                'category_id' => $category->id,
                'name' => $category->name,
                'icon' => 'fa fa-plus-circle',
                'is_added' => true,
                'is_custom' => true,
                'is_active' => $category->is_active,
            ]);

        return $templateCards->concat($customCards)->values();
    }

    public function importAllTemplates(Hotel $hotel, HotelPmsService $service): int
    {
        $templates = $this->categoryTemplatesForService($service);
        $imported = 0;

        foreach ($templates as $key => $template) {
            if ($service->serviceCategories()->where('template_key', $key)->exists()) {
                continue;
            }

            $this->createFromTemplate($hotel, $service, $key);
            $imported++;
        }

        return $imported;
    }

    public function createFromTemplate(Hotel $hotel, HotelPmsService $service, string $templateKey): HotelPmsServiceCategory
    {
        $templates = $this->categoryTemplatesForService($service);

        if (! isset($templates[$templateKey])) {
            throw new InvalidArgumentException('Unknown category template.');
        }

        if ($service->serviceCategories()->where('template_key', $templateKey)->exists()) {
            throw new InvalidArgumentException('This category is already added.');
        }

        $template = $templates[$templateKey];
        $sortOrder = $service->serviceCategories()->count() + 1;

        return $service->serviceCategories()->create([
            'hotel_id' => $hotel->id,
            'template_key' => $templateKey,
            'name' => $template['name'],
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
    }

    public function createCustom(Hotel $hotel, HotelPmsService $service, string $name): HotelPmsServiceCategory
    {
        $sortOrder = $service->serviceCategories()->count() + 1;

        return $service->serviceCategories()->create([
            'hotel_id' => $hotel->id,
            'template_key' => null,
            'name' => trim($name),
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
    }

    public function updateCategory(HotelPmsServiceCategory $category, array $data): HotelPmsServiceCategory
    {
        $category->update([
            'name' => trim($data['name']),
            'is_active' => ! empty($data['is_active']),
        ]);

        return $category->fresh();
    }

    public function deleteCategory(HotelPmsServiceCategory $category): void
    {
        $category->delete();
    }

    public function findForService(HotelPmsService $service, int $categoryId): HotelPmsServiceCategory
    {
        return $service->serviceCategories()->whereKey($categoryId)->firstOrFail();
    }
}
