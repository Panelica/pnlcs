<?php

namespace Modules\Addons\BankPayments\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BankPaymentsReviewMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailboxName,
        public int $matchCount,
        public string $queueUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('bank-payments::emails.review_subject', ['count' => $this->matchCount]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'bank-payments::emails.review',
            with: [
                'mailboxName' => $this->mailboxName,
                'matchCount' => $this->matchCount,
                'queueUrl' => $this->queueUrl,
            ],
        );
    }
}
