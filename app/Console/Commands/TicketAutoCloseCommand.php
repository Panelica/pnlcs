<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Services\TicketService;
use Illuminate\Console\Command;

/**
 * Close tickets nobody has answered for a while.
 *
 * A ticket status can be marked "close automatically" (auto_close) - typically
 * Answered: staff replied and the customer never came back. Once the ticket
 * has had no reply for TicketAutoCloseHours (0 = off), it is closed the same
 * way staff close one, so TicketClosed and its hook run.
 */
class TicketAutoCloseCommand extends Command
{
    protected $signature = 'pnlcs:ticket-auto-close {--dry-run : List what would be closed without closing it}';

    protected $description = 'Close tickets left inactive in an auto-close status';

    public function handle(TicketService $tickets): int
    {
        $hours = (int) Setting::get('TicketAutoCloseHours', 0);
        if ($hours <= 0) {
            $this->info('Ticket auto-close is off.');

            return Command::SUCCESS;
        }

        $statuses = TicketStatus::where('auto_close', 1)->pluck('title')
            ->reject(fn ($title) => strcasecmp((string) $title, 'Closed') === 0)->values();
        if ($statuses->isEmpty()) {
            $this->info('No ticket status is marked to close automatically.');

            return Command::SUCCESS;
        }

        $cutoff = now()->subHours($hours);
        $closed = 0;
        Ticket::whereIn('status', $statuses->all())
            ->where(fn ($q) => $q->where('last_reply', '<', $cutoff)
                ->orWhere(fn ($q) => $q->whereNull('last_reply')->where('updated_at', '<', $cutoff)))
            ->chunkById(100, function ($batch) use ($tickets, &$closed) {
                foreach ($batch as $ticket) {
                    if ($this->option('dry-run')) {
                        $this->line("would close ticket #{$ticket->tid}");

                        continue;
                    }
                    $tickets->closeTicket($ticket);
                    $closed++;
                }
            });

        $this->info("Closed {$closed} inactive ticket(s).");

        return Command::SUCCESS;
    }
}
