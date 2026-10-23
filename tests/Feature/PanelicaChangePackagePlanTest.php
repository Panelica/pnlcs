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
 * create() opens the account on package_name (falling back to the older
 * panelica_plan_id), but changePackage() read only panelica_plan_id. A product
 * whose plan was picked in the package field could not be moved onto.
 */
function movingService(): Service
{
    $server = Server::factory()->create(['type' => 'panelica', 'hostname' => 'panel.test', 'ip_address' => '', 'port' => 8443, 'password' => 'pk_test', 'access_hash' => 'sk_test']);
    $client = Client::factory()->create();
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'server_type' => 'panelica']);

    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id, 'server_id' => $server->id,
        'order_id' => Order::factory()->create(['client_id' => $client->id])->id, 'domain' => 'shop.test', 'status' => 'active',
    ]);
    $service->forceFill(['module_data' => ['panelica_user_id' => 'user-1']])->save();

    return $service->fresh();
}

it('moves the account onto the plan in package_name', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => []], 200)]);

    $result = (new PanelicaModule)->changePackage(movingService(), ['config_options' => json_encode(['package_name' => 'plan-b'])]);

    expect($result['success'])->toBeTrue();
    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_ends_with($request->url(), '/v1/accounts/user-1')
        && $request['plan_id'] === 'plan-b');
});

it('still reads the older panelica_plan_id', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => []], 200)]);

    (new PanelicaModule)->changePackage(movingService(), ['config_options' => json_encode(['package_name' => '', 'panelica_plan_id' => 'plan-old'])]);

    Http::assertSent(fn ($request) => $request->method() === 'PATCH' && $request['plan_id'] === 'plan-old');
});

it('refuses a product with no plan at all', function () {
    Http::fake();

    $result = (new PanelicaModule)->changePackage(movingService(), ['config_options' => json_encode([])]);

    expect($result['success'])->toBeFalse();
    Http::assertNothingSent();
});
