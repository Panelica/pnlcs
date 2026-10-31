<?php

namespace App\Mail;

use App\Models\Setting;
use App\Mail\Concerns\LocalizesToRecipient;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class TicketReplyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    use LocalizesToRecipient;

    public function __construct(
        public Ticket $ticket,
        public string $replyMessage,
        public bool $isStaffReply = false
    ) {
        $this->localizeTo($this->ticket);
    }

    public function envelope(): Envelope
    {
        $tid = $this->ticket->tid ?? $this->ticket->id;

        return new Envelope(subject: __('email.ticket.reply_subject', ['number' => $tid]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-reply',
            with: [
                'ticket' => $this->ticket,
                'replyMessage' => $this->replyMessage,
                'isStaffReply' => $this->isStaffReply,
                'companyName' => company_name(),
            ],
        );
    }
}
