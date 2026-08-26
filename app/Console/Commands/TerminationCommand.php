<?php

namespace App\Console\Commands;

use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\Setting;
use App\Services\ProvisioningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Termination deletes a customer's account and everything in it, so the rules
 * here are deliberately narrower than auto-suspend's:
 *
 *  - It is OFF until an admin turns it on (Settings -> Automation).
 *  - It only ever looks at services that are suspended AND whose client still
 *    owes an overdue invoice. Paying the debt takes a service off the list at
 *    once, even before unsuspend-on-payment has switched it back on.
 *  - A pending cancellation request belongs to pnlcs:process-cancellations,
 *    which honours the customer's chosen end-of-billing date; this command
 *    stays away from those services entirely.
 */
class TerminationCommand extends Command
{
    protected $signature = 'pnlcs:auto-terminate {--dry-run : List what would be terminated without touching anything}';

    protected $description = 'Terminate services that have stayed suspended over an unpaid invoice';

    public function handle(ProvisioningService $provisioning): int
    {
        if (! (int) Setting::get('AutoTerminationEnabled', 0)) {
            $this->info('Automatic termination is disabled.');

            return Command::SUCCESS;
        }

        $days = max(1, (int) Setting::get('AutoTerminationDays', 30));

        $services = Service::with(['client.group', 'server', 'product'])
            ->where('status', ServiceStatus::Suspended->value)
            // A manually suspended service without a date on record is not
            // "suspended for N days" by any measure; whereDate also excludes
            // NULL by itself, this whereNotNull just says so out loud.
            ->whereNotNull('suspension_date')
            ->whereDate('suspension_date', '<=', today()->subDays($days))
            ->whereHas('client', function ($q) {
                $q->whereHas('invoices', fn ($iq) => $iq->overduePastGrace(0));
            })
            ->whereDoesntHave('cancellationRequest', fn ($c) => $c->whereNull('processed_at'))
            ->get();

        $terminated = 0;
        $exempt = 0;
        $failed = 0;

        foreach ($services as $service) {
            if ($provisioning->hasGivenUp($service, 'terminate')) {
                $failed++;

                continue;
            }

            if ((bool) ($service->client?->group?->terminate_exempt ?? false)) {
                $exempt++;

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("would terminate service #{$service->id} ({$service->domain})");

                continue;
            }

            // Same server-vs-local split as auto-suspend: an account that lives
            // on a server must be removed there, not merely restatused here.
            if ($service->server_id && $provisioning->resolveModule($service)) {
                $result = $provisioning->terminateAccount($service);
                if ($result['success'] ?? false) {
                    $terminated++;
                } else {
                    // terminateAccount already queued a retry and logged details.
                    $failed++;
                    Log::warning("Auto-terminate failed for service #{$service->id}: ".($result['message'] ?? 'unknown'));
                }

                continue;
            }

            $service->update([
                'status' => ServiceStatus::Terminated->value,
                'termination_date' => now(),
            ]);
            $terminated++;
        }

        $this->info("Terminated {$terminated} services ({$exempt} exempt, {$failed} failed and queued for retry).");

        return Command::SUCCESS;
    }
}
