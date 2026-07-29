<?php

use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Domain;
use Database\Factories\ApiCredentialFactory;

/**
 * What the domain API says about a domain.
 *
 * Four endpoints answered without asking the registrar. The lock status came
 * from a column the table does not have, so every domain reported itself
 * unlocked. Locking one echoed the request back and told nobody. The transfer
 * code was eight random characters — the customer would have taken it to their
 * new registrar and been turned away. Updating whois reported success and
 * changed nothing.
 */
function domainApiHeaders(): array
{
    $credential = ApiCredential::factory()->create();

    return [
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ];
}

function apiDomain(string $registrar = 'Manual'): Domain
{
    return Domain::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'domain' => 'api-truth.com',
        'registrar' => $registrar,
        'status' => 'active',
    ]);
}

test('a transfer code is never invented', function () {
    $domain = apiDomain();

    // The manual registrar keeps no codes, so the answer must be a refusal
    // rather than something an integration would read as a code.
    $response = $this->withHeaders(domainApiHeaders())
        ->getJson('/api/v1/domainrequestepp?domainid='.$domain->id)
        ->assertStatus(422);

    $body = $response->json();

    expect($body['data']['eppcode'] ?? $body['eppcode'] ?? null)->toBeNull();
});

test('a domain with no registrar module is told so, not answered with a default', function () {
    $domain = apiDomain('not-a-real-registrar');

    $this->withHeaders(domainApiHeaders())
        ->getJson('/api/v1/domaingetlockingstatus?domainid='.$domain->id)
        ->assertStatus(422);
});

test('locking a domain does not report success without the registrar', function () {
    $domain = apiDomain('not-a-real-registrar');

    $this->withHeaders(domainApiHeaders())
        ->postJson('/api/v1/domainupdatelockingstatus', [
            'domainid' => $domain->id,
            'lockstatus' => true,
        ])->assertStatus(422);
});

test('updating whois says it is not implemented instead of claiming success', function () {
    $domain = apiDomain();

    $this->withHeaders(domainApiHeaders())
        ->postJson('/api/v1/domainupdatewhoisinfo', [
            'domainid' => $domain->id,
            'contactdetails' => ['Registrant' => ['Name' => 'New Name']],
        ])->assertStatus(501);
});
