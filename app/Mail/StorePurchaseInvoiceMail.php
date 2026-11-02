<?php

namespace App\Mail;

use App\Models\Hotel;
use App\Models\HotelPurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StorePurchaseInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Hotel $hotel,
        public HotelPurchaseOrder $order,
    ) {}

    public function envelope(): Envelope
    {
        $hotelName = $this->hotel->display_name ?: $this->hotel->name;
        $invoiceNo = $this->order->invoice_number ?: $this->order->po_number;

        return new Envelope(
            subject: 'Invoice '.$invoiceNo.' · '.$this->order->po_number.' · '.$hotelName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.store-purchase-invoice',
        );
    }
}
