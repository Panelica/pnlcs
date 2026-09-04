<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Mail\Concerns\LocalizesToRecipient;
use App\Services\InvoicePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class InvoiceCreatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    use LocalizesToRecipient;

    public function __construct(
        public Invoice $invoice
    ) {
        $this->localizeTo($this->invoice);
    }

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
                'companyName' => company_name(),
            ],
        );
    }

    public function attachments(): array
    {
        $pdf = app(InvoicePdfService::class)->generate($this->invoice);

        $num = str_replace(['/', '\\'], '-', (string) ($this->invoice->invoice_num ?? $this->invoice->id));

        return [
            Attachment::fromData(fn () => $pdf->output(), "invoice-{$num}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
