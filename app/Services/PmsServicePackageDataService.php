<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelPmsService;
use App\Models\HotelPmsServicePackage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PmsServicePackageDataService
{
    public function queryForService(int $serviceId): Builder
    {
        return HotelPmsServicePackage::query()
            ->where('hotel_pms_service_id', $serviceId)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /** @return list<string, array<string, mixed>> */
    public function packageTemplatesForService(HotelPmsService $service): array
    {
        if (! $service->usesPackages()) {
            return [];
        }

        if ($service->template_key === null || $service->template_key === '') {
            return [];
        }

        return config('hotel_pms_service_templates.package_templates.'.$service->template_key, []);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function cardsForService(HotelPmsService $service): Collection
    {
        $existing = $this->queryForService($service->id)->get();
        $byTemplateKey = $existing->filter(fn (HotelPmsServicePackage $pkg) => $pkg->template_key !== null)
            ->keyBy('template_key');

        $templateCards = collect($this->packageTemplatesForService($service))
            ->map(function (array $template, string $key) use ($byTemplateKey) {
                $package = $byTemplateKey->get($key);

                return [
                    'type' => 'template',
                    'template_key' => $key,
                    'package_id' => $package?->id,
                    'name' => $template['name'],
                    'abbreviation' => $template['abbreviation'] ?? $key,
                    'icon' => $template['icon'] ?? 'fa fa-tag',
                    'is_added' => $package !== null,
                    'is_custom' => false,
                    'is_active' => $package?->is_active ?? true,
                ];
            });

        $customCards = $existing
            ->filter(fn (HotelPmsServicePackage $package) => $package->isCustom())
            ->map(fn (HotelPmsServicePackage $package) => [
                'type' => 'custom',
                'template_key' => null,
                'package_id' => $package->id,
                'name' => $package->name,
                'abbreviation' => $package->abbreviation,
                'icon' => 'fa fa-plus-circle',
                'is_added' => true,
                'is_custom' => true,
                'is_active' => $package->is_active,
            ]);

        return $templateCards->concat($customCards)->values();
    }

    public function importAllTemplates(Hotel $hotel, HotelPmsService $service): int
    {
        $templates = $this->packageTemplatesForService($service);
        $imported = 0;

        foreach ($templates as $key => $template) {
            if ($service->servicePackages()->where('template_key', $key)->exists()) {
                continue;
            }

            $this->createFromTemplate($hotel, $service, $key);
            $imported++;
        }

        return $imported;
    }

    public function createFromTemplate(Hotel $hotel, HotelPmsService $service, string $templateKey): HotelPmsServicePackage
    {
        $templates = $this->packageTemplatesForService($service);

        if (! isset($templates[$templateKey])) {
            throw new InvalidArgumentException('Unknown package template.');
        }

        if ($service->servicePackages()->where('template_key', $templateKey)->exists()) {
            throw new InvalidArgumentException('This package is already added.');
        }

        $template = $templates[$templateKey];
        $sortOrder = $service->servicePackages()->count() + 1;

        return $service->servicePackages()->create([
            'hotel_id' => $hotel->id,
            'template_key' => $templateKey,
            'name' => $template['name'],
            'abbreviation' => $template['abbreviation'] ?? $templateKey,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
    }

    public function createCustom(Hotel $hotel, HotelPmsService $service, string $name, ?string $abbreviation = null): HotelPmsServicePackage
    {
        $sortOrder = $service->servicePackages()->count() + 1;

        return $service->servicePackages()->create([
            'hotel_id' => $hotel->id,
            'template_key' => null,
            'name' => trim($name),
            'abbreviation' => $abbreviation ? trim($abbreviation) : null,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
    }

    /** @param  array<string, mixed>  $data */
    public function updatePackage(HotelPmsServicePackage $package, array $data): HotelPmsServicePackage
    {
        $package->update([
            'name' => trim($data['name']),
            'abbreviation' => ! empty($data['abbreviation']) ? trim($data['abbreviation']) : null,
            'is_active' => ! empty($data['is_active']),
        ]);

        return $package->fresh();
    }

    public function deletePackage(HotelPmsServicePackage $package): void
    {
        $package->delete();
    }
}
