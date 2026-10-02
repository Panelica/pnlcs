<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class BulkMassMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $mailBody,
        public string $recipientName,
        // Set for a marketing message: the foot of the mail and the
        // List-Unsubscribe headers point at it.
        public ?string $unsubscribeUrl = null,
    ) {}

    public function headers(): Headers
    {
        if (! $this->unsubscribeUrl) {
            return new Headers;
        }

        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.bulk-mass', with: [
            'body' => $this->mailBody,
            'recipientName' => $this->recipientName,
            'unsubscribeUrl' => $this->unsubscribeUrl,
        ]);
    }
}
