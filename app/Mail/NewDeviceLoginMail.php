<?php

namespace App\Mail;

use App\Mail\Concerns\LocalizesToRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when a login signs in from a browser it has not used before.
 *
 * The customer is the only one who can tell their own new laptop from
 * somebody else's; this tells them while the session is still open.
 */
class NewDeviceLoginMail extends Mailable implements ShouldQueue
{
    use LocalizesToRecipient;
    use Queueable, SerializesModels;

    public function __construct(
        public string $email,
        public string $loginIp,
        public string $loginDevice,
        public string $loginTime,
    ) {
        $this->localizeTo($this->email);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('email.new_device_login.subject', ['company' => company_name()]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-device-login',
            with: [
                'email' => $this->email,
                'loginIp' => $this->loginIp,
                'loginDevice' => $this->loginDevice,
                'loginTime' => $this->loginTime,
                'securityUrl' => route('client.account.security'),
                'companyName' => company_name(),
            ],
        );
    }
}
