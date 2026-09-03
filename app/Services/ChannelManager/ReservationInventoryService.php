<?php

namespace App\Services\ChannelManager;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\HotelRoomInventory;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class ReservationInventoryService
{
    public function __construct(
        private ChannelManagerCodeResolver $codes,
        private ChannelManagerPushService $cmPush,
    ) {}

    public function syncAfterWebhook(Hotel $hotel, string $action, ?CmReservation $before, CmReservation $after, bool $adjustInventory = true): void
    {
        if (! $adjustInventory) {
            return;
        }

        if ($action === 'book' && $before && $before->status === CmReservation::STATUS_CONFIRMED && ! $before->isCancelled()) {
            return;
        }

        if ($action === 'cancel') {
            $this->restoreReservation($hotel, $before ?? $after);
        } elseif ($action === 'modify') {
            if ($before && ! $before->isCancelled()) {
                $this->restoreReservation($hotel, $before);
            }

            if (! $after->isCancelled()) {
                $this->consumeReservation($hotel, $after);
            }
        } else {
            if (! $after->isCancelled()) {
                $this->consumeReservation($hotel, $after);
            }
        }

        $this->pushInventoryForReservations($hotel, $before, $after);
    }

    public function syncAfterManualBooking(Hotel $hotel, CmReservation $reservation): void
    {
        if ($reservation->isCancelled()) {
            return;
        }

        $this->consumeReservation($hotel, $reservation);
        $this->pushInventoryForReservations($hotel, null, $reservation);
    }

    private function consumeReservation(Hotel $hotel, CmReservation $reservation): void
    {
        foreach ($this->nightlyAllocations($hotel, $reservation) as $allocation) {
            $this->adjustAvailableCount(
                $hotel,
                $allocation['room_id'],
                $allocation['date'],
                -$allocation['rooms']
            );
        }
    }

    private function restoreReservation(Hotel $hotel, CmReservation $reservation): void
    {
        foreach ($this->nightlyAllocations($hotel, $reservation) as $allocation) {
            $this->adjustAvailableCount(
                $hotel,
                $allocation['room_id'],
                $allocation['date'],
                $allocation['rooms']
            );
        }
    }

    /** @return list<array{room_id: int, date: string, rooms: int}> */
    private function nightlyAllocations(Hotel $hotel, CmReservation $reservation): array
    {
        if ($reservation->checkin === null || $reservation->checkout === null) {
            return [];
        }

        $allocations = [];
        $checkin = Carbon::parse($reservation->checkin)->startOfDay();
        $checkout = Carbon::parse($reservation->checkout)->startOfDay();

        if ($checkout->lte($checkin)) {
            return [];
        }

        foreach ($reservation->roomLines() as $line) {
            $roomCode = trim((string) ($line['roomCode'] ?? $line['room_code'] ?? ''));
            $room = $this->codes->resolveRoomByCode($hotel, $roomCode);

            if ($room === null) {
                continue;
            }

            $qty = max(1, (int) ($line['numberOfRooms'] ?? $line['roomCount'] ?? $line['quantity'] ?? 1));

            foreach (CarbonPeriod::create($checkin, '1 day', $checkout->copy()->subDay()) as $day) {
                $allocations[] = [
                    'room_id' => $room->id,
                    'date' => Carbon::parse($day)->format('Y-m-d'),
                    'rooms' => $qty,
                ];
            }
        }

        if ($allocations === [] && $reservation->roomLines() === []) {
            $room = $hotel->rooms()->where('is_enabled', true)->orderBy('rank')->first();
            if ($room === null) {
                return [];
            }

            $qty = max(1, $reservation->roomCount());

            foreach (CarbonPeriod::create($checkin, '1 day', $checkout->copy()->subDay()) as $day) {
                $allocations[] = [
                    'room_id' => $room->id,
                    'date' => Carbon::parse($day)->format('Y-m-d'),
                    'rooms' => $qty,
                ];
            }
        }

        return $allocations;
    }

    private function adjustAvailableCount(Hotel $hotel, int $roomId, string $dateKey, int $delta): void
    {
        $room = HotelRoom::query()
            ->where('hotel_id', $hotel->id)
            ->where('is_enabled', true)
            ->find($roomId);

        if ($room === null) {
            return;
        }

        $row = HotelRoomInventory::query()->firstOrCreate(
            ['hotel_room_id' => $roomId, 'date' => $dateKey],
            [
                'hotel_id' => $hotel->id,
                'available_count' => (int) $room->room_count,
            ]
        );

        $next = max(0, min(999, (int) $row->available_count + $delta));
        $row->update(['available_count' => $next]);
    }

    private function pushInventoryForReservations(Hotel $hotel, ?CmReservation $before, CmReservation $after): void
    {
        if (! $this->cmPush->canPush()) {
            return;
        }

        $start = collect([
            $before?->checkin?->format('Y-m-d'),
            $after->checkin?->format('Y-m-d'),
        ])->filter()->sort()->first();

        if ($start === null) {
            return;
        }

        $this->cmPush->pushAfterInventorySave($hotel, Carbon::parse($start));
    }
}
