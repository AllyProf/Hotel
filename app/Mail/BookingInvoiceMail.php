<?php

namespace App\Mail;

use App\Models\CmReservation;
use App\Models\Hotel;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Hotel $hotel,
        public CmReservation $reservation,
    ) {}

    public function envelope(): Envelope
    {
        $hotelName = $this->hotel->display_name ?: $this->hotel->name;

        return new Envelope(
            subject: 'Invoice · '.$this->reservation->booking_id.' · '.$hotelName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-invoice',
        );
    }
}
