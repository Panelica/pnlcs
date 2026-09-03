<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use App\Mail\Concerns\LocalizesToRecipient;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Password reset email. Sent synchronously (not queued) so the reset link
 * reaches the user immediately and does not depend on a running queue worker.
 * The token is delivered here — never written to the application log.
 */
class PasswordResetMail extends Mailable
{
    use SerializesModels;
    use LocalizesToRecipient;

    public function __construct(
        public string $resetUrl,
        public string $email,
    ) {
        $this->localizeTo($this->email);
    }

    /**
     * The header that tells the mail log not to keep this body.
     *
     * The token is hashed in the database, and this class has always said it
     * is never written to the log - but every sent mail is copied into the
     * emails table, so the link that opens the account was kept in plain text
     * beside the hash, shown in the customer's mail history and handed out by
     * the getemails endpoint.
     */
    public const SENSITIVE_HEADER = 'X-PNLCS-Sensitive';

    public function headers(): Headers
    {
        return new Headers(
            text: [self::SENSITIVE_HEADER => '1'],
        );
    }

    public function envelope(): Envelope
    {
        $companyName = company_name();

        return new Envelope(subject: "Reset your {$companyName} password");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset',
            with: [
                'resetUrl' => $this->resetUrl,
                'email' => $this->email,
                'companyName' => company_name(),
            ],
        );
    }
}
