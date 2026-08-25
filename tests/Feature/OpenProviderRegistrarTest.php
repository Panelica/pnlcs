<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\RegistrarSettings;
use Illuminate\Support\Facades\Http;
use Modules\Registrars\OpenProvider\OpenProviderRegistrar;

/*
 * The Openprovider module, against the API's published contract.
 *
 * Every request shape here mirrors the official Swagger definitions
 * (docs.openprovider.com, 2026-08): bearer login, the {code, desc, data}
 * envelope, handles for contacts, and the exact bodies of check, create,
 * transfer, renew and update. The fakes answer with the documented envelope
 * so a drift in what we send is caught by asserting the recorded requests.
 */

function opSettings(): void
{
    foreach (['username' => 'reseller', 'password' => 'secret', 'test_mode' => '1'] as $k => $v) {
        RegistrarSettings::updateOrCreate(
            ['registrar' => 'openprovider', 'setting' => $k],
            ['value' => $v]
        );
    }
}

function opDomain(): Domain
{
    $client = Client::factory()->create([
        'first_name' => 'Ada', 'last_name' => 'Lovelace',
        'email' => 'ada@example.test', 'country' => 'NL', 'city' => 'Amsterdam',
    ]);

    return Domain::factory()->create([
        'client_id' => $client->id,
        'domain' => 'example.com',
        'registrar' => 'OpenProvider',
    ]);
}

function opEnvelope(array $data = []): array
{
    return ['code' => 0, 'desc' => '', 'data' => $data];
}

test('it logs in once and reuses the bearer token', function () {
    opSettings();
    Http::fake([
        '*/auth/login' => Http::response(opEnvelope(['token' => 'tok-1', 'reseller_id' => 7])),
        '*/domains/check' => Http::response(opEnvelope(['results' => [['domain' => 'example.com', 'status' => 'free']]])),
    ]);

    $module = new OpenProviderRegistrar;
    $module->checkAvailability('example.com');
    $module->checkAvailability('other.com');

    // One login for two calls, and the token travels as a bearer header.
    Http::assertSentCount(3);
    Http::assertSent(fn ($r) => str_contains($r->url(), '/domains/check')
        && $r->hasHeader('Authorization', 'Bearer tok-1'));
});

test('availability speaks the documented status word', function () {
    opSettings();
    Http::fake([
        '*/auth/login' => Http::response(opEnvelope(['token' => 't'])),
        '*/domains/check' => Http::response(opEnvelope(['results' => [['domain' => 'example.com', 'status' => 'active']]])),
    ]);

    // "active" means taken; only "free" may sell.
    expect((new OpenProviderRegistrar)->checkAvailability('example.com')['available'])->toBeFalse();
});

test('registering creates the customer handle once and sends the documented body', function () {
    opSettings();
    $domain = opDomain();

    Http::fake([
        '*/auth/login' => Http::response(opEnvelope(['token' => 't'])),
        '*/customers' => Http::response(opEnvelope(['handle' => 'AB123456-NL'])),
        '*/domains' => Http::response(opEnvelope(['id' => 99])),
    ]);

    $result = (new OpenProviderRegistrar)->register($domain, 2, [
        'nameservers' => ['ns1.example.net', 'ns2.example.net'],
    ]);

    expect($result['success'])->toBeTrue();

    Http::assertSent(function ($r) {
        return str_ends_with(parse_url($r->url(), PHP_URL_PATH), '/customers')
            && $r['name']['first_name'] === 'Ada'
            && $r['address']['country'] === 'NL';
    });
    Http::assertSent(function ($r) {
        return str_ends_with(parse_url($r->url(), PHP_URL_PATH), '/domains')
            && $r['domain'] === ['name' => 'example', 'extension' => 'com']
            && $r['period'] === 2
            && $r['owner_handle'] === 'AB123456-NL'
            && $r['name_servers'] === [
                ['name' => 'ns1.example.net', 'seq_nr' => 0],
                ['name' => 'ns2.example.net', 'seq_nr' => 1],
            ];
    });

    // The handle is remembered: a second registration must not mint a second
    // identity for the same customer.
    expect(RegistrarSettings::where('registrar', 'openprovider')
        ->where('setting', 'handle_client_'.$domain->client_id)->value('value'))
        ->toBe('AB123456-NL');
});

test('a transfer carries the EPP code and imports the nameservers', function () {
    opSettings();
    $domain = opDomain();
    RegistrarSettings::updateOrCreate(
        ['registrar' => 'openprovider', 'setting' => 'handle_client_'.$domain->client_id],
        ['value' => 'AB123456-NL']
    );

    Http::fake([
        '*/auth/login' => Http::response(opEnvelope(['token' => 't'])),
        '*/domains/transfer' => Http::response(opEnvelope(['id' => 100])),
    ]);

    expect((new OpenProviderRegistrar)->transfer($domain, 'epp-secret')['success'])->toBeTrue();

    Http::assertSent(fn ($r) => str_contains($r->url(), '/domains/transfer')
        && $r['auth_code'] === 'epp-secret'
        && $r['import_nameservers_from_registry'] === true
        && $r['owner_handle'] === 'AB123456-NL');   // reused, not re-created
});

test('the EPP code comes from the authcode endpoint', function () {
    opSettings();
    $domain = opDomain();

    Http::fake([
        '*/auth/login' => Http::response(opEnvelope(['token' => 't'])),
        '*full_name=example.com*' => Http::response(opEnvelope(['results' => [['id' => 55]]])),
        '*/domains/55/authcode' => Http::response(opEnvelope(['auth_code' => 'the-code'])),
    ]);

    expect((new OpenProviderRegistrar)->getEPPCode($domain))->toBe('the-code');
});

test('a non-zero code in the envelope surfaces its description, not success', function () {
    opSettings();
    Http::fake([
        '*/auth/login' => Http::response(opEnvelope(['token' => 't'])),
        '*/domains/check' => Http::response(['code' => 399, 'desc' => 'Unknown TLD', 'data' => []]),
    ]);

    $result = (new OpenProviderRegistrar)->checkAvailability('example.nosuchtld');

    expect($result['available'])->toBeFalse()
        ->and($result['error'])->toContain('Unknown TLD');
});

test('sync reads the expiry and maps ACT to active', function () {
    opSettings();
    $domain = opDomain();

    Http::fake([
        '*/auth/login' => Http::response(opEnvelope(['token' => 't'])),
        '*full_name=example.com*' => Http::response(opEnvelope(['results' => [[
            'id' => 55, 'status' => 'ACT', 'expiration_date' => '2027-08-25 00:00:00',
        ]]])),
    ]);

    $result = (new OpenProviderRegistrar)->syncDomain($domain);

    expect($result['success'])->toBeTrue()
        ->and($result['expiry_date'])->toBe('2027-08-25')
        ->and($result['status'])->toBe('active');
});

test('the module is registered and appears with its config fields', function () {
    $module = app(App\Services\Module\ModuleRegistry::class)->getRegistrarModule('openprovider');

    expect($module)->toBeInstanceOf(OpenProviderRegistrar::class)
        ->and(collect($module->getConfigFields())->pluck('name')->all())
        ->toBe(['username', 'password', 'test_mode']);
});
