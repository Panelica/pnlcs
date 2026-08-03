<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Modules\Servers\CPanel\CPanelModule;

/**
 * Which plan the product sells.
 *
 * A panel keeps its own plans - a WHM package, a Panelica plan - and the
 * product's job is to say which one it sells. Only Panelica ever offered that
 * choice; cPanel had no field at all, so every account was created on WHM's
 * "default" package whatever the customer had paid for.
 *
 * The plans are asked of the server, the way WHMCS does it, and stored in one
 * key that every module reads.
 */
function packageAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

function whmServer(): Server
{
    return Server::factory()->create([
        'type' => 'cpanel',
        'hostname' => 'whm.example.test',
        'ip_address' => '203.0.113.10',
        'port' => 2087,
        'username' => 'root',
        'access_hash' => 'TOKEN',
        'active' => true,
    ]);
}

function fakeWhmPackages(): void
{
    Http::fake(['*listpkgs*' => Http::response([
        'metadata' => ['result' => 1, 'reason' => 'OK'],
        'data' => ['pkg' => [
            ['name' => 'starter_1gb', 'QUOTA' => '1024', 'MAXPOP' => '5'],
            ['name' => 'default', 'QUOTA' => 'unlimited', 'MAXPOP' => 'unlimited'],
        ]],
    ], 200)]);
}

test('the form offers the plans the server has', function () {
    whmServer();
    fakeWhmPackages();

    $this->actingAs(packageAdmin(), 'admin')
        ->getJson(route('admin.products.packages', ['module' => 'cpanel']))
        ->assertOk()
        ->assertJsonPath('packages.0.id', 'default')
        ->assertJsonPath('packages.1.id', 'starter_1gb')
        // The quota is shown so the operator can tell them apart.
        ->assertJsonPath('packages.1.name', 'starter_1gb (disk 1024, email 5)');
});

test('with no server of that type the form says so instead of showing nothing', function () {
    $response = $this->actingAs(packageAdmin(), 'admin')
        ->getJson(route('admin.products.packages', ['module' => 'cpanel']))
        ->assertOk();

    expect($response->json('packages'))->toBe([])
        ->and($response->json('error'))->not->toBeNull();
});

test('a chosen plan is kept on the product', function () {
    whmServer();
    fakeWhmPackages();
    $group = ProductGroup::factory()->create();

    $this->actingAs(packageAdmin(), 'admin')
        ->post(route('admin.products.store'), [
            'name' => 'Starter',
            'group_id' => $group->id,
            'type' => 'hosting',
            'pay_type' => 'recurring',
            'server_type' => 'cpanel',
            'package_name' => 'starter_1gb',
        ])->assertSessionHasNoErrors();

    $product = Product::where('name', 'Starter')->firstOrFail();
    $config = is_string($product->config_options) ? json_decode($product->config_options, true) : $product->config_options;

    expect($config['package_name'] ?? null)->toBe('starter_1gb');
});

test('the module sends the chosen plan to the server', function () {
    $server = whmServer();

    Http::fake([
        '*createacct*' => Http::response(['metadata' => ['result' => 1, 'reason' => 'OK']], 200),
        '*' => Http::response(['metadata' => ['result' => 1, 'reason' => 'OK']], 200),
    ]);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'cpanel',
        'config_options' => json_encode(['package_name' => 'starter_1gb']),
    ]);

    $service = Service::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'domain' => 'buyer-example.com',
        'status' => 'pending',
    ]);

    app(CPanelModule::class)->create($service);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'createacct')
        && ($request['plan'] ?? null) === 'starter_1gb');
});
