<?php

use App\Models\Admin;
use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Two promises the API reference made that the code did not keep:
 * getclientsdetails takes "clientid or email" (only clientid was read), and
 * addclient takes a password that opens a portal login (it was swallowed
 * silently, leaving an account nobody could sign in to).
 */
function docsApiHeaders(): array
{
    $admin = Admin::factory()->create();
    ApiCredential::create([
        'admin_id' => $admin->id,
        'identifier' => 'docs_fix_key',
        'secret' => ApiCredential::hashSecret('docs_fix_secret'),
        'active' => true,
    ]);

    return ['X-API-Key' => 'docs_fix_key', 'X-API-Secret' => 'docs_fix_secret'];
}

it('finds a client by email, as the docs promise', function () {
    $headers = docsApiHeaders();
    $client = Client::factory()->create(['email' => 'lookup-by-email@example.com']);

    $this->getJson('/api/v1/getclientsdetails?email=lookup-by-email@example.com', $headers)
        ->assertOk()
        ->assertJsonPath('client.id', $client->id);
});

it('still finds a client by clientid', function () {
    $headers = docsApiHeaders();
    $client = Client::factory()->create();

    $this->getJson('/api/v1/getclientsdetails?clientid='.$client->id, $headers)
        ->assertOk()
        ->assertJsonPath('client.id', $client->id);
});

it('asks for one of the two identifiers instead of a bare not-found', function () {
    $headers = docsApiHeaders();

    $this->getJson('/api/v1/getclientsdetails', $headers)
        ->assertStatus(400);
});

it('opens a portal login when addclient carries a password', function () {
    $headers = docsApiHeaders();

    $response = $this->postJson('/api/v1/addclient', [
        'firstname' => 'Porta', 'lastname' => 'Login',
        'email' => 'portal-login@example.com',
        'password2' => 'Str0ngPass!2026',
    ], $headers)->assertOk();

    $user = User::where('email', 'portal-login@example.com')->first();
    expect($user)->not->toBeNull()
        ->and(Hash::check('Str0ngPass!2026', $user->password))->toBeTrue();

    $client = Client::find($response->json('clientid'));
    expect($client->users()->wherePivot('owner', true)->pluck('users.id')->all())->toBe([$user->id]);
});

it('rejects a too-short addclient password instead of swallowing it', function () {
    $headers = docsApiHeaders();

    $this->postJson('/api/v1/addclient', [
        'firstname' => 'Shorty', 'lastname' => 'Pass',
        'email' => 'short-pass@example.com',
        'password2' => 'short',
    ], $headers)->assertStatus(422);

    expect(Client::where('email', 'short-pass@example.com')->exists())->toBeFalse();
});

it('still creates a passwordless bookkeeping client when no password is sent', function () {
    $headers = docsApiHeaders();

    $response = $this->postJson('/api/v1/addclient', [
        'firstname' => 'Books', 'lastname' => 'Only',
        'email' => 'books-only@example.com',
    ], $headers)->assertOk();

    expect(Client::find($response->json('clientid')))->not->toBeNull()
        ->and(User::where('email', 'books-only@example.com')->exists())->toBeFalse();
});
