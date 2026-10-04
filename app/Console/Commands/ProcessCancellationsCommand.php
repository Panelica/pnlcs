<?php

namespace App\Console\Commands;

use App\Events\ServiceTerminated;
use App\Models\Service;
use App\Services\ProvisioningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
        // Suspended services count too. A customer who wants out should not be
        // held to a service because they are behind on it - one request here
        // has been waiting since April for that reason.
        $services = Service::with('server', 'product', 'client', 'cancellationRequest')
            ->whereIn('status', ['active', 'suspended'])
            // Only requests nobody has acted on. Nothing used to close them,
            // so a service put back to work was cancelled again the next night.
            ->whereHas('cancellationRequest', fn ($c) => $c->whereNull('processed_at'))
            ->where(function ($q) {
                $q->whereHas('cancellationRequest', fn ($c) => $c->whereNull('processed_at')->whereRaw(
                    "LOWER(REPLACE(REPLACE(type, ' ', '_'), '-', '_')) = ?", ['immediate']
                ))->orWhere('next_due_date', '<=', now());
            })
            ->get();

        $processed = 0;
        $provisioning = app(ProvisioningService::class);

        foreach ($services as $service) {
            // Through the provisioning service, which queues a retry when the
            // server cannot be reached and announces what happened. Calling the
            // module here meant a failed termination was logged and forgotten.
            // Same as the other jobs: what the queue gave up on for good is
            // not asked again.
            if ($provisioning->hasGivenUp($service, 'terminate')) {
                continue;
            }

            $onServer = $service->server_id && $provisioning->resolveModule($service);
            if ($onServer) {
                $result = $provisioning->terminateAccount($service);

                if (! ($result['success'] ?? false)) {
                    Log::warning("Cancellation terminate failed for service #{$service->id}: "
                        .($result['message'] ?? 'unknown error').' — left open for the retry queue');

                    continue;
                }
            }

            // The customer asked to stop, so it is recorded as cancelled rather
            // than terminated.
            $service->update([
                'status' => 'cancelled',
                'termination_date' => now(),
            ]);
            // terminateAccount raised ServiceTerminated for a server; a
            // service with nothing on a server ends here and raises it now.
            // The event sends the customer's mail (SendNotificationListener),
            // so the job no longer sends one of its own: on a server that was
            // the same mail twice.
            if (! $onServer) {
                event(new ServiceTerminated($service));
            }

            // r136-openrequest: close the request that is actually open.
            //
            // A service can carry more than one over its life - the form allows
            // a new one once the previous has been acted on - and the relation
            // is an unordered hasOne, so it handed back the oldest row. The job
            // stamped that one a second time and left the open request open, so
            // the moment the service was put back to work it was cancelled
            // again: the exact thing closing the request was meant to prevent.
            $service->cancellationRequest()
                ->whereNull('processed_at')
                ->update(['processed_at' => now()]);

            $processed++;
        }

        $this->info("Processed {$processed} cancellation(s).");

        return Command::SUCCESS;
    }
}
