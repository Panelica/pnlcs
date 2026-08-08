<?php

use App\Models\ApiCredential;
use App\Models\Client;
use Database\Factories\ApiCredentialFactory;

/**
 * The API letting two clients share an email address.
 *
 * A client is found by their email address all over this application: signing
 * in, resetting a password, matching an incoming support email to an account.
 * The admin form has always refused an address that already belongs to
 * somebody, and clients.email carries an ordinary index, not a unique one, so
 * the database will not refuse it either.
 *
 * addclient checked only that the address looked like an address, and
 * updateclient copied whatever it was given straight onto the record - no
 * format check, no uniqueness check, and the same for the account status: an
 * unknown one reached the enum cast and came back to the caller as a 500.
 */
function clientApiHeaders(): array
{
    $credential = ApiCredential::factory()->create();

    return [
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ];
}

it('refuses to open a second account on an address already in use', function () {
    Client::factory()->create(['email' => 'taken@example.test']);

    $this->withHeaders(clientApiHeaders())->postJson('/api/v1/addclient', [
        'firstname' => 'Second',
        'lastname' => 'Person',
        'email' => 'taken@example.test',
    ])->assertStatus(422);

    expect(Client::where('email', 'taken@example.test')->count())->toBe(1);
});

it('still opens an account on an address nobody has', function () {
    $this->withHeaders(clientApiHeaders())->postJson('/api/v1/addclient', [
        'firstname' => 'New',
        'lastname' => 'Person',
        'email' => 'fresh@example.test',
    ])->assertSuccessful();

    expect(Client::where('email', 'fresh@example.test')->count())->toBe(1);
});

it('refuses to move a client onto somebody else address', function () {
    Client::factory()->create(['email' => 'taken@example.test']);
    $client = Client::factory()->create(['email' => 'mine@example.test']);

    $this->withHeaders(clientApiHeaders())->postJson('/api/v1/updateclient', [
        'clientid' => $client->id,
        'email' => 'taken@example.test',
    ])->assertStatus(422);

    expect($client->fresh()->email)->toBe('mine@example.test');
});

it('lets a client keep their own address while something else changes', function () {
    $client = Client::factory()->create(['email' => 'mine@example.test', 'city' => 'Ankara']);

    $this->withHeaders(clientApiHeaders())->postJson('/api/v1/updateclient', [
        'clientid' => $client->id,
        'email' => 'mine@example.test',
        'city' => 'Istanbul',
    ])->assertSuccessful();

    expect($client->fresh()->city)->toBe('Istanbul')
        ->and($client->fresh()->email)->toBe('mine@example.test');
});

it('refuses a status the account screens do not offer', function () {
    $client = Client::factory()->create(['status' => 'active']);

    $this->withHeaders(clientApiHeaders())->postJson('/api/v1/updateclient', [
        'clientid' => $client->id,
        'status' => 'banana',
    ])->assertStatus(422);

    expect($client->fresh()->status->value)->toBe('active');
});

it('still accepts a status the account screens do offer', function () {
    $client = Client::factory()->create(['status' => 'active']);

    $this->withHeaders(clientApiHeaders())->postJson('/api/v1/updateclient', [
        'clientid' => $client->id,
        'status' => 'inactive',
    ])->assertSuccessful();

    expect($client->fresh()->status->value)->toBe('inactive');
});
