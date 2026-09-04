<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Mail\Concerns\LocalizesToRecipient;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class InvoiceOverdueMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    use LocalizesToRecipient;

    public function __construct(
        public Invoice $invoice,
        public int $daysOverdue
    ) {
        $this->localizeTo($this->invoice);
    }

    public function envelope(): Envelope
    {
        $num = $this->invoice->invoice_num ?? $this->invoice->id;

        return new Envelope(subject: "Overdue: Invoice #{$num} is {$this->daysOverdue} days past due");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice-overdue',
            with: [
                'invoice' => $this->invoice,
                'daysOverdue' => $this->daysOverdue,
                'companyName' => company_name(),
            ],
        );
    }
}
