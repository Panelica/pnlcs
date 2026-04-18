<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Enums\ServiceStatus;
use App\Models\Invoice;
use App\Models\Service;
use App\Services\Module\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\ServiceUnsuspensionMail;

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
            // Check if there's a related paid invoice (paid today or recently)
            $hasPaidInvoice = Invoice::where('client_id', $service->client_id)
                ->where('status', InvoiceStatus::Paid->value)
                ->whereHas('items', function ($q) use ($service) {
                    $q->where('rel_id', $service->id)
                      ->whereIn('type', ['Hosting', 'Service', 'hosting', 'service']);
                })
                ->where('date_paid', '>=', now()->subDay())
                ->exists();

            // Also check if all overdue invoices for this client are now paid
            $hasOverdue = Invoice::where('client_id', $service->client_id)
                ->whereIn('status', [InvoiceStatus::Overdue->value, InvoiceStatus::Unpaid->value])
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
}
