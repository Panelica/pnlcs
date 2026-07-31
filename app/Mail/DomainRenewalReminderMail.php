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

class DomainRenewalReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Domain $domain,
        public int $daysUntilExpiry
    ) {}

    public function envelope(): Envelope
    {
        $name = $this->domain->domain ?? $this->domain->name ?? $this->domain->id;

        return new Envelope(subject: "Domain {$name} expires in {$this->daysUntilExpiry} days");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.domain-renewal-reminder',
            with: [
                'domain' => $this->domain,
                'daysUntilExpiry' => $this->daysUntilExpiry,
                'companyName' => company_name(),
            ],
        );
    }
}
