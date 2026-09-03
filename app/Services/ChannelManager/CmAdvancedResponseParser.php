<?php

namespace App\Services\ChannelManager;

class CmAdvancedResponseParser
{
    /** @return list<array<string, mixed>> */
    public static function items(mixed $response, string ...$keys): array
    {
        if (! is_array($response)) {
            return [];
        }

        foreach ($keys as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return self::normalizeList($response[$key]);
            }
        }

        if (array_is_list($response)) {
            return self::normalizeList($response);
        }

        if (self::looksLikeRecord($response)) {
            return [$response];
        }

        return [];
    }

    /** @param  array<int|string, mixed>  $list */
    private static function normalizeList(array $list): array
    {
        $items = [];

        foreach ($list as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if (self::looksLikeRecord($entry)) {
                $items[] = $entry;

                continue;
            }

            foreach (['message', 'review', 'data', 'details'] as $nested) {
                if (isset($entry[$nested]) && is_array($entry[$nested])) {
                    $items[] = $entry[$nested];
                }
            }
        }

        return $items;
    }

    /** @param  array<string, mixed>  $row */
    private static function looksLikeRecord(array $row): bool
    {
        $keys = [
            'id', 'messageId', 'message_id', 'reviewId', 'review_id',
            'bookingId', 'booking_id', 'channel', 'body', 'message',
            'review', 'rating', 'guestName', 'guest_name',
        ];

        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return true;
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $row */
    public static function externalId(array $row): ?string
    {
        foreach (['id', 'messageId', 'message_id', 'reviewId', 'review_id', 'externalId', 'external_id'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $row */
    public static function channel(array $row): ?string
    {
        foreach (['channel', 'ota', 'source', 'platform'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $row */
    public static function bookingId(array $row): ?string
    {
        foreach (['bookingId', 'booking_id', 'reservationId', 'reservation_id'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $row */
    public static function guestName(array $row): ?string
    {
        if (isset($row['guest']) && is_array($row['guest'])) {
            $guest = trim(((string) ($row['guest']['firstName'] ?? '')).' '.((string) ($row['guest']['lastName'] ?? '')));

            if ($guest !== '') {
                return $guest;
            }
        }

        foreach (['guestName', 'guest_name', 'customerName', 'customer_name'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $row */
    public static function body(array $row): ?string
    {
        foreach (['body', 'message', 'text', 'content', 'review', 'comment', 'description'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $row */
    public static function subject(array $row): ?string
    {
        foreach (['subject', 'title', 'topic'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $row */
    public static function rating(array $row): ?float
    {
        foreach (['rating', 'score', 'stars'] as $key) {
            if (isset($row[$key]) && is_numeric($row[$key])) {
                return (float) $row[$key];
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $row */
    public static function sentAt(array $row): ?string
    {
        foreach (['sentAt', 'sent_at', 'createdAt', 'created_at', 'timestamp', 'date', 'reviewDate', 'review_date'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $row */
    public static function direction(array $row): string
    {
        $value = strtolower(trim((string) ($row['direction'] ?? $row['type'] ?? $row['flow'] ?? '')));

        if (in_array($value, ['out', 'outbound', 'sent', 'reply'], true)) {
            return 'outbound';
        }

        return 'inbound';
    }
}
