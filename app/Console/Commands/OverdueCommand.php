<?php

namespace App\Console\Commands;

use App\Services\InvoiceGenerationService;
use Illuminate\Console\Command;

class OverdueCommand extends Command
{
    protected $signature = 'pnlcs:mark-overdue';

    protected $description = 'Mark unpaid invoices past due date as overdue';

    /**
     * One question, asked from one place.
     *
     * invoices.due_date is a date, and this compared it against now(): at 06:30
     * an invoice due today was already "past its due date" and was stamped
     * overdue hours before the day it was due had ended. The customer saw
     * OVERDUE on an invoice they had all day to pay, and every clock hanging off
     * that status - the dunning stages, the late fee, the suspension grace -
     * started a day early.
     *
     * The same job already existed, written correctly, in the invoice
     * generation service, and nothing called it. This runs that one.
     */
    public function handle(InvoiceGenerationService $invoices): int
    {
        $count = $invoices->markOverdueInvoices();

        $this->info("Marked {$count} invoices as overdue.");

        return Command::SUCCESS;
    }
}
