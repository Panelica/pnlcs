<?php

namespace App\Mail;

use App\Models\PaymentNotification;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentNotificationRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public PaymentNotification $notification) {}

    public function envelope(): Envelope
    {
        $num = $this->notification->invoice?->invoice_num ?? $this->notification->invoice_id;

        return new Envelope(subject: "Payment notification for Invoice #{$num} could not be verified");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-notification-rejected',
            with: [
                'notification' => $this->notification,
                'invoice'      => $this->notification->invoice,
                'companyName'  => Setting::get('CompanyName', 'PNLCS'),
            ],
        );
    }
}
