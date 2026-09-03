<?php

namespace App\Models;

use App\Services\OtaLogoService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelCmReview extends Model
{
    protected $fillable = [
        'hotel_id',
        'external_id',
        'channel',
        'booking_id',
        'guest_name',
        'rating',
        'title',
        'body',
        'review_date',
        'response',
        'responded_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:1',
            'review_date' => 'date',
            'responded_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function channelLabel(): string
    {
        if (trim((string) $this->channel) === '') {
            return '—';
        }

        return app(OtaLogoService::class)->presentationForChannel($this->channel)['name'];
    }

    public function reviewDateLabel(): string
    {
        return $this->review_date?->format('d M Y') ?? '—';
    }

    public function ratingStars(): string
    {
        if ($this->rating === null) {
            return '—';
        }

        $filled = max(0, min(5, (int) round((float) $this->rating)));

        return str_repeat('★', $filled).str_repeat('☆', 5 - $filled);
    }
}
