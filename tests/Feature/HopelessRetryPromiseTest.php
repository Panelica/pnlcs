<?php

use App\Models\Client;
use App\Models\ModuleQueue;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use App\Services\NotificationService;
use App\Services\ProvisioningService;
use Illuminate\Support\Facades\Http;

/**
 * A retry that was never going to happen, announced as if it were.
 *
 * When a module refuses for a reason that cannot change - no account to act
 * on - the queue now records it as failed and does not schedule another
 * attempt. But the alert sent at that moment still reads "Module action
 * failed - queued for retry ... Queued for automatic retry", written for the
 * case where a retry really is coming.
 *
 * So the one message the operator gets tells them to wait. Nothing else is
 * ever sent, because the entry the "permanently failed" alert comes from is
 * never picked up again. Four unsuspend entries on this installation have sat
 * in exactly that state since the 4th: a paying customer left switched off,
 * and an operator who was told it was in hand.
 */
function hopelessService(string $domain = 'no-account-alert.test'): Service
{
    $server = Server::factory()->create([
        'type' => 'panelica', 'hostname' => 'panel.hopeless.test',
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
        'status' => 'suspended',
        'domain' => $domain,
        'notes' => null,
    ]);
}

/** Capture what the panel told the operator. */
function capturedAlerts(): ArrayObject
{
    $seen = new ArrayObject;

    $spy = Mockery::mock(NotificationService::class);
    $spy->shouldReceive('dispatch')->andReturnUsing(function ($event, $data = []) use ($seen) {
        $seen[] = ['event' => $event] + $data;
    });

    app()->instance(NotificationService::class, $spy);

    return $seen;
}

it('does not promise a retry it will not make', function () {
    Http::fake();
    $alerts = capturedAlerts();

    $service = hopelessService();

    app(ProvisioningService::class)->unsuspendAccount($service);

    $entry = ModuleQueue::where('service_id', $service->id)->firstOrFail();
    expect($entry->status)->toBe('failed');
    expect($entry->next_attempt_at)->toBeNull();

    expect($alerts->count())->toBe(1);
    expect($alerts[0]['event'])->toBe('module.failed_permanently');
    expect(strtolower($alerts[0]['message']))->not->toContain('queued for automatic retry');
});

it('still says a retry is coming when one is', function () {
    Http::fake(['*' => Http::response('', 503)]);
    $alerts = capturedAlerts();

    $service = hopelessService('transient-alert.test');
    $service->forceFill(['module_data' => ['panelica_user_id' => '42']])->save();

    app(ProvisioningService::class)->unsuspendAccount($service);

    $entry = ModuleQueue::where('service_id', $service->id)->firstOrFail();
    expect($entry->status)->toBe('pending');

    expect($alerts->count())->toBe(1);
    expect($alerts[0]['event'])->toBe('module.failed');
    expect(strtolower($alerts[0]['message']))->toContain('queued for automatic retry');
});

it('gives up on the first refusal that cannot change, not the fifth', function () {
    Http::fake();
    capturedAlerts();

    $service = hopelessService('queue-permanent.test');

    $entry = ModuleQueue::create([
        'service_id' => $service->id,
        'action' => 'unsuspend',
        'status' => 'pending',
        'attempts' => 0,
        'next_attempt_at' => now()->subMinute(),
    ]);

    $this->artisan('pnlcs:module-queue')->assertSuccessful();

    $entry->refresh();
    expect($entry->status)->toBe('failed');
    expect($entry->attempts)->toBe(1);
});
