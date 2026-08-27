<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelSetting;

class OtaConnectionService
{
    public function __construct(
        private HotelIntegrationService $hotelIntegrations,
        private OtaLogoService $otaLogos,
    ) {}

    /** @return array<string, array<string, mixed>> */
    public function allConnections(Hotel $hotel): array
    {
        $connections = $this->channelManager($hotel)['ota_connections'] ?? [];

        return is_array($connections) ? $connections : [];
    }

    public function connection(Hotel $hotel, string $slug): array
    {
        $stored = $this->allConnections($hotel)[$slug] ?? [];

        return array_merge([
            'enabled' => false,
            'hotel_code' => '',
            'currency' => $hotel->currency ?? 'USD',
            'rate_multiplier' => 1.0,
            'mapped_at' => null,
        ], is_array($stored) ? $stored : []);
    }

    public function isConfigured(Hotel $hotel, string $slug): bool
    {
        $conn = $this->connection($hotel, $slug);

        return ! empty($conn['enabled'])
            && trim((string) ($conn['hotel_code'] ?? '')) !== '';
    }

    /** @return list<array<string, mixed>> */
    public function configured(Hotel $hotel): array
    {
        return collect($this->otaLogos->all())
            ->filter(fn (array $ota) => $this->isConfigured($hotel, $ota['slug']))
            ->values()
            ->all();
    }

    /** @return list<string> */
    public function configuredSlugs(Hotel $hotel): array
    {
        return array_column($this->configured($hotel), 'slug');
    }

    /** @param array<string, mixed> $data */
    public function saveConnection(Hotel $hotel, string $slug, array $data): void
    {
        if (! $this->isValidSlug($slug)) {
            abort(422, 'Unknown OTA.');
        }

        $settings = $this->hotelIntegrations->ensureSettings($hotel);
        $integrations = $settings->integrations ?? $this->hotelIntegrations->defaultIntegrations($hotel);
        $cm = array_merge(
            $this->hotelIntegrations->defaultChannelManager($hotel),
            $integrations['channel_manager'] ?? []
        );

        $connections = is_array($cm['ota_connections'] ?? null) ? $cm['ota_connections'] : [];
        $hotelCode = trim((string) ($data['hotel_code'] ?? ''));
        $enabled = (bool) ($data['enabled'] ?? false);

        $connections[$slug] = [
            'enabled' => $enabled,
            'hotel_code' => $hotelCode,
            'currency' => trim((string) ($data['currency'] ?? 'USD')) ?: 'USD',
            'rate_multiplier' => max(0.1, (float) ($data['rate_multiplier'] ?? 1)),
            'mapped_at' => ($enabled && $hotelCode !== '') ? now()->toIso8601String() : ($connections[$slug]['mapped_at'] ?? null),
        ];

        $cm['ota_connections'] = $connections;
        $integrations['channel_manager'] = $cm;
        $settings->update(['integrations' => $integrations]);
    }

    /** @return array<string, mixed> */
    private function channelManager(Hotel $hotel): array
    {
        $settings = $this->hotelIntegrations->ensureSettings($hotel);

        return array_merge(
            $this->hotelIntegrations->defaultChannelManager($hotel),
            $settings->integrations['channel_manager'] ?? []
        );
    }

    private function isValidSlug(string $slug): bool
    {
        return collect(config('otas', []))->contains(fn (array $ota) => ($ota['slug'] ?? '') === $slug);
    }
}
