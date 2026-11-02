<?php

namespace App\Services\Swimming;

use App\Models\Hotel;
use App\Models\HotelPmsService;
use App\Models\HotelPmsSwimmingRate;
use Illuminate\Support\Collection;

class SwimmingRateDataService
{
    /** @return list<string> */
    public static function allowedCurrencies(): array
    {
        return array_keys(config('hotel_pms_service_templates.swimming_currencies', ['TZS' => '', 'USD' => '']));
    }

    /** @return list<string> */
    public static function allowedPricingUnits(): array
    {
        return array_keys(config('hotel_pms_service_templates.swimming_pricing_units', []));
    }

    /** @return list<string> */
    public static function allowedAudiences(): array
    {
        return array_keys(config('hotel_pms_service_templates.swimming_audiences', []));
    }

    /** @return Collection<int, HotelPmsSwimmingRate> */
    public function ratesForService(HotelPmsService $service): Collection
    {
        $this->ensureDefaultRateRows($service);

        return HotelPmsSwimmingRate::query()
            ->where('hotel_pms_service_id', $service->id)
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function ensureDefaultRateRows(HotelPmsService $service): void
    {
        $service->loadMissing(['serviceCategories', 'hotel']);
        if ($service->serviceCategories->isEmpty()) {
            return;
        }

        $currency = $this->normalizeCurrency($service->displayCurrency($service->hotel));
        $defaultPrice = (float) $service->amount;

        foreach ($service->serviceCategories as $category) {
            foreach (['adult', 'child'] as $audience) {
                $exists = HotelPmsSwimmingRate::query()
                    ->where('hotel_pms_service_id', $service->id)
                    ->where('hotel_pms_service_category_id', $category->id)
                    ->where('audience', $audience)
                    ->where('pricing_unit', 'per_person')
                    ->exists();

                if ($exists) {
                    continue;
                }

                HotelPmsSwimmingRate::query()->create([
                    'hotel_id' => $service->hotel_id,
                    'hotel_pms_service_id' => $service->id,
                    'hotel_pms_service_category_id' => $category->id,
                    'audience' => $audience,
                    'pricing_unit' => 'per_person',
                    'unit_price' => $audience === 'child' ? 0 : $defaultPrice,
                    'currency' => $currency,
                    'is_active' => true,
                    'sort_order' => $audience === 'child' ? 1 : 0,
                ]);
            }
        }
    }

    /** @param  list<array<string, mixed>>  $rows */
    public function syncRates(Hotel $hotel, HotelPmsService $service, array $rows): void
    {
        $service->load('serviceCategories');
        $allowedCategoryIds = $service->serviceCategories->pluck('id')->all();
        $defaultCurrency = $this->normalizeCurrency($service->displayCurrency($hotel));
        $keepIds = [];

        foreach ($rows as $sort => $row) {
            $categoryId = ! empty($row['category_id']) ? (int) $row['category_id'] : null;
            if (! $categoryId || ! in_array($categoryId, $allowedCategoryIds, true)) {
                continue;
            }

            $audience = in_array($row['audience'] ?? '', self::allowedAudiences(), true)
                ? $row['audience']
                : 'adult';
            $pricingUnit = in_array($row['pricing_unit'] ?? '', self::allowedPricingUnits(), true)
                ? $row['pricing_unit']
                : 'per_person';

            $attrs = [
                'hotel_id' => $hotel->id,
                'hotel_pms_service_id' => $service->id,
                'hotel_pms_service_category_id' => $categoryId,
                'audience' => $audience,
                'pricing_unit' => $pricingUnit,
                'label' => trim((string) ($row['label'] ?? '')) ?: null,
                'unit_price' => max(0, (float) ($row['unit_price'] ?? 0)),
                'currency' => $this->normalizeCurrency((string) ($row['currency'] ?? $defaultCurrency)),
                'is_active' => ! empty($row['is_active']),
                'sort_order' => (int) $sort,
            ];

            if (! empty($row['id'])) {
                $rate = $service->swimmingRates()->whereKey((int) $row['id'])->first();
                if ($rate) {
                    $rate->update($attrs);
                    $keepIds[] = $rate->id;

                    continue;
                }
            }

            $rate = $service->swimmingRates()->create($attrs);
            $keepIds[] = $rate->id;
        }

        if ($keepIds === []) {
            $service->swimmingRates()->delete();
        } else {
            $service->swimmingRates()->whereNotIn('id', $keepIds)->delete();
        }
    }

    public function findRateForService(HotelPmsService $service, int $rateId): ?HotelPmsSwimmingRate
    {
        return HotelPmsSwimmingRate::query()
            ->where('hotel_pms_service_id', $service->id)
            ->whereKey($rateId)
            ->where('is_active', true)
            ->with('category')
            ->first();
    }

    public function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        return in_array($currency, self::allowedCurrencies(), true) ? $currency : 'TZS';
    }
}
