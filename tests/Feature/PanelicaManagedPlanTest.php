<?php

use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Panelica\PanelicaModule;

/**
 * An account opened on limits nobody checked.
 *
 * A managed product keeps a plan of its own on the panel, named after the
 * product, and pushes the product's resource configuration into it: the basic
 * columns with one call and the cgroup limits - cpu, io, inodes, ssh level -
 * with another. The call that creates the plan is checked. Neither of the two
 * that write the limits is.
 *
 * So when the panel refuses one of them - a validation error, a panel too old
 * for those columns - the plan keeps whatever limits it had, the account is
 * opened on it anyway, and the operator is told the account was created
 * successfully. The customer pays for the resources on the product page and
 * gets the ones the plan happened to be carrying.
 */
function panelicaServer(): Server
{
    return Server::factory()->create([
        'type' => 'panelica',
        'hostname' => 'panel.test',
        'ip_address' => '',
        'port' => 8443,
        'username' => 'admin',
        'password' => 'pk_live_key',
        'access_hash' => 'sk_live_secret',
    ]);
}

function panelicaService(Server $server, array $config): Service
{
    $client = Client::factory()->create();
    $group = ProductGroup::factory()->create();
    $product = Product::factory()->create([
        'group_id' => $group->id,
        'server_type' => 'panelica',
        'config_options' => json_encode($config),
    ]);

    return Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'order_id' => Order::factory()->create(['client_id' => $client->id])->id,
        'domain' => 'shop.test',
        'status' => 'pending',
    ]);
}

function managedConfig(array $extra = []): array
{
    return array_merge([
        'res_managed' => 1,
        'res_cpu_percent' => 200,
        'res_ram_mb' => 2048,
        'res_ssh_level' => 'jailed',
    ], $extra);
}

it('does not open an account on a plan whose limits the panel refused', function () {
    $server = panelicaServer();
    $service = panelicaService($server, managedConfig());

    // The plan already exists, so both writes are PATCHes: the basic columns,
    // then the limits - and the panel refuses the limits.
    Http::fake([
        '*/v1/plans/*' => Http::sequence()
            ->push(['data' => ['id' => 'plan-1']], 200)
            ->push(['message' => 'cpu_percent is not supported on this panel'], 400),
        '*/v1/plans' => Http::response(['data' => [['id' => 'plan-1', 'name' => 'pnlcs-p'.$service->product_id]]], 200),
        '*' => Http::response(['data' => ['id' => 'acct-1']], 201),
    ]);

    $result = (new PanelicaModule)->create($service);

    expect($result['success'])->toBeFalse()
        ->and(strtolower($result['message']))->toContain('plan');

    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/v1/accounts'));
});

it('does not open an account when the panel would not make the plan at all', function () {
    Http::fake([
        '*/v1/plans' => Http::sequence()
            ->push(['data' => []], 200)                      // nothing named after this product yet
            ->push(['message' => 'plan limit reached'], 500), // and it will not be created
        '*' => Http::response(['data' => ['id' => 'acct-1']], 201),
    ]);

    $server = panelicaServer();
    $result = (new PanelicaModule)->create(panelicaService($server, managedConfig()));

    expect($result['success'])->toBeFalse();

    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/v1/accounts'));
});

it('opens the account on the managed plan once the limits are in', function () {
    $server = panelicaServer();
    $service = panelicaService($server, managedConfig());

    Http::fake([
        '*/v1/plans/*' => Http::response(['data' => ['id' => 'plan-1']], 200),
        '*/v1/plans' => Http::response(['data' => [['id' => 'plan-1', 'name' => 'pnlcs-p'.$service->product_id]]], 200),
        '*/v1/accounts' => Http::response(['data' => ['id' => 'acct-1']], 201),
        '*/v1/domains' => Http::response(['data' => ['id' => 'dom-1']], 201),
        '*' => Http::response(['data' => []], 200),
    ]);

    $result = (new PanelicaModule)->create($service);

    expect($result['success'])->toBeTrue();

    $account = collect(Http::recorded())
        ->first(fn ($pair) => str_ends_with($pair[0]->url(), '/v1/accounts'));

    expect($account)->not->toBeNull()
        ->and($account[0]->data()['plan_id'])->toBe('plan-1');
});

it('leaves a product that is not managed alone', function () {
    $server = panelicaServer();
    $service = panelicaService($server, ['package_name' => 'starter']);

    Http::fake([
        '*/v1/accounts' => Http::response(['data' => ['id' => 'acct-2']], 201),
        '*/v1/domains' => Http::response(['data' => ['id' => 'dom-2']], 201),
        '*' => Http::response(['data' => []], 200),
    ]);

    expect((new PanelicaModule)->create($service)['success'])->toBeTrue();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/v1/plans'));

    $account = collect(Http::recorded())
        ->first(fn ($pair) => str_ends_with($pair[0]->url(), '/v1/accounts'));

    expect($account[0]->data()['plan_id'])->toBe('starter');
});

it('gives the panel a slug when creating a managed plan', function () {
    $sent = null;
    Illuminate\Support\Facades\Http::fake(function ($request) use (&$sent) {
        $url = $request->url();
        if (str_contains($url, '/v1/plans') && $request->method() === 'POST') {
            $sent = $request->data();

            return Illuminate\Support\Facades\Http::response(['data' => ['id' => 'plan-new']], 200);
        }
        if (str_contains($url, '/v1/plans')) {
            return Illuminate\Support\Facades\Http::response(['data' => []], 200);
        }
        if (str_contains($url, '/v1/domains')) {
            return Illuminate\Support\Facades\Http::response(['data' => ['id' => 'dom-1']], 200);
        }
        if (str_contains($url, '/v1/accounts')) {
            return Illuminate\Support\Facades\Http::response(['data' => ['id' => 'acct-1']], 200);
        }

        return Illuminate\Support\Facades\Http::response(['data' => []], 200);
    });

    $server = panelicaServer();
    $service = panelicaService($server, managedConfig());

    app(Modules\Servers\Panelica\PanelicaModule::class)->create($service);

    // The panel validates slug as required. Sending only a name failed, and the
    // order died with "Managed plan could not be prepared on the panel" - every
    // managed product, every time.
    expect($sent['slug'] ?? null)->not->toBeNull()
        ->and($sent['name'] ?? null)->not->toBeNull();
});
