<?php

namespace App\Mail;

use App\Mail\Concerns\LocalizesToRecipient;
use App\Models\Invoice;
use App\Services\InvoicePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The final e-invoice, sent once KSeF has issued a number for it.
 *
 * The invoice is emailed only after KSeF acceptance so the attached PDF is
 * the definitive document - with the KSeF number and verification QR printed
 * on it - rather than a pre-submission draft.
 */
class KsefInvoiceIssuedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    use LocalizesToRecipient;

    public function __construct(
        public Invoice $invoice,
        public string $ksefNumber,
    ) {
        $this->localizeTo($this->invoice);
    }

    public function envelope(): Envelope
    {
        $num = $this->invoice->invoice_num ?? $this->invoice->id;

        return new Envelope(subject: __('email.ksef_issued.subject', ['number' => $num]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ksef-invoice-issued',
            with: [
                'invoice' => $this->invoice,
                'ksefNumber' => $this->ksefNumber,
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
