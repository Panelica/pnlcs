<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BulkMassMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $mailBody,
        public string $recipientName
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(view: "emails.bulk-mass", with: [
            "body" => $this->mailBody,
            "recipientName" => $this->recipientName,
        ]);
    }
}
