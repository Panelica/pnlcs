<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Password reset email. Sent synchronously (not queued) so the reset link
 * reaches the user immediately and does not depend on a running queue worker.
 * The token is delivered here — never written to the application log.
 */
class PasswordResetMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public string $resetUrl,
        public string $email,
    ) {}

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
                'resetUrl'    => $this->resetUrl,
                'email'       => $this->email,
                'companyName' => company_name(),
            ],
        );
    }
}
