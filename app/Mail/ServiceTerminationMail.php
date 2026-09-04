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

class ServiceTerminationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    use LocalizesToRecipient;

    public function __construct(
        public Service $service
    ) {
        $this->localizeTo($this->service);
    }

    public function envelope(): Envelope
    {
        $domain = $this->service->domain ?? $this->service->name ?? $this->service->id;

        return new Envelope(subject: "Service {$domain} Terminated");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.service-termination',
            with: [
                'service' => $this->service,
                'companyName' => company_name(),
            ],
        );
    }
}
