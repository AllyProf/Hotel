<?php

namespace App\Models;

use App\Services\OtaLogoService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelCmMessage extends Model
{
    protected $fillable = [
        'hotel_id',
        'external_id',
        'channel',
        'booking_id',
        'guest_name',
        'subject',
        'body',
        'direction',
        'sent_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
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

    public function preview(): string
    {
        $text = trim(strip_tags((string) ($this->body ?? '')));

        if ($text === '') {
            return '—';
        }

        return strlen($text) > 120 ? substr($text, 0, 117).'...' : $text;
    }

    public function sentAtLabel(): string
    {
        return $this->sent_at?->format('d M Y H:i') ?? '—';
    }
}
