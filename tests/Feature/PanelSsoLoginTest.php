<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Faz 5: a customer can jump into their hosting control panel with one click.
 * The client login route mints a panel SSO url via the module and redirects to
 * it; another client cannot use it (scoped by client_id).
 */

function ssoService(): array
{
    $client = Client::factory()->create();
    $user   = User::factory()->create();
    $user->clients()->attach($client->id);

    $server = Server::create([
        'name' => 'Panel', 'hostname' => 'panel.test', 'ip_address' => '10.0.0.1',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'port' => 8443, 'active' => true,
    ]);
    $product = Product::factory()->create(['server_type' => 'panelica']);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id, 'server_id' => $server->id,
        'status' => 'active', 'notes' => json_encode(['panelica_user_id' => 'acct-9']),
    ]);

    return [$user, $service];
}

it('redirects the client to the panel single sign-on url', function () {
    Http::fake(['*/v1/accounts/*/sso-login' => Http::response(['data' => ['url' => 'https://panel.test:8443/sso?token=xyz']], 200)]);
    [$user, $service] = ssoService();

    $this->actingAs($user)
        ->get(route('client.services.login', $service))
        ->assertRedirect('https://panel.test:8443/sso?token=xyz');

    Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/accounts/acct-9/sso-login') && $r->method() === 'POST');
});

it('forbids single sign-on into another client service', function () {
    [$user, $service] = ssoService();

    $other       = User::factory()->create();
    $otherClient = Client::factory()->create();
    $other->clients()->attach($otherClient->id);

    $this->actingAs($other)
        ->get(route('client.services.login', $service))
        ->assertForbidden();
});
