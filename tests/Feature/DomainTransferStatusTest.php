<?php

use App\Contracts\RegistrarModuleInterface;
use App\Models\Client;
use App\Models\Domain;
use App\Services\Module\ModuleRegistry;
use App\Services\OrderService;

/*
 * A started transfer is not finished: the losing registrar still has days to
 * release the domain. Most registrar modules record that on the domain
 * themselves ('pending_transfer'), and the order step then overwrote it with
 * 'active', so the panel showed a domain as live that had not moved yet.
 */

function transferRegistrar(string $name, ?string $writes, ?string $returns = null): void
{
    $fake = Mockery::mock(RegistrarModuleInterface::class);
    $fake->shouldReceive('transfer')->andReturnUsing(function ($domain, $epp) use ($writes, $returns) {
        if ($writes !== null) {
            $domain->update(['status' => $writes]);
        }

        return array_filter(['success' => true, 'message' => 'started', 'status' => $returns], fn ($v) => $v !== null);
    });
    $fake->shouldReceive('getModuleName')->andReturn($name);

    app()->instance(RegistrarModuleInterface::class, $fake);
    app(ModuleRegistry::class)->registerRegistrar($name, RegistrarModuleInterface::class);
}

function orderedTransfer(string $registrar): Domain
{
    return Domain::create([
        'client_id' => Client::factory()->create()->id, 'domain' => 'move-me.com', 'type' => 'Transfer',
        'registrar' => $registrar, 'status' => 'pending', 'registration_period' => 1, 'epp_code' => 'AbC-123',
        'first_payment_amount' => 0, 'recurring_amount' => 10,
    ]);
}

test('the pending transfer status a module writes is kept', function () {
    transferRegistrar('xferwrites', writes: 'pending_transfer');
    $domain = orderedTransfer('xferwrites');

    app(OrderService::class)->retryDomainRegistration($domain);

    expect($domain->fresh()->status)->toBe('pending_transfer')
        ->and($domain->fresh()->epp_code)->toBeNull();
});

test('a status the module returns wins', function () {
    transferRegistrar('xferreturns', writes: null, returns: 'pending_transfer');
    $domain = orderedTransfer('xferreturns');

    app(OrderService::class)->retryDomainRegistration($domain);

    expect($domain->fresh()->status)->toBe('pending_transfer');
});

test('a module that says nothing about the status still leaves the domain active', function () {
    transferRegistrar('xfersilent', writes: null);
    $domain = orderedTransfer('xfersilent');

    app(OrderService::class)->retryDomainRegistration($domain);

    expect($domain->fresh()->status)->toBe('active');
});
