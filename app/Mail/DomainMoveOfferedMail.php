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
            'companyName' => company_name(),
        ]);
    }
}
