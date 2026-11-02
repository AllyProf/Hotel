<?php

namespace App\Mail;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelLostFoundItem;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LostFoundGuestNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Hotel $hotel,
        public HotelLostFoundItem $item,
        public CmReservation $reservation,
        public string $frontDeskEmail,
        public ?string $frontDeskPhone,
    ) {}

    public function envelope(): Envelope
    {
        $hotelName = $this->hotel->display_name ?: $this->hotel->name;

        return new Envelope(
            subject: 'Possible found property · '.$hotelName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lost-found-guest-notification',
        );
    }
}
