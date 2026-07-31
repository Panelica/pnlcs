<?php

namespace App\Mail;

use App\Models\Client;
use App\Models\PaymentMethod;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class CreditCardExpiryMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Client $client,
        public PaymentMethod $paymentMethod
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your credit card is expiring soon',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.cc-expiry',
            with: [
                'client' => $this->client,
                'paymentMethod' => $this->paymentMethod,
                'companyName' => company_name(),
            ],
        );
    }
}
