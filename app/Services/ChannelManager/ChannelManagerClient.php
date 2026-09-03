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
        return $this->fetchData('reservation', $hotelCode, $startDate, $endDate);
    }

    /** @return array{success: bool, http_code: int|null, message: string, response: mixed} */
    public function fetchInventory(string $hotelCode, string $startDate, string $endDate): array
    {
        return $this->fetchData('inventory', $hotelCode, $startDate, $endDate);
    }

    /** @return array{success: bool, http_code: int|null, message: string, response: mixed} */
    public function fetchRates(string $hotelCode, string $startDate, string $endDate): array
    {
        return $this->fetchData('rates', $hotelCode, $startDate, $endDate);
    }

    /** @return array{success: bool, http_code: int|null, message: string, response: mixed} */
    public function pushInventoryRestrictions(array $payload): array
    {
        return $this->post('/update/{partnerId}', $payload);
    }

    /** @return array{success: bool, http_code: int|null, message: string, response: mixed} */
    public function pushRateRestrictions(array $payload): array
    {
        return $this->post('/update-rates/{partnerId}', $payload);
    }

    /** @return array{success: bool, http_code: int|null, message: string, response: mixed} */
    public function markNoShow(string $hotelCode, string $bookingId, string $channel): array
    {
        return $this->post('/marknoshow/{partnerId}', [
            'hotelCode' => $hotelCode,
            'bookingId' => $bookingId,
            'channel' => $channel,
        ]);
    }

    /** @return array{success: bool, http_code: int|null, message: string, response: mixed} */
    public function channelMultiplier(string $hotelCode, float $multiplier, array $channels): array
    {
        return $this->post('/channel_multiplier/{partnerId}', [
            'hotelCode' => $hotelCode,
            'multiplier' => $multiplier,
            'channels' => array_values($channels),
        ]);
    }

    /** @param  list<string>  $channels @return array{success: bool, http_code: int|null, message: string, response: mixed} */
    public function fetchMessages(string $hotelCode, string $startDate, string $endDate, array $channels = []): array
    {
        $payload = [
            'hotelCode' => $hotelCode,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];

        if ($channels !== []) {
            $payload['channels'] = array_values($channels);
            $payload['toChannels'] = array_values($channels);
        }

        return $this->post('/message/{partnerId}', $payload);
    }

    /** @param  list<string>  $channels @return array{success: bool, http_code: int|null, message: string, response: mixed} */
    public function fetchReviews(string $hotelCode, string $startDate, string $endDate, array $channels = []): array
    {
        $payload = [
            'hotelCode' => $hotelCode,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'type' => 'review',
        ];

        if ($channels !== []) {
            $payload['channels'] = array_values($channels);
            $payload['toChannels'] = array_values($channels);
        }

        return $this->post('/message/{partnerId}', $payload);
    }

    /** @return array{success: bool, http_code: int|null, message: string, response: mixed} */
    private function fetchData(string $type, string $hotelCode, string $startDate, string $endDate): array
    {
        return $this->post('/data/{partnerId}', [
            'type' => $type,
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
            $ok = AiosellPayloadBuilder::isSuccessfulResponse($body, $response->successful());

            return [
                'success' => $ok,
                'http_code' => $response->status(),
                'message' => AiosellPayloadBuilder::responseMessage($body, $ok),
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
        return AiosellPayloadBuilder::responseMessage($body, $ok);
    }
}
