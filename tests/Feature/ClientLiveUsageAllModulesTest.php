<?php

use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;

/**
 * The service page's usage strip (disk, bandwidth, CPU, RAM) was drawn for
 * Panelica only, although HestiaCP and aaPanel answer the same usage endpoint
 * through their liveUsage().
 */
function liveUsageService(string $type): array
{
    $server = Server::factory()->create(['type' => $type, 'hostname' => 'panel.test', 'ip_address' => '', 'port' => 8083, 'username' => 'admin', 'password' => 'x', 'access_hash' => 'y']);
    $client = Client::factory()->create();
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'server_type' => $type]);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id, 'server_id' => $server->id,
        'order_id' => Order::factory()->create(['client_id' => $client->id])->id, 'domain' => 'shop.test', 'status' => 'active',
    ]);
    $user = User::factory()->create();
    $user->clients()->attach($client->id);

    return [$user, $service];
}

it('shows the usage strip for a HestiaCP service', function () {
    [$user, $service] = liveUsageService('hestiacp');

    $this->actingAs($user)->get(route('client.services.show', $service))
        ->assertOk()
        ->assertSee('id="m-disk"', false)
        ->assertSee(route('client.services.usage', $service), false);
});

it('leaves the strip off for a module that does not report usage', function () {
    [$user, $service] = liveUsageService('directadmin');

    $this->actingAs($user)->get(route('client.services.show', $service))
        ->assertOk()
        ->assertDontSee('id="m-disk"', false);
});
