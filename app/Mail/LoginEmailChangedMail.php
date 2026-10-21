<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use App\Mail\Concerns\LocalizesToRecipient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the address that is losing the account.
 *
 * The sign-in address used to be changeable without so much as a password, and
 * without a word to the address being replaced - which is also the address
 * "forgot password" delivers to.
 */
class LoginEmailChangedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    use LocalizesToRecipient;

    public function __construct(
        public string $previousEmail,
        public string $newEmail,
        // Changed by staff on the account (#210), not by the customer with
        // their password: the mail says so.
        public bool $byStaff = false,
    ) {
        $this->localizeTo($this->newEmail);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('email.login_email_changed.subject', ['company' => company_name()]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.login-email-changed',
            with: [
                'previousEmail' => $this->previousEmail,
                'newEmail' => $this->newEmail,
                'byStaff' => $this->byStaff,
                'companyName' => company_name(),
            ],
        );
    }
}
