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

class PaymentReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    use LocalizesToRecipient;

    public function __construct(
        public Invoice $invoice,
        public int $daysOffset // positive = days until due, negative = days overdue
    ) {
        $this->localizeTo($this->invoice);
    }

    public function envelope(): Envelope
    {
        $num = $this->invoice->invoice_num ?? $this->invoice->id;

        // Two cases, as before: an invoice that is already past due says so.
        // The day count the old subject carried is left to the body - the
        // shipped keys have no place for it in five languages.
        $key = $this->daysOffset > 0 ? 'email.invoice.reminder_subject' : 'email.invoice.overdue_subject';

        return new Envelope(subject: __($key, ['number' => $num]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-reminder',
            with: [
                'invoice' => $this->invoice,
                'daysOffset' => $this->daysOffset,
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
