<?php

namespace App\Mail;

use App\Mail\Concerns\LocalizesToRecipient;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * The code that confirms a sensitive action (SensitiveActionConfirmation).
 *
 * Not queued: the customer is waiting on the page for it. Kept out of the mail
 * history like the password reset, since the code is a working key while it
 * lives.
 */
class ConfirmationCodeMail extends Mailable
{
    use LocalizesToRecipient;
    use SerializesModels;

    public function __construct(
        public string $code,
        public string $email,
        public int $minutes,
    ) {
        $this->localizeTo($this->email);
    }

    public function headers(): Headers
    {
        return new Headers(text: [PasswordResetMail::SENSITIVE_HEADER => '1']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('client.confirm.mail_subject', ['company' => company_name()]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.confirmation-code',
            // `email` lets the template greet the customer by name.
            with: ['code' => $this->code, 'minutes' => $this->minutes, 'email' => $this->email, 'companyName' => company_name()],
        );
    }
}
