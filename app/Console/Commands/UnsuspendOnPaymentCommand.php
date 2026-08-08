<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Enums\ServiceStatus;
use App\Mail\ServiceUnsuspensionMail;
use App\Models\Invoice;
use App\Models\Service;
use App\Services\Module\ModuleRegistry;
use App\Services\ProvisioningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class UnsuspendOnPaymentCommand extends Command
{
    protected $signature = 'pnlcs:unsuspend-on-payment';

    protected $description = 'Unsuspend services when their overdue invoices are paid';

    public function handle(ProvisioningService $provisioning): int
    {
        // Find suspended services with recently paid invoices
        $services = Service::with('client')->where('status', ServiceStatus::Suspended->value)->get();
        $unsuspended = 0;
        $skipped = 0;
        $stillBehind = 0;
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

            // And the client is not carrying a debt that auto-suspend would act
            // on tomorrow morning. Auto-suspend asks about the client, this
            // asked only about the service, so a client behind on a domain
            // renewal had their hosting switched off at 07:00 and back on by
            // 07:30, day after day, with an email each way.
            if (Invoice::where('client_id', $service->client_id)
                ->overduePastGrace(SuspensionCommand::GRACE_DAYS)
                ->exists()) {
                $stillBehind++;

                continue;
            }

            // The queue has already worked out that some of these cannot come
            // right - a service the panel has no account for will not grow one
            // because this runs again. Asking every half hour wrote the same
            // refusal to the log about a hundred times a day.
            if ($provisioning->hasGivenUp($service, 'unsuspend')) {
                $skipped++;

                continue;
            }

            if ($hasPaidInvoice && ! $hasOverdue) {
                $serverModule = $service->server_id
                    ? $registry->getServerModule($service->server->type ?? 'custom')
                    : null;

                if ($serverModule) {
                    // Through the provisioning service, which queues a retry
                    // and raises the alert. Calling the module here meant a
                    // refusal went to the log and no further: no retry, nobody
                    // told, and a customer who had paid left switched off until
                    // somebody happened to read it. On this installation that
                    // warning had been repeating every half hour for days.
                    $result = $provisioning->unsuspendAccount($service);

                    if (! ($result['success'] ?? false)) {
                        Log::warning("Unsuspend failed for service #{$service->id}: "
                            .($result['message'] ?? 'unknown error').' — queued for retry');

                        continue;
                    }

                    // unsuspendAccount has already cleared the suspension.
                    $service->refresh();
                } else {
                    $service->update([
                        'status' => ServiceStatus::Active->value,
                        'suspension_date' => null,
                        'suspension_reason' => null,
                    ]);
                }

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

        $this->info("Unsuspended {$unsuspended} service(s), {$stillBehind} left suspended over a debt still outstanding, {$skipped} left alone as unfixable.");

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
