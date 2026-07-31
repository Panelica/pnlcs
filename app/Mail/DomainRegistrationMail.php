<?php

namespace App\Mail;

use App\Models\Domain;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class DomainRegistrationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Domain $domain
    ) {}

    public function envelope(): Envelope
    {
        $name = $this->domain->domain ?? $this->domain->name ?? $this->domain->id;

        return new Envelope(subject: "Domain {$name} Registered");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.domain-registration',
            with: [
                'domain' => $this->domain,
                'companyName' => company_name(),
            ],
        );
    }
}
