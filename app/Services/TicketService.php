<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketReply;

class TicketService
{
    public function createTicket(array $data): Ticket
    {
        $data['tid'] = $this->generateTicketId();
        $data['status'] = $data['status'] ?? 'Open';
        $data['last_reply'] = now();

        return Ticket::create($data);
    }

    public function addReply(Ticket $ticket, array $data): TicketReply
    {
        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'client_id' => $data['client_id'] ?? null,
            'admin' => $data['admin'] ?? '',
            'message' => $data['message'],
        ]);

        $newStatus = ! empty($data['admin']) ? 'Answered' : 'Customer-Reply';
        $ticket->recordReply($newStatus);

        return $reply;
    }

    public function closeTicket(Ticket $ticket): Ticket
    {
        $ticket->update(['status' => 'Closed']);

        return $ticket->fresh();
    }

    public function reopenTicket(Ticket $ticket): Ticket
    {
        $ticket->update(['status' => 'Open']);

        return $ticket->fresh();
    }

    protected function generateTicketId(): string
    {
        do {
            $tid = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        } while (Ticket::where('tid', $tid)->exists());

        return $tid;
    }
}
