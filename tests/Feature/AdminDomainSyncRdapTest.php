<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Domain;
use App\Models\RegistrarSettings;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

/*
 * The admin "Synchronizuj" button (admin.domains.sync) has its own sync path —
 * it does not run pnlcs:domain-sync — so the RDAP backfill must live there too.
 * A domain without a registration date asks RDAP once, exactly like the daily
 * sync, and never overwrites a date the panel already knows.
 */

function rdapBtnDomain(?string $registrationDate = null): Domain
{
    return Domain::create([
        'client_id' => Client::factory()->create()->id,
        'domain' => 'rdap-button.com',
        'type' => 'Register',
        'registrar' => 'DomainNameAPI',
        'status' => 'active',
        'registration_period' => 1,
        'registration_date' => $registrationDate,
        'expiry_date' => '2027-10-01',
        'next_due_date' => '2027-10-01',
        'first_payment_amount' => 10,
        'recurring_amount' => 10,
    ]);
}

function rdapBtnInfo(): void
{
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'reseller_id'], ['value' => 'reseller-1']);
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'api_key'], ['value' => 'key-1']);

    Http::fake([
        '*domains/info*' => Http::response([
            'domainName' => 'rdap-button.com',
            'expirationDate' => '2027-10-01T00:00:00',
            'lockStatus' => true,
        ], 200),
        'rdap.org/*' => Http::response([
            'objectClassName' => 'domain',
            'ldhName' => 'rdap-button.com',
            'events' => [
                ['eventAction' => 'registration', 'eventDate' => '2012-05-06T00:00:00Z'],
            ],
        ], 200),
    ]);
}

it('fills a missing registration date from RDAP on the admin sync button', function () {
    rdapBtnInfo();
    $domain = rdapBtnDomain();

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->post(route('admin.domains.sync', $domain))
        ->assertRedirect();

    expect($domain->fresh()->registration_date->toDateString())->toBe('2012-05-06');
});

it('does not ask RDAP on the button when the date is known', function () {
    rdapBtnInfo();
    $domain = rdapBtnDomain('2010-01-01');

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->post(route('admin.domains.sync', $domain))
        ->assertRedirect();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'rdap.org'));
    expect($domain->fresh()->registration_date->toDateString())->toBe('2010-01-01');
});

it('does not ask RDAP on the button when the lookups are disabled', function () {
    Setting::set('DomainSyncRdapMaxPerRun', '0', 'general');
    rdapBtnInfo();
    $domain = rdapBtnDomain();

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->post(route('admin.domains.sync', $domain))
        ->assertRedirect();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'rdap.org'));
    expect($domain->fresh()->registration_date)->toBeNull();
});

it('asks RDAP for an OVH domain, because OVH reports the transfer date', function () {
    RegistrarSettings::updateOrCreate(['registrar' => 'ovh', 'setting' => 'app_key'], ['value' => 'k']);
    RegistrarSettings::updateOrCreate(['registrar' => 'ovh', 'setting' => 'app_secret'], ['value' => 's']);
    RegistrarSettings::updateOrCreate(['registrar' => 'ovh', 'setting' => 'consumer_key'], ['value' => 'c']);

    Http::fake([
        // RDAP first: "*/domain/ovh-button.com" would otherwise match
        // rdap.org/domain/ovh-button.com as well, and answer it with OVH's
        // body, which carries no events.
        'rdap.org/*' => Http::response([
            'objectClassName' => 'domain',
            'ldhName' => 'ovh-button.com',
            'events' => [
                ['eventAction' => 'registration', 'eventDate' => '1998-09-15T00:00:00Z'],
            ],
        ], 200),
        '*/domain/ovh-button.com/serviceInfos' => Http::response([
            'creation' => '2015-04-10T08:00:00+02:00',
            'expiration' => '2027-06-01T10:00:00+02:00',
        ], 200),
        '*/domain/ovh-button.com' => Http::response(['status' => 'ok'], 200),
    ]);

    $domain = Domain::create([
        'client_id' => Client::factory()->create()->id,
        'domain' => 'ovh-button.com',
        'type' => 'Register',
        'registrar' => 'OVH',
        'status' => 'active',
        'registration_period' => 1,
        'registration_date' => null,
        'expiry_date' => '2027-06-01',
        'next_due_date' => '2027-06-01',
        'first_payment_amount' => 10,
        'recurring_amount' => 10,
    ]);

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->post(route('admin.domains.sync', $domain))
        ->assertRedirect();

    // OVH's serviceInfos.creation is when the service was created in the OVH
    // account - the transfer date for a domain transferred in. The sync fills
    // registration_date from the registrar first and RDAP only ever fills a
    // null one, so taking OVH's value would freeze the wrong date for good.
    // RDAP is asked instead and answers with the registry's own date.
    Http::assertSent(fn ($request) => str_contains($request->url(), 'rdap.org'));
    expect($domain->fresh()->registration_date->toDateString())->toBe('1998-09-15');
});
