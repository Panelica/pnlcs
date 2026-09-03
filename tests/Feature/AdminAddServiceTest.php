<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use App\Services\ProvisioningService;
use Illuminate\Support\Facades\Http;

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

it('provisions on the server only when the operator asks', function () {
    $client = Client::factory()->create();
    $product = addServiceProduct();
    $server = Server::factory()->create();

    $mock = Mockery::mock(ProvisioningService::class);
    $mock->shouldReceive('createAccount')->once()->andReturn(['success' => true]);
    app()->instance(ProvisioningService::class, $mock);

    $this->actingAs(addServiceAdmin(), 'admin')
        ->post(route('admin.clients.services.store', $client), [
            'product_id' => $product->id,
            'server_id' => $server->id,
            'billing_cycle' => 'Monthly',
            'amount' => '10',
            'status' => 'active',
            'provision' => '1',
        ])
        ->assertRedirect();

    // The record starts pending; createAccount() (mocked here) is what activates it.
    expect(Service::where('client_id', $client->id)->value('status'))->toBe('pending');
});

it('does not touch the server when provisioning is off', function () {
    $client = Client::factory()->create();
    $product = addServiceProduct();

    $mock = Mockery::mock(ProvisioningService::class);
    $mock->shouldReceive('createAccount')->never();
    app()->instance(ProvisioningService::class, $mock);

    $this->actingAs(addServiceAdmin(), 'admin')
        ->post(route('admin.clients.services.store', $client), [
            'product_id' => $product->id,
            'billing_cycle' => 'Monthly',
            'amount' => '10',
            'status' => 'active',
        ])
        ->assertRedirect();

    expect(Service::where('client_id', $client->id)->value('status'))->toBe('active');
});

it('needs a server before it can provision', function () {
    $client = Client::factory()->create();
    $product = addServiceProduct();

    $this->actingAs(addServiceAdmin(), 'admin')
        ->post(route('admin.clients.services.store', $client), [
            'product_id' => $product->id,
            'billing_cycle' => 'Monthly',
            'amount' => '10',
            'status' => 'active',
            'provision' => '1',
        ])
        ->assertSessionHasErrors('server_id');

    expect(Service::where('client_id', $client->id)->count())->toBe(0);
});

it('links a migrated service to an existing server account', function () {
    Http::fake(['*' => Http::response(['data' => []], 200)]);

    $client = Client::factory()->create();
    $product = addServiceProduct();
    $server = Server::factory()->create(['type' => 'panelica']);

    $this->actingAs(addServiceAdmin(), 'admin')
        ->post(route('admin.clients.services.store', $client), [
            'product_id' => $product->id,
            'server_id' => $server->id,
            'billing_cycle' => 'Monthly',
            'amount' => '10',
            'status' => 'active',
            'link_user_id' => 'panel-user-abc-123',
        ])
        ->assertRedirect();

    $service = Service::where('client_id', $client->id)->first();

    expect($service->module_data['panelica_user_id'] ?? null)->toBe('panel-user-abc-123')
        ->and($service->order_id)->toBeNull();
});

it('serves the existing-account picker for a server', function () {
    Http::fake([
        '*/v1/accounts' => Http::response(['data' => [
            ['id' => 'u1', 'username' => 'alice', 'email' => 'alice@example.com', 'role' => 'USER', 'status' => 'active'],
            ['id' => 'admin1', 'username' => 'root', 'role' => 'ADMIN', 'status' => 'active'],
        ]], 200),
    ]);

    $server = Server::factory()->create(['type' => 'panelica']);

    $res = $this->actingAs(addServiceAdmin(), 'admin')
        ->get(route('admin.clients.server-accounts', $server))
        ->assertOk()
        ->assertJsonStructure(['accounts' => [['id', 'username', 'email', 'status']]]);

    $accounts = $res->json('accounts');
    expect($accounts)->toHaveCount(1)->and($accounts[0]['username'])->toBe('alice');
});
