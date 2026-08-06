<?php

use App\Contracts\RegistrarModuleInterface;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Order;
use App\Services\Module\ModuleRegistry;
use App\Services\NotificationService;
use App\Services\OrderService;

/**
 * A domain nobody registered.
 *
 * Accepting a paid order flips every pending domain on it to active with a
 * single update. No registrar is told. register() is written in all four
 * registrar modules and the order flow calls none of them.
 *
 * So a customer buys a domain, pays for it, and the panel shows it as active
 * while no registry has ever heard of it. Services on the same order go
 * through the provisioning service and reach a server; domains do not.
 */
function registeringRegistrar(string $name = 'registeringreg', bool $succeeds = true): ArrayObject
{
    $calls = new ArrayObject;

    $fake = Mockery::mock(RegistrarModuleInterface::class);
    $fake->shouldReceive('register')->andReturnUsing(function ($domain, $years, $params = []) use ($calls, $succeeds) {
        $calls[] = ['domain' => $domain->domain, 'years' => $years];

        if ($succeeds) {
            $domain->update(['status' => 'active']);
        }

        return ['success' => $succeeds, 'message' => $succeeds ? 'registered' : 'registry refused'];
    });
    $fake->shouldReceive('getModuleName')->andReturn($name);

    app()->instance(RegistrarModuleInterface::class, $fake);
    app(ModuleRegistry::class)->registerRegistrar($name, RegistrarModuleInterface::class);

    return $calls;
}

function domainOrderFor(string $registrar, string $domainName = 'boughtdomain.com'): Order
{
    $client = Client::factory()->create(['tax_exempt' => true]);

    return app(OrderService::class)->processOrder($client, [
        [
            'type' => 'domain',
            'domain' => $domainName,
            'amount' => 12.00,
            'domain_type' => 'register',
            'registrar' => $registrar,
            'registration_period' => 2,
        ],
    ], 'banktransfer');
}

it('registers the domain with the registrar when the order is accepted', function () {
    $calls = registeringRegistrar();
    $order = domainOrderFor('registeringreg');

    app(OrderService::class)->acceptOrder($order);

    expect($calls->count())->toBe(1);
    expect($calls[0]['domain'])->toBe('boughtdomain.com');
    expect($calls[0]['years'])->toBe(2);
});

it('does not call a domain active when the registry refused it', function () {
    registeringRegistrar('refusingreg', succeeds: false);
    $order = domainOrderFor('refusingreg');

    app(OrderService::class)->acceptOrder($order);

    $domain = Domain::where('order_id', $order->id)->firstOrFail();

    expect(strtolower((string) $domain->status))->not->toBe('active');
});

it('tells somebody when the registry refused', function () {
    registeringRegistrar('refusingreg2', succeeds: false);

    $seen = new ArrayObject;
    $spy = Mockery::mock(NotificationService::class);
    $spy->shouldReceive('dispatch')->andReturnUsing(function ($event, $data = []) use ($seen) {
        $seen[] = $event;
    });
    app()->instance(NotificationService::class, $spy);

    $order = domainOrderFor('refusingreg2');
    app(OrderService::class)->acceptOrder($order);

    expect(in_array('domain.registration_failed', $seen->getArrayCopy(), true))->toBeTrue();
});

it('still activates a domain the panel does not register through anyone', function () {
    $order = domainOrderFor('nobody-registers-here');

    app(OrderService::class)->acceptOrder($order);

    $domain = Domain::where('order_id', $order->id)->firstOrFail();

    expect(strtolower((string) $domain->status))->toBe('active');
});
