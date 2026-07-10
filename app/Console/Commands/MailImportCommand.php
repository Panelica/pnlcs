<?php

namespace App\Console\Commands;

use App\Models\TicketDepartment;
use App\Services\TicketMailImportService;
use Illuminate\Console\Command;

class MailImportCommand extends Command
{
    protected $signature = 'pnlcs:mail-import {--department= : Import a single department by ID}';

    protected $description = 'Import support mailboxes (IMAP/POP3) into tickets';

    public function handle(TicketMailImportService $importer): int
    {
        if ($this->option('department')) {
            $department = TicketDepartment::findOrFail((int) $this->option('department'));
            if (!$department->import_active) {
                $this->warn("Department #{$department->id} has mail import disabled.");
                return self::FAILURE;
            }
            $totals = $importer->importDepartment($department);
        } else {
            $totals = $importer->importAll();
        }

        $this->info(sprintf(
            'Mail import done — created: %d, replied: %d, rejected: %d, skipped: %d, errors: %d',
            $totals['created'], $totals['replied'], $totals['rejected'], $totals['skipped'], $totals['errors']
        ));

        return self::SUCCESS;
    }
}
