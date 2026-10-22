<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Modules\Servers\DirectAdmin\DirectAdminModule;
use Modules\Servers\Panelica\PanelicaModule;

/**
 * The product form saves package_name as "" when no package is picked (or the
 * panel's list is empty), and every reader used `??`, which keeps "". So the
 * module's default ("Default" on DirectAdmin) and the older panelica_plan_id
 * were never used, and a Panelica account was opened with no plan.
 */
function blankPackageService(string $type, array $config, array $server = []): Service
{
    $server = Server::factory()->create(array_merge([
        'type' => $type, 'hostname' => 'panel.test', 'ip_address' => '', 'port' => 8443,
        'username' => 'admin', 'password' => 'pk_live_key', 'access_hash' => 'sk_live_secret',
    ], $server));
    $client = Client::factory()->create();
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id, 'server_type' => $type, 'config_options' => json_encode($config),
    ]);

    return Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id, 'server_id' => $server->id,
        'order_id' => Order::factory()->create(['client_id' => $client->id])->id,
        'domain' => 'shop.test', 'status' => 'pending', 'username' => null,
    ]);
}

it('opens a Panelica account on the older plan id when package_name is blank', function () {
    Http::fake([
        '*/v1/accounts' => Http::response(['data' => ['id' => 'acct-1']], 201),
        '*/v1/domains' => Http::response(['data' => ['id' => 'dom-1']], 201),
        '*' => Http::response(['data' => []], 200),
    ]);

    (new PanelicaModule)->create(blankPackageService('panelica', ['package_name' => '', 'panelica_plan_id' => 'plan-uuid']));

    $account = collect(Http::recorded())->first(fn ($pair) => str_ends_with($pair[0]->url(), '/v1/accounts'));

    expect($account)->not->toBeNull()
        ->and($account[0]->data()['plan_id'] ?? null)->toBe('plan-uuid');
});

it('falls back to the DirectAdmin default package when package_name is blank', function () {
    Http::fake(['*' => Http::response('error=0&text=ok', 200)]);

    (new DirectAdminModule)->create(blankPackageService('directadmin', ['package_name' => ''], ['port' => 2222, 'password' => 'admin-secret', 'access_hash' => '']));

    Http::assertSent(function ($request) {
        parse_str((string) $request->body(), $body);

        return str_contains($request->url(), 'CMD_API_ACCOUNT_USER') && ($body['package'] ?? null) === 'Default';
    });
});

it('does not store a blank package name from the product form', function () {
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id, 'server_type' => '',
        'config_options' => json_encode(['package_name' => 'gold']),
    ]);

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->put(route('admin.products.update', $product), [
            'name' => $product->name, 'type' => 'hosting', 'pay_type' => 'recurring', 'group_id' => $product->group_id,
            'package_name' => '',
        ])->assertSessionHasNoErrors();

    $config = $product->fresh()->config_options;
    $config = is_string($config) ? json_decode($config, true) : $config;

    expect($config)->not->toHaveKey('package_name');
});
