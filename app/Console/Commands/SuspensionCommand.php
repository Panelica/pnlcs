<?php
namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\Invoice;
use Illuminate\Console\Command;

class SuspensionCommand extends Command
{
    protected $signature = 'pnlcs:auto-suspend';
    protected $description = 'Auto-suspend services with overdue invoices';

    public function handle(): int
    {
        $services = Service::where('status', ServiceStatus::Active->value)
            ->whereHas('client', function ($q) {
                $q->whereHas('invoices', fn ($iq) => $iq->where('status', InvoiceStatus::Overdue->value)->where('due_date', '<', now()->subDays(3)));
            })
            ->where('override_auto_suspend_date', null)
            ->get();

        $count = 0;
        foreach ($services as $service) {
            $service->update([
                'status' => ServiceStatus::Suspended->value,
                'suspension_date' => now(),
                'suspension_reason' => 'Overdue Invoice - Automatic Suspension',
            ]);
            $count++;
        }

        $this->info("Suspended {$count} services.");
        return Command::SUCCESS;
    }
}
