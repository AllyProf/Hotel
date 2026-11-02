<?php

namespace App\Services\Conference;

use App\Models\Hotel;
use App\Models\HotelPmsConferenceRate;
use App\Models\HotelPmsService;
use Illuminate\Support\Collection;

class ConferenceRateDataService
{
    /** @return list<string> */
    public static function allowedCurrencies(): array
    {
        return array_keys(config('hotel_pms_service_templates.conference_currencies', ['TZS' => '', 'USD' => '']));
    }

    /** @return list<string> */
    public static function allowedPricingUnits(): array
    {
        return array_keys(config('hotel_pms_service_templates.conference_pricing_units', []));
    }

    /** @return Collection<int, HotelPmsConferenceRate> */
    public function ratesForService(HotelPmsService $service): Collection
    {
        $this->ensureDefaultRateRows($service);

        return HotelPmsConferenceRate::query()
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
        $defaultUnit = in_array($service->pricing_unit, self::allowedPricingUnits(), true)
            ? $service->pricing_unit
            : 'per_day';

        foreach ($service->serviceCategories as $category) {
            $exists = HotelPmsConferenceRate::query()
                ->where('hotel_pms_service_id', $service->id)
                ->where('hotel_pms_service_category_id', $category->id)
                ->exists();

            if ($exists) {
                continue;
            }

            HotelPmsConferenceRate::query()->create([
                'hotel_id' => $service->hotel_id,
                'hotel_pms_service_id' => $service->id,
                'hotel_pms_service_category_id' => $category->id,
                'label' => $category->name,
                'capacity' => 0,
                'pricing_unit' => $defaultUnit,
                'unit_price' => $defaultPrice,
                'currency' => $currency,
                'is_active' => true,
            ]);
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

            $pricingUnit = in_array($row['pricing_unit'] ?? '', self::allowedPricingUnits(), true)
                ? $row['pricing_unit']
                : 'per_day';

            $attrs = [
                'hotel_id' => $hotel->id,
                'hotel_pms_service_id' => $service->id,
                'hotel_pms_service_category_id' => $categoryId,
                'label' => trim((string) ($row['label'] ?? '')) ?: null,
                'capacity' => max(0, min(9999, (int) ($row['capacity'] ?? 0))),
                'pricing_unit' => $pricingUnit,
                'unit_price' => max(0, (float) ($row['unit_price'] ?? 0)),
                'currency' => $this->normalizeCurrency((string) ($row['currency'] ?? $defaultCurrency)),
                'is_active' => ! empty($row['is_active']),
                'sort_order' => (int) $sort,
            ];

            if (! empty($row['id'])) {
                $rate = $service->conferenceRates()->whereKey((int) $row['id'])->first();
                if ($rate) {
                    $rate->update($attrs);
                    $keepIds[] = $rate->id;

                    continue;
                }
            }

            $rate = $service->conferenceRates()->create($attrs);
            $keepIds[] = $rate->id;
        }

        if ($keepIds === []) {
            $service->conferenceRates()->delete();
        } else {
            $service->conferenceRates()->whereNotIn('id', $keepIds)->delete();
        }
    }

    public function findRateForService(HotelPmsService $service, int $rateId): ?HotelPmsConferenceRate
    {
        return HotelPmsConferenceRate::query()
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
