<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Services\Module\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessCancellationsCommand extends Command
{
    protected $signature = 'pnlcs:process-cancellations';
    protected $description = 'Process services with pending end-of-billing cancellations';

    public function handle(): int
    {
        $services = Service::with('server', 'product')
            ->where('status', 'Active')
            ->whereNotNull('cancellation_type')
            ->where('next_due_date', '<=', now())
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
                    if (!$result['success']) {
                        Log::warning("Cancellation terminate failed for service #{$service->id}: {$result['message']}");
                    }
                } catch (\Throwable $e) {
                    Log::error("Cancellation exception for service #{$service->id}: {$e->getMessage()}");
                }
            }

            $service->update([
                'status' => 'Cancelled',
                'termination_date' => now(),
            ]);

            $processed++;
        }

        $this->info("Processed {$processed} cancellation(s).");
        return Command::SUCCESS;
    }
}
