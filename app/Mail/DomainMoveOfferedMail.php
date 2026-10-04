<?php

namespace App\Mail;

use App\Mail\Concerns\LocalizesToRecipient;
use App\Models\DomainMoveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DomainMoveOfferedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    use LocalizesToRecipient;

    public function __construct(public DomainMoveRequest $offer)
    {
        $this->localizeTo($this->offer->toClient);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('email.domain_move.subject', ['domain' => $this->offer->domain?->domain]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.domain-move-offered', with: [
            'offer' => $this->offer,
            // For the operator's template: the receiver is the client; the
            // domain goes by name (its model belongs to the giver).
            'client' => $this->offer->toClient,
            'offeredDomain' => (string) $this->offer->domain?->domain,
            'offeredBy' => (string) ($this->offer->fromClient?->full_name ?: $this->offer->fromClient?->email),
            'offerEnds' => (string) $this->offer->expires_at?->format(date_fmt()),
            'companyName' => company_name(),
        ]);
    }
}
