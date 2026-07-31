<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class PaymentReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public int $daysOffset // positive = days until due, negative = days overdue
    ) {}

    public function envelope(): Envelope
    {
        $num = $this->invoice->invoice_num ?? $this->invoice->id;

        if ($this->daysOffset > 0) {
            $subject = "Payment Reminder: Invoice #{$num} due in {$this->daysOffset} day(s)";
        } else {
            $days = abs($this->daysOffset);
            $subject = "Overdue Notice: Invoice #{$num} is {$days} day(s) past due";
        }

        return new Envelope(subject: $subject);
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
}
