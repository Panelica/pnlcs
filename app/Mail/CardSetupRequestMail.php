<?php

namespace App\Mail;

use App\Mail\Concerns\LocalizesToRecipient;
use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Staff asking a customer to add a card themselves.
 *
 * Staff signed in as the customer cannot store one (App\Support\Impersonation):
 * the card and the agreement to charge it are the cardholder's. This sends the
 * customer the link to their own Add card page instead; after signing in they
 * land on it.
 */
class CardSetupRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    use LocalizesToRecipient;

    public function __construct(public Client $client)
    {
        $this->localizeTo($this->client);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('email.card_request.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.card-setup-request',
            with: [
                'client' => $this->client,
                'addCardUrl' => route('client.payment-methods.add-card'),
                'companyName' => company_name(),
            ],
        );
    }
}
