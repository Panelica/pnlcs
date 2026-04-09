<?php
namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Console\Command;

class OverdueCommand extends Command
{
    protected $signature = 'pnlcs:mark-overdue';
    protected $description = 'Mark unpaid invoices past due date as overdue';

    public function handle(): int
    {
        $count = Invoice::where('status', InvoiceStatus::Unpaid->value)
            ->where('due_date', '<', now())
            ->update(['status' => InvoiceStatus::Overdue->value]);

        $this->info("Marked {$count} invoices as overdue.");
        return Command::SUCCESS;
    }
}
