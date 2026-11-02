<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelRatePlan;
use App\Models\HotelRoom;
use App\Models\HotelSetting;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingEngineService
{
    public function __construct(
        private HotelIntegrationService $integrations,
        private HotelSettingsService $settingsService,
    ) {}

    /** @return array<string, mixed> */
    public function uiConfig(): array
    {
        return config('hotel_booking_engine', []);
    }

    public function ensureSettings(Hotel $hotel): HotelSetting
    {
        return $this->integrations->ensureSettings($hotel);
    }

    /** @return array<string, mixed> */
    public function liveLinkData(Hotel $hotel): array
    {
        $settings = $this->ensureSettings($hotel);

        return $this->integrations->bookingEngineForDisplay($hotel, $settings);
    }

    /** @return array{hotel: Hotel, settings: HotelSetting, booking_engine: array<string, mixed>} */
    public function resolvePublicHotel(string $slug): ?array
    {
        $slug = Str::slug($slug);

        if ($slug === '') {
            return null;
        }

        $settings = HotelSetting::query()
            ->where('integrations->booking_engine->public_slug', $slug)
            ->first();

        $hotel = $settings
            ? Hotel::query()
                ->with(['rooms.photos', 'rooms.ratePlans', 'rooms.units', 'settings'])
                ->find($settings->hotel_id)
            : null;

        if (! $hotel) {
            $hotel = Hotel::query()
                ->where('slug', $slug)
                ->with(['rooms.photos', 'rooms.ratePlans', 'rooms.units', 'settings'])
                ->first();

            if ($hotel) {
                $settings = $this->ensureSettings($hotel);
            }
        }

        if (! $hotel || ! $settings) {
            return null;
        }

        $bookingEngine = $this->integrations->bookingEngineForDisplay($hotel, $settings);

        if (! ($bookingEngine['enabled'] ?? false)) {
            return null;
        }

        if (($bookingEngine['public_slug'] ?? '') !== $slug) {
            return null;
        }

        if (! $hotel->hasFeature('booking_engine_website')) {
            return null;
        }

        return [
            'hotel' => $hotel,
            'settings' => $settings,
            'booking_engine' => $bookingEngine,
        ];
    }

    /**
     * Enable direct booking and set a public slug for local testing.
     *
     * @return array{slug: string, url: string, rooms: int, rate_plans: int}
     */
    public function configureForTesting(Hotel $hotel): array
    {
        $settings = $this->ensureSettings($hotel);
        $slug = Str::slug($hotel->slug ?: $hotel->name ?: ('hotel-'.$hotel->id));

        $integrations = is_array($settings->integrations) ? $settings->integrations : [];
        $current = array_merge(
            $this->integrations->defaultBookingEngine($hotel),
            $integrations['booking_engine'] ?? []
        );

        $current['enabled'] = true;
        $current['public_slug'] = $slug;
        $integrations['booking_engine'] = $current;
        $settings->update(['integrations' => $integrations]);

        $be = is_array($settings->be) ? $settings->be : [];
        if (! isset($be['default_occupancy'])) {
            $be = array_merge($this->settingsService->defaultBe(), $be);
            $settings->update(['be' => $be]);
        }

        $rooms = HotelRoom::query()->where('hotel_id', $hotel->id)->get();
        foreach ($rooms as $room) {
            if (! $room->is_enabled) {
                $room->update(['is_enabled' => true]);
            }

            $plans = HotelRatePlan::query()->where('hotel_room_id', $room->id)->get();
            if ($plans->isEmpty()) {
                HotelRatePlan::query()->create([
                    'hotel_id' => $hotel->id,
                    'hotel_room_id' => $room->id,
                    'code' => 'BAR',
                    'occupancy' => max(2, (int) $room->max_occupancy ?: 2),
                    'meal_plan' => 'EP',
                    'base_rate' => 100000,
                    'local_base_rate' => 100000,
                    'local_currency' => $hotel->currency ?: 'TZS',
                    'international_currency' => $hotel->currency ?: 'USD',
                    'pricing_mode' => HotelRatePlan::PRICING_BOTH,
                    'be_ratio' => 0.85,
                    'description' => 'Best available rate',
                ]);
            } else {
                foreach ($plans as $plan) {
                    if ((float) $plan->base_rate <= 0 && (float) ($plan->local_base_rate ?? 0) <= 0) {
                        $plan->update([
                            'base_rate' => 100,
                            'local_base_rate' => 100000,
                        ]);
                    }
                }
            }
        }

        $planCount = HotelRatePlan::query()->where('hotel_id', $hotel->id)->count();

        return [
            'slug' => $slug,
            'url' => $this->integrations->directBookingUrl($current),
            'rooms' => $rooms->count(),
            'rate_plans' => $planCount,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function roomOffers(Hotel $hotel, Carbon $checkin, Carbon $checkout): array
    {
        $nights = max(1, $checkin->diffInDays($checkout));
        $currency = strtoupper($hotel->currency ?: 'USD');
        $offers = [];

        foreach ($hotel->rooms->where('is_enabled', true) as $room) {
            $plan = $room->ratePlans->sortByDesc('is_master')->first();
            if (! $plan) {
                continue;
            }

            $base = (float) $plan->rateForGuestType('international');
            if ($base <= 0) {
                $base = (float) ($plan->local_base_rate ?? 0);
            }

            $ratio = (float) ($plan->be_ratio ?? 1);
            if ($ratio <= 0) {
                $ratio = 1;
            }

            $daily = round($base * $ratio, 2);
            $total = round($daily * $nights, 2);
            $photo = $room->photos->firstWhere('is_primary', true) ?? $room->photos->first();

            $unitCount = $room->relationLoaded('units') ? $room->units->count() : $room->units()->count();
            $maxRooms = max(1, (int) ($room->room_count ?: ($unitCount > 0 ? $unitCount : 1)));

            $offers[] = [
                'room_id' => $room->id,
                'rate_plan_id' => $plan->id,
                'name' => $room->display_name ?: $room->name,
                'description' => $room->description ?: ($plan->description ?: ''),
                'meal_plan' => $plan->meal_plan,
                'max_occupancy' => (int) ($room->max_occupancy ?: $plan->occupancy ?: 2),
                'max_rooms' => $maxRooms,
                'photo_url' => $photo?->url(),
                'currency' => strtoupper($plan->international_currency ?: $currency),
                'daily_rate' => $daily,
                'nights' => $nights,
                'total' => $total,
            ];
        }

        return $offers;
    }

    /** @return array<string, mixed>|null */
    public function validateCoupon(Hotel $hotel, string $code, Carbon $checkin, Carbon $checkout): ?array
    {
        $settings = $this->settingsService->ensureDefaults($hotel);
        $coupons = $settings->be['coupons'] ?? [];
        $code = strtoupper(trim($code));

        if ($code === '' || ! is_array($coupons)) {
            return null;
        }

        foreach ($coupons as $coupon) {
            if (! is_array($coupon) || strtoupper((string) ($coupon['code'] ?? '')) !== $code) {
                continue;
            }

            if (! ($coupon['active'] ?? true)) {
                return null;
            }

            $nights = max(1, $checkin->diffInDays($checkout));
            if ($nights < (int) ($coupon['min_stay'] ?? 0)) {
                return null;
            }

            $from = ! empty($coupon['valid_from']) ? Carbon::parse($coupon['valid_from'])->startOfDay() : null;
            $to = ! empty($coupon['valid_to']) ? Carbon::parse($coupon['valid_to'])->endOfDay() : null;

            if ($from && $checkin->lt($from)) {
                return null;
            }

            if ($to && $checkin->gt($to)) {
                return null;
            }

            return $coupon;
        }

        return null;
    }

    public function applyCouponDiscount(float $total, array $coupon): float
    {
        $type = (string) ($coupon['discount_type'] ?? 'percent');
        $value = (float) ($coupon['discount_value'] ?? 0);

        if ($value <= 0) {
            return $total;
        }

        if ($type === 'fixed') {
            return max(0, round($total - $value, 2));
        }

        return max(0, round($total * (1 - min(100, $value) / 100), 2));
    }

    /** @return list<array<string, mixed>> */
    public function coupons(Hotel $hotel): array
    {
        $settings = $this->settingsService->ensureDefaults($hotel);
        $coupons = $settings->be['coupons'] ?? [];

        if (! is_array($coupons)) {
            return [];
        }

        return collect($coupons)
            ->filter(fn ($row) => is_array($row))
            ->sortBy(fn (array $row) => strtolower((string) ($row['code'] ?? '')))
            ->values()
            ->all();
    }

    /** @param  array<string, mixed>  $data */
    public function storeCoupon(Hotel $hotel, array $data): void
    {
        $settings = $this->settingsService->ensureDefaults($hotel);
        $be = is_array($settings->be) ? $settings->be : [];
        $coupons = is_array($be['coupons'] ?? null) ? $be['coupons'] : [];

        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $this->assertUniqueCouponCode($coupons, $code, null);

        $coupons[] = [
            'id' => (string) Str::uuid(),
            'code' => $code,
            'discount_type' => $data['discount_type'] ?? 'percent',
            'discount_value' => (float) ($data['discount_value'] ?? 0),
            'valid_from' => $data['valid_from'] ?? null,
            'valid_to' => $data['valid_to'] ?? null,
            'active' => (bool) ($data['active'] ?? true),
            'min_stay' => max(0, (int) ($data['min_stay'] ?? 0)),
        ];

        $max = (int) ($this->uiConfig()['max_coupons'] ?? 50);
        if (count($coupons) > $max) {
            throw ValidationException::withMessages([
                'code' => "You can add up to {$max} coupons.",
            ]);
        }

        $be['coupons'] = $coupons;
        $settings->update(['be' => $be]);
    }

    /** @param  array<string, mixed>  $data */
    public function updateCoupon(Hotel $hotel, string $couponId, array $data): void
    {
        $settings = $this->settingsService->ensureDefaults($hotel);
        $be = is_array($settings->be) ? $settings->be : [];
        $coupons = is_array($be['coupons'] ?? null) ? $be['coupons'] : [];

        $found = false;
        foreach ($coupons as $index => $coupon) {
            if (($coupon['id'] ?? '') !== $couponId) {
                continue;
            }

            $code = strtoupper(trim((string) ($data['code'] ?? $coupon['code'] ?? '')));
            $this->assertUniqueCouponCode($coupons, $code, $couponId);

            $coupons[$index] = array_merge($coupon, [
                'code' => $code,
                'discount_type' => $data['discount_type'] ?? $coupon['discount_type'] ?? 'percent',
                'discount_value' => (float) ($data['discount_value'] ?? $coupon['discount_value'] ?? 0),
                'valid_from' => $data['valid_from'] ?? $coupon['valid_from'] ?? null,
                'valid_to' => $data['valid_to'] ?? $coupon['valid_to'] ?? null,
                'active' => array_key_exists('active', $data) ? (bool) $data['active'] : (bool) ($coupon['active'] ?? true),
                'min_stay' => max(0, (int) ($data['min_stay'] ?? $coupon['min_stay'] ?? 0)),
            ]);
            $found = true;
            break;
        }

        if (! $found) {
            abort(404);
        }

        $be['coupons'] = $coupons;
        $settings->update(['be' => $be]);
    }

    public function deleteCoupon(Hotel $hotel, string $couponId): void
    {
        $settings = $this->settingsService->ensureDefaults($hotel);
        $be = is_array($settings->be) ? $settings->be : [];
        $coupons = is_array($be['coupons'] ?? null) ? $be['coupons'] : [];

        $be['coupons'] = array_values(array_filter(
            $coupons,
            fn (array $coupon) => ($coupon['id'] ?? '') !== $couponId
        ));
        $settings->update(['be' => $be]);
    }

    /** @param  list<array<string, mixed>>  $coupons */
    private function assertUniqueCouponCode(array $coupons, string $code, ?string $ignoreId): void
    {
        if ($code === '') {
            throw ValidationException::withMessages(['code' => 'Coupon code is required.']);
        }

        foreach ($coupons as $coupon) {
            if ($ignoreId !== null && ($coupon['id'] ?? '') === $ignoreId) {
                continue;
            }

            if (strtoupper((string) ($coupon['code'] ?? '')) === $code) {
                throw ValidationException::withMessages(['code' => 'This coupon code already exists.']);
            }
        }
    }
}
