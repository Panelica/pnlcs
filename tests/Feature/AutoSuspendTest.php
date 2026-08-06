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
use Illuminate\Support\Facades\Http;

/**
 * pnlcs:auto-suspend used to flip the local status only: it never called the
 * server module, so an overdue customer stayed fully live on cPanel/Plesk/
 * Panelica while the panel claimed the service was suspended. Its sibling
 * commands (unsuspend-on-payment, process-cancellations) did call their
 * modules — only suspend was missing. The client and client-group suspension
 * exemption flags were also never read by anything.
 */
function overdueService(array $serviceAttrs = [], ?Client $client = null): Service
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
        'status' => 'active',
        'domain' => 'overdue-example.com',
        'username' => 'ovuser',
        'override_auto_suspend_date' => null,
        // Module data lives in notes; the Panelica module needs the remote id.
        'notes' => json_encode(['panelica_user_id' => 4242]),
    ], $serviceAttrs));

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'overdue',
        'due_date' => now()->subDays(10),
        'total' => 50,
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id, 'client_id' => $client->id,
        'type' => 'Hosting', 'rel_id' => $service->id,
        'description' => 'Hosting', 'amount' => 50, 'taxed' => false, 'due_date' => now()->subDays(10),
    ]);

    return $service;
}

test('an overdue service is suspended on the server, not just in the database', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    $service = overdueService();

    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();

    expect($service->fresh()->status)->toBe('suspended')
        ->and($service->fresh()->suspension_date)->not->toBeNull();

    // The module was actually contacted — the whole point of the fix.
    Http::assertSentCount(1);
});

test('a failing module suspend leaves the service active and queues a retry', function () {
    Http::fake(['*' => Http::response(['error' => 'boom'], 500)]);
    $service = overdueService();

    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();

    expect($service->fresh()->status)->toBe('active')
        ->and(ModuleQueue::where('service_id', $service->id)->where('action', 'suspend')->exists())->toBeTrue();
});

test('a client marked override_auto_suspend is never suspended', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    $client = Client::factory()->create(['override_auto_suspend' => true]);
    $service = overdueService([], $client);

    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();

    expect($service->fresh()->status)->toBe('active');
    Http::assertNothingSent();
});

test('a client in a suspend_exempt group is never suspended', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    $group = ClientGroup::create(['name' => 'VIP', 'suspend_exempt' => true]);
    $client = Client::factory()->create(['group_id' => $group->id]);
    $service = overdueService([], $client);

    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();

    expect($service->fresh()->status)->toBe('active');
    Http::assertNothingSent();
});

test('a service with an override date is skipped', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    $service = overdueService(['override_auto_suspend_date' => now()->addMonth()]);

    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();

    expect($service->fresh()->status)->toBe('active');
});

test('services without a server module are still marked suspended locally', function () {
    $client = Client::factory()->create();
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => null,
    ]);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id,
        'server_id' => null, 'status' => 'active', 'override_auto_suspend_date' => null,
    ]);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id, 'status' => 'overdue',
        'due_date' => now()->subDays(10), 'total' => 25,
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id, 'client_id' => $client->id,
        'type' => 'Hosting', 'rel_id' => $service->id,
        'description' => 'x', 'amount' => 25, 'taxed' => false, 'due_date' => now()->subDays(10),
    ]);

    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();

    expect($service->fresh()->status)->toBe('suspended');
});

test('a service inside the grace period is not suspended yet', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    $client = Client::factory()->create();
    $service = overdueService([], $client);
    Invoice::where('client_id', $client->id)->update(['due_date' => now()->subDay()]);

    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();

    expect($service->fresh()->status)->toBe('active');
});

test('dry run reports without changing anything', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    $service = overdueService();

    $this->artisan('pnlcs:auto-suspend', ['--dry-run' => true])->assertSuccessful();

    expect($service->fresh()->status)->toBe('active');
    Http::assertNothingSent();
});

// A product can name a server module while the service itself was never put on
// a server. Resolving the module from the product alone is enough to reach it,
// and the module then picks a server by itself and stamps it on the service -
// suspending an account that was never created there, on a box the customer
// has nothing to do with.
test('an overdue service that was never provisioned is not sent to somebody elses server', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);

    $client = Client::factory()->create();
    $server = Server::factory()->create(['type' => 'panelica', 'hostname' => 'unrelated.test', 'active' => true]);
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'panelica',
    ]);
    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'server_id' => null,
        'status' => 'active',
        'override_auto_suspend_date' => null,
    ]);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id, 'status' => 'overdue',
        'due_date' => now()->subDays(10), 'total' => 25,
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id, 'client_id' => $client->id,
        'type' => 'Hosting', 'rel_id' => $service->id,
        'description' => 'x', 'amount' => 25, 'taxed' => false, 'due_date' => now()->subDays(10),
    ]);

    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();

    Http::assertNothingSent();

    expect($service->fresh()->server_id)->toBeNull()
        ->and(strtolower($service->fresh()->status))->toBe('suspended');
});

// ---------------------------------------------------------------------------
// A hold with a date on it
// ---------------------------------------------------------------------------

/**
 * "Do not suspend until" is a date, not a switch.
 *
 * The command reads override_auto_suspend_date with whereNull, so a service
 * carrying any date at all is passed over - for ever. A hold put on until the
 * end of the month goes on protecting the account next year, and the customer
 * can stop paying without anything happening.
 *
 * The column is not on any screen today, so nothing sets it by hand; an import
 * or a support script that does would get a permanent exemption out of what
 * reads like a temporary one.
 */
test('a hold that has run out does not stop the suspension', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    $service = overdueService(['override_auto_suspend_date' => now()->subDays(5)->toDateString()]);

    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();

    expect($service->fresh()->status)->toBe('suspended');
});

test('a hold that is still running does stop it', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    $service = overdueService(['override_auto_suspend_date' => now()->addDays(5)->toDateString()]);

    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();

    expect($service->fresh()->status)->toBe('active');
    Http::assertNothingSent();
});

test('a hold that ends today still holds', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    $service = overdueService(['override_auto_suspend_date' => now()->toDateString()]);

    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();

    expect($service->fresh()->status)->toBe('active');
});
