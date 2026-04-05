<?php

namespace App\Mail;

use App\Models\Service;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CancellationConfirmMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Service $service,
        public string $cancellationType = 'end_of_billing'
    ) {}

    public function envelope(): Envelope
    {
        $domain = $this->service->domain ?? $this->service->name ?? $this->service->id;

        return new Envelope(subject: "Cancellation Confirmed - {$domain}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.cancellation-confirm',
            with: [
                'service' => $this->service,
                'cancellationType' => $this->cancellationType,
                'companyName' => Setting::get('CompanyName', 'PNLCS'),
            ],
        );
    }
}
