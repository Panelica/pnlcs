<?php

use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Servers\HestiaCP\HestiaCPModule;

/**
 * HestiaCP create() marked the service active when v-add-domain failed (the
 * failure was only logged), and a second customer whose domain started the
 * same way failed on v-add-user's "already exists" with no second try.
 */
function hestiaNewService(string $domain = 'shop.example.com'): Service
{
    $server = Server::factory()->create([
        'type' => 'hestiacp', 'hostname' => 'hestia.test', 'ip_address' => '', 'port' => 8083,
        'username' => 'admin', 'password' => 'admin-secret', 'access_hash' => '',
    ]);
    $client = Client::factory()->create();
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id, 'server_type' => 'hestiacp',
        'config_options' => json_encode(['package_name' => 'default']),
    ]);

    return Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id, 'server_id' => $server->id,
        'order_id' => Order::factory()->create(['client_id' => $client->id])->id,
        'domain' => $domain, 'status' => 'pending', 'username' => null,
    ]);
}

/** Answer each HestiaCP command with the exit code given for it (0 otherwise). */
function hestiaAnswers(array $codes): void
{
    Http::fake(function (Request $request) use ($codes) {
        $cmd = $request['cmd'] ?? '';
        $code = $codes[$cmd] ?? 0;

        return Http::response((string) (is_array($code) ? array_shift($GLOBALS['hestiaQueue'][$cmd]) : $code), 200);
    });
}

it('does not mark the service active when the domain could not be added', function () {
    hestiaAnswers(['v-add-domain' => 3]);
    $service = hestiaNewService();

    $result = (new HestiaCPModule)->create($service);

    expect($result['success'])->toBeFalse()
        ->and($service->fresh()->status)->not->toBe('active');
    Http::assertSent(fn (Request $request) => ($request['cmd'] ?? '') === 'v-delete-user' && $request['arg1'] === 'shop');
});

it('tries a second username when the first one is taken', function () {
    $GLOBALS['hestiaQueue'] = ['v-add-user' => [4, 0]];
    hestiaAnswers(['v-add-user' => []]);
    $service = hestiaNewService();

    $result = (new HestiaCPModule)->create($service);

    expect($result['success'])->toBeTrue()
        ->and($service->fresh()->username)->toBe('shops'.$service->id)
        ->and($service->fresh()->status)->toBe('active');
});

it('still opens the account when everything succeeds', function () {
    hestiaAnswers([]);
    $service = hestiaNewService();

    expect((new HestiaCPModule)->create($service)['success'])->toBeTrue()
        ->and($service->fresh()->username)->toBe('shop');
});
