<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * The link that proves an address belongs to the person who typed it.
 *
 * Not queued. The customer is looking at "check your email" the second they
 * leave the sign-up form; making that depend on a queue worker being alive
 * turns a working panel into a broken one the first time the worker stops.
 *
 * Like the password reset, the link opens an account, so it is kept out of the
 * mail history: a stored copy would let anyone who can read that screen
 * verify - and sign in as - somebody else's account.
 */
class EmailVerificationMail extends Mailable
{
    use SerializesModels;

    /** Same header the password reset uses; the logger checks for it. */
    public const SENSITIVE_HEADER = 'X-PNLCS-Sensitive';

    public function __construct(
        public string $verifyUrl,
        public string $email,
        public string $firstName = '',
    ) {}

    public function headers(): Headers
    {
        return new Headers(text: [self::SENSITIVE_HEADER => '1']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('client.email_verify.mail_subject', ['company' => company_name()])
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.email-verification',
            with: [
                'verifyUrl' => $this->verifyUrl,
                'email' => $this->email,
                'firstName' => $this->firstName,
                'companyName' => company_name(),
            ],
        );
    }
}
