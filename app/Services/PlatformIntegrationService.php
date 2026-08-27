<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Crypt;

class PlatformIntegrationService
{
    /** @return array<string, mixed> */
    public function ensureIntegrations(): array
    {
        $setting = PlatformSetting::current();
        $integrations = $setting->integrations;

        if (! is_array($integrations) || ! isset($integrations['channel_manager'])) {
            $integrations = ['channel_manager' => $this->defaultChannelManager()];
            $setting->update(['integrations' => $integrations]);
        }

        return $setting->fresh()->integrations ?? [];
    }

    /** @return array<string, mixed> */
    public function defaultChannelManager(): array
    {
        return [
            'enabled' => false,
            'provider_name' => config('channel_manager_integration.provider_name'),
            'base_url' => config('channel_manager_integration.default_base_url'),
            'partner_id' => '',
            'api_username' => '',
            'api_password' => null,
            'webhook_username' => '',
            'webhook_password' => null,
            'webhook_path' => 'webhooks/cm/reservations',
            'use_sandbox' => true,
        ];
    }

    public function isChannelManagerConfigured(): bool
    {
        return $this->channelManagerCredentials() !== null
            && (bool) ($this->channelManagerConfig()['enabled'] ?? false);
    }

    /** @return array<string, mixed> */
    public function channelManagerConfig(): array
    {
        return array_merge(
            $this->defaultChannelManager(),
            $this->ensureIntegrations()['channel_manager'] ?? []
        );
    }

    public function isChannelManagerSandbox(): bool
    {
        return (bool) ($this->channelManagerConfig()['use_sandbox'] ?? false);
    }

    /** @return array{username: string, password: string, base_url: string, partner_id: string}|null */
    public function channelManagerCredentials(): ?array
    {
        $cm = $this->channelManagerConfig();

        $username = trim((string) ($cm['api_username'] ?? ''));
        if ($username === '' && $this->isChannelManagerSandbox()) {
            $username = trim((string) config('channel_manager_integration.sandbox.api_username', ''));
        }

        $password = null;
        if (! empty($cm['api_password'])) {
            try {
                $password = Crypt::decryptString($cm['api_password']);
            } catch (\Throwable) {
                $password = null;
            }
        }

        if ($password === null && $this->isChannelManagerSandbox()) {
            $password = config('channel_manager_integration.sandbox.api_password');
        }

        if ($username === '' || empty($password)) {
            return null;
        }

        $partnerId = trim((string) ($cm['partner_id'] ?? ''));
        if ($partnerId === '' && $this->isChannelManagerSandbox()) {
            $partnerId = config('channel_manager_integration.sandbox.partner_id', 'sample-pms');
        }

        return [
            'username' => $username,
            'password' => (string) $password,
            'base_url' => rtrim($cm['base_url'] ?? config('channel_manager_integration.default_base_url'), '/'),
            'partner_id' => $partnerId,
        ];
    }

    /** @return array<string, mixed> */
    public function channelManagerForDisplay(?string $hotelCode = null): array
    {
        $stored = array_merge(
            $this->defaultChannelManager(),
            $this->ensureIntegrations()['channel_manager'] ?? []
        );

        $stored['has_api_password'] = ! empty($stored['api_password']);
        $stored['has_webhook_password'] = ! empty($stored['webhook_password']);
        unset($stored['api_password'], $stored['webhook_password']);

        $stored['webhook_url'] = $this->webhookUrl($stored['webhook_path'] ?? '');
        $stored['property_details_url'] = $this->buildApiUrl(
            $stored['base_url'] ?? '',
            '/property_details/'.($hotelCode ?: config('channel_manager_integration.sandbox.hotel_code')),
            ['partnerId' => $stored['partner_id'] ?: config('channel_manager_integration.sandbox.partner_id')]
        );

        return $stored;
    }

    /** @param array<string, mixed> $data */
    public function updateChannelManager(array $data): void
    {
        $setting = PlatformSetting::current();
        $current = array_merge(
            $this->defaultChannelManager(),
            $setting->integrations['channel_manager'] ?? []
        );

        $merged = array_merge($current, [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'provider_name' => trim((string) ($data['provider_name'] ?? $current['provider_name'])),
            'base_url' => rtrim(trim((string) ($data['base_url'] ?? $current['base_url'])), '/'),
            'partner_id' => trim((string) ($data['partner_id'] ?? '')),
            'api_username' => trim((string) ($data['api_username'] ?? '')),
            'webhook_username' => trim((string) ($data['webhook_username'] ?? '')),
            'webhook_path' => trim((string) ($data['webhook_path'] ?? $current['webhook_path'])),
            'use_sandbox' => (bool) ($data['use_sandbox'] ?? false),
        ]);

        if (! empty($data['api_password'])) {
            $merged['api_password'] = Crypt::encryptString((string) $data['api_password']);
        }

        if (! empty($data['webhook_password'])) {
            $merged['webhook_password'] = Crypt::encryptString((string) $data['webhook_password']);
        }

        $integrations = $setting->integrations ?? [];
        $integrations['channel_manager'] = $merged;
        $setting->update(['integrations' => $integrations]);
    }

    /** @param array<string, string> $query */
    public function buildApiUrl(string $baseUrl, string $path, array $query = []): string
    {
        $url = rtrim($baseUrl, '/').'/'.ltrim($path, '/');

        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        return $url;
    }

    /** @param array<string, mixed> $cm */
    public function resolveEndpointUrl(array $cm, string $pathTemplate): string
    {
        $path = str_replace(
            ['{partnerId}', '{hotelCode}', '{webhookUrl}'],
            [
                $cm['partner_id'] ?: '{partnerId}',
                config('channel_manager_integration.sandbox.hotel_code'),
                $cm['webhook_url'] ?? '{webhookUrl}',
            ],
            $pathTemplate
        );

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return rtrim($cm['base_url'] ?? config('channel_manager_integration.default_base_url'), '/').'/'.ltrim($path, '/');
    }

    public function webhookUrl(string $path): string
    {
        return rtrim(config('app.url'), '/').'/'.ltrim($path, '/');
    }
}
