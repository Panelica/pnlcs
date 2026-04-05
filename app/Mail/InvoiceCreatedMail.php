<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice
    ) {}

    public function envelope(): Envelope
    {
        $num = $this->invoice->invoice_num ?? $this->invoice->id;

        return new Envelope(subject: "Invoice #{$num} Created");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice-created',
            with: [
                'invoice' => $this->invoice,
                'companyName' => Setting::get('CompanyName', 'PNLCS'),
            ],
        );
    }
}
