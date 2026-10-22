<?php

use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Modules\Servers\Panelica\PanelicaModule;

/**
 * A product without a server group took the first active server of its type,
 * ignoring max_accounts and the disabled flag that a group honours, so a full
 * or disabled server kept receiving new accounts.
 */
function ungroupedService(array $attrs = []): Service
{
    $client = Client::factory()->create();
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id, 'server_type' => 'panelica', 'server_group_id' => null,
    ]);

    return Service::factory()->create(array_merge([
        'client_id' => $client->id, 'order_id' => Order::factory()->create(['client_id' => $client->id])->id,
        'product_id' => $product->id, 'server_id' => null, 'status' => 'pending',
    ], $attrs));
}

function pickServer(Service $service): ?Server
{
    $module = app(PanelicaModule::class);

    return (new ReflectionMethod($module, 'getServer'))->invoke($module, $service->fresh()->load('product', 'server'));
}

it('moves on to the next server when the first one is full', function () {
    $full = Server::factory()->create(['type' => 'panelica', 'max_accounts' => 1, 'active' => true, 'disabled' => false]);
    $next = Server::factory()->create(['type' => 'panelica', 'max_accounts' => 0, 'active' => true, 'disabled' => false]);
    ungroupedService(['server_id' => $full->id, 'status' => 'active']);

    expect(pickServer(ungroupedService())->id)->toBe($next->id);
});

it('skips a disabled server', function () {
    Server::factory()->create(['type' => 'panelica', 'max_accounts' => 0, 'active' => true, 'disabled' => true]);
    $open = Server::factory()->create(['type' => 'panelica', 'max_accounts' => 0, 'active' => true, 'disabled' => false]);

    expect(pickServer(ungroupedService())->id)->toBe($open->id);
});

it('finds no server when every server is full', function () {
    $only = Server::factory()->create(['type' => 'panelica', 'max_accounts' => 1, 'active' => true, 'disabled' => false]);
    ungroupedService(['server_id' => $only->id, 'status' => 'suspended']);

    expect(pickServer(ungroupedService()))->toBeNull();
});

it('still fills the lowest server first while it has room', function () {
    $first = Server::factory()->create(['type' => 'panelica', 'max_accounts' => 5, 'active' => true, 'disabled' => false]);
    Server::factory()->create(['type' => 'panelica', 'max_accounts' => 5, 'active' => true, 'disabled' => false]);
    ungroupedService(['server_id' => $first->id, 'status' => 'active']);

    expect(pickServer(ungroupedService())->id)->toBe($first->id);
});
