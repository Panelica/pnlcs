<?php

namespace App\Mail;

use App\Models\SslOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SslCertificateExpiringMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SslOrder $order,
        public int $daysRemaining,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "SSL Certificate Expiring in {$this->daysRemaining} Days - " . ($this->order->domain ?: 'Order #' . $this->order->id),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ssl-certificate-expiring',
            with: [
                'order' => $this->order,
                'daysRemaining' => $this->daysRemaining,
                'viewUrl' => route('client.ssl.show', $this->order),
                'companyName' => config('app.name'),
            ],
        );
    }
}
