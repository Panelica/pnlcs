<?php

use App\Models\CancellationRequest;
use App\Models\Client;
use App\Models\ModuleQueue;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Processing a cancellation the customer asked for.
 *
 * The command called the server module directly instead of going through the
 * provisioning service, and then marked the service cancelled whatever the
 * module answered. A termination that failed — a server that was down for the
 * five minutes the cron ran — left the account running on the server with the
 * panel saying cancelled, nothing queued to try again and nobody told. The
 * customer kept their hosting for nothing, quietly and indefinitely.
 */
function cancelledService(array $attrs = []): Service
{
    $client = Client::factory()->create();
    $server = Server::factory()->create(['type' => 'panelica', 'hostname' => 'srv.test', 'active' => true]);
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'panelica',
    ]);

    $service = Service::factory()->create(array_merge([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'status' => 'active',
        'domain' => 'stopping-example.com',
        'username' => 'stopuser',
        'next_due_date' => now()->subDay(),
        'notes' => json_encode(['panelica_user_id' => 909]),
    ], $attrs));

    CancellationRequest::create([
        'service_id' => $service->id,
        'type' => 'immediate',
        'reason' => 'Moving elsewhere',
    ]);

    return $service;
}

test('a cancellation removes the account and closes the service', function () {
    Mail::fake();
    Http::fake(['*' => Http::response(['success' => true], 200)]);

    $service = cancelledService();

    $this->artisan('pnlcs:process-cancellations')->assertSuccessful();

    expect($service->fresh()->status)->toBe('cancelled')
        ->and($service->fresh()->termination_date)->not->toBeNull();

    Http::assertSentCount(1);
});

test('a termination the server refused leaves the service open and queues a retry', function () {
    Mail::fake();
    Http::fake(['*' => Http::response(['error' => 'server unreachable'], 500)]);

    $service = cancelledService();

    $this->artisan('pnlcs:process-cancellations')->assertSuccessful();

    // Still the customer's service: the account is alive on the server.
    expect($service->fresh()->status)->toBe('active')
        ->and(ModuleQueue::where('service_id', $service->id)->where('action', 'terminate')->exists())->toBeTrue();
});

test('a service with no server module is closed locally', function () {
    Mail::fake();

    $service = cancelledService(['server_id' => null]);

    $this->artisan('pnlcs:process-cancellations')->assertSuccessful();

    expect($service->fresh()->status)->toBe('cancelled');
});
