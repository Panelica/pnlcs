<?php

use App\Models\Client;
use App\Models\ClientGroup;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ModuleQueue;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

/**
 * pnlcs:auto-terminate deletes customer data, so every guard rail gets its own
 * test: the switch ships off, the day counter is respected, paying the debt
 * takes a service off the list, exempt groups and pending cancellations are
 * left alone, and dry-run changes nothing.
 */
function suspendedService(array $serviceAttrs = [], ?Client $client = null, bool $withDebt = true): Service
{
    $client ??= Client::factory()->create();
    $server = Server::factory()->create(['type' => 'panelica', 'hostname' => 'srv.test', 'active' => true]);
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'panelica',
    ]);

    $service = Service::factory()->create(array_merge([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'status' => 'suspended',
        'suspension_date' => now()->subDays(45),
        'suspension_reason' => 'Overdue Invoice - Automatic Suspension',
        'domain' => 'longsuspended-example.com',
        'username' => 'lsuser',
        'notes' => json_encode(['panelica_user_id' => 4242]),
    ], $serviceAttrs));

    if ($withDebt) {
        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'status' => 'overdue',
            'due_date' => now()->subDays(50),
            'total' => 50,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'client_id' => $client->id,
            'type' => 'Hosting', 'rel_id' => $service->id,
            'description' => 'Hosting', 'amount' => 50, 'taxed' => false, 'due_date' => now()->subDays(50),
        ]);
    }

    return $service;
}

function enableAutoTermination(int $days = 30): void
{
    Setting::set('AutoTerminationEnabled', '1');
    Setting::set('AutoTerminationDays', (string) $days);
}

test('termination is off by default and touches nothing', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    $service = suspendedService();

    $this->artisan('pnlcs:auto-terminate')
        ->expectsOutputToContain('disabled')
        ->assertSuccessful();

    expect($service->fresh()->status)->toBe('suspended');
    Http::assertNothingSent();
});

test('a long-suspended service with unpaid debt is terminated on the server', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    enableAutoTermination(30);
    $service = suspendedService();

    $this->artisan('pnlcs:auto-terminate')->assertSuccessful();

    expect($service->fresh()->status)->toBe('terminated')
        ->and($service->fresh()->termination_date)->not->toBeNull();
    Http::assertSentCount(1);
});

test('a service suspended for fewer days than the setting is left alone', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    enableAutoTermination(30);
    $service = suspendedService(['suspension_date' => now()->subDays(10)]);

    $this->artisan('pnlcs:auto-terminate')->assertSuccessful();

    expect($service->fresh()->status)->toBe('suspended');
    Http::assertNothingSent();
});

test('the day counter comes from the setting, not a constant', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    enableAutoTermination(60);
    $service = suspendedService(['suspension_date' => now()->subDays(45)]);

    $this->artisan('pnlcs:auto-terminate')->assertSuccessful();

    expect($service->fresh()->status)->toBe('suspended');
});

test('a suspended service whose debt has been paid is never terminated', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    enableAutoTermination(30);
    $service = suspendedService(withDebt: false);

    $this->artisan('pnlcs:auto-terminate')->assertSuccessful();

    expect($service->fresh()->status)->toBe('suspended');
    Http::assertNothingSent();
});

test('a client in a terminate_exempt group is never terminated', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    enableAutoTermination(30);
    $group = ClientGroup::create(['name' => 'Protected', 'terminate_exempt' => true]);
    $client = Client::factory()->create(['group_id' => $group->id]);
    $service = suspendedService([], $client);

    $this->artisan('pnlcs:auto-terminate')->assertSuccessful();

    expect($service->fresh()->status)->toBe('suspended');
    Http::assertNothingSent();
});

test('a service with a pending cancellation request is left to process-cancellations', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    enableAutoTermination(30);
    $service = suspendedService();
    $service->cancellationRequest()->create([
        'reason' => 'moving away',
        'type' => 'end_of_billing',
    ]);

    $this->artisan('pnlcs:auto-terminate')->assertSuccessful();

    expect($service->fresh()->status)->toBe('suspended');
    Http::assertNothingSent();
});

test('dry-run lists the service and changes nothing', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    enableAutoTermination(30);
    $service = suspendedService();

    $this->artisan('pnlcs:auto-terminate', ['--dry-run' => true])
        ->expectsOutputToContain("would terminate service #{$service->id}")
        ->assertSuccessful();

    expect($service->fresh()->status)->toBe('suspended');
    Http::assertNothingSent();
});

test('a failing module terminate leaves the service suspended and queues a retry', function () {
    Http::fake(['*' => Http::response(['error' => 'boom'], 500)]);
    enableAutoTermination(30);
    $service = suspendedService();

    $this->artisan('pnlcs:auto-terminate')->assertSuccessful();

    expect($service->fresh()->status)->toBe('suspended')
        ->and(ModuleQueue::where('service_id', $service->id)->where('action', 'terminate')->exists())->toBeTrue();
});

test('a manually suspended service without a suspension date is never picked up', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    enableAutoTermination(30);
    $service = suspendedService(['suspension_date' => null]);

    $this->artisan('pnlcs:auto-terminate')->assertSuccessful();

    expect($service->fresh()->status)->toBe('suspended');
    Http::assertNothingSent();
});

test('services without a server module are still marked terminated locally', function () {
    enableAutoTermination(30);
    $client = Client::factory()->create();
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => null,
    ]);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id,
        'server_id' => null, 'status' => 'suspended',
        'suspension_date' => now()->subDays(45),
    ]);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id, 'status' => 'overdue',
        'due_date' => now()->subDays(50), 'total' => 25,
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id, 'client_id' => $client->id,
        'type' => 'Hosting', 'rel_id' => $service->id,
        'description' => 'x', 'amount' => 25, 'taxed' => false, 'due_date' => now()->subDays(50),
    ]);

    $this->artisan('pnlcs:auto-terminate')->assertSuccessful();

    expect($service->fresh()->status)->toBe('terminated')
        ->and($service->fresh()->termination_date)->not->toBeNull();
});
