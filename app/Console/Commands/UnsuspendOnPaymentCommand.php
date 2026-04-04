<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Service;
use App\Services\Module\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UnsuspendOnPaymentCommand extends Command
{
    protected $signature = 'pnlcs:unsuspend-on-payment';
    protected $description = 'Unsuspend services when their overdue invoices are paid';

    public function handle(): int
    {
        // Find suspended services with recently paid invoices
        $services = Service::where('status', 'Suspended')->get();
        $unsuspended = 0;
        $registry = app(ModuleRegistry::class);

        foreach ($services as $service) {
            // Check if there's a related paid invoice (paid today or recently)
            $hasPaidInvoice = Invoice::where('client_id', $service->client_id)
                ->whereIn('status', ['Paid', 'paid'])
                ->whereHas('items', function ($q) use ($service) {
                    $q->where('rel_id', $service->id)
                      ->whereIn('type', ['Hosting', 'Service', 'hosting', 'service']);
                })
                ->where('date_paid', '>=', now()->subDay())
                ->exists();

            // Also check if all overdue invoices for this client are now paid
            $hasOverdue = Invoice::where('client_id', $service->client_id)
                ->whereIn('status', ['Overdue', 'overdue', 'Unpaid', 'unpaid'])
                ->whereHas('items', function ($q) use ($service) {
                    $q->where('rel_id', $service->id);
                })
                ->exists();

            if ($hasPaidInvoice && !$hasOverdue) {
                $serverModule = $service->server
                    ? $registry->getServerModule($service->server->type ?? 'custom')
                    : null;

                if ($serverModule) {
                    try {
                        $result = $serverModule->unsuspend($service);
                        if (!$result['success']) {
                            Log::warning("Unsuspend failed for service #{$service->id}: {$result['message']}");
                            continue;
                        }
                    } catch (\Throwable $e) {
                        Log::error("Unsuspend exception for service #{$service->id}: {$e->getMessage()}");
                        continue;
                    }
                }

                $service->update([
                    'status' => 'Active',
                    'suspension_date' => null,
                    'suspension_reason' => null,
                ]);

                $unsuspended++;
            }
        }

        $this->info("Unsuspended {$unsuspended} service(s).");
        return Command::SUCCESS;
    }
}
