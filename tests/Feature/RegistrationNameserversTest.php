<?php

use App\Contracts\RegistrarModuleInterface;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Order;
use App\Models\Product;
use App\Models\Server;
use App\Models\ServerGroup;
use App\Models\Setting;
use App\Services\Module\ModuleRegistry;
use App\Services\OrderService;

/*
 * Which nameservers a domain is registered with.
 *
 * Nothing was passed to the registrar, so every module used its own settings:
 * for DomainNameAPI the registrar's parking servers. A customer who bought
 * hosting and a new domain together got a domain that did not point at the
 * hosting server, and a site that did not open until they changed it by hand.
 * The General Settings nameservers (DefaultNameserver1-5) were read by HRD
 * only.
 */

function nsRegistrar(string $name): ArrayObject
{
    $calls = new ArrayObject;
    $fake = Mockery::mock(RegistrarModuleInterface::class);
    $fake->shouldReceive('register')->andReturnUsing(function ($domain, $years, $params = []) use ($calls) {
        $calls[] = $params;
        $domain->update(['status' => 'active']);

        return ['success' => true, 'message' => 'registered'];
    });
    $fake->shouldReceive('getModuleName')->andReturn($name);
    app()->instance(RegistrarModuleInterface::class, $fake);
    app(ModuleRegistry::class)->registerRegistrar($name, RegistrarModuleInterface::class);

    return $calls;
}

/** A hosting product on a server group whose servers carry these nameservers. */
function nsHostingProduct(array ...$serverNs): Product
{
    $group = ServerGroup::factory()->create();
    foreach ($serverNs as $ns) {
        $server = Server::factory()->create(['type' => 'custom', 'nameserver1' => $ns[0] ?? null, 'nameserver2' => $ns[1] ?? null]);
        $group->servers()->attach($server->id);
    }

    return Product::factory()->create(['type' => 'hosting', 'server_type' => 'custom', 'server_group_id' => $group->id]);
}

function nsOrder(string $registrar, string $domain, ?Product $hosting = null): Order
{
    $items = [];
    if ($hosting) {
        $items[] = ['type' => 'service', 'product_id' => $hosting->id, 'domain' => $domain, 'billing_cycle' => 'annually', 'amount' => 50.00];
    }
    $items[] = ['type' => 'domain', 'domain' => $domain, 'amount' => 12.00, 'domain_type' => 'register', 'registrar' => $registrar, 'registration_period' => 1];

    return app(OrderService::class)->processOrder(Client::factory()->create(['tax_exempt' => true]), $items, 'banktransfer');
}

it('registers a domain bought with hosting with the hosting server\'s nameservers', function () {
    $calls = nsRegistrar('nsreg1');
    $order = nsOrder('nsreg1', 'withhosting.com', nsHostingProduct(['ns1.host.test', 'ns2.host.test']));

    app(OrderService::class)->acceptOrder($order);

    expect($calls)->toHaveCount(1)
        ->and($calls[0]['ns1'])->toBe('ns1.host.test')
        ->and($calls[0]['ns2'])->toBe('ns2.host.test')
        ->and($calls[0]['nameservers'])->toBe(['ns1.host.test', 'ns2.host.test'])
        ->and(Domain::where('order_id', $order->id)->first()->nameserverList())->toBe(['ns1.host.test', 'ns2.host.test']);
});

it('uses the group\'s nameservers only when all its servers agree, else the general default', function () {
    Setting::set('DefaultNameserver1', 'ns1.default.test');
    Setting::set('DefaultNameserver2', 'ns2.default.test');
    $calls = nsRegistrar('nsreg2');
    $order = nsOrder('nsreg2', 'twoservers.com', nsHostingProduct(['ns1.a.test', 'ns2.a.test'], ['ns1.b.test', 'ns2.b.test']));

    app(OrderService::class)->acceptOrder($order);

    expect($calls[0]['ns1'])->toBe('ns1.default.test')->and($calls[0]['ns2'])->toBe('ns2.default.test');
});

it('gives a domain bought on its own the general default nameservers', function () {
    Setting::set('DefaultNameserver1', 'ns1.default.test');
    Setting::set('DefaultNameserver2', 'ns2.default.test');
    $calls = nsRegistrar('nsreg3');

    app(OrderService::class)->acceptOrder(nsOrder('nsreg3', 'alone.com'));

    expect($calls[0]['nameservers'])->toBe(['ns1.default.test', 'ns2.default.test']);
});

it('passes no nameservers when none are set anywhere, so the registrar keeps its own', function () {
    Setting::set('DefaultNameserver1', '');
    Setting::set('DefaultNameserver2', '');
    $calls = nsRegistrar('nsreg4');

    app(OrderService::class)->acceptOrder(nsOrder('nsreg4', 'nothing.com'));

    expect($calls[0])->not->toHaveKey('ns1')->not->toHaveKey('nameservers');
});

it('keeps nameservers already on the domain record', function () {
    $calls = nsRegistrar('nsreg5');
    $order = nsOrder('nsreg5', 'ownns.com', nsHostingProduct(['ns1.host.test', 'ns2.host.test']));
    Domain::where('order_id', $order->id)->update(['nameservers' => json_encode(['ns1.mine.test', 'ns2.mine.test'])]);

    app(OrderService::class)->acceptOrder($order);

    expect($calls[0]['nameservers'])->toBe(['ns1.mine.test', 'ns2.mine.test']);
});
