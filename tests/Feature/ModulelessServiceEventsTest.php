<?php

use App\Events\ServiceActivated;
use App\Events\ServiceSuspended;
use App\Events\ServiceTerminated;
use App\Mail\ServiceTerminationMail;
use App\Mail\ServiceWelcomeMail;
use App\Models\CancellationRequest;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Service;
use App\Models\Setting;
use App\Services\OrderService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/*
 * A product sold without a server module - a licence, a script, a download -
 * went active, was suspended and was ended with only its status changed.
 * The events a module's create, suspend and terminate raise were never
 * raised, so the customer got no welcome mail, the ServiceActivated /
 * ServiceSuspended / ServiceTerminated hooks never ran (an addon could not
 * hand out or withdraw a licence key), and nothing was logged.
 */

function mlService(array $attrs = []): Service
{
    $product = Product::factory()->create(['type' => 'other', 'server_type' => null, 'tax' => false, 'auto_setup' => 'payment']);

    return Service::factory()->create(array_merge([
        'client_id' => Client::factory()->create(['tax_exempt' => true])->id,
        'product_id' => $product->id, 'server_id' => null, 'status' => 'active', 'domain' => 'licence-1',
    ], $attrs));
}

test('a product without a server module raises ServiceActivated when its order is accepted, and the customer is welcomed', function () {
    Mail::fake();
    $client = Client::factory()->create(['tax_exempt' => true]);
    $product = Product::factory()->create(['type' => 'other', 'server_type' => null, 'tax' => false, 'auto_setup' => 'payment']);

    $order = app(OrderService::class)->processOrder($client, [[
        'type' => 'service', 'product_id' => $product->id, 'domain' => 'licence-2', 'amount' => 49, 'billing_cycle' => 'Annually',
    ]], 'banktransfer');

    Event::fake([ServiceActivated::class]);
    app(OrderService::class)->acceptOrder($order->fresh(), manual: true);
    $service = Service::where('order_id', $order->id)->first();

    expect($service->status)->toBe('active');
    Event::assertDispatched(ServiceActivated::class, fn ($e) => $e->service->id === $service->id);
});

test('the welcome mail goes out for it', function () {
    Mail::fake();
    $client = Client::factory()->create(['tax_exempt' => true]);
    $product = Product::factory()->create(['type' => 'other', 'server_type' => null, 'tax' => false, 'auto_setup' => 'payment']);

    $order = app(OrderService::class)->processOrder($client, [[
        'type' => 'service', 'product_id' => $product->id, 'domain' => 'licence-3', 'amount' => 49, 'billing_cycle' => 'Annually',
    ]], 'banktransfer');
    app(OrderService::class)->acceptOrder($order->fresh(), manual: true);

    Mail::assertQueued(ServiceWelcomeMail::class, 1);
});

test('a certificate waiting for its CSR is not welcomed as ready', function () {
    $client = Client::factory()->create(['tax_exempt' => true]);
    $product = Product::factory()->create(['type' => 'ssl', 'server_type' => null, 'tax' => false, 'auto_setup' => 'payment']);

    $order = app(OrderService::class)->processOrder($client, [[
        'type' => 'service', 'product_id' => $product->id, 'domain' => 'secure.example.test', 'amount' => 9, 'billing_cycle' => 'Annually',
    ]], 'banktransfer');

    Event::fake([ServiceActivated::class]);
    app(OrderService::class)->acceptOrder($order->fresh(), manual: true);

    Event::assertNotDispatched(ServiceActivated::class);
});

test('suspending it for an overdue invoice raises ServiceSuspended', function () {
    Setting::set('AutoSuspensionDays', '3');
    $service = mlService();
    Invoice::factory()->create(['client_id' => $service->client_id, 'status' => 'overdue', 'due_date' => now()->subDays(20), 'total' => 49]);

    Event::fake([ServiceSuspended::class]);
    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();

    expect($service->fresh()->status)->toBe('suspended');
    Event::assertDispatched(ServiceSuspended::class, fn ($e) => $e->service->id === $service->id);
});

test('ending it raises ServiceTerminated', function () {
    Setting::set('AutoTerminationEnabled', '1');
    Setting::set('AutoTerminationDays', '30');
    $service = mlService(['status' => 'suspended', 'suspension_date' => now()->subDays(45)]);
    Invoice::factory()->create(['client_id' => $service->client_id, 'status' => 'overdue', 'due_date' => now()->subDays(50), 'total' => 49]);

    Event::fake([ServiceTerminated::class]);
    $this->artisan('pnlcs:auto-terminate')->assertSuccessful();

    expect($service->fresh()->status)->toBe('terminated');
    Event::assertDispatched(ServiceTerminated::class, fn ($e) => $e->service->id === $service->id);
});

test('a cancellation raises ServiceTerminated once and the customer gets one mail', function () {
    Mail::fake();
    $service = mlService(['next_due_date' => now()->subDay()]);
    CancellationRequest::create(['service_id' => $service->id, 'type' => 'immediate', 'reason' => 'No longer needed']);

    $this->artisan('pnlcs:process-cancellations')->assertSuccessful();

    expect($service->fresh()->status)->toBe('cancelled');
    Mail::assertQueued(ServiceTerminationMail::class, 1);
});

test('a cancellation on a server sends the customer one mail, not two', function () {
    Mail::fake();
    \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['success' => true], 200)]);
    $server = \App\Models\Server::factory()->create(['type' => 'panelica', 'hostname' => 'srv.test', 'active' => true]);
    $product = Product::factory()->create(['server_type' => 'panelica']);
    $service = Service::factory()->create([
        'client_id' => Client::factory()->create()->id, 'product_id' => $product->id, 'server_id' => $server->id,
        'status' => 'active', 'domain' => 'leaving-example.com', 'username' => 'leaving', 'next_due_date' => now()->subDay(),
        'notes' => json_encode(['panelica_user_id' => 910]),
    ]);
    CancellationRequest::create(['service_id' => $service->id, 'type' => 'immediate', 'reason' => 'Moving elsewhere']);

    $this->artisan('pnlcs:process-cancellations')->assertSuccessful();

    expect($service->fresh()->status)->toBe('cancelled');
    Mail::assertQueued(ServiceTerminationMail::class, 1);
});
