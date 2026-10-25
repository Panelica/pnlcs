<?php

namespace App\Jobs;

use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Services\OrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Set up one service of an order that was paid for, away from the request.
 *
 * A paid order used to build every server inside the request that paid for it
 * - a Panelica account and its app, a Proxmox clone, resize and boot, one after
 * the other - so the customer stared at a spinning checkout for minutes. The
 * request now only accepts the order; this job does the setup. It runs once:
 * a refusal goes the way it always did (the service stays pending, the module
 * queue retries it, staff are told), so retrying here would only repeat it.
 */
class ProvisionOrderedService implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $serviceId) {}

    public function handle(OrderService $orders): void
    {
        $service = Service::with('product')->find($this->serviceId);

        // Set up already (by staff, or a retry got there first), or no longer wanted.
        if (! $service || $service->status !== ServiceStatus::Pending->value) {
            return;
        }

        $orders->provisionOrderedService($service);
    }
}
