<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class HotelIntegrationService
{
    public function __construct(
        private HotelSettingsService $settingsService,
        private PlatformIntegrationService $platformIntegrations,
    ) {}

    public function ensureSettings(Hotel $hotel): HotelSetting
    {
        $settings = $this->settingsService->ensureDefaults($hotel);
        $integrations = $settings->integrations;

        if (! is_array($integrations) || ! isset($integrations['channel_manager'], $integrations['booking_engine'])) {
            $settings->update([
                'integrations' => array_merge(
                    $this->defaultIntegrations($hotel),
                    is_array($integrations) ? $integrations : []
                ),
            ]);
            $settings = $settings->fresh();
        }

        return $settings;
    }

    /** @return array<string, mixed> */
    public function defaultIntegrations(Hotel $hotel): array
    {
        return [
            'channel_manager' => $this->defaultChannelManager($hotel),
            'booking_engine' => $this->defaultBookingEngine($hotel),
        ];
    }

    /** @return array<string, mixed> */
    public function defaultChannelManager(Hotel $hotel): array
    {
        return [
            'hotel_code' => Str::slug($hotel->name ?: 'hotel-'.$hotel->id),
        ];
    }

    /** @return array<string, mixed> */
    public function defaultBookingEngine(Hotel $hotel): array
    {
        $slug = Str::slug($hotel->name ?: 'hotel-'.$hotel->id);

        return [
            'enabled' => false,
            'public_slug' => $slug,
            'custom_domain' => '',
        ];
    }

    /** @return array{status: string, label: string, hotel_code: string, platform_configured: bool} */
    public function channelManagerStatus(Hotel $hotel, HotelSetting $settings): array
    {
        $hotelCm = array_merge(
            $this->defaultChannelManager($hotel),
            $settings->integrations['channel_manager'] ?? []
        );

        $platformConfigured = $this->platformIntegrations->isChannelManagerConfigured();

        if ($platformConfigured && ! empty($hotelCm['hotel_code'])) {
            return [
                'status' => 'active',
                'label' => 'Active',
                'hotel_code' => $hotelCm['hotel_code'],
                'platform_configured' => true,
            ];
        }

        if ($platformConfigured) {
            return [
                'status' => 'pending',
                'label' => 'Setup in progress',
                'hotel_code' => $hotelCm['hotel_code'],
                'platform_configured' => true,
            ];
        }

        return [
            'status' => 'platform_pending',
            'label' => 'Pending connection',
            'hotel_code' => $hotelCm['hotel_code'],
            'platform_configured' => false,
        ];
    }

    /** @return array<string, mixed> */
    public function bookingEngineForDisplay(Hotel $hotel, HotelSetting $settings): array
    {
        $stored = array_merge(
            $this->defaultBookingEngine($hotel),
            $settings->integrations['booking_engine'] ?? []
        );

        $stored['booking_url'] = $this->directBookingUrl($stored);

        return $stored;
    }

    /** @param array<string, mixed> $data */
    public function updateBookingEngine(Hotel $hotel, HotelSetting $settings, array $data): void
    {
        $current = array_merge(
            $this->defaultBookingEngine($hotel),
            $settings->integrations['booking_engine'] ?? []
        );

        $slug = Str::slug(trim((string) ($data['public_slug'] ?? $current['public_slug'])));

        $merged = array_merge($current, [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'public_slug' => $slug ?: $current['public_slug'],
            'custom_domain' => trim((string) ($data['custom_domain'] ?? '')),
        ]);

        $integrations = $settings->integrations ?? $this->defaultIntegrations($hotel);
        $integrations['booking_engine'] = $merged;
        $settings->update(['integrations' => $integrations]);
    }

    /** @param array<string, mixed> $be */
    public function directBookingUrl(array $be): string
    {
        if (! empty($be['custom_domain'])) {
            $domain = rtrim($be['custom_domain'], '/');

            return str_starts_with($domain, 'http') ? $domain : 'https://'.$domain;
        }

        return rtrim(config('app.url'), '/').'/book/'.($be['public_slug'] ?? 'hotel');
    }
}
