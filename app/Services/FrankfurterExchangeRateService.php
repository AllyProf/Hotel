<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FrankfurterExchangeRateService
{
    public function convert(float $amount, string $from, string $to, ?string $date = null): ?float
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        if ($from === '' || $to === '') {
            return null;
        }

        if ($from === $to) {
            return round($amount, 2);
        }

        $rate = $this->getRate($from, $to, $date);
        if ($rate === null) {
            return null;
        }

        return round($amount * $rate, 2);
    }

    public function getRate(string $from, string $to, ?string $date = null): ?float
    {
        $from = strtolower(trim($from));
        $to = strtolower(trim($to));

        if ($from === '' || $to === '' || $from === $to) {
            return $from === $to ? 1.0 : null;
        }

        $dateKey = $date ? trim($date) : 'latest';
        $cacheKey = 'frankfurter.rate.'.$dateKey.'.'.$from.'.'.$to;
        $ttl = (int) config('hotel_analytics.frankfurter.cache_seconds', 3600);

        $payload = Cache::remember($cacheKey, $ttl, function () use ($from, $to, $date) {
            return $this->fetchRatePayload($from, $to, $date);
        });

        if (! is_array($payload) || ! isset($payload['rate'])) {
            return null;
        }

        $rate = (float) $payload['rate'];

        return $rate > 0 ? $rate : null;
    }

    /**
     * @param  array<string, float>  $amountsByCurrency
     * @return array{
     *     total: float,
     *     rate_date: ?string,
     *     failed: array<string, float>,
     *     conversions: list<array{from: string, amount: float, converted: float, rate: float}>
     * }
     */
    public function sumInCurrency(array $amountsByCurrency, string $targetCurrency, ?string $date = null): array
    {
        $targetCurrency = strtoupper(trim($targetCurrency));
        $total = 0.0;
        $failed = [];
        $conversions = [];
        $rateDate = null;

        foreach ($amountsByCurrency as $code => $amount) {
            $amount = (float) $amount;
            $code = strtoupper(trim((string) $code));
            if ($code === '' || abs($amount) < 0.009) {
                continue;
            }

            if ($code === $targetCurrency) {
                $total += $amount;
                $conversions[] = [
                    'from' => $code,
                    'amount' => $amount,
                    'converted' => $amount,
                    'rate' => 1.0,
                ];

                continue;
            }

            $rate = $this->getRate($code, $targetCurrency, $date);
            if ($rate === null) {
                $failed[$code] = ($failed[$code] ?? 0) + $amount;

                continue;
            }

            $converted = round($amount * $rate, 2);
            $total += $converted;
            $rateDate ??= $this->getRateMetaDate($code, $targetCurrency, $date);
            $conversions[] = [
                'from' => $code,
                'amount' => $amount,
                'converted' => $converted,
                'rate' => $rate,
            ];
        }

        return [
            'total' => round($total, 2),
            'rate_date' => $rateDate,
            'failed' => $failed,
            'conversions' => $conversions,
        ];
    }

    /** @return array{rate: float, date: ?string}|null */
    private function fetchRatePayload(string $from, string $to, ?string $date): ?array
    {
        $baseUrl = rtrim((string) config('hotel_analytics.frankfurter.base_url', 'https://api.frankfurter.dev'), '/');
        $url = $baseUrl.'/v2/rate/'.$from.'/'.$to;
        if ($date) {
            $url .= '?date='.urlencode($date);
        }

        try {
            $response = Http::connectTimeout(10)
                ->timeout(15)
                ->acceptJson()
                ->get($url);

            if (! $response->successful()) {
                Log::warning('Frankfurter rate request failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();
            if (! is_array($data) || ! isset($data['rate'])) {
                return null;
            }

            return [
                'rate' => (float) $data['rate'],
                'date' => isset($data['date']) ? (string) $data['date'] : null,
            ];
        } catch (\Throwable $exception) {
            Log::warning('Frankfurter rate request error', [
                'url' => $url,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function getRateMetaDate(string $from, string $to, ?string $date): ?string
    {
        $from = strtolower(trim($from));
        $to = strtolower(trim($to));
        $dateKey = $date ? trim($date) : 'latest';
        $cacheKey = 'frankfurter.rate.'.$dateKey.'.'.$from.'.'.$to;
        $payload = Cache::get($cacheKey);

        return is_array($payload) ? ($payload['date'] ?? null) : null;
    }
}
