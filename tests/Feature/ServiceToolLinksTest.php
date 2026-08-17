<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/*
 * The hosting tools sat one page in, on a service page nobody thought to open,
 * so a customer with Docker apps available never found them. These pin the
 * shortcuts now shown on the dashboard and the service list, and - just as
 * importantly - that they stay off rows with nothing behind them.
 */

function toolServer(): Server
{
    return Server::create([
        'name' => 'Panel', 'hostname' => 'panel.tools.test', 'ip_address' => '10.0.0.12',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'port' => 8443, 'active' => true,
    ]);
}

function toolService(?Server $server, array $config = [], array $moduleData = ['panelica_user_id' => 'acct-1'], string $status = 'active'): array
{
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => $server ? 'panelica' : null,
        'config_options' => json_encode($config),
    ]);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id,
        'server_id' => $server?->id, 'domain' => 'tools.test',
        'status' => $status, 'module_data' => $moduleData,
    ]);

    return [$user, $service];
}

beforeEach(function () {
    Http::fake(fn () => Http::response(['data' => []], 200));
});

it('offers the tools on the dashboard row', function () {
    [$user, $service] = toolService(toolServer());

    $this->actingAs($user)->get(route('client.home'))
        ->assertOk()
        ->assertSee(route('client.services.containers', $service))
        ->assertSee(route('client.services.files', $service));
});

it('offers the tools on the service list row', function () {
    [$user, $service] = toolService(toolServer());

    $this->actingAs($user)->get(route('client.services.index'))
        ->assertOk()
        ->assertSee(route('client.services.containers', $service));
});

it('shows apps alone for a container plan', function () {
    [$user, $service] = toolService(toolServer(), ['panelica_container_plan' => 1]);

    $page = $this->actingAs($user)->get(route('client.services.index'))->assertOk();
    $page->assertSee(route('client.services.containers', $service));
    // A container plan has no website, so a Files link would open on nothing.
    $page->assertDontSee(route('client.services.files', $service));
});

it('stays off a service with no provisioned account', function () {
    [$user, $service] = toolService(toolServer(), [], []);

    $this->actingAs($user)->get(route('client.services.index'))
        ->assertOk()
        ->assertDontSee(route('client.services.containers', $service));
});

it('stays off a terminated service', function () {
    [$user, $service] = toolService(toolServer(), [], ['panelica_user_id' => 'acct-1'], 'terminated');

    $this->actingAs($user)->get(route('client.services.index'))
        ->assertOk()
        ->assertDontSee(route('client.services.containers', $service));
});

it('stays off a service that is not on a hosting panel', function () {
    [$user, $service] = toolService(null);

    $this->actingAs($user)->get(route('client.services.index'))
        ->assertOk()
        ->assertDontSee(route('client.services.containers', $service));
});
