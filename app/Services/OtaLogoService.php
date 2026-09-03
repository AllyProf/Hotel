<?php

namespace App\Services;

class OtaLogoService
{
    /** @var array<string, string> */
    private const CHANNEL_ALIASES = [
        'gommt' => 'goibibo',
        'goibibo' => 'goibibo',
        'bookingcom' => 'booking-com',
        'booking' => 'booking-com',
        'makemytrip' => 'makemytrip',
        'mmt' => 'makemytrip',
        'agoda' => 'agoda',
        'expedia' => 'expedia',
        'airbnb' => 'airbnb',
        'cleartrip' => 'cleartrip',
        'easemytrip' => 'ease-my-trip',
        'hostelworld' => 'hostelworld',
        'hotelbeds' => 'hotelbeds',
        'traveloka' => 'traveloka',
        'tripcom' => 'trip-com',
        'ctrip' => 'trip-com',
        'yatra' => 'yatra',
        'travelguru' => 'travelguru',
        'happyeasygo' => 'happyeasygo',
        'tiketcom' => 'tiket-com',
    ];

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return collect(config('otas', []))->map(function (array $ota) {
            $slug = $ota['slug'];
            $svgPath = public_path('panel-assets/img/otas/'.$ota['logo']);
            $pngPath = public_path('panel-assets/img/otas/'.$slug.'.png');

            if (file_exists($pngPath)) {
                $ota['logo_url'] = asset('panel-assets/img/otas/'.$slug.'.png');
            } elseif (file_exists($svgPath)) {
                $ota['logo_url'] = asset('panel-assets/img/otas/'.$ota['logo']);
            } else {
                $ota['logo_url'] = 'https://www.google.com/s2/favicons?domain='.$ota['domain'].'&sz=128';
            }

            return $ota;
        })->all();
    }

    /** @return array<string, mixed> */
    public function presentationForChannel(string $channel): array
    {
        $channel = trim($channel);
        $normalized = $this->normalize($channel);

        if (in_array($normalized, ['direct', 'pms', 'walkin', 'phone', 'frontdesk'], true)) {
            return [
                'name' => 'Direct',
                'label' => $channel !== '' ? $channel : 'Direct',
                'brand_color' => '#940000',
                'logo_url' => null,
                'initials' => 'DI',
                'slug' => 'direct',
            ];
        }

        $ota = $this->resolveByChannel($channel);
        $name = $ota['name'] ?? ($channel !== '' ? $channel : 'OTA');
        $brandColor = $ota['brand_color'] ?? '#6b7280';

        return [
            'name' => $name,
            'label' => $channel !== '' ? $channel : $name,
            'brand_color' => $brandColor,
            'logo_url' => $ota['logo_url'] ?? null,
            'initials' => $this->initials($name),
            'slug' => $ota['slug'] ?? null,
        ];
    }

    /** @return array<string, mixed>|null */
    public function resolveByChannel(string $channel): ?array
    {
        $channel = trim($channel);
        if ($channel === '') {
            return null;
        }

        $normalized = $this->normalize($channel);

        if (isset(self::CHANNEL_ALIASES[$normalized])) {
            $bySlug = $this->findBySlug(self::CHANNEL_ALIASES[$normalized]);
            if ($bySlug !== null) {
                return $bySlug;
            }
        }

        foreach ($this->all() as $ota) {
            $slugNorm = $this->normalize($ota['slug']);
            $nameNorm = $this->normalize($ota['name']);
            $domainNorm = $this->normalize(str_replace('.com', '', $ota['domain']));

            if ($normalized === $slugNorm
                || $normalized === $nameNorm
                || $normalized === $domainNorm
                || str_contains($normalized, $slugNorm)
                || str_contains($slugNorm, $normalized)
                || str_contains($nameNorm, $normalized)) {
                return $ota;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function findBySlug(string $slug): ?array
    {
        foreach ($this->all() as $ota) {
            if ($ota['slug'] === $slug) {
                return $ota;
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($value)) ?? '';
    }

    private function initials(string $name): string
    {
        return collect(explode(' ', $name))
            ->filter()
            ->map(fn ($word) => strtoupper(substr($word, 0, 1)))
            ->take(2)
            ->implode('');
    }
}
