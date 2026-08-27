<?php

namespace App\Services\ChannelManager;

use App\Services\PlatformIntegrationService;
use Illuminate\Support\Facades\Http;

class ChannelManagerClient
{
    public function __construct(private PlatformIntegrationService $integrations) {}

    public function isConfigured(): bool
    {
        return $this->integrations->isChannelManagerConfigured();
    }

    /** @return array{success: bool, http_code: int|null, message: string, response: mixed} */
    public function getPropertyDetails(string $hotelCode): array
    {
        $credentials = $this->integrations->channelManagerCredentials();

        if ($credentials === null) {
            return [
                'success' => false,
                'http_code' => null,
                'message' => 'Channel Manager is not configured.',
                'response' => null,
            ];
        }

        $url = rtrim($credentials['base_url'], '/')
            .'/property_details/'.$hotelCode
            .'?partnerId='.urlencode($credentials['partner_id']);

        return $this->request('GET', $url, $credentials, null);
    }

    /** @return array{success: bool, http_code: int|null, message: string, response: mixed} */
    public function pushInventory(string $hotelCode, array $payload): array
    {
        return $this->post('/update/{partnerId}', $payload);
    }

    /** @return array{success: bool, http_code: int|null, message: string, response: mixed} */
    public function pushRates(string $hotelCode, array $payload): array
    {
        return $this->post('/update-rates/{partnerId}', $payload);
    }

    /** @return array{success: bool, http_code: int|null, message: string, response: mixed} */
    public function fetchReservations(string $hotelCode, string $startDate, string $endDate): array
    {
        return $this->post('/data/{partnerId}', [
            'type' => 'reservation',
            'hotelCode' => $hotelCode,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    /** @return array{success: bool, http_code: int|null, message: string, response: mixed} */
    private function post(string $pathTemplate, array $payload): array
    {
        $credentials = $this->integrations->channelManagerCredentials();

        if ($credentials === null) {
            return [
                'success' => false,
                'http_code' => null,
                'message' => 'Channel Manager is not configured.',
                'response' => null,
            ];
        }

        $url = rtrim($credentials['base_url'], '/').str_replace(
            '{partnerId}',
            $credentials['partner_id'],
            $pathTemplate
        );

        return $this->request('POST', $url, $credentials, $payload);
    }

    /** @return array{success: bool, http_code: int|null, message: string, response: mixed} */
    private function request(string $method, string $url, array $credentials, ?array $payload, int $attempt = 1): array
    {
        try {
            $request = Http::connectTimeout(15)
                ->timeout(45)
                ->withBasicAuth($credentials['username'], $credentials['password'])
                ->acceptJson();

            $response = $method === 'GET'
                ? $request->get($url)
                : $request->asJson()->post($url, $payload ?? []);

            $body = $response->json() ?? $response->body();
            $ok = $response->successful();

            return [
                'success' => $ok,
                'http_code' => $response->status(),
                'message' => $this->messageFromResponse($body, $ok),
                'response' => $body,
            ];
        } catch (\Throwable $e) {
            if ($attempt < 2 && $this->isRetryable($e)) {
                usleep(750000 * $attempt);

                return $this->request($method, $url, $credentials, $payload, $attempt + 1);
            }

            return [
                'success' => false,
                'http_code' => null,
                'message' => $this->friendlyErrorMessage($e->getMessage()),
                'response' => null,
            ];
        }
    }

    private function isRetryable(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'ssl connection')
            || str_contains($message, 'could not resolve')
            || str_contains($message, 'connection refused');
    }

    private function friendlyErrorMessage(string $message): string
    {
        $lower = strtolower($message);

        if (str_contains($lower, 'timed out') || str_contains($lower, 'timeout') || str_contains($lower, 'ssl connection')) {
            return 'Channel Manager did not respond in time. Your changes were saved — please try syncing again in a moment.';
        }

        return $message;
    }

    private function messageFromResponse(mixed $body, bool $ok): string
    {
        if (is_array($body)) {
            if (isset($body['message'])) {
                return (string) $body['message'];
            }
            if (isset($body['success'])) {
                return $body['success'] ? 'Success' : 'Request failed';
            }
        }

        return $ok ? 'OK' : 'Request failed';
    }
}
