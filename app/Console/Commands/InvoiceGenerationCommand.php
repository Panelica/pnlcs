<?php

namespace App\Console\Commands;

use App\Services\InvoiceGenerationService;
use Illuminate\Console\Command;

/**
 * Nightly renewal billing.
 *
 * This used to carry its own copy of the billing rules and never called
 * InvoiceGenerationService, so a fix landed in one of them and the other kept
 * running the old logic — that is exactly how disk and bandwidth overage went
 * unbilled. Everything now lives in the service; this command runs it and
 * reports.
 */
class InvoiceGenerationCommand extends Command
{
    protected $signature = 'pnlcs:generate-invoices';

    protected $description = 'Generate invoices for services, addons and domains due within the next billing period';

    public function handle(): int
    {
        $summary = app(InvoiceGenerationService::class)->generateDueInvoices();

        $this->info("Generated {$summary['generated']} invoices.");

        if ($summary['skipped'] > 0) {
            $this->line("Skipped {$summary['skipped']} client(s) with nothing billable.");
        }

        if ($summary['errors'] > 0) {
            $this->warn("{$summary['errors']} client(s) failed; see the log.");
        }

        return Command::SUCCESS;
    }
}
