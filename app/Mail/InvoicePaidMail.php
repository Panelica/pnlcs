<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoicePaidMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public ?string $transactionId = null
    ) {}

    public function envelope(): Envelope
    {
        $num = $this->invoice->invoice_num ?? $this->invoice->id;

        return new Envelope(subject: "Payment Received - Invoice #{$num}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice-paid',
            with: [
                'invoice' => $this->invoice,
                'transactionId' => $this->transactionId,
                'companyName' => Setting::get('CompanyName', 'PNLCS'),
            ],
        );
    }
}
