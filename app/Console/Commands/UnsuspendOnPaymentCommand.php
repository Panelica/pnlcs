<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Enums\ServiceStatus;
use App\Mail\ServiceUnsuspensionMail;
use App\Models\Invoice;
use App\Models\Service;
use App\Services\Module\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class UnsuspendOnPaymentCommand extends Command
{
    protected $signature = 'pnlcs:unsuspend-on-payment';

    protected $description = 'Unsuspend services when their overdue invoices are paid';

    public function handle(): int
    {
        // Find suspended services with recently paid invoices
        $services = Service::with('client')->where('status', ServiceStatus::Suspended->value)->get();
        $unsuspended = 0;
        $registry = app(ModuleRegistry::class);

        foreach ($services as $service) {
            // Only what this command switched off. A service suspended for
            // fraud or by hand belongs to whoever suspended it - it used to be
            // turned back on by any payment that happened to land.
            if (! $this->suspendedForNonPayment($service)) {
                continue;
            }

            // At least one invoice for this service has been paid. Not "paid
            // recently": the window used to be twenty-four hours, so a run
            // missed - the scheduler stopped, an admin marked an old invoice
            // paid - left the service off for good, with nothing owed and the
            // payment on the books.
            $hasPaidInvoice = Invoice::where('client_id', $service->client_id)
                ->where('status', InvoiceStatus::Paid->value)
                ->whereHas('items', function ($q) use ($service) {
                    $q->where('rel_id', $service->id)
                        ->whereIn('type', ['Hosting', 'Service', 'hosting', 'service']);
                })
                ->exists();

            // Nothing still outstanding against it.
            $hasOverdue = Invoice::where('client_id', $service->client_id)
                ->whereIn('status', [InvoiceStatus::Overdue->value, InvoiceStatus::Unpaid->value])
                ->whereHas('items', function ($q) use ($service) {
                    $q->where('rel_id', $service->id);
                })
                ->exists();

            if ($hasPaidInvoice && ! $hasOverdue) {
                $serverModule = $service->server
                    ? $registry->getServerModule($service->server->type ?? 'custom')
                    : null;

                if ($serverModule) {
                    try {
                        $result = $serverModule->unsuspend($service);
                        if (! $result['success']) {
                            Log::warning("Unsuspend failed for service #{$service->id}: {$result['message']}");

                            continue;
                        }
                    } catch (\Throwable $e) {
                        Log::error("Unsuspend exception for service #{$service->id}: {$e->getMessage()}");

                        continue;
                    }
                }

                $service->update([
                    'status' => ServiceStatus::Active->value,
                    'suspension_date' => null,
                    'suspension_reason' => null,
                ]);

                if ($service->client?->email) {
                    try {
                        Mail::to($service->client->email)->queue(new ServiceUnsuspensionMail($service));
                    } catch (\Throwable $e) {
                        Log::warning("Unsuspension mail failed for service #{$service->id}: {$e->getMessage()}");
                    }
                }

                $unsuspended++;
            }
        }

        $this->info("Unsuspended {$unsuspended} service(s).");

        return Command::SUCCESS;
    }

    /**
     * Whether this is a suspension the billing side put in place.
     *
     * SuspensionCommand writes "Overdue Invoice - Automatic Suspension". A
     * blank reason is treated as ours too: services suspended before that text
     * existed carry nothing, and leaving them off forever helps nobody.
     */
    private function suspendedForNonPayment(Service $service): bool
    {
        $reason = trim((string) $service->suspension_reason);

        if ($reason === '') {
            return true;
        }

        return str_contains(strtolower($reason), 'overdue')
            || str_contains(strtolower($reason), 'unpaid');
    }
}
