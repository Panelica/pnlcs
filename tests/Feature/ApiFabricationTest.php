<?php

use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use Database\Factories\ApiCredentialFactory;

/**
 * Endpoints that answered with something they had made up.
 *
 * capturepayment said "captured" and took no money. createssotoken handed out
 * 64 random characters as a login token stored nowhere and consumed by
 * nothing, and createclientinvite an invite code that could never be redeemed.
 * getuserpermissions returned the same list whoever was asked about, and
 * updateuserpermissions reported that access had been restricted while storing
 * nothing. domainrelease reported success with the registrar never told.
 *
 * A caller acts on an answer like that, which is why a refusal is the better
 * one.
 */
function fabricationHeaders(): array
{
    $credential = ApiCredential::factory()->create();

    return [
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ];
}

test('capturing a payment does not claim to have taken money', function () {
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 90]);

    $response = $this->withHeaders(fabricationHeaders())
        ->postJson('/api/v1/capturepayment', ['invoiceid' => $invoice->id])
        ->assertStatus(501);

    expect($response->getContent())->not->toContain('captured')
        ->and($invoice->fresh()->status)->toBe('unpaid');
});

test('no login token is handed out that cannot sign anyone in', function () {
    $client = Client::factory()->create();

    $response = $this->withHeaders(fabricationHeaders())
        ->postJson('/api/v1/createssotoken', ['clientid' => $client->id])
        ->assertStatus(501);

    expect($response->json('data.token') ?? $response->json('token'))->toBeNull();
});

test('no invite code is handed out that cannot be redeemed', function () {
    $this->withHeaders(fabricationHeaders())
        ->postJson('/api/v1/createclientinvite', ['clientid' => Client::factory()->create()->id])
        ->assertStatus(501);
});

test('permissions are neither invented nor pretended to be saved', function () {
    $headers = fabricationHeaders();

    $this->withHeaders($headers)
        ->getJson('/api/v1/getuserpermissions?userid=1')
        ->assertStatus(501);

    $this->withHeaders($headers)
        ->postJson('/api/v1/updateuserpermissions', ['userid' => 1, 'permissions' => ['view_invoices']])
        ->assertStatus(501);
});

test('releasing a domain is refused rather than reported', function () {
    $domain = Domain::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'domain' => 'release-me.com',
        'registrar' => 'Manual',
    ]);

    $this->withHeaders(fabricationHeaders())
        ->postJson('/api/v1/domainrelease', ['domainid' => $domain->id])
        ->assertStatus(501);
});
