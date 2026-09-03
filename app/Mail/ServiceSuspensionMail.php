<?php

namespace App\Mail;

use App\Models\Service;
use App\Mail\Concerns\LocalizesToRecipient;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ServiceSuspensionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    use LocalizesToRecipient;

    public function __construct(
        public Service $service,
        public string $reason = ''
    ) {
        $this->localizeTo($this->service);
    }

    public function envelope(): Envelope
    {
        $domain = $this->service->domain ?? $this->service->name ?? $this->service->id;

        return new Envelope(subject: "Service {$domain} Suspended");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.service-suspension',
            with: [
                'service' => $this->service,
                'reason' => $this->reason,
                'companyName' => company_name(),
            ],
        );
    }
}
