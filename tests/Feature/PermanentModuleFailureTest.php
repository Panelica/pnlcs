<?php

use App\Models\Client;
use App\Models\ModuleQueue;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use App\Services\ProvisioningService;
use Illuminate\Support\Facades\Http;

/**
 * Failures that will never come right.
 *
 * The retry queue treats every refusal the same: give it five minutes, try
 * again, and when the attempts run out, pick it back up the next time the job
 * runs. That is right for a server that was unreachable last night.
 *
 * It is not right for a service the panel has no account for. Four services on
 * this installation are in that state - carrying a server but no account
 * identity, because they were never created through the module - and every
 * half-hourly run has tried to unsuspend them and written the same refusal to
 * the log since the beginning of the month: two hundred and nine times each,
 * a hundred and forty-five more today. No number of retries can find an
 * account that was never made.
 */
function serviceWithNoAccount(): Service
{
    $server = Server::factory()->create([
        'type' => 'panelica', 'hostname' => 'panel.permanent.test',
        'access_hash' => 'sk', 'password' => 'pk', 'active' => true,
    ]);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'panelica',
    ]);

    return Service::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'status' => 'active',
        'domain' => 'no-account.test',
        'notes' => null,
    ]);
}

it('stops retrying when there is no account to act on', function () {
    Http::fake();

    $service = serviceWithNoAccount();
    $provisioning = app(ProvisioningService::class);

    $provisioning->suspendAccount($service, 'overdue');

    $entry = ModuleQueue::where('service_id', $service->id)->where('action', 'suspend')->firstOrFail();

    expect($entry->status)->toBe('failed');

    // The next run says the same thing; it does not start the clock again.
    $provisioning->suspendAccount($service, 'overdue');

    $entry->refresh();

    expect($entry->status)->toBe('failed')
        ->and(ModuleQueue::where('service_id', $service->id)->where('action', 'suspend')->count())->toBe(1);
});

it('still retries a server that did not answer', function () {
    Http::fake(['*' => Http::response(['message' => 'gateway timeout'], 504)]);

    $service = serviceWithNoAccount();
    $service->update(['notes' => json_encode(['panelica_user_id' => 4242])]);

    $provisioning = app(ProvisioningService::class);
    $provisioning->suspendAccount($service->fresh(), 'overdue');

    $entry = ModuleQueue::where('service_id', $service->id)->where('action', 'suspend')->firstOrFail();

    expect($entry->status)->toBe('pending');

    $entry->update(['status' => 'failed', 'attempts' => 5]);

    $provisioning->suspendAccount($service->fresh(), 'overdue');

    expect($entry->fresh()->status)->toBe('pending')
        ->and($entry->fresh()->attempts)->toBe(0);
});
