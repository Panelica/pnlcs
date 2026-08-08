<?php

use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Domain;
use Database\Factories\ApiCredentialFactory;

/**
 * The domain equivalent of the service status that stopped the billing.
 *
 * updatedomain copied status, expiry_date and next_due_date onto the record
 * with nothing checked, and domains.status is not cast to the DomainStatus
 * enum. The renewal run bills domains whose status is active or grace, so a
 * status outside that pair - a typo, a status from another system - takes the
 * domain out of the billing run for good: the customer keeps the name, the
 * registry keeps charging the operator, and no invoice is ever raised again.
 *
 * The two date columns were taking any string at all, which reached Carbon and
 * came back to the caller as a 500 rather than a validation error.
 */
function domainUpdateApiHeaders(): array
{
    $credential = ApiCredential::factory()->create();

    return [
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ];
}

function apiEditableDomain(): Domain
{
    return Domain::create([
        'client_id' => Client::factory()->create()->id,
        'domain' => 'example-api.test',
        'type' => 'Register',
        'registration_period' => 1,
        'registration_date' => today()->subYear(),
        'expiry_date' => today()->addMonth(),
        'next_due_date' => today()->addMonth(),
        'status' => 'active',
        'recurring_amount' => 12.99,
        'registrar' => 'manual',
    ]);
}

it('refuses a domain status the renewal run would not recognise', function () {
    $domain = apiEditableDomain();

    $this->withHeaders(domainUpdateApiHeaders())->postJson('/api/v1/updateclientdomain', [
        'domainid' => $domain->id,
        'status' => 'actve',
    ])->assertStatus(422);

    expect($domain->fresh()->status)->toBe('active');
});

it('still accepts a status the panel uses', function () {
    $domain = apiEditableDomain();

    $this->withHeaders(domainUpdateApiHeaders())->postJson('/api/v1/updateclientdomain', [
        'domainid' => $domain->id,
        'status' => 'grace',
    ])->assertSuccessful();

    expect($domain->fresh()->status)->toBe('grace');
});

it('refuses an expiry date that is not a date', function () {
    $domain = apiEditableDomain();

    $this->withHeaders(domainUpdateApiHeaders())->postJson('/api/v1/updateclientdomain', [
        'domainid' => $domain->id,
        'expiry_date' => 'next year sometime',
    ])->assertStatus(422);

    expect($domain->fresh()->expiry_date->toDateString())->toBe(today()->addMonth()->toDateString());
});

it('still moves the dates when given real ones', function () {
    $domain = apiEditableDomain();
    $when = today()->addYear()->toDateString();

    $this->withHeaders(domainUpdateApiHeaders())->postJson('/api/v1/updateclientdomain', [
        'domainid' => $domain->id,
        'expiry_date' => $when,
        'next_due_date' => $when,
    ])->assertSuccessful();

    expect($domain->fresh()->expiry_date->toDateString())->toBe($when)
        ->and($domain->fresh()->next_due_date->toDateString())->toBe($when);
});

it('still changes the fields it was always free to change', function () {
    $domain = apiEditableDomain();

    $this->withHeaders(domainUpdateApiHeaders())->postJson('/api/v1/updateclientdomain', [
        'domainid' => $domain->id,
        'notes' => 'Transferred in from the old registrar.',
        'id_protection' => true,
    ])->assertSuccessful();

    expect($domain->fresh()->notes)->toBe('Transferred in from the old registrar.')
        ->and($domain->fresh()->id_protection)->toBeTrue();
});
