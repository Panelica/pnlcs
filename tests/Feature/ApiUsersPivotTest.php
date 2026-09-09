<?php

use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\User;
use Database\Factories\ApiCredentialFactory;

/*
 * A login belongs to accounts through the client_user pivot; users carry no
 * client_id. The two user endpoints assumed a column that does not exist:
 * listing by client answered with a database error, and a user added with a
 * clientid was created but attached to nothing, so it could see no account.
 */

function usersApiHeaders(): array
{
    $credential = ApiCredential::factory()->create();

    return ['X-API-Key' => $credential->identifier, 'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET];
}

test('getusers lists the logins attached to a client', function () {
    $client = Client::factory()->create();
    $mine = User::factory()->create(['email' => 'mine@example.test']);
    $mine->clients()->attach($client->id);
    User::factory()->create(['email' => 'other@example.test']);

    $response = $this->withHeaders(usersApiHeaders())->getJson('/api/v1/getusers?clientid='.$client->id)->assertSuccessful();

    $emails = collect($response->json('users'))->pluck('email')->all();
    expect($emails)->toBe(['mine@example.test']);
});

test('adduser with a clientid attaches the login to that account', function () {
    $client = Client::factory()->create();

    $this->withHeaders(usersApiHeaders())->postJson('/api/v1/adduser', [
        'email' => 'new@example.test', 'password' => 'secret-enough', 'first_name' => 'New', 'last_name' => 'User', 'clientid' => $client->id,
    ])->assertSuccessful();

    $user = User::where('email', 'new@example.test')->first();
    expect($user)->not->toBeNull()
        ->and($user->clients()->pluck('clients.id')->all())->toBe([$client->id]);
});
