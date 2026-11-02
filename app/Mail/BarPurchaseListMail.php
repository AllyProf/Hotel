<?php

namespace App\Mail;

use App\Models\HotelBarPurchaseList;
use App\Services\Bar\BarPurchaseListService;
use App\Support\DepartmentProcurement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BarPurchaseListMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $viewUrl;

    public function __construct(
        public HotelBarPurchaseList $purchaseList,
    ) {
        $this->viewUrl = app(BarPurchaseListService::class)->sharedViewUrl($purchaseList);
    }

    public function envelope(): Envelope
    {
        $hotelName = $this->purchaseList->hotel->display_name ?: $this->purchaseList->hotel->name;

        return new Envelope(
            subject: DepartmentProcurement::label($this->purchaseList->workflowKey()).' purchase list · '.$this->purchaseList->reference_no.' · '.$hotelName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bar-purchase-list',
        );
    }
}
