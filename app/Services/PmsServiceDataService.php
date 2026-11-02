<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelPmsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PmsServiceDataService
{
    public function __construct(
        private PmsServiceCategoryDataService $categories,
        private PmsServicePackageDataService $packages,
    ) {}
    /** @return array<string, mixed> */
    public function uiConfig(): array
    {
        return config('hotel_pms.services', []);
    }

    public function query(int $hotelId): Builder
    {
        return HotelPmsService::query()
            ->where('hotel_id', $hotelId)
            ->orderBy('name');
    }

    /** @return Collection<int, array<string, mixed>> */
    public function cardsForHotel(Hotel $hotel): Collection
    {
        $services = $this->query($hotel->id)->get();
        $byTemplateKey = $services->filter(fn (HotelPmsService $service) => $service->template_key !== null)
            ->keyBy('template_key');

        $cards = collect(config('hotel_pms_service_templates.templates', []))
            ->map(function (array $template, string $key) use ($byTemplateKey) {
                $service = $byTemplateKey->get($key);

                return [
                    'type' => 'template',
                    'template_key' => $key,
                    'service_id' => $service?->id,
                    'name' => $template['name'],
                    'icon' => $template['icon'] ?? 'fa fa-tag',
                    'is_added' => $service !== null,
                ];
            });

        $customCards = $services
            ->filter(fn (HotelPmsService $service) => $service->isCustom())
            ->map(fn (HotelPmsService $service) => [
                'type' => 'custom',
                'template_key' => null,
                'service_id' => $service->id,
                'name' => $service->name,
                'icon' => 'fa fa-plus-circle',
                'is_added' => true,
            ]);

        return $cards->concat($customCards)->values();
    }

    public function createCustom(Hotel $hotel, string $name): HotelPmsService
    {
        return $hotel->pmsServices()->create($this->defaultServiceAttributes([
            'template_key' => null,
            'name' => $name,
            'category' => 'other',
        ]));
    }

    public function createFromTemplate(Hotel $hotel, string $templateKey): HotelPmsService
    {
        $templates = config('hotel_pms_service_templates.templates', []);

        if (! isset($templates[$templateKey])) {
            throw new InvalidArgumentException('Unknown service.');
        }

        if ($hotel->pmsServices()->where('template_key', $templateKey)->exists()) {
            throw new InvalidArgumentException('This service is already added.');
        }

        $template = $templates[$templateKey];

        $defaults = $template['defaults'] ?? [];

        $attrs = array_merge([
            'template_key' => $templateKey,
            'name' => $template['name'],
            'category' => $templateKey,
            'currency' => strtoupper(trim((string) ($hotel->currency ?: 'TZS'))),
        ], $defaults);

        if ($templateKey === 'parking') {
            $attrs['settings'] = config('hotel_pms_service_templates.facility_defaults.parking', []);
        }

        if ($templateKey === 'laundry') {
            $attrs['settings'] = config('hotel_pms_service_templates.facility_defaults.laundry', []);
            $hotel->load('settings');
            $legacyItems = $hotel->settings?->laundry['items'] ?? [];
            if ($legacyItems !== []) {
                $attrs['settings']['items'] = $legacyItems;
            }
        }

        if ($templateKey === 'swimming') {
            $attrs['settings'] = config('hotel_pms_service_templates.facility_defaults.swimming', []);
        }

        if ($templateKey === 'conference') {
            $attrs['settings'] = config('hotel_pms_service_templates.facility_defaults.conference', []);
        }

        $service = $hotel->pmsServices()->create($this->defaultServiceAttributes($attrs));

        if (($template['product_based'] ?? true) === true) {
            $this->categories->importAllTemplates($hotel, $service);

            if ($service->usesPackages()) {
                $this->packages->importAllTemplates($hotel, $service);
            }
        }

        return $service;
    }

    /** @param  array<string, mixed>  $data */
    public function updateSettings(HotelPmsService $service, array $data): HotelPmsService
    {
        $taxCategory = trim($data['tax_category'] ?? 'No Tax');
        $hasTax = strtolower($taxCategory) !== 'no tax';

        $update = [
            'amount' => $data['amount'] ?? 0,
            'currency' => ! empty($data['currency']) ? strtoupper(trim($data['currency'])) : null,
            'pricing_unit' => $data['pricing_unit'] ?? 'fixed',
            'tax_category' => $taxCategory !== '' ? $taxCategory : 'No Tax',
            'hsn_code' => ! empty($data['hsn_code']) ? trim($data['hsn_code']) : null,
            'tax_inclusive' => $hasTax && ! empty($data['tax_inclusive']),
            'visible_on_be' => ! empty($data['visible_on_be']),
            'amount_editable' => ! empty($data['amount_editable']),
            'is_active' => ! empty($data['is_active']),
            'comments' => ! empty($data['comments']) ? trim($data['comments']) : null,
        ];

        if ($service->isParking()) {
            $update['settings'] = $this->normalizeParkingSettings($data);
        } elseif ($service->isLaundry()) {
            $update['settings'] = $this->normalizeLaundrySettings($data);
        } elseif ($service->isSwimming()) {
            $update['settings'] = $this->normalizeSwimmingSettings($data);
        } elseif ($service->isConference()) {
            $update['settings'] = $this->normalizeConferenceSettings($data);
        }

        $service->update($update);

        return $service->fresh();
    }

    /** @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeParkingSettings(array $data): array
    {
        return [
            'complimentary_for_inhouse' => ! empty($data['complimentary_for_inhouse']),
            'grace_period_minutes' => max(0, min(1440, (int) ($data['grace_period_minutes'] ?? 0))),
            'require_plate_number' => ! empty($data['require_plate_number']),
            'require_vehicle_type' => ! empty($data['require_vehicle_type']),
            'collect_vehicle_notes' => ! empty($data['collect_vehicle_notes']),
        ];
    }

    /** @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeConferenceSettings(array $data): array
    {
        return [
            'business_hours_start' => trim((string) ($data['business_hours_start'] ?? '08:00')) ?: '08:00',
            'business_hours_end' => trim((string) ($data['business_hours_end'] ?? '22:00')) ?: '22:00',
            'allow_external_booking' => ! empty($data['allow_external_booking']),
            'require_contact_for_external' => ! empty($data['require_contact_for_external']),
            'require_room_for_inhouse' => ! empty($data['require_room_for_inhouse']),
            'post_to_folio' => ! empty($data['post_to_folio']),
        ];
    }

    private function normalizeSwimmingSettings(array $data): array
    {
        return [
            'open_time' => trim((string) ($data['open_time'] ?? '07:00')) ?: '07:00',
            'close_time' => trim((string) ($data['close_time'] ?? '19:00')) ?: '19:00',
            'complimentary_for_inhouse' => ! empty($data['complimentary_for_inhouse']),
            'allow_desk_complimentary_override' => ! empty($data['allow_desk_complimentary_override']),
            'max_persons_per_entry' => max(1, min(99, (int) ($data['max_persons_per_entry'] ?? 10))),
            'require_room_for_inhouse' => ! empty($data['require_room_for_inhouse']),
            'require_contact_for_walkin' => ! empty($data['require_contact_for_walkin']),
            'post_to_folio' => ! empty($data['post_to_folio']),
            'allow_walk_in' => ! empty($data['allow_walk_in']),
        ];
    }

    /** @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeLaundrySettings(array $data): array
    {
        $items = collect($data['laundry_items'] ?? [])
            ->map(fn ($item) => ['name' => trim((string) ($item['name'] ?? ''))])
            ->filter(fn (array $item) => $item['name'] !== '')
            ->values()
            ->all();

        return [
            'turnaround_hours' => max(1, min(168, (int) ($data['turnaround_hours'] ?? 24))),
            'express_surcharge_percent' => max(0, min(500, (int) ($data['express_surcharge_percent'] ?? 0))),
            'complimentary_pieces' => max(0, min(99, (int) ($data['complimentary_pieces'] ?? 0))),
            'require_room_number' => ! empty($data['require_room_number']),
            'post_to_folio' => ! empty($data['post_to_folio']),
            'items' => $items,
        ];
    }

    public function deleteService(HotelPmsService $service): void
    {
        $service->servicePackages()->delete();
        $service->serviceCategories()->delete();
        $service->delete();
    }

    public function findForHotel(Hotel $hotel, int $serviceId): HotelPmsService
    {
        return $hotel->pmsServices()->whereKey($serviceId)->firstOrFail();
    }

    /** @param  array<string, mixed>  $overrides */
    private function defaultServiceAttributes(array $overrides): array
    {
        return array_merge([
            'amount' => 0,
            'pricing_unit' => 'fixed',
            'tax_category' => 'No Tax',
            'hsn_code' => null,
            'tax_inclusive' => false,
            'currency' => null,
            'visible_on_be' => false,
            'amount_editable' => true,
            'is_active' => true,
            'comments' => null,
        ], $overrides);
    }
}
