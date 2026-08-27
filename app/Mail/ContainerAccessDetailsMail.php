<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The connection details of an installed app, sent to the account's own email
 * on the customer's explicit request. This mail carries the generated
 * passwords in the clear - that is its purpose - so it is only ever addressed
 * to the client the service belongs to.
 */
class ContainerAccessDetailsMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, string>  $items  label => value
     */
    public function __construct(
        public string $appName,
        public array $items,
        public string $notes,
        public ?string $accessUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('client.hosting.containers.email_subject', ['app' => $this->appName]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.container-access-details',
            with: [
                'appName' => $this->appName,
                'items' => $this->items,
                'notes' => $this->notes,
                'accessUrl' => $this->accessUrl,
                'companyName' => config('app.name'),
            ],
        );
    }
}
