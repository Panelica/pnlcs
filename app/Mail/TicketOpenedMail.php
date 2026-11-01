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

class TicketOpenedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    use LocalizesToRecipient;

    public function __construct(
        public Ticket $ticket,
        public bool $isAdmin = false,
        public bool $byStaff = false
    ) {
        $this->localizeTo($this->ticket);
    }

    public function envelope(): Envelope
    {
        $tid = $this->ticket->tid ?? $this->ticket->id;

        $title = $this->ticket->title ?? $this->ticket->subject ?? '';

        // Not translated, on purpose. This subject is not prose: "[Ticket
        // #123456]" is the token TicketMailImportService matches to append a
        // customer's emailed reply to the right ticket (TID_PATTERN), and the
        // rest is the customer's own words. A localized "Ticket Opened -
        // #123456" loses both: the importer cannot find the ticket, so the
        // reply opens a new one, and the inbox gives no hint what it is about.
        return new Envelope(subject: $this->repeatsWhatWasWritten()
            ? "[Ticket #{$tid}] {$title}"
            : "[Ticket #{$tid}] ".company_name());
    }

    /**
     * Whether the acknowledgement quotes the subject and the message back.
     *
     * A ticket without an account comes from the public contact form (or a
     * mail to the support address), where anybody types the address the
     * acknowledgement goes to. Quoting their words back made the panel send
     * any text to any address, in the operator's name. Such a sender gets the
     * reference and the department, which is what they need to follow up.
     */
    private function repeatsWhatWasWritten(): bool
    {
        return $this->isAdmin || $this->byStaff || $this->ticket->client_id !== null;
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-opened',
            with: [
                'ticket' => $this->ticket,
                'isAdmin' => $this->isAdmin,
                'byStaff' => $this->byStaff,
                'companyName' => company_name(),
                'repeatsContent' => $this->repeatsWhatWasWritten(),
            ],
        );
    }
}
