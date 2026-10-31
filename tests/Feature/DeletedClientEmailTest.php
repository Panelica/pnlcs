<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\User;
use Database\Factories\ApiCredentialFactory;

/**
 * A deleted client keeping its email address.
 *
 * Deleting a client only soft deletes the row, on purpose: its invoices and
 * credits still point at it. The email rules counted that row all the same, so
 * the address stayed "already taken" for good. The customer could not be
 * opened again under their own address, from the admin form or the api, and no
 * other client could be moved onto it either.
 */
function deletedClientEmailAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

function deletedClientOn(string $email): Client
{
    $client = Client::factory()->create(['email' => $email]);
    $client->delete();

    return $client;
}

it('opens a new client on the address of a deleted one from the admin form', function () {
    deletedClientOn('back@example.test');

    $this->actingAs(deletedClientEmailAdmin(), 'admin')
        ->post(route('admin.clients.store'), [
            'first_name' => 'Back',
            'last_name' => 'Again',
            'email' => 'back@example.test',
            'status' => 'active',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Client::where('email', 'back@example.test')->count())->toBe(1)
        ->and(Client::withTrashed()->where('email', 'back@example.test')->count())->toBe(2);
});

it('frees the address of a deleted client that had a login of its own', function () {
    $client = Client::factory()->create(['email' => 'login@example.test']);
    $user = User::factory()->create(['email' => 'login@example.test']);
    $client->users()->attach($user);
    $client->delete();

    $this->actingAs(deletedClientEmailAdmin(), 'admin')
        ->post(route('admin.clients.store'), [
            'first_name' => 'Login',
            'last_name' => 'Again',
            'email' => 'login@example.test',
            'status' => 'active',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Client::where('email', 'login@example.test')->count())->toBe(1);
});

it('moves a client onto the address of a deleted one from the admin form', function () {
    deletedClientOn('moved@example.test');
    $client = Client::factory()->create(['email' => 'old@example.test', 'country' => 'TR']);

    $this->actingAs(deletedClientEmailAdmin(), 'admin')
        ->put(route('admin.clients.update', $client), [
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'email' => 'moved@example.test',
            'country' => 'TR',
            'status' => 'active',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($client->fresh()->email)->toBe('moved@example.test');
});

it('still refuses the address of a client that is not deleted', function () {
    Client::factory()->create(['email' => 'live@example.test']);

    $this->actingAs(deletedClientEmailAdmin(), 'admin')
        ->post(route('admin.clients.store'), [
            'first_name' => 'Second',
            'last_name' => 'Person',
            'email' => 'live@example.test',
            'status' => 'active',
        ])
        ->assertSessionHasErrors('email');

    expect(Client::where('email', 'live@example.test')->count())->toBe(1);
});

it('opens and moves clients onto the address of a deleted one through the api', function () {
    $credential = ApiCredential::factory()->create();
    $headers = [
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ];
    deletedClientOn('api-new@example.test');
    deletedClientOn('api-moved@example.test');

    $this->withHeaders($headers)->postJson('/api/v1/addclient', [
        'firstname' => 'Api',
        'lastname' => 'Person',
        'email' => 'api-new@example.test',
    ])->assertSuccessful();

    $client = Client::factory()->create(['email' => 'api-old@example.test']);
    $this->withHeaders($headers)->postJson('/api/v1/updateclient', [
        'clientid' => $client->id,
        'email' => 'api-moved@example.test',
    ])->assertSuccessful();

    expect(Client::where('email', 'api-new@example.test')->count())->toBe(1)
        ->and($client->fresh()->email)->toBe('api-moved@example.test');
});
