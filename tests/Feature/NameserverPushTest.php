<?php

use App\Contracts\RegistrarModuleInterface;
use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Domain;
use App\Models\User;
use App\Services\Module\ModuleRegistry;
use Database\Factories\ApiCredentialFactory;

/**
 * Nameservers the registry never hears about.
 *
 * Changing nameservers writes them into the domains table and tells the
 * customer they were updated. Nothing tells the registrar. Every module
 * implements saveNameservers() and not one line of the application calls it.
 *
 * So a customer moving their site to another host changes the nameservers,
 * reads "nameservers updated", and the registry goes on pointing at the old
 * ones. Their site never moves, and the panel shows the change that did not
 * happen.
 */
function recordingRegistrar(string $name = 'recordingreg', bool $succeeds = true): ArrayObject
{
    $saved = new ArrayObject;

    $fake = Mockery::mock(RegistrarModuleInterface::class);
    $fake->shouldReceive('saveNameservers')->andReturnUsing(function ($domain, $nameservers) use ($saved, $succeeds) {
        $saved[] = array_values($nameservers);

        return $succeeds;
    });
    $fake->shouldReceive('getModuleName')->andReturn($name);

    app()->instance(RegistrarModuleInterface::class, $fake);
    app(ModuleRegistry::class)->registerRegistrar($name, RegistrarModuleInterface::class);

    return $saved;
}

function domainWithRegistrar(string $registrar = 'recordingreg'): array
{
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);

    $domain = Domain::factory()->create([
        'client_id' => $client->id,
        'registrar' => $registrar,
        'nameservers' => json_encode(['ns1' => 'ns1.old.test', 'ns2' => 'ns2.old.test']),
        'status' => 'active',
    ]);

    return [$user, $domain];
}

it('tells the registrar about the new nameservers', function () {
    $saved = recordingRegistrar();
    [$user, $domain] = domainWithRegistrar();

    test()->actingAs($user)->put(route('client.domains.nameservers', $domain), [
        'ns1' => 'ns1.new.test',
        'ns2' => 'ns2.new.test',
    ]);

    expect($saved->count())->toBe(1);
    expect($saved[0])->toBe(['ns1.new.test', 'ns2.new.test']);
});

it('does not claim success when the registrar refuses', function () {
    recordingRegistrar('refusingreg', succeeds: false);
    [$user, $domain] = domainWithRegistrar('refusingreg');

    test()->actingAs($user)->put(route('client.domains.nameservers', $domain), [
        'ns1' => 'ns1.new.test',
        'ns2' => 'ns2.new.test',
    ])->assertSessionHas('error');

    // And the panel does not show a change the registry never took.
    expect($domain->fresh()->nameservers)->toContain('ns1.old.test');
});

it('still stores them for a domain nobody registers through the panel', function () {
    [$user, $domain] = domainWithRegistrar('nobody-registers-here');

    test()->actingAs($user)->put(route('client.domains.nameservers', $domain), [
        'ns1' => 'ns1.new.test',
        'ns2' => 'ns2.new.test',
    ])->assertSessionHas('success');

    expect($domain->fresh()->nameservers)->toContain('ns1.new.test');
});

it('tells the registrar through the api door as well', function () {
    $saved = recordingRegistrar('apireg');
    [$user, $domain] = domainWithRegistrar('apireg');

    $credential = ApiCredential::factory()->create();

    test()->withHeaders([
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ])->postJson('/api/v1/domainupdatenameservers', [
        'domainid' => $domain->id,
        'ns1' => 'ns1.new.test',
        'ns2' => 'ns2.new.test',
    ]);

    expect($saved->count())->toBe(1);
});
