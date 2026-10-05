<?php

namespace App\Services;

use App\Events\TicketClosed;
use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

    public function closeTicket(Ticket $ticket, bool $byClient = false): Ticket
    {
        $ticket->update(['status' => 'Closed']);

        event(new TicketClosed($ticket, $byClient));

        return $ticket->fresh();
    }

    public function reopenTicket(Ticket $ticket): Ticket
    {
        $ticket->update(['status' => 'Open']);

        return $ticket->fresh();
    }

    /**
     * Remove a ticket for good: its replies, notes, tags, rating, watchers and
     * log go with it (all cascade on ticket_id), and so do the files attached
     * to it. The admin area had no way to do this at all, and the API's
     * deleteticket dropped the row and left every attachment on disk.
     *
     * The usual reason is spam: the public contact form opens a ticket for
     * anybody who fills it in, with no account behind it.
     */
    public function deleteTicket(Ticket $ticket, string $by = 'System'): void
    {
        $id = $ticket->id;
        $tid = $ticket->tid;
        $clientId = $ticket->client_id;

        $files = $ticket->replies()->pluck('attachment')
            ->push($ticket->attachment)
            ->filter()
            ->unique()
            ->values();

        DB::transaction(function () use ($ticket, $id) {
            // A ticket merged into this one would point at a row that is gone.
            Ticket::where('merged_ticket_id', $id)->update(['merged_ticket_id' => null]);
            $ticket->delete();
        });

        $disk = Storage::disk('local');
        foreach ($files as $path) {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }
        $disk->deleteDirectory("ticket-attachments/{$id}");

        ActivityLog::log("Ticket #{$tid} deleted", $by, $clientId);
    }

    protected function generateTicketId(): string
    {
        do {
            $tid = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        } while (Ticket::where('tid', $tid)->exists());

        return $tid;
    }
}
