<?php

namespace App\Mail;

use App\Models\SslOrder;
use App\Mail\Concerns\LocalizesToRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SslConfigurationRequiredMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    use LocalizesToRecipient;

    public function __construct(
        public SslOrder $order,
    ) {
        $this->localizeTo($this->order);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SSL Certificate Configuration Required - ' . ($this->order->domain ?: 'Order #' . $this->order->id),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.ssl-configuration-required',
            with: [
                'order' => $this->order,
                'configureUrl' => route('client.ssl.configure', $this->order),
                'companyName' => config('app.name'),
            ],
        );
    }
}
