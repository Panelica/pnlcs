<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\ServerGroup;
use App\Models\Service;
use Modules\Servers\Panelica\PanelicaModule;

/**
 * Server & server-group management regressions:
 *  - storeServer used to validate only name/hostname/type, silently dropping
 *    credentials, IP, port and every other posted field.
 *  - updateServer wiped the stored (encrypted) password/access_hash whenever
 *    the field was left blank, despite the form promising "leave blank to keep".
 *  - storeServerGroup validated fill_type against fill/round_robin while the
 *    form posted sequential/roundrobin, so groups could never be created.
 *  - Nothing ever attached servers to groups, and provisioning ignored the
 *    product's server group entirely (always first active server of the type).
 */
function serverAdmin(): Admin
{
    return Admin::factory()->create();
}

function makeService(?Product $product = null, array $attrs = []): Service
{
    $client = Client::factory()->create();
    $order = Order::factory()->create(['client_id' => $client->id]);

    return Service::factory()->create(array_merge([
        'client_id' => $client->id,
        'order_id' => $order->id,
        'product_id' => $product?->id,
        'server_id' => null,
        'status' => 'pending',
    ], $attrs));
}

function panelicaProduct(?ServerGroup $group = null): Product
{
    return Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'panelica',
        'server_group_id' => $group?->id,
    ]);
}

function resolveServerFor(Service $service): ?Server
{
    $module = app(PanelicaModule::class);
    $method = new ReflectionMethod($module, 'getServer');

    return $method->invoke($module, $service->fresh()->load('product', 'server'));
}

// ---------------------------------------------------------------------------
// storeServer
// ---------------------------------------------------------------------------

test('storeServer persists every posted field including encrypted credentials', function () {
    $this->actingAs(serverAdmin(), 'admin')
        ->post(route('admin.config.servers.store'), [
            'name' => 'Node A',
            'hostname' => 'node-a.example.com',
            'ip_address' => '203.0.113.10',
            'type' => 'panelica',
            'port' => 8443,
            'username' => 'apiuser',
            'password' => 'super-secret',
            'access_hash' => 'hash-material',
            'max_accounts' => 250,
            'nameserver1' => 'ns1.example.com',
            'nameserver2' => 'ns2.example.com',
            'active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

    $server = Server::where('name', 'Node A')->firstOrFail();
    expect($server->hostname)->toBe('node-a.example.com')
        ->and($server->ip_address)->toBe('203.0.113.10')
        ->and($server->port)->toBe(8443)
        ->and($server->username)->toBe('apiuser')
        ->and($server->password)->toBe('super-secret')
        ->and($server->access_hash)->toBe('hash-material')
        ->and($server->max_accounts)->toBe(250)
        ->and($server->nameserver1)->toBe('ns1.example.com')
        ->and($server->active)->toBeTrue();
    // Stored encrypted, not plaintext.
    expect($server->getRawOriginal('password'))->not->toBe('super-secret');
});

test('storeServer with unchecked active checkbox creates an inactive server', function () {
    $this->actingAs(serverAdmin(), 'admin')
        ->post(route('admin.config.servers.store'), [
            'name' => 'Node B',
            'hostname' => 'node-b.example.com',
            'type' => 'panelica',
        ])->assertRedirect()->assertSessionHasNoErrors();

    expect(Server::where('name', 'Node B')->firstOrFail()->active)->toBeFalse();
});

// ---------------------------------------------------------------------------
// updateServer — blank credentials must be preserved
// ---------------------------------------------------------------------------

test('updateServer keeps stored password and access_hash when fields are blank', function () {
    $server = Server::factory()->create(['password' => 'keep-me', 'access_hash' => 'keep-hash']);

    $this->actingAs(serverAdmin(), 'admin')
        ->put(route('admin.config.servers.update', $server), [
            'name' => $server->name,
            'hostname' => $server->hostname,
            'password' => '',
            'access_hash' => '',
            'max_accounts' => 99,
            'active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

    $server->refresh();
    expect($server->password)->toBe('keep-me')
        ->and($server->access_hash)->toBe('keep-hash')
        ->and($server->max_accounts)->toBe(99);
});

test('updateServer replaces credentials when new values are supplied', function () {
    $server = Server::factory()->create(['password' => 'old-pass', 'access_hash' => 'old-hash']);

    $this->actingAs(serverAdmin(), 'admin')
        ->put(route('admin.config.servers.update', $server), [
            'name' => $server->name,
            'hostname' => $server->hostname,
            'password' => 'new-pass',
            'access_hash' => 'new-hash',
            'active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

    $server->refresh();
    expect($server->password)->toBe('new-pass')
        ->and($server->access_hash)->toBe('new-hash');
});

test('updateServer can deactivate a server via unchecked checkbox', function () {
    $server = Server::factory()->create(['active' => true]);

    $this->actingAs(serverAdmin(), 'admin')
        ->put(route('admin.config.servers.update', $server), [
            'name' => $server->name,
            'hostname' => $server->hostname,
        ])->assertRedirect()->assertSessionHasNoErrors();

    expect($server->refresh()->active)->toBeFalse();
});

// ---------------------------------------------------------------------------
// Server groups — creatable from the UI vocabulary, servers attachable
// ---------------------------------------------------------------------------

test('storeServerGroup accepts both fill strategies and syncs member servers', function () {
    $servers = Server::factory()->count(2)->create();

    $this->actingAs(serverAdmin(), 'admin')
        ->post(route('admin.config.server-groups.store'), [
            'name' => 'Shared Pool',
            'fill_type' => 'round_robin',
            'server_ids' => $servers->pluck('id')->all(),
        ])->assertRedirect()->assertSessionHasNoErrors();

    $group = ServerGroup::where('name', 'Shared Pool')->firstOrFail();
    expect($group->fill_type)->toBe('round_robin')
        ->and($group->servers()->pluck('servers.id')->all())
        ->toEqualCanonicalizing($servers->pluck('id')->all());
});

test('storeServerGroup rejects the legacy sequential/roundrobin vocabulary', function () {
    $this->actingAs(serverAdmin(), 'admin')
        ->from(route('admin.config.server-groups'))
        ->post(route('admin.config.server-groups.store'), [
            'name' => 'Broken Group',
            'fill_type' => 'sequential',
        ])->assertRedirect(route('admin.config.server-groups'))
        ->assertSessionHasErrors('fill_type');

    expect(ServerGroup::where('name', 'Broken Group')->exists())->toBeFalse();
});

test('updateServerGroup replaces the member server set', function () {
    $group = ServerGroup::factory()->create(['fill_type' => 'fill']);
    [$a, $b, $c] = Server::factory()->count(3)->create();
    $group->servers()->sync([$a->id, $b->id]);

    $this->actingAs(serverAdmin(), 'admin')
        ->put(route('admin.config.server-groups.update', $group), [
            'name' => $group->name,
            'fill_type' => 'fill',
            'server_ids' => [$c->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

    expect($group->servers()->pluck('servers.id')->all())->toBe([$c->id]);
});

// ---------------------------------------------------------------------------
// Provisioning server selection
// ---------------------------------------------------------------------------

test('fill strategy loads the lowest server until capacity, then overflows', function () {
    $group = ServerGroup::factory()->create(['fill_type' => 'fill']);
    $first = Server::factory()->create(['type' => 'panelica', 'max_accounts' => 2, 'active' => true, 'disabled' => false]);
    $second = Server::factory()->create(['type' => 'panelica', 'max_accounts' => 2, 'active' => true, 'disabled' => false]);
    $group->servers()->sync([$first->id, $second->id]);
    $product = panelicaProduct($group);

    // One occupied slot on the first server — still has capacity.
    makeService($product, ['server_id' => $first->id, 'status' => 'active']);
    expect(resolveServerFor(makeService($product))->id)->toBe($first->id);

    // Fill the first server completely — selection must overflow to the second.
    makeService($product, ['server_id' => $first->id, 'status' => 'suspended']);
    expect(resolveServerFor(makeService($product))->id)->toBe($second->id);
});

test('round robin strategy picks the least loaded server', function () {
    $group = ServerGroup::factory()->create(['fill_type' => 'round_robin']);
    $busy = Server::factory()->create(['type' => 'panelica', 'max_accounts' => 0, 'active' => true, 'disabled' => false]);
    $idle = Server::factory()->create(['type' => 'panelica', 'max_accounts' => 0, 'active' => true, 'disabled' => false]);
    $group->servers()->sync([$busy->id, $idle->id]);
    $product = panelicaProduct($group);

    makeService($product, ['server_id' => $busy->id, 'status' => 'active']);
    makeService($product, ['server_id' => $busy->id, 'status' => 'active']);

    expect(resolveServerFor(makeService($product))->id)->toBe($idle->id);
});

test('an exhausted group fails selection instead of overflowing outside the group', function () {
    $group = ServerGroup::factory()->create(['fill_type' => 'fill']);
    $only = Server::factory()->create(['type' => 'panelica', 'max_accounts' => 1, 'active' => true, 'disabled' => false]);
    $group->servers()->sync([$only->id]);
    // An active server of the right type OUTSIDE the group must not be used.
    Server::factory()->create(['type' => 'panelica', 'max_accounts' => 0, 'active' => true, 'disabled' => false]);
    $product = panelicaProduct($group);

    makeService($product, ['server_id' => $only->id, 'status' => 'active']);

    expect(resolveServerFor(makeService($product)))->toBeNull();
});

test('selection skips inactive and wrong-type servers inside the group', function () {
    $group = ServerGroup::factory()->create(['fill_type' => 'fill']);
    $inactive = Server::factory()->create(['type' => 'panelica', 'active' => false, 'disabled' => false]);
    $wrongType = Server::factory()->create(['type' => 'cpanel', 'active' => true, 'disabled' => false]);
    $good = Server::factory()->create(['type' => 'panelica', 'max_accounts' => 0, 'active' => true, 'disabled' => false]);
    $group->servers()->sync([$inactive->id, $wrongType->id, $good->id]);

    expect(resolveServerFor(makeService(panelicaProduct($group)))->id)->toBe($good->id);
});

test('the chosen server is persisted on the service for later lifecycle calls', function () {
    $group = ServerGroup::factory()->create(['fill_type' => 'fill']);
    $server = Server::factory()->create(['type' => 'panelica', 'max_accounts' => 0, 'active' => true, 'disabled' => false]);
    $group->servers()->sync([$server->id]);

    $service = makeService(panelicaProduct($group));
    resolveServerFor($service);

    expect($service->fresh()->server_id)->toBe($server->id);
});

test('products without a group still fall back to the first active server of the type', function () {
    $server = Server::factory()->create(['type' => 'panelica', 'active' => true, 'disabled' => false]);

    $service = makeService(panelicaProduct());

    expect(resolveServerFor($service)->id)->toBe($server->id)
        ->and($service->fresh()->server_id)->toBe($server->id);
});
