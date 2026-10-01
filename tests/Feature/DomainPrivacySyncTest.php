<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Domain;
use App\Models\RegistrarSettings;
use Illuminate\Support\Facades\Http;

/*
 * A domain's WHOIS privacy is read back from the registrar.
 *
 * DomainNameAPI switches privacy on at registration by default, whatever the
 * order asked for (confirmed against its OTE on 2026-10-01: a name registered
 * with privacyProtection false came back with privacyProtectionStatus true).
 * Nothing read that back, so the customer's domain page said "disabled" for a
 * name that was in fact hidden.
 */

function dpsSettings(): void
{
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'reseller_id'], ['value' => 'reseller-1']);
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'api_key'], ['value' => 'key-1']);
}

function dpsDomain(bool $idProtection): Domain
{
    return Domain::create([
        'client_id' => Client::factory()->create()->id,
        'domain' => 'privacy-example.com',
        'type' => 'Register',
        'registrar' => 'DomainNameAPI',
        'status' => 'active',
        'registration_period' => 1,
        'expiry_date' => '2027-10-01',
        'next_due_date' => '2027-10-01',
        'first_payment_amount' => 10,
        'recurring_amount' => 10,
        'id_protection' => $idProtection,
    ]);
}

function dpsInfo(array $extra = []): void
{
    Http::fake(['*domains/info*' => Http::response(array_merge([
        'domainName' => 'privacy-example.com',
        'expirationDate' => '2027-10-01T00:00:00',
        'lockStatus' => true,
    ], $extra))]);
}

it('marks privacy on when the registrar has it on', function () {
    dpsSettings();
    dpsInfo(['privacyProtectionStatus' => true]);
    $domain = dpsDomain(false);

    $this->artisan('pnlcs:domain-sync')->assertExitCode(0);

    expect($domain->fresh()->id_protection)->toBeTrue();
});

it('marks privacy off when the registrar has it off', function () {
    dpsSettings();
    dpsInfo(['privacyProtectionStatus' => false]);
    $domain = dpsDomain(true);

    $this->artisan('pnlcs:domain-sync')->assertExitCode(0);

    expect($domain->fresh()->id_protection)->toBeFalse();
});

it('leaves privacy alone when the registrar does not say', function () {
    dpsSettings();
    dpsInfo();
    $domain = dpsDomain(true);

    $this->artisan('pnlcs:domain-sync')->assertExitCode(0);

    expect($domain->fresh()->id_protection)->toBeTrue();
});

it('reads privacy back on the admin sync button too', function () {
    dpsSettings();
    dpsInfo(['privacyProtectionStatus' => true]);
    $domain = dpsDomain(false);

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->post(route('admin.domains.sync', $domain))
        ->assertRedirect();

    expect($domain->fresh()->id_protection)->toBeTrue();
});
