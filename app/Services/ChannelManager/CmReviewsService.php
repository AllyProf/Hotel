<?php

namespace App\Services\ChannelManager;

use App\Models\Hotel;
use App\Models\HotelCmReview;
use Carbon\Carbon;

class CmReviewsService
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
        $result = $this->client->fetchReviews($hotelCode, $startDate, $endDate, $channels);

        if (! $result['success']) {
            return [
                'success' => false,
                'message' => $this->friendlySyncMessage($result['message']),
                'imported' => 0,
                'total' => 0,
            ];
        }

        $items = CmAdvancedResponseParser::items($result['response'], 'reviews', 'data', 'items');
        $imported = 0;

        foreach ($items as $item) {
            if ($this->storeItem($hotel, $item)) {
                $imported++;
            }
        }

        return [
            'success' => true,
            'message' => $imported > 0
                ? "Synced {$imported} review(s) from Channel Manager."
                : 'Channel Manager returned no reviews for this range.',
            'imported' => $imported,
            'total' => count($items),
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function list(Hotel $hotel, array $filters)
    {
        $query = HotelCmReview::query()->where('hotel_id', $hotel->id);

        if (! empty($filters['start_date'])) {
            $query->whereDate('review_date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('review_date', '<=', $filters['end_date']);
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
                    ->orWhere('title', 'like', $term);
            });
        }

        return $query->orderByDesc('review_date')->orderByDesc('id')->paginate(20)->withQueryString();
    }

    /** @param  array<string, mixed>  $row */
    private function storeItem(Hotel $hotel, array $row): bool
    {
        $externalId = CmAdvancedResponseParser::externalId($row);
        $reviewDate = CmAdvancedResponseParser::sentAt($row);

        if ($externalId === null) {
            $externalId = hash('xxh128', json_encode($row));
        }

        HotelCmReview::query()->updateOrCreate(
            [
                'hotel_id' => $hotel->id,
                'external_id' => $externalId,
            ],
            [
                'channel' => CmAdvancedResponseParser::channel($row),
                'booking_id' => CmAdvancedResponseParser::bookingId($row),
                'guest_name' => CmAdvancedResponseParser::guestName($row),
                'rating' => CmAdvancedResponseParser::rating($row),
                'title' => CmAdvancedResponseParser::subject($row),
                'body' => CmAdvancedResponseParser::body($row),
                'review_date' => $reviewDate ? Carbon::parse($reviewDate)->toDateString() : now()->toDateString(),
                'response' => trim((string) ($row['response'] ?? $row['reply'] ?? '')) ?: null,
                'responded_at' => isset($row['respondedAt']) ? Carbon::parse((string) $row['respondedAt']) : null,
                'payload' => $row,
            ]
        );

        return true;
    }

    private function friendlySyncMessage(string $message): string
    {
        $lower = strtolower($message);

        if (str_contains($lower, 'channel not live')) {
            return 'Channel Manager review sync requires live OTA channels. Connect OTAs in Mapping Setup, then try again.';
        }

        return $message !== '' ? $message : 'Could not sync reviews from Channel Manager.';
    }
}
