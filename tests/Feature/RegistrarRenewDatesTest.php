<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\RegistrarSettings;
use App\Services\DomainService;
use Illuminate\Support\Facades\Http;

/*
 * A renewal the registrar accepted moves the domain's dates on.
 *
 * DomainService::renewDomain() leaves the dates to the module when renew()
 * succeeds; eNom, Namecheap, ResellerClub, HRD and Manual move them. The
 * DomainNameAPI and Openprovider modules did not, so after a paid renewal
 * next_due_date stayed where it was - and the "this period is already paid"
 * guard in InvoiceGenerationService then never billed the following year.
 * Found renewing a domain against the DomainNameAPI OTE on 2026-10-01: the
 * registry moved the expiry a year on, the panel did not.
 */

function rrdDomain(string $registrar): Domain
{
    $client = Client::factory()->create();

    return Domain::factory()->create([
        'client_id' => $client->id,
        'domain' => 'renew-example.com',
        'registrar' => $registrar,
        'registration_period' => 1,
        'expiry_date' => '2027-10-01',
        'next_due_date' => '2027-10-01',
    ]);
}

function rrdDnaSettings(): void
{
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'reseller_id'], ['value' => 'reseller-1']);
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'api_key'], ['value' => 'key-1']);
}

function rrdOpSettings(): void
{
    foreach (['username' => 'reseller', 'password' => 'secret', 'test_mode' => '1'] as $k => $v) {
        RegistrarSettings::updateOrCreate(['registrar' => 'openprovider', 'setting' => $k], ['value' => $v]);
    }
}

it('moves expiry and next due date on when DomainNameAPI renews', function () {
    rrdDnaSettings();
    Http::fake(['*domains/renew*' => Http::response(['success' => true])]);
    $domain = rrdDomain('DomainNameAPI');

    app(DomainService::class)->renewDomain($domain, 1);

    $domain->refresh();
    expect($domain->expiry_date->toDateString())->toBe('2028-10-01')
        ->and($domain->next_due_date->toDateString())->toBe('2028-10-01');
});

it('leaves the dates alone when DomainNameAPI refuses the renewal', function () {
    rrdDnaSettings();
    Http::fake(['*domains/renew*' => Http::response(['error' => ['message' => 'Insufficient balance']], 400)]);
    $domain = rrdDomain('DomainNameAPI');

    app(DomainService::class)->renewDomain($domain, 1);

    $domain->refresh();
    expect($domain->expiry_date->toDateString())->toBe('2027-10-01')
        ->and($domain->next_due_date->toDateString())->toBe('2027-10-01');
});

it('moves the dates on by the number of years renewed, once', function () {
    rrdDnaSettings();
    Http::fake(['*domains/renew*' => Http::response(['success' => true])]);
    $domain = rrdDomain('DomainNameAPI');

    app(DomainService::class)->renewDomain($domain, 2);

    expect($domain->fresh()->next_due_date->toDateString())->toBe('2029-10-01');
});

it('moves expiry and next due date on when Openprovider renews', function () {
    rrdOpSettings();
    Http::fake([
        '*/auth/login' => Http::response(['code' => 0, 'desc' => '', 'data' => ['token' => 't']]),
        '*/domains/*/renew' => Http::response(['code' => 0, 'desc' => '', 'data' => []]),
        '*/domains*' => Http::response(['code' => 0, 'desc' => '', 'data' => ['results' => [['id' => 42]]]]),
    ]);
    $domain = rrdDomain('OpenProvider');

    app(DomainService::class)->renewDomain($domain, 1);

    $domain->refresh();
    expect($domain->expiry_date->toDateString())->toBe('2028-10-01')
        ->and($domain->next_due_date->toDateString())->toBe('2028-10-01');
});
