<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;

/*
 * Attaching an existing service to a client by hand — the migration path.
 *
 * An operator moving customers in from cPanel/WHMCS/manual setups needs to
 * record services that already run on a server so PNLCS bills their renewals.
 * The record must be created without an order and without provisioning: the
 * account already exists.
 */
function addServiceAdmin(): Admin
{
    $role = AdminRole::factory()->fullAdmin()->create();

    return Admin::factory()->create(['role_id' => $role->id]);
}

function addServiceProduct(): Product
{
    return Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'panelica',
    ]);
}

it('records an existing service against a client without an order', function () {
    $client = Client::factory()->create();
    $product = addServiceProduct();
    $server = Server::factory()->create();

    $this->actingAs(addServiceAdmin(), 'admin')
        ->post(route('admin.clients.services.store', $client), [
            'product_id' => $product->id,
            'server_id' => $server->id,
            'domain' => 'existing.example.com',
            'billing_cycle' => 'Annually',
            'amount' => '49.00',
            'next_due_date' => now()->addYear()->toDateString(),
            'status' => 'active',
        ])
        ->assertRedirect(route('admin.clients.show', ['client' => $client, 'tab' => 'services']));

    $service = Service::where('client_id', $client->id)->first();

    expect($service)->not->toBeNull()
        ->and($service->product_id)->toBe($product->id)
        ->and($service->server_id)->toBe($server->id)
        ->and($service->domain)->toBe('existing.example.com')
        ->and($service->billing_cycle)->toBe('Annually')
        ->and((float) $service->amount)->toBe(49.0)
        ->and($service->status)->toBe('active')
        ->and($service->order_id)->toBeNull(); // recorded, not ordered/provisioned
});

it('lets the operator record a $0 (free) service', function () {
    $client = Client::factory()->create();
    $product = addServiceProduct();

    $this->actingAs(addServiceAdmin(), 'admin')
        ->post(route('admin.clients.services.store', $client), [
            'product_id' => $product->id,
            'billing_cycle' => 'Monthly',
            'amount' => '0',
            'status' => 'active',
        ])
        ->assertRedirect();

    expect((float) Service::where('client_id', $client->id)->value('amount'))->toBe(0.0);
});

it('requires a product', function () {
    $client = Client::factory()->create();

    $this->actingAs(addServiceAdmin(), 'admin')
        ->post(route('admin.clients.services.store', $client), [
            'billing_cycle' => 'Monthly',
            'amount' => '10',
            'status' => 'active',
        ])
        ->assertSessionHasErrors('product_id');

    expect(Service::where('client_id', $client->id)->count())->toBe(0);
});
