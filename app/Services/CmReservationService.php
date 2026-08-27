<?php

namespace App\Services;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelSetting;
use Illuminate\Support\Str;

class CmReservationService
{
    public function __construct(
        private HotelIntegrationService $hotelIntegrations,
        private PlatformIntegrationService $platformIntegrations,
    ) {}

    /** @param array<string, mixed> $payload */
    public function storeFromWebhook(array $payload): CmReservation
    {
        $hotelCode = (string) ($payload['hotelCode'] ?? '');
        $bookingId = (string) ($payload['bookingId'] ?? '');
        $action = (string) ($payload['action'] ?? 'book');
        $amount = is_array($payload['amount'] ?? null) ? $payload['amount'] : [];
        $guest = is_array($payload['guest'] ?? null) ? $payload['guest'] : [];

        $status = match ($action) {
            'cancel' => CmReservation::STATUS_CANCELLED,
            'modify' => CmReservation::STATUS_MODIFIED,
            default => CmReservation::STATUS_CONFIRMED,
        };

        return CmReservation::query()->updateOrCreate(
            [
                'hotel_code' => $hotelCode,
                'booking_id' => $bookingId,
            ],
            [
                'hotel_id' => $this->resolveHotelId($hotelCode),
                'channel' => (string) ($payload['channel'] ?? ''),
                'action' => $action,
                'status' => $status,
                'checkin' => $payload['checkin'] ?? null,
                'checkout' => $payload['checkout'] ?? null,
                'guest_first_name' => $guest['firstName'] ?? null,
                'guest_last_name' => $guest['lastName'] ?? null,
                'amount_after_tax' => $amount['amountAfterTax'] ?? null,
                'amount_before_tax' => $amount['amountBeforeTax'] ?? null,
                'tax' => $amount['tax'] ?? null,
                'currency' => $amount['currency'] ?? null,
                'rooms' => $payload['rooms'] ?? null,
                'payload' => $payload,
            ]
        );
    }

    /**
     * Pull reservations from Channel Manager and store them locally.
     *
     * @return array{success: bool, message: string, imported: int, total: int}
     */
    public function syncFromChannelManager(string $hotelCode, ?int $hotelId, string $startDate, string $endDate): array
    {
        $client = app(\App\Services\ChannelManager\ChannelManagerClient::class);

        if (! $client->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Channel Manager is not configured.',
                'imported' => 0,
                'total' => 0,
            ];
        }

        $result = $client->fetchReservations($hotelCode, $startDate, $endDate);

        if (! $result['success']) {
            return [
                'success' => false,
                'message' => $result['message'],
                'imported' => 0,
                'total' => 0,
            ];
        }

        $items = $this->extractReservationItems($result['response']);
        $imported = 0;

        foreach ($items as $item) {
            $payload = $this->normalizeReservationPayload($item, $hotelCode);

            if ($payload === null) {
                continue;
            }

            if ($hotelId !== null) {
                $payload['hotelCode'] = $hotelCode;
            }

            $this->storeFromWebhook($payload);
            $imported++;
        }

        return [
            'success' => true,
            'message' => $imported > 0
                ? "Imported {$imported} booking(s) from Channel Manager."
                : 'Channel Manager returned no bookings for this date range.',
            'imported' => $imported,
            'total' => count($items),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function extractReservationItems(mixed $response): array
    {
        if (! is_array($response)) {
            return [];
        }

        foreach (['data', 'reservations', 'bookings', 'items'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return $this->flattenReservationList($response[$key]);
            }
        }

        if ($this->looksLikeReservation($response)) {
            return [$response];
        }

        if (array_is_list($response)) {
            return $this->flattenReservationList($response);
        }

        return [];
    }

    /** @param  array<int|string, mixed>  $list */
    private function flattenReservationList(array $list): array
    {
        $items = [];

        foreach ($list as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if ($this->looksLikeReservation($entry)) {
                $items[] = $entry;

                continue;
            }

            foreach (['reservation', 'booking', 'details'] as $nested) {
                if (isset($entry[$nested]) && is_array($entry[$nested]) && $this->looksLikeReservation($entry[$nested])) {
                    $items[] = array_merge($entry, $entry[$nested]);
                }
            }
        }

        return $items;
    }

    /** @param  array<string, mixed>  $item */
    private function looksLikeReservation(array $item): bool
    {
        return isset($item['bookingId'])
            || isset($item['booking_id'])
            || isset($item['bookingID']);
    }

    /** @param  array<string, mixed>  $item */
    private function normalizeReservationPayload(array $item, string $hotelCode): ?array
    {
        $bookingId = (string) ($item['bookingId'] ?? $item['booking_id'] ?? $item['bookingID'] ?? '');

        if ($bookingId === '') {
            return null;
        }

        $action = strtolower((string) ($item['action'] ?? ''));
        if ($action === '') {
            $status = strtolower((string) ($item['status'] ?? 'confirmed'));
            $action = match (true) {
                str_contains($status, 'cancel') => 'cancel',
                str_contains($status, 'modify') => 'modify',
                default => 'book',
            };
        }

        $guest = is_array($item['guest'] ?? null) ? $item['guest'] : [];
        $amount = is_array($item['amount'] ?? null) ? $item['amount'] : [];

        if ($guest === [] && is_array($item['guestDetails'] ?? null)) {
            $guest = $item['guestDetails'];
        }

        if ($amount === [] && is_array($item['priceDetails'] ?? null)) {
            $amount = $item['priceDetails'];
        }

        return [
            'action' => in_array($action, ['book', 'modify', 'cancel'], true) ? $action : 'book',
            'hotelCode' => (string) ($item['hotelCode'] ?? $item['hotel_code'] ?? $hotelCode),
            'channel' => (string) ($item['channel'] ?? $item['ota'] ?? $item['source'] ?? 'OTA'),
            'bookingId' => $bookingId,
            'checkin' => $item['checkin'] ?? $item['checkIn'] ?? $item['check_in'] ?? null,
            'checkout' => $item['checkout'] ?? $item['checkOut'] ?? $item['check_out'] ?? null,
            'guest' => [
                'firstName' => $guest['firstName'] ?? $guest['first_name'] ?? $item['guest_first_name'] ?? null,
                'lastName' => $guest['lastName'] ?? $guest['last_name'] ?? $item['guest_last_name'] ?? null,
            ],
            'amount' => [
                'amountAfterTax' => $amount['amountAfterTax'] ?? $amount['amount_after_tax'] ?? $item['amount_after_tax'] ?? null,
                'amountBeforeTax' => $amount['amountBeforeTax'] ?? $amount['amount_before_tax'] ?? $item['amount_before_tax'] ?? null,
                'tax' => $amount['tax'] ?? $item['tax'] ?? null,
                'currency' => $amount['currency'] ?? $item['currency'] ?? null,
            ],
            'rooms' => $item['rooms'] ?? null,
        ];
    }

    public function resolveHotelId(string $hotelCode): ?int
    {
        if ($hotelCode === '') {
            return null;
        }

        if ($this->platformIntegrations->isChannelManagerSandbox()
            && $hotelCode === config('channel_manager_integration.sandbox.hotel_code')) {
            return Hotel::query()->orderBy('id')->value('id');
        }

        $settings = HotelSetting::query()->with('hotel')->get();

        foreach ($settings as $row) {
            $hotel = $row->hotel;
            if (! $hotel) {
                continue;
            }

            $cm = array_merge(
                $this->hotelIntegrations->defaultChannelManager($hotel),
                $row->integrations['channel_manager'] ?? []
            );

            $code = trim((string) ($cm['hotel_code'] ?? ''));
            if ($code !== '' && $code === $hotelCode) {
                return $hotel->id;
            }

            if (Str::slug($hotel->name ?: 'hotel-'.$hotel->id) === $hotelCode) {
                return $hotel->id;
            }
        }

        return null;
    }
}
