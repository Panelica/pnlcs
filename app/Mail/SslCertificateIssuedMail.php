<?php

namespace App\Mail;

use App\Models\SslOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SslCertificateIssuedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public SslOrder $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SSL Certificate Issued - ' . ($this->order->domain ?: 'Order #' . $this->order->id),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ssl-certificate-issued',
            with: [
                'order' => $this->order,
                'viewUrl' => route('client.ssl.show', $this->order),
                'downloadUrl' => route('client.ssl.download', $this->order),
                'companyName' => config('app.name'),
            ],
        );
    }
}
