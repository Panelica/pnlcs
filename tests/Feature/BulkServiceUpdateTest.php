<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\ModuleQueue;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use App\Models\ServiceAddon;
use Illuminate\Support\Facades\Http;

/**
 * Changing a batch of services from the admin screen.
 *
 * The update ran straight through the query builder, which does not fire model
 * events, so everything that hangs off a service changing state was skipped.
 * Ending twenty accounts in one go left twenty addons active and billing.
 */
function bulkServices(int $count, string $status = 'active'): array
{
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id]);
    $addonProduct = ProductAddon::create([
        'name' => 'Dedicated IP', 'packages' => null,
        'hidden' => false, 'retired' => false, 'sort_order' => 1, 'tax' => false,
    ]);

    $services = [];

    for ($i = 0; $i < $count; $i++) {
        $client = Client::factory()->create();
        $service = Service::factory()->create([
            'client_id' => $client->id,
            'product_id' => $product->id,
            'status' => $status,
            'amount' => 20,
            'billing_cycle' => 'Monthly',
            'next_due_date' => now()->addMonth(),
            'domain' => "bulk{$i}.com",
        ]);

        ServiceAddon::create([
            'service_id' => $service->id,
            'addon_id' => $addonProduct->id,
            'client_id' => $client->id,
            'qty' => 1,
            'amount' => 5,
            'billing_cycle' => 'Monthly',
            'next_due_date' => now()->addMonth(),
            'status' => 'active',
        ]);

        $services[] = $service;
    }

    return $services;
}

test('ending services in bulk stops their addons too', function () {
    $admin = Admin::factory()->create();
    $services = bulkServices(3);

    $this->actingAs($admin, 'admin')->post(route('admin.bulk.service-update'), [
        'service_ids' => collect($services)->pluck('id')->all(),
        'status' => 'terminated',
    ])->assertRedirect();

    foreach ($services as $service) {
        expect($service->fresh()->status)->toBe('terminated');
    }

    $stillBilling = ServiceAddon::whereIn('service_id', collect($services)->pluck('id'))
        ->where('status', 'active')
        ->count();

    expect($stillBilling)->toBe(0);
});

test('a bulk termination records when it happened', function () {
    $admin = Admin::factory()->create();
    $services = bulkServices(2);

    $this->actingAs($admin, 'admin')->post(route('admin.bulk.service-update'), [
        'service_ids' => collect($services)->pluck('id')->all(),
        'status' => 'terminated',
    ])->assertRedirect();

    foreach ($services as $service) {
        expect($service->fresh()->termination_date)->not->toBeNull();
    }
});

test('a bulk suspension records when it happened and leaves addons alone', function () {
    $admin = Admin::factory()->create();
    $services = bulkServices(2);

    $this->actingAs($admin, 'admin')->post(route('admin.bulk.service-update'), [
        'service_ids' => collect($services)->pluck('id')->all(),
        'status' => 'suspended',
    ])->assertRedirect();

    foreach ($services as $service) {
        expect($service->fresh()->status)->toBe('suspended')
            ->and($service->fresh()->suspension_date)->not->toBeNull();
    }

    // Suspension is usually non-payment; the extras are still owed for.
    expect(ServiceAddon::where('status', 'active')->count())->toBe(2);
});

/**
 * A service that actually lives on a panel.
 */
function bulkServerBackedService(string $status = 'active'): Service
{
    $server = Server::factory()->create([
        'type' => 'cpanel',
        'hostname' => 'whm.bulk.test',
        'access_hash' => 'token-123',
        'active' => true,
    ]);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'cpanel',
    ]);

    return Service::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'username' => 'bulkuser',
        'status' => $status,
        'domain' => 'bulk-server-backed.com',
    ]);
}

// The single-service screen suspends through the server module. This one wrote
// the status straight to the database, so the panel said "suspended" while the
// account carried on serving the site.
test('suspending in bulk tells the server', function () {
    Http::fake(['*' => Http::response(['metadata' => ['result' => 1, 'reason' => 'OK']], 200)]);

    $service = bulkServerBackedService();

    $this->actingAs(Admin::factory()->create(), 'admin')->post(route('admin.bulk.service-update'), [
        'service_ids' => [$service->id],
        'status' => 'suspended',
    ])->assertRedirect();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'suspendacct'));
    expect($service->fresh()->status)->toBe('suspended');
});

// If the panel refuses, the record cannot claim the account was suspended.
test('a bulk suspension the server refuses is not recorded as done', function () {
    Http::fake(['*' => Http::response(['metadata' => ['result' => 0, 'reason' => 'account not found']], 200)]);

    $service = bulkServerBackedService();

    $this->actingAs(Admin::factory()->create(), 'admin')->post(route('admin.bulk.service-update'), [
        'service_ids' => [$service->id],
        'status' => 'suspended',
    ])->assertRedirect();

    expect($service->fresh()->status)->toBe('active')
        ->and(ModuleQueue::where('service_id', $service->id)->count())->toBeGreaterThan(0);
});

// Cancelling is a billing decision, not a server one: no panel is called.
test('a bulk cancellation stays local', function () {
    Http::fake();

    $service = bulkServerBackedService();

    $this->actingAs(Admin::factory()->create(), 'admin')->post(route('admin.bulk.service-update'), [
        'service_ids' => [$service->id],
        'status' => 'cancelled',
    ])->assertRedirect();

    Http::assertNothingSent();
    expect($service->fresh()->status)->toBe('cancelled');
});
