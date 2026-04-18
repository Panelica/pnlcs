<?php

namespace App\Console\Commands;

use App\Services\TicketEscalationService;
use Illuminate\Console\Command;

class TicketEscalationCommand extends Command
{
    protected $signature = "pnlcs:ticket-escalation";
    protected $description = "Check and escalate tickets based on escalation rules";

    public function handle(TicketEscalationService $service): int
    {
        $count = $service->checkAndEscalate();
        $this->info("Escalated {$count} ticket(s).");
        return Command::SUCCESS;
    }
}
