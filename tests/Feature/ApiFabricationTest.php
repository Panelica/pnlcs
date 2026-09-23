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
    // An account with no login has nobody to sign in as: no token.
    $client = Client::factory()->create();

    $response = $this->withHeaders(fabricationHeaders())
        ->postJson('/api/v1/createssotoken', ['clientid' => $client->id])
        ->assertStatus(404);

    expect($response->json('access_token'))->toBeNull();
    // With a login, the token is a real one-time link (ApiNewEndpointsTest).
});

test('no invite code is handed out that cannot be redeemed', function () {
    // Without an address there is nobody to send it to: no invitation is made.
    $this->withHeaders(fabricationHeaders())
        ->postJson('/api/v1/createclientinvite', ['clientid' => Client::factory()->create()->id])
        ->assertStatus(422);

    expect(\App\Models\UserInvite::count())->toBe(0);
    // A real invitation is redeemed end to end in ApiNewEndpointsTest.
});

test('permissions are neither invented nor pretended to be saved', function () {
    $headers = fabricationHeaders();

    // Per-login permissions are stored and enforced now (ClientPermissions);
    // a name that is not one of them is refused, never saved.
    $client = Client::factory()->create();
    $user = \App\Models\User::factory()->create();
    $user->clients()->attach($client->id, ['owner' => false]);

    $this->withHeaders($headers)
        ->getJson('/api/v1/getuserpermissions?userid='.$user->id)
        ->assertStatus(422);

    $this->withHeaders($headers)
        ->postJson('/api/v1/updateuserpermissions', ['userid' => $user->id, 'clientid' => $client->id, 'permissions' => ['view_invoices']])
        ->assertStatus(422);
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
