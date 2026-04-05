<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketOpened
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public bool $isAdmin = false,
    ) {}
}
