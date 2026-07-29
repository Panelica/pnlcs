<?php

use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use Database\Factories\ApiCredentialFactory;

/**
 * The API is a second door onto the same records.
 *
 * Deleting a customer from the admin screen is refused while they still have
 * services that have not been terminated, because the cascade takes the
 * service rows away and nothing closes the accounts on the server. The API
 * did the same delete with no such check.
 */
function apiHeaders(): array
{
    $credential = ApiCredential::factory()->create();

    return [
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ];
}

function clientWithLiveService(string $status = 'active'): Client
{
    $client = Client::factory()->create();

    Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id])->id,
        'status' => $status,
        'domain' => 'api-guard.com',
    ]);

    return $client;
}

test('the API refuses to delete a customer who still has hosting', function () {
    $client = clientWithLiveService('active');

    $this->postJson('/api/v1/deleteclient', ['clientid' => $client->id], apiHeaders())
        ->assertStatus(422);

    expect(Client::find($client->id))->not->toBeNull();
});

test('a suspended account counts as still hosting on the API too', function () {
    $client = clientWithLiveService('suspended');

    $this->postJson('/api/v1/deleteclient', ['clientid' => $client->id], apiHeaders())
        ->assertStatus(422);

    expect(Client::find($client->id))->not->toBeNull();
});

test('the API deletes a customer whose services have ended', function () {
    $client = clientWithLiveService('terminated');

    $this->postJson('/api/v1/deleteclient', ['clientid' => $client->id], apiHeaders())
        ->assertOk();

    expect(Client::find($client->id))->toBeNull();
});

test('the API deletes a customer with nothing attached', function () {
    $client = Client::factory()->create();

    $this->postJson('/api/v1/deleteclient', ['clientid' => $client->id], apiHeaders())
        ->assertOk();

    expect(Client::find($client->id))->toBeNull();
});
