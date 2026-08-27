<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelInventoryDay;
use App\Models\HotelRoomInventory;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class RoomInventoryService
{
    public const WINDOW_DAYS = 14;

    public function __construct(private OtaConnectionService $otaConnections) {}

    /** @return list<string> */
    public function otaSlugsFor(Hotel $hotel): array
    {
        return $this->otaConnections->configuredSlugs($hotel);
    }

    /** @return array<string, bool> */
    public function defaultOtaStatus(Hotel $hotel): array
    {
        return array_fill_keys($this->otaSlugsFor($hotel), true);
    }

    /** @param array<string, bool|null|int|string> $status */
    public function normalizeOtaStatus(Hotel $hotel, array $status): array
    {
        $normalized = $this->defaultOtaStatus($hotel);

        foreach ($this->otaSlugsFor($hotel) as $slug) {
            if (array_key_exists($slug, $status)) {
                $normalized[$slug] = filter_var($status[$slug], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $normalized;
    }

    /** @param array<string, bool> $status */
    public function availabilityState(array $status): string
    {
        $values = array_values($status);

        if ($values === []) {
            return 'open';
        }

        $openCount = count(array_filter($values));
        $total = count($values);

        if ($openCount === $total) {
            return 'open';
        }

        if ($openCount === 0) {
            return 'closed';
        }

        return 'partial';
    }

    /** @return Collection<int, Carbon> */
    public function dateRange(Carbon $start): Collection
    {
        return collect(CarbonPeriod::create($start->copy()->startOfDay(), $start->copy()->addDays(self::WINDOW_DAYS - 1)))
            ->map(fn (Carbon $date) => $date->copy()->startOfDay())
            ->values();
    }

    /** @return array<string, mixed> */
    public function grid(Hotel $hotel, Carbon $start): array
    {
        $dates = $this->dateRange($start);
        $dateKeys = $dates->map(fn (Carbon $d) => $d->format('Y-m-d'))->all();

        $rooms = $hotel->rooms()
            ->where('is_enabled', true)
            ->orderBy('rank')
            ->orderBy('id')
            ->get();

        $dayFlags = HotelInventoryDay::query()
            ->where('hotel_id', $hotel->id)
            ->whereBetween('date', [$dateKeys[0], end($dateKeys)])
            ->get()
            ->keyBy(fn (HotelInventoryDay $row) => $row->date->format('Y-m-d'));

        $inventory = HotelRoomInventory::query()
            ->where('hotel_id', $hotel->id)
            ->whereIn('hotel_room_id', $rooms->pluck('id'))
            ->whereBetween('date', [$dateKeys[0], end($dateKeys)])
            ->get()
            ->groupBy('hotel_room_id');

        $roomRows = [];
        $totals = array_fill_keys($dateKeys, 0);

        foreach ($rooms as $room) {
            $counts = [];
            $roomInventory = $inventory->get($room->id, collect())->keyBy(fn (HotelRoomInventory $row) => $row->date->format('Y-m-d'));

            foreach ($dateKeys as $dateKey) {
                $counts[$dateKey] = isset($roomInventory[$dateKey])
                    ? (int) $roomInventory[$dateKey]->available_count
                    : (int) $room->room_count;
                $totals[$dateKey] += $counts[$dateKey];
            }

            $roomRows[] = [
                'id' => $room->id,
                'name' => $room->display_name ?: $room->name,
                'counts' => $counts,
            ];
        }

        $isOpen = [];
        $otaAvailability = [];
        foreach ($dateKeys as $dateKey) {
            if (isset($dayFlags[$dateKey]) && is_array($dayFlags[$dateKey]->ota_status)) {
                $otaAvailability[$dateKey] = $this->normalizeOtaStatus($hotel, $dayFlags[$dateKey]->ota_status);
            } elseif (isset($dayFlags[$dateKey])) {
                $open = (bool) $dayFlags[$dateKey]->is_open;
                $otaAvailability[$dateKey] = array_fill_keys($this->otaSlugsFor($hotel), $open);
            } else {
                $otaAvailability[$dateKey] = $this->defaultOtaStatus($hotel);
            }

            $isOpen[$dateKey] = $this->availabilityState($otaAvailability[$dateKey]);
        }

        $occupancy = array_fill_keys($dateKeys, 0.0);

        return [
            'dates' => $dates,
            'dateKeys' => $dateKeys,
            'rooms' => $roomRows,
            'isOpen' => $isOpen,
            'otaAvailability' => $otaAvailability,
            'totals' => $totals,
            'occupancy' => $occupancy,
            'configuredOtaCount' => count($this->otaSlugsFor($hotel)),
        ];
    }

    /** @param array<int, array<string, int|string>> $roomCounts @param array<string, array<string, bool|int|string>|bool|int|string> $availability */
    public function save(Hotel $hotel, Carbon $start, array $roomCounts, array $availability): void
    {
        $dates = $this->dateRange($start);
        $dateKeys = $dates->map(fn (Carbon $d) => $d->format('Y-m-d'))->all();
        $roomIds = $hotel->rooms()->where('is_enabled', true)->pluck('id')->all();
        $validRoomIds = array_flip($roomIds);

        foreach ($dateKeys as $dateKey) {
            $raw = $availability[$dateKey] ?? null;

            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $raw = is_array($decoded) ? $decoded : null;
            }

            if (is_array($raw)) {
                $otaStatus = $this->normalizeOtaStatus($hotel, $raw);
            } else {
                $open = filter_var($raw ?? true, FILTER_VALIDATE_BOOLEAN);
                $otaStatus = array_fill_keys($this->otaSlugsFor($hotel), $open);
            }

            $isOpen = $this->availabilityState($otaStatus) !== 'closed';

            HotelInventoryDay::query()->updateOrCreate(
                ['hotel_id' => $hotel->id, 'date' => $dateKey],
                [
                    'is_open' => $isOpen,
                    'ota_status' => $otaStatus,
                ]
            );
        }

        foreach ($roomCounts as $roomId => $counts) {
            if (! isset($validRoomIds[(int) $roomId])) {
                continue;
            }

            foreach ($dateKeys as $dateKey) {
                if (! array_key_exists($dateKey, $counts)) {
                    continue;
                }

                $available = max(0, min(999, (int) $counts[$dateKey]));

                HotelRoomInventory::query()->updateOrCreate(
                    [
                        'hotel_room_id' => (int) $roomId,
                        'date' => $dateKey,
                    ],
                    [
                        'hotel_id' => $hotel->id,
                        'available_count' => $available,
                    ]
                );
            }
        }
    }

    public function setDayAvailability(Hotel $hotel, string $dateKey, bool $available): void
    {
        $this->setDayOtaStatus($hotel, $dateKey, array_fill_keys($this->otaSlugsFor($hotel), $available));
    }

    /** @param array<string, bool|int|string|null> $otaStatus */
    public function setDayOtaStatus(Hotel $hotel, string $dateKey, array $otaStatus): void
    {
        $normalized = $this->normalizeOtaStatus($hotel, $otaStatus);
        $state = $this->availabilityState($normalized);

        HotelInventoryDay::query()->updateOrCreate(
            ['hotel_id' => $hotel->id, 'date' => $dateKey],
            [
                'is_open' => $state !== 'closed',
                'ota_status' => $normalized,
            ]
        );
    }

    /** @return list<string> */
    public function stoppedChannelNames(Hotel $hotel, array $otaStatus): array
    {
        $names = [];

        foreach ($this->otaConnections->configured($hotel) as $ota) {
            $slug = $ota['slug'] ?? '';
            if ($slug !== '' && isset($otaStatus[$slug]) && ! $otaStatus[$slug]) {
                $names[] = $ota['name'] ?? $slug;
            }
        }

        return $names;
    }
}
