<?php

namespace App\Services\ChannelManager;

class AiosellPayloadBuilder
{
    /** @return array<string, bool|int|null> */
    public static function emptyRestrictions(array $overrides = []): array
    {
        return array_merge([
            'stopSell' => false,
            'exactStayArrival' => null,
            'maximumStayArrival' => null,
            'minimumAdvanceReservation' => null,
            'minimumStay' => 1,
            'closeOnArrival' => false,
            'minimumStayArrival' => null,
            'maximumStay' => null,
            'maximumAdvanceReservation' => null,
            'closeOnDeparture' => false,
        ], $overrides);
    }

    public static function isSuccessfulResponse(mixed $body, bool $httpOk): bool
    {
        if (! $httpOk) {
            return false;
        }

        if (! is_array($body)) {
            return true;
        }

        if (array_key_exists('success', $body)) {
            return (bool) $body['success'];
        }

        if (array_key_exists('status', $body)) {
            return (bool) $body['status'];
        }

        return true;
    }

    public static function responseMessage(mixed $body, bool $ok): string
    {
        if (is_array($body)) {
            if (isset($body['message'])) {
                return (string) $body['message'];
            }

            if (isset($body['success'])) {
                return $body['success'] ? 'Success' : 'Request failed';
            }

            if (isset($body['status'])) {
                return $body['status'] ? 'Success' : 'Request failed';
            }
        }

        return $ok ? 'OK' : 'Request failed';
    }

    /** @param  array<string, mixed>  $row  Internal snake_case restriction from bulk update */
    /** @return array<string, bool|int|null> */
    public static function fromInternalRestriction(array $row): array
    {
        return self::emptyRestrictions([
            'stopSell' => ! empty($row['stop_sell']),
            'closeOnArrival' => ! empty($row['close_on_arrival']),
            'closeOnDeparture' => ! empty($row['close_on_departure']),
            'minimumStay' => isset($row['min_stay']) && $row['min_stay'] !== '' ? (int) $row['min_stay'] : null,
            'minimumStayArrival' => isset($row['min_stay_arrival']) && $row['min_stay_arrival'] !== '' ? (int) $row['min_stay_arrival'] : null,
            'maximumStay' => isset($row['max_stay']) && $row['max_stay'] !== '' ? (int) $row['max_stay'] : null,
            'maximumStayArrival' => isset($row['max_stay_arrival']) && $row['max_stay_arrival'] !== '' ? (int) $row['max_stay_arrival'] : null,
            'exactStayArrival' => isset($row['exact_stay_arrival']) && $row['exact_stay_arrival'] !== '' ? (int) $row['exact_stay_arrival'] : null,
            'minimumAdvanceReservation' => isset($row['min_advance']) && $row['min_advance'] !== '' ? (int) $row['min_advance'] : null,
            'maximumAdvanceReservation' => isset($row['max_advance']) && $row['max_advance'] !== '' ? (int) $row['max_advance'] : null,
        ]);
    }

    /** @param  list<string>  $slugs  Internal OTA slugs or "all" */
    /** @return list<string> */
    public static function toAiosellChannels(array $slugs): array
    {
        if ($slugs === [] || in_array('all', $slugs, true)) {
            return collect(config('otas', []))
                ->pluck('slug')
                ->map(fn (string $slug) => self::toAiosellChannel($slug))
                ->unique()
                ->values()
                ->all();
        }

        return collect($slugs)
            ->map(fn (string $slug) => self::toAiosellChannel($slug))
            ->unique()
            ->values()
            ->all();
    }

    public static function toAiosellChannel(string $slug): string
    {
        return match ($slug) {
            'booking-com' => 'booking.com',
            'goibibo' => 'Goibibo',
            'makemytrip' => 'MakeMyTrip',
            'trip-com' => 'ctrip',
            'ease-my-trip' => 'easemytrip',
            'tiket-com' => 'tiket',
            'bookings-maker' => 'bookingsmaker',
            'vhs-hub' => 'vhshub',
            default => $slug,
        };
    }
}
