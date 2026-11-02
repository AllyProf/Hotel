<?php

namespace App\Services;

use App\Mail\LostFoundGuestNotificationMail;
use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelLostFoundGuestNotification;
use App\Models\HotelLostFoundItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class LostFoundNotificationService
{
    public function __construct(
        private HotelLogService $logs,
    ) {}

    /** @return array<int, array<int, array{sent_at: string, email: string}>> */
    public function latestByItems(Collection $items): array
    {
        $itemIds = $items->pluck('id')->filter()->all();
        if ($itemIds === []) {
            return [];
        }

        $rows = HotelLostFoundGuestNotification::query()
            ->whereIn('hotel_lost_found_item_id', $itemIds)
            ->orderByDesc('created_at')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $itemId = (int) $row->hotel_lost_found_item_id;
            $resId = (int) $row->cm_reservation_id;
            if ($resId <= 0 || isset($map[$itemId][$resId])) {
                continue;
            }
            $map[$itemId][$resId] = [
                'sent_at' => $row->created_at?->format('d/m/Y H:i') ?? '',
                'email' => $row->email_to,
            ];
        }

        return $map;
    }

    public function sendGuestEmail(Hotel $hotel, HotelLostFoundItem $item, CmReservation $reservation): void
    {
        if ((int) $item->hotel_id !== (int) $hotel->id) {
            throw new InvalidArgumentException('Item not found.');
        }

        if ((int) $reservation->hotel_id !== (int) $hotel->id) {
            throw new InvalidArgumentException('Reservation not found.');
        }

        if (! $item->isOpen()) {
            throw new InvalidArgumentException('This item is no longer in storage.');
        }

        $unitId = (int) $item->hotel_room_unit_id;
        if ($unitId <= 0 || ! in_array($unitId, $reservation->assignedRoomUnitIds(), true)) {
            throw new InvalidArgumentException('This guest stay does not match the room on the found item.');
        }

        $email = $reservation->guestEmail();
        if ($email === null || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('No valid email on file for this guest.');
        }

        $frontDeskEmail = trim((string) ($hotel->email ?? ''));
        if ($frontDeskEmail === '' || ! filter_var($frontDeskEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Set the hotel email under Settings so guests can reply.');
        }

        $phone = trim((string) ($hotel->phone ?? ''));
        if ($hotel->phone_country_code && $phone !== '' && ! str_starts_with($phone, '+')) {
            $phone = trim($hotel->phone_country_code.' '.$phone);
        }

        Mail::to($email)->send(new LostFoundGuestNotificationMail(
            $hotel,
            $item,
            $reservation,
            $frontDeskEmail,
            $phone !== '' ? $phone : null,
        ));

        HotelLostFoundGuestNotification::query()->create([
            'hotel_id' => $hotel->id,
            'hotel_lost_found_item_id' => $item->id,
            'cm_reservation_id' => $reservation->id,
            'email_to' => $email,
            'guest_name' => $reservation->guestName(),
            'sent_by' => auth()->id(),
        ]);

        $this->logs->record($hotel, [
            'action_type' => 'Lost & Found email',
            'room_no' => $item->room_no,
            'details' => $item->reference_no.' → '.$email,
        ]);
    }

    public function loggedTodayCount(Hotel $hotel): int
    {
        return HotelLostFoundItem::query()
            ->where('hotel_id', $hotel->id)
            ->whereDate('logged_at', now()->toDateString())
            ->count();
    }

    /** @param  Collection<int, CmReservation>  $reservations
     * @return array<int, Collection<int, HotelLostFoundItem>>
     */
    public function openItemsByReservation(Hotel $hotel, Collection $reservations): array
    {
        $reservationUnits = [];
        $unitIds = [];

        foreach ($reservations as $reservation) {
            $ids = $reservation->assignedRoomUnitIds();
            if ($ids !== []) {
                $reservationUnits[$reservation->id] = $ids;
                $unitIds = array_merge($unitIds, $ids);
            }
        }

        $unitIds = array_values(array_unique($unitIds));
        if ($unitIds === []) {
            return [];
        }

        $items = HotelLostFoundItem::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', HotelLostFoundItem::STATUS_STORED)
            ->whereIn('hotel_room_unit_id', $unitIds)
            ->orderByDesc('logged_at')
            ->get();

        $grouped = [];
        foreach ($reservations as $reservation) {
            $roomIds = $reservationUnits[$reservation->id] ?? [];
            if ($roomIds === []) {
                continue;
            }

            $matched = $items->filter(
                fn (HotelLostFoundItem $item) => in_array((int) $item->hotel_room_unit_id, $roomIds, true)
            )->values();

            if ($matched->isNotEmpty()) {
                $grouped[$reservation->id] = $matched;
            }
        }

        return $grouped;
    }
}
