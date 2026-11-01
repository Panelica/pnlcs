<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use App\Models\SslOrder;
use Illuminate\Support\Facades\Http;

/*
 * Three staff actions that reach past the screen they sit on: a certificate's
 * private key, a server account taken over from a client's page, and the
 * address customers' install commands come from. Each needs the permission
 * for what it touches.
 */

function srStaff(array $permissions): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'is_full_admin' => false, 'permissions' => $permissions])->id,
    ]);
}

function srAddService(Admin $staff, Client $client, Server $server, array $more = [])
{
    return test()->actingAs($staff, 'admin')->post(route('admin.clients.services.store', $client), $more + [
        'product_id' => Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'server_type' => 'panelica'])->id,
        'server_id' => $server->id,
        'billing_cycle' => 'Monthly',
        'amount' => '10',
        'status' => 'active',
    ]);
}

// ----------------------------------------------------------------- SSL key ---

test('staff who may only view services cannot download a certificate with its key', function () {
    $order = SslOrder::create(['client_id' => Client::factory()->create()->id, 'module' => 'test', 'status' => 'Completed']);

    $this->actingAs(srStaff(['view_services', 'list_services']), 'admin')
        ->get(route('admin.ssl.download', $order))
        ->assertForbidden();
});

test('the order page still opens for them, without the download button', function () {
    $order = SslOrder::create(['client_id' => Client::factory()->create()->id, 'module' => 'test', 'status' => 'Completed', 'cert' => 'CERT']);
    $download = route('admin.ssl.download', $order);

    expect($this->actingAs(srStaff(['view_services', 'list_services']), 'admin')->get(route('admin.ssl.show', $order))->assertOk()->getContent())
        ->not->toContain($download)
        ->and($this->actingAs(srStaff(['view_services', 'manage_services']), 'admin')->get(route('admin.ssl.show', $order))->assertOk()->getContent())
        ->toContain($download);
});

// --------------------------------------------------- service added by hand ---

test('staff who edit clients cannot take over an existing server account', function () {
    Http::fake(['*' => Http::response(['data' => []], 200)]);
    $client = Client::factory()->create();

    srAddService(srStaff(['edit_clients', 'view_clients']), $client, Server::factory()->create(['type' => 'panelica']), ['link_user_id' => 'someone-else'])
        ->assertForbidden();

    expect(Service::where('client_id', $client->id)->count())->toBe(0);
});

test('nor build an account on a server', function () {
    $client = Client::factory()->create();

    srAddService(srStaff(['edit_clients', 'view_clients']), $client, Server::factory()->create(['type' => 'panelica']), ['provision' => '1'])
        ->assertForbidden();

    expect(Service::where('client_id', $client->id)->count())->toBe(0);
});

test('they can still record a service that needs no server action', function () {
    $client = Client::factory()->create();

    srAddService(srStaff(['edit_clients', 'view_clients']), $client, Server::factory()->create(['type' => 'panelica']))
        ->assertRedirect();

    expect(Service::where('client_id', $client->id)->count())->toBe(1);
});

test('a panel account another live service manages is not linked twice', function () {
    Http::fake(['*' => Http::response(['data' => []], 200)]);
    $server = Server::factory()->create(['type' => 'panelica']);
    $owner = Service::factory()->create(['server_id' => $server->id, 'status' => 'active', 'module_data' => ['panelica_user_id' => 'u-1']]);
    $client = Client::factory()->create();
    $staff = srStaff(['edit_clients', 'view_clients', 'manage_services']);

    srAddService($staff, $client, $server, ['link_user_id' => 'u-1'])
        ->assertSessionHas('error', __('admin.clients.link_taken', ['service' => $owner->id]));
    expect(Service::where('client_id', $client->id)->count())->toBe(0);

    srAddService($staff, $client, $server, ['link_user_id' => 'u-2'])->assertSessionHas('success');
    expect(Service::where('client_id', $client->id)->first()->module_data['panelica_user_id'])->toBe('u-2');
});

test('one whose service has ended can be linked again', function () {
    Http::fake(['*' => Http::response(['data' => []], 200)]);
    $server = Server::factory()->create(['type' => 'panelica']);
    Service::factory()->create(['server_id' => $server->id, 'status' => 'terminated', 'module_data' => ['panelica_user_id' => 'u-1']]);
    $client = Client::factory()->create();

    srAddService(srStaff(['edit_clients', 'view_clients', 'manage_services']), $client, $server, ['link_user_id' => 'u-1'])
        ->assertSessionHas('success');
});

// ------------------------------------------------- Panelica partner account ---

test('staff who manage products cannot change the partner key, the Central address or the install link', function () {
    $staff = srStaff(['manage_products', 'list_products']);

    $this->actingAs($staff, 'admin')->post(route('admin.panelica-licenses.connect'), ['api_key' => 'rk_x', 'central_url' => 'https://elsewhere.example'])->assertForbidden();
    $this->actingAs($staff, 'admin')->post(route('admin.panelica-licenses.install-code'), ['code' => 'mine'])->assertForbidden();
    $this->actingAs($staff, 'admin')->post(route('admin.panelica-licenses.disconnect'))->assertForbidden();
});

test('staff who manage servers reach them', function () {
    $response = $this->actingAs(srStaff(['manage_servers']), 'admin')
        ->post(route('admin.panelica-licenses.connect'), ['api_key' => 'not-a-partner-key']);

    expect($response->status())->not->toBe(403);
});
