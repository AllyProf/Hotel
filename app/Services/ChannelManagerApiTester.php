<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Http\Request as HttpRequest;

class ChannelManagerApiTester
{
    public function __construct(private PlatformIntegrationService $integrations) {}

    /** @return array{summary: array{total: int, passed: int, failed: int, skipped: int}, results: list<array<string, mixed>>} */
    public function runAll(): array
    {
        $credentials = $this->integrations->channelManagerCredentials();

        if ($credentials === null) {
            return [
                'summary' => ['total' => 0, 'passed' => 0, 'failed' => 1, 'skipped' => 0],
                'results' => [[
                    'key' => 'credentials',
                    'name' => 'API Credentials',
                    'method' => '—',
                    'url' => '—',
                    'status' => 'fail',
                    'http_code' => null,
                    'message' => 'Channel Manager is not configured. Save credentials and enable integration first.',
                    'response' => null,
                ]],
            ];
        }

        $hotelCode = $this->hotelCode();
        $partnerId = $credentials['partner_id'];
        $baseUrl = $credentials['base_url'];
        $today = now()->format('Y-m-d');
        $tomorrow = now()->addDay()->format('Y-m-d');

        $tests = [
            $this->testCase('property_details', 'Get Property / Mapping Details', 'GET',
                "{$baseUrl}/property_details/{$hotelCode}?partnerId={$partnerId}", null, $credentials),

            $this->testCase('inventory_push', 'Inventory Push', 'POST',
                "{$baseUrl}/update/{$partnerId}",
                [
                    'hotelCode' => $hotelCode,
                    'updates' => [[
                        'startDate' => $today,
                        'endDate' => $today,
                        'rooms' => [
                            ['available' => 5, 'roomCode' => 'executive'],
                            ['available' => 3, 'roomCode' => 'suite'],
                        ],
                    ]],
                ], $credentials),

            $this->testCase('rate_push', 'Rate Push', 'POST',
                "{$baseUrl}/update-rates/{$partnerId}",
                [
                    'hotelCode' => $hotelCode,
                    'updates' => [[
                        'startDate' => $today,
                        'endDate' => $tomorrow,
                        'rates' => [
                            ['roomCode' => 'executive', 'rate' => 1749, 'rateplanCode' => 'executive-s-ep'],
                            ['roomCode' => 'suite', 'rate' => 2999, 'rateplanCode' => 'suite-d-cp'],
                        ],
                    ]],
                ], $credentials),

            $this->testCase('inventory_restrictions', 'Inventory Restrictions Push', 'POST',
                "{$baseUrl}/update/{$partnerId}",
                [
                    'hotelCode' => $hotelCode,
                    'toChannels' => ['agoda', 'booking.com'],
                    'updates' => [[
                        'startDate' => $today,
                        'endDate' => $today,
                        'rooms' => [[
                            'roomCode' => 'executive',
                            'restrictions' => [
                                'stopSell' => false,
                                'minimumStay' => 1,
                                'closeOnArrival' => false,
                                'closeOnDeparture' => false,
                            ],
                        ]],
                    ]],
                ], $credentials),

            $this->testCase('rate_restrictions', 'Rate Restrictions Push', 'POST',
                "{$baseUrl}/update-rates/{$partnerId}",
                [
                    'hotelCode' => $hotelCode,
                    'toChannels' => ['agoda', 'booking.com'],
                    'updates' => [[
                        'startDate' => $today,
                        'endDate' => $today,
                        'rates' => [[
                            'roomCode' => 'executive',
                            'rateplanCode' => 'executive-s-ep',
                            'restrictions' => [
                                'stopSell' => false,
                                'minimumStay' => 1,
                                'closeOnArrival' => false,
                                'closeOnDeparture' => false,
                            ],
                        ]],
                    ]],
                ], $credentials),

            $this->testCase('fetch_inventory', 'Fetch Inventory', 'POST',
                "{$baseUrl}/data/{$partnerId}",
                [
                    'type' => 'inventory',
                    'hotelCode' => $hotelCode,
                    'startDate' => $today,
                    'endDate' => $tomorrow,
                ], $credentials),

            $this->testCase('fetch_rates', 'Fetch Rates', 'POST',
                "{$baseUrl}/data/{$partnerId}",
                [
                    'type' => 'rates',
                    'hotelCode' => $hotelCode,
                    'startDate' => $today,
                    'endDate' => $tomorrow,
                ], $credentials),

            $this->testCase('fetch_reservations', 'Fetch Reservations', 'POST',
                "{$baseUrl}/data/{$partnerId}",
                [
                    'type' => 'reservation',
                    'hotelCode' => $hotelCode,
                    'startDate' => $today,
                    'endDate' => $tomorrow,
                ], $credentials),

            $this->testCase('channel_multiplier', 'Channel Multiplier', 'POST',
                "{$baseUrl}/channel_multiplier/{$partnerId}",
                [
                    'hotelCode' => $hotelCode,
                    'multiplier' => 1,
                    'channels' => ['agoda'],
                ], $credentials),

            $this->testCase('mark_noshow', 'Mark No Show', 'POST',
                "{$baseUrl}/marknoshow/{$partnerId}",
                [
                    'hotelCode' => $hotelCode,
                    'bookingId' => '111222350',
                    'channel' => 'gommt',
                ], $credentials, acceptHttpCodes: [200, 400, 404], softFailCodes: [500]),

            $this->testWebhook($hotelCode, $today, $tomorrow),
        ];

        $passed = collect($tests)->where('status', 'pass')->count();
        $failed = collect($tests)->where('status', 'fail')->count();
        $skipped = collect($tests)->where('status', 'skip')->count();

        return [
            'summary' => [
                'total' => count($tests),
                'passed' => $passed,
                'failed' => $failed,
                'skipped' => $skipped,
            ],
            'results' => $tests,
        ];
    }

    /** @return array<string, mixed> */
    private function testWebhook(string $hotelCode, string $checkin, string $checkout): array
    {
        $display = $this->integrations->channelManagerForDisplay($hotelCode);
        $url = $display['webhook_url'] ?? '';

        $integrations = $this->integrations->ensureIntegrations()['channel_manager'] ?? [];
        $webhookUser = $integrations['webhook_username'] ?? '';
        $webhookPass = null;

        if (! empty($integrations['webhook_password'])) {
            try {
                $webhookPass = \Illuminate\Support\Facades\Crypt::decryptString($integrations['webhook_password']);
            } catch (\Throwable) {
                $webhookPass = null;
            }
        }

        $payload = [
            'action' => 'book',
            'hotelCode' => $hotelCode,
            'channel' => 'Goibibo',
            'bookingId' => 'TEST-'.now()->format('YmdHis'),
            'checkin' => $checkin,
            'checkout' => $checkout,
            'segment' => 'OTA',
            'pah' => false,
            'amount' => [
                'amountAfterTax' => 1204,
                'amountBeforeTax' => 1075,
                'tax' => 129,
                'currency' => 'INR',
            ],
            'guest' => ['firstName' => 'Test', 'lastName' => 'Guest'],
            'rooms' => [[
                'roomCode' => 'executive',
                'rateplanCode' => 'executive-s-ep',
                'occupancy' => ['adults' => 1, 'children' => 0],
                'prices' => [['date' => $checkin, 'sellRate' => 1075]],
            ]],
        ];

        try {
            $request = HttpRequest::create(
                route('webhooks.cm.reservations', [], false),
                'POST',
                server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
                content: json_encode($payload)
            );

            if ($webhookUser !== '' && $webhookPass !== null) {
                $request->headers->set('Authorization', 'Basic '.base64_encode($webhookUser.':'.$webhookPass));
            }

            $response = app()->handle($request);
            $httpCode = $response->getStatusCode();
            $body = json_decode($response->getContent(), true) ?? $response->getContent();
            $ok = $httpCode >= 200 && $httpCode < 300;

            return [
                'key' => 'reservation_webhook',
                'name' => 'Reservation Webhook (inbound)',
                'method' => 'POST',
                'url' => $url,
                'status' => $ok ? 'pass' : 'fail',
                'http_code' => $httpCode,
                'message' => $this->messageFromResponse($body, $ok),
                'response' => $this->preview($body),
            ];
        } catch (\Throwable $e) {
            return [
                'key' => 'reservation_webhook',
                'name' => 'Reservation Webhook (inbound)',
                'method' => 'POST',
                'url' => $url,
                'status' => 'fail',
                'http_code' => null,
                'message' => $e->getMessage(),
                'response' => null,
            ];
        }
    }

    /** @param  array<string, mixed>|null  $payload */
    private function testCase(
        string $key,
        string $name,
        string $method,
        string $url,
        ?array $payload,
        array $credentials,
        array $acceptHttpCodes = [200],
        array $softFailCodes = [],
    ): array {
        $lastError = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $request = Http::connectTimeout(15)
                    ->timeout(45)
                    ->withBasicAuth($credentials['username'], $credentials['password'])
                    ->acceptJson();

                $response = $method === 'GET'
                    ? $request->get($url)
                    : $request->asJson()->post($url, $payload ?? []);

                $httpCode = $response->status();
                $body = $response->json() ?? $response->body();
                $ok = in_array($httpCode, $acceptHttpCodes, true) || $response->successful();
                $soft = in_array($httpCode, $softFailCodes, true);

                return [
                    'key' => $key,
                    'name' => $name,
                    'method' => $method,
                    'url' => $url,
                    'status' => $ok ? 'pass' : ($soft ? 'skip' : 'fail'),
                    'http_code' => $httpCode,
                    'message' => $soft
                        ? $this->messageFromResponse($body, false).' (expected without a real booking ID)'
                        : $this->messageFromResponse($body, $ok),
                    'response' => $this->preview($body),
                ];
            } catch (\Throwable $e) {
                $lastError = $e;

                if ($attempt < 2 && $this->isRetryable($e)) {
                    usleep(750000);

                    continue;
                }

                break;
            }
        }

        return [
            'key' => $key,
            'name' => $name,
            'method' => $method,
            'url' => $url,
            'status' => 'fail',
            'http_code' => null,
            'message' => $this->friendlyErrorMessage($lastError?->getMessage() ?? 'Request failed'),
            'response' => null,
        ];
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
            return 'Aiosell API did not respond in time (network/SSL timeout). Retry in a moment — your app is fine.';
        }

        return $message;
    }

    private function hotelCode(): string
    {
        $cm = array_merge(
            $this->integrations->defaultChannelManager(),
            $this->integrations->ensureIntegrations()['channel_manager'] ?? []
        );

        if ($cm['use_sandbox'] ?? true) {
            return config('channel_manager_integration.sandbox.hotel_code', 'sandbox-pms');
        }

        return config('channel_manager_integration.sandbox.hotel_code', 'sandbox-pms');
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

    private function preview(mixed $body): ?string
    {
        if ($body === null) {
            return null;
        }

        $text = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_SLASHES);

        return Str::limit($text ?: '', 300);
    }
}
