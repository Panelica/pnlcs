<?php

namespace App\Mail;

use App\Models\Setting;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class TicketOpenedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public bool $isAdmin = false
    ) {}

    public function envelope(): Envelope
    {
        $tid = $this->ticket->tid ?? $this->ticket->id;
        $title = $this->ticket->title ?? $this->ticket->subject ?? '';

        return new Envelope(subject: "[Ticket #{$tid}] {$title}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-opened',
            with: [
                'ticket' => $this->ticket,
                'isAdmin' => $this->isAdmin,
                'companyName' => Setting::get('CompanyName', 'PNLCS'),
            ],
        );
    }
}
