<?php

namespace App\Mail;

use App\Models\Service;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ServiceWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Service $service
    ) {}

    public function envelope(): Envelope
    {
        $domain = $this->service->domain ?? $this->service->name ?? $this->service->id;

        return new Envelope(subject: "Your Service {$domain} is Ready!");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.service-welcome',
            with: [
                'service' => $this->service,
                'companyName' => company_name(),
            ],
        );
    }
}
