<?php

namespace App\Console\Commands;

use App\Mail\ServiceTerminationMail;
use App\Models\Service;
use App\Services\Module\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessCancellationsCommand extends Command
{
    protected $signature = 'pnlcs:process-cancellations';

    protected $description = 'Process services with pending end-of-billing cancellations';

    public function handle(): int
    {
        // The request type used to be ignored entirely: every cancellation
        // waited for the paid period to end, so a customer who asked to stop
        // immediately kept a running service and the choice on the form meant
        // nothing.
        $services = Service::with('server', 'product', 'client', 'cancellationRequest')
            ->where('status', 'active')
            ->whereHas('cancellationRequest')
            ->where(function ($q) {
                $q->whereHas('cancellationRequest', fn ($c) => $c->whereRaw(
                    "LOWER(REPLACE(REPLACE(type, ' ', '_'), '-', '_')) = ?", ['immediate']
                ))->orWhere('next_due_date', '<=', now());
            })
            ->get();

        $processed = 0;
        $registry = app(ModuleRegistry::class);

        foreach ($services as $service) {
            $serverModule = $service->server
                ? $registry->getServerModule($service->server->type ?? 'custom')
                : null;

            if ($serverModule) {
                try {
                    $result = $serverModule->terminate($service);
                    if (! $result['success']) {
                        Log::warning("Cancellation terminate failed for service #{$service->id}: {$result['message']}");
                    }
                } catch (\Throwable $e) {
                    Log::error("Cancellation exception for service #{$service->id}: {$e->getMessage()}");
                }
            }

            $service->update([
                'status' => 'cancelled',
                'termination_date' => now(),
            ]);

            if ($service->client?->email) {
                try {
                    Mail::to($service->client->email)->queue(new ServiceTerminationMail($service));
                } catch (\Throwable $e) {
                    Log::warning("Cancellation mail failed for service #{$service->id}: {$e->getMessage()}");
                }
            }

            $processed++;
        }

        $this->info("Processed {$processed} cancellation(s).");

        return Command::SUCCESS;
    }
}
