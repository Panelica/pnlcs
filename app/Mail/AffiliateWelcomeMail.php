<?php

namespace App\Mail;

use App\Models\Affiliate;
use App\Models\Client;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class AffiliateWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Client $client,
        public Affiliate $affiliate
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welcome to our Affiliate Program!');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.affiliate-welcome',
            with: [
                'client' => $this->client,
                'affiliate' => $this->affiliate,
                'companyName' => company_name(),
            ],
        );
    }
}
