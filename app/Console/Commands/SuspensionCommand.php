<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Services\ProvisioningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SuspensionCommand extends Command
{
    protected $signature = 'pnlcs:auto-suspend {--dry-run : List what would be suspended without touching anything}';

    protected $description = 'Auto-suspend services with overdue invoices';

    /** Grace period, in days, after an invoice becomes overdue. */
    private const GRACE_DAYS = 3;

    public function handle(ProvisioningService $provisioning): int
    {
        $services = Service::with(['client.group', 'server', 'product'])
            ->where('status', ServiceStatus::Active->value)
            ->whereNull('override_auto_suspend_date')
            ->whereHas('client', function ($q) {
                $q->whereHas('invoices', fn ($iq) => $iq
                    ->where('status', InvoiceStatus::Overdue->value)
                    ->where('due_date', '<', now()->subDays(self::GRACE_DAYS)));
            })
            ->get();

        $suspended = 0;
        $exempt = 0;
        $failed = 0;

        foreach ($services as $service) {
            if ($this->isExempt($service)) {
                $exempt++;

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("would suspend service #{$service->id} ({$service->domain})");

                continue;
            }

            // A service backed by a server module must be suspended ON THE
            // SERVER — flipping the local status alone used to leave the
            // hosting account fully live while the panel claimed otherwise.
            if ($provisioning->resolveModule($service)) {
                $result = $provisioning->suspendAccount($service, 'Overdue Invoice - Automatic Suspension');
                if ($result['success'] ?? false) {
                    $suspended++;
                } else {
                    // suspendAccount already queued a retry and logged details.
                    $failed++;
                    Log::warning("Auto-suspend failed for service #{$service->id}: ".($result['message'] ?? 'unknown'));
                }

                continue;
            }

            // No server module (e.g. a manual or non-hosting product): there is
            // nothing remote to suspend, so record the local state only.
            $service->update([
                'status' => ServiceStatus::Suspended->value,
                'suspension_date' => now(),
                'suspension_reason' => 'Overdue Invoice - Automatic Suspension',
            ]);
            $suspended++;
        }

        $this->info("Suspended {$suspended} services ({$exempt} exempt, {$failed} failed and queued for retry).");

        return Command::SUCCESS;
    }

    /**
     * Suspension exemptions. These columns were settable from the admin UI but
     * no code ever read them, so "never suspend this client" was ignored.
     */
    private function isExempt(Service $service): bool
    {
        $client = $service->client;
        if (! $client) {
            return false;
        }

        return (bool) ($client->override_auto_suspend ?? false)
            || (bool) ($client->group?->suspend_exempt ?? false);
    }
}
