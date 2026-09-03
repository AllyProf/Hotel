<?php

namespace App\Services\ChannelManager;

use App\Models\Hotel;
use App\Models\HotelCmMessage;
use Carbon\Carbon;

class CmMessagesService
{
    public function __construct(
        private ChannelManagerClient $client,
        private ChannelManagerCodeResolver $codes,
    ) {}

    /** @param  list<string>  $channels @return array{success: bool, message: string, imported: int, total: int} */
    public function sync(Hotel $hotel, string $startDate, string $endDate, array $channels = []): array
    {
        if (! $this->client->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Channel Manager is not configured.',
                'imported' => 0,
                'total' => 0,
            ];
        }

        $hotelCode = $this->codes->hotelCode($hotel);
        $result = $this->client->fetchMessages($hotelCode, $startDate, $endDate, $channels);

        if (! $result['success']) {
            return [
                'success' => false,
                'message' => $this->friendlySyncMessage($result['message']),
                'imported' => 0,
                'total' => 0,
            ];
        }

        $items = CmAdvancedResponseParser::items($result['response'], 'messages', 'data', 'items');
        $imported = 0;

        foreach ($items as $item) {
            if ($this->storeItem($hotel, $item)) {
                $imported++;
            }
        }

        return [
            'success' => true,
            'message' => $imported > 0
                ? "Synced {$imported} message(s) from Channel Manager."
                : 'Channel Manager returned no messages for this range.',
            'imported' => $imported,
            'total' => count($items),
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function list(Hotel $hotel, array $filters)
    {
        $query = HotelCmMessage::query()->where('hotel_id', $hotel->id);

        if (! empty($filters['start_date'])) {
            $query->where('sent_at', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }

        if (! empty($filters['end_date'])) {
            $query->where('sent_at', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        if (! empty($filters['channels'])) {
            $query->whereIn('channel', $filters['channels']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('guest_name', 'like', $term)
                    ->orWhere('booking_id', 'like', $term)
                    ->orWhere('body', 'like', $term)
                    ->orWhere('subject', 'like', $term);
            });
        }

        return $query->orderByDesc('sent_at')->orderByDesc('id')->paginate(20)->withQueryString();
    }

    /** @param  array<string, mixed>  $row */
    private function storeItem(Hotel $hotel, array $row): bool
    {
        $externalId = CmAdvancedResponseParser::externalId($row);
        $sentAt = CmAdvancedResponseParser::sentAt($row);

        if ($externalId === null) {
            $externalId = hash('xxh128', json_encode($row));
        }

        HotelCmMessage::query()->updateOrCreate(
            [
                'hotel_id' => $hotel->id,
                'external_id' => $externalId,
            ],
            [
                'channel' => CmAdvancedResponseParser::channel($row),
                'booking_id' => CmAdvancedResponseParser::bookingId($row),
                'guest_name' => CmAdvancedResponseParser::guestName($row),
                'subject' => CmAdvancedResponseParser::subject($row),
                'body' => CmAdvancedResponseParser::body($row),
                'direction' => CmAdvancedResponseParser::direction($row),
                'sent_at' => $sentAt ? Carbon::parse($sentAt) : now(),
                'payload' => $row,
            ]
        );

        return true;
    }

    private function friendlySyncMessage(string $message): string
    {
        $lower = strtolower($message);

        if (str_contains($lower, 'channel not live')) {
            return 'Channel Manager message sync requires live OTA channels. Connect OTAs in Mapping Setup, then try again.';
        }

        return $message !== '' ? $message : 'Could not sync messages from Channel Manager.';
    }
}
