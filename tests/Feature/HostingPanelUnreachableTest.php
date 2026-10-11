<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/*
 * A hosting panel that does not answer. Every hosting tab asks the panel
 * first, and an unreachable panel threw a ConnectionException past them as a
 * 500 "Server Error". The customer now lands on the service page, or back on
 * the form they sent, with a message saying the panel is not answering.
 */

function unreachableService(): array
{
    $server = Server::create([
        'name' => 'Panel', 'hostname' => 'panel.test', 'ip_address' => '10.0.0.9',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'port' => 8443, 'active' => true,
    ]);
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => Product::factory()->create(['server_type' => 'panelica'])->id,
        'server_id' => $server->id, 'domain' => 'shop.example.com', 'status' => 'active',
        'module_data' => ['panelica_user_id' => 'acct-1', 'panelica_domain_id' => 'dom-1'],
    ]);

    return [$user, $service];
}

test('a hosting tab whose panel does not answer goes back to the service page with a message', function (string $tab) {
    Http::fake(fn () => Http::failedConnection());
    [$user, $service] = unreachableService();

    $this->actingAs($user)->get(route($tab, $service))
        ->assertRedirect(route('client.services.show', $service))
        ->assertSessionHas('error', __('client.hosting.panel_unreachable'));
})->with(['client.services.emails', 'client.services.dns', 'client.services.databases']);

test('a form sent while the panel does not answer comes back with a message', function () {
    Http::fake(fn () => Http::failedConnection());
    [$user, $service] = unreachableService();

    $this->actingAs($user)->from(route('client.services.emails', $service))
        ->post(route('client.services.emails.store', $service), ['domain_id' => 'dom-1', 'local_part' => 'info', 'password' => 'a-long-password'])
        ->assertRedirect(route('client.services.emails', $service))
        ->assertSessionHas('error', __('client.hosting.panel_unreachable'));
});

test('elsewhere a connection failure is not turned into this message', function () {
    Http::fake(fn () => Http::failedConnection());
    [$user, $service] = unreachableService();

    // Another customer's tab is still a 403, before the panel is ever asked.
    $other = User::factory()->create();
    $other->clients()->attach(Client::factory()->create()->id);
    $this->actingAs($other)->get(route('client.services.emails', $service))->assertForbidden();
});

test('the service page the customer is sent to opens while the panel does not answer', function () {
    Http::fake(fn () => Http::failedConnection());
    [$user, $service] = unreachableService();

    $this->actingAs($user)->get(route('client.services.show', $service))->assertOk();
});
