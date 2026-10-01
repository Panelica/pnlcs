<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A ticket was closed. Reaches hooks as TicketClosed and, for code written
 * for WHMCS, TicketClose.
 */
class TicketClosed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public bool $byClient = false,
    ) {}
}
