<?php

use App\Contracts\RegistrarModuleInterface;
use App\Events\DomainExpired;
use App\Events\DomainRegistered;
use App\Events\DomainRenewed;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Domain;
use App\Services\DomainService;
use App\Services\Module\ModuleRegistry;
use App\Services\OrderService;
use Illuminate\Support\Facades\Event;

/*
 * A domain was registered, renewed, transferred and lapsed without a single
 * event or hook. Services had ServiceActivated and friends; an addon that
 * wanted to tell the customer their domain was live, or to send a domain to
 * an accounting system on renewal, had nothing to listen to.
 */

function dleRegistrar(string $name = 'dlereg'): void
{
    $fake = Mockery::mock(RegistrarModuleInterface::class);
    $fake->shouldReceive('register')->andReturnUsing(function ($domain) {
        $domain->update(['status' => 'active']);

        return ['success' => true];
    });
    $fake->shouldReceive('renew')->andReturn(['success' => true]);
    $fake->shouldReceive('getModuleName')->andReturn($name);
    app()->instance(RegistrarModuleInterface::class, $fake);
    app(ModuleRegistry::class)->registerRegistrar($name, RegistrarModuleInterface::class);
}

test('a domain the registrar registers raises DomainRegistered, runs both hooks and is logged', function () {
    dleRegistrar();
    $client = Client::factory()->create(['tax_exempt' => true]);
    $order = app(OrderService::class)->processOrder($client, [[
        'type' => 'domain', 'domain' => 'fresh-shop.com', 'amount' => 12, 'domain_type' => 'register', 'registrar' => 'dlereg', 'registration_period' => 1,
    ]], 'banktransfer');
    $seen = [];
    add_hook('DomainRegistered', function ($vars) use (&$seen) {
        $seen[] = 'pnlcs:'.$vars['domain']->domain;
    });
    add_hook('AfterRegistrarRegistration', function ($vars) use (&$seen) {
        $seen[] = 'whmcs:'.$vars['domain']->domain;
    });

    app(OrderService::class)->acceptOrder($order);

    expect($seen)->toBe(['pnlcs:fresh-shop.com', 'whmcs:fresh-shop.com'])
        ->and(ActivityLog::where('client_id', $client->id)->where('description', 'Domain fresh-shop.com registered')->exists())->toBeTrue();
});

test('a renewal raises DomainRenewed, at a registrar and for a domain with none', function () {
    dleRegistrar();
    $atRegistrar = Domain::factory()->create(['domain' => 'kept-shop.com', 'registrar' => 'dlereg', 'status' => 'active', 'expiry_date' => now()->addMonth()]);
    $manual = Domain::factory()->create(['domain' => 'own-shop.com', 'registrar' => '', 'status' => 'active', 'expiry_date' => now()->addMonth()]);

    Event::fake([DomainRenewed::class]);
    app(DomainService::class)->renewDomain($atRegistrar, 2);
    app(DomainService::class)->renewDomain($manual, 1);

    Event::assertDispatched(DomainRenewed::class, fn ($e) => $e->domain->id === $atRegistrar->id && $e->years === 2);
    Event::assertDispatched(DomainRenewed::class, fn ($e) => $e->domain->id === $manual->id && $e->years === 1);
});

test('a refused renewal raises nothing', function () {
    $fake = Mockery::mock(RegistrarModuleInterface::class);
    $fake->shouldReceive('renew')->andReturn(['success' => false, 'message' => 'no balance']);
    $fake->shouldReceive('getModuleName')->andReturn('dlerefuse');
    app()->instance(RegistrarModuleInterface::class, $fake);
    app(ModuleRegistry::class)->registerRegistrar('dlerefuse', RegistrarModuleInterface::class);
    $domain = Domain::factory()->create(['domain' => 'broke-shop.com', 'registrar' => 'dlerefuse', 'status' => 'active', 'expiry_date' => now()->addMonth()]);

    Event::fake([DomainRenewed::class]);
    app(DomainService::class)->renewDomain($domain, 1);

    Event::assertNotDispatched(DomainRenewed::class);
});

test('the daily sync raises DomainExpired when it moves a domain past its expiry', function () {
    $domain = Domain::factory()->create(['domain' => 'gone-shop.com', 'registrar' => 'manual', 'status' => 'active', 'expiry_date' => now()->subDays(40)]);

    Event::fake([DomainExpired::class]);
    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    expect($domain->fresh()->status)->toBe('expired');
    Event::assertDispatched(DomainExpired::class, fn ($e) => $e->domain->id === $domain->id && $e->status === 'expired');
});
