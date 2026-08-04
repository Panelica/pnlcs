<?php

use App\Models\Admin;
use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Domain;
use Database\Factories\ApiCredentialFactory;

/**
 * Deleting a customer who still has domains.
 *
 * Deletion refuses while the customer has services that have not been
 * terminated, because the hosting would carry on running with nothing left to
 * say who it belongs to. A registered domain is the same thing and was not
 * checked: deleting the customer took the domain row with it, so nothing
 * renewed it and nothing said it existed, while the registration carried on
 * at the registrar until it quietly lapsed.
 */
function clientWithDomain(string $status = 'active'): Client
{
    $client = Client::factory()->create();

    Domain::create([
        'client_id' => $client->id,
        'domain' => 'still-registered.com',
        'type' => 'Register',
        'registration_period' => 1,
        'registration_date' => now()->subMonths(2),
        'expiry_date' => now()->addMonths(10),
        'next_due_date' => now()->addMonths(10),
        'status' => $status,
        'recurring_amount' => 12.99,
        'registrar' => 'enom',
    ]);

    return $client;
}

it('refuses to delete a customer whose domain is still registered', function () {
    $client = clientWithDomain();

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->delete(route('admin.clients.destroy', $client))
        ->assertRedirect();

    expect(Client::find($client->id))->not->toBeNull()
        ->and(Domain::where('client_id', $client->id)->count())->toBe(1);
});

it('deletes a customer whose domains are done with', function () {
    $client = clientWithDomain('cancelled');

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->delete(route('admin.clients.destroy', $client))
        ->assertRedirect();

    expect(Client::find($client->id))->toBeNull();
});

it('refuses at the api too', function () {
    $credential = ApiCredential::factory()->create();
    $client = clientWithDomain();

    $this->withHeaders([
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ])->postJson('/api/v1/deleteclient', ['clientid' => $client->id])
        ->assertStatus(422);

    expect(Client::find($client->id))->not->toBeNull();
});
