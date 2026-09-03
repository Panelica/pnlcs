<?php

namespace App\Mail;

use App\Models\Client;
use App\Mail\Concerns\LocalizesToRecipient;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class AccountSignupMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    use LocalizesToRecipient;

    public function __construct(
        public Client $client
    ) {
        $this->localizeTo($this->client);
    }

    public function envelope(): Envelope
    {
        $companyName = company_name();

        return new Envelope(subject: "Welcome to {$companyName}!");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account-signup',
            with: [
                'client' => $this->client,
                'companyName' => company_name(),
            ],
        );
    }
}
