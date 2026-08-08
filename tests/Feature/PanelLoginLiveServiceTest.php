<?php

use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * A control panel session handed out for a service that is over.
 *
 * Cancelling a service, terminating it, or marking it fraud all mean the same
 * thing here: the customer may no longer act on it. This controller says so in
 * one place - isLive(), which the cancellation form asks before it will do
 * anything - and the single sign-on action never asked. The button is only
 * drawn for an active service, but the route answers whoever calls it, and a
 * customer who has the URL from last month still has it.
 *
 * So a service the operator terminated for fraud would still be sent to the
 * panel to have a login session made for it.
 */
function panelSsoService(string $status): Service
{
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);

    $server = Server::factory()->create([
        'type' => 'panelica',
        'hostname' => 'panel.test',
        'ip_address' => '',
        'port' => 8443,
        'password' => 'pk_live',
        'access_hash' => 'sk_live',
        'active' => true,
    ]);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'panelica',
    ]);

    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'order_id' => Order::factory()->create(['client_id' => $client->id])->id,
        'domain' => 'shop.test',
        'status' => $status,
    ]);

    $service->forceFill(['module_data' => ['panelica_user_id' => 'acct-1']])->save();

    test()->actingAs($user);

    return $service;
}

it('does not ask the panel for a session for a service marked fraud', function () {
    Http::fake();

    $service = panelSsoService('fraud');

    $this->get(route('client.services.login', $service))->assertRedirect();

    Http::assertNothingSent();
});

it('does not ask the panel for a session for a terminated service', function () {
    Http::fake();

    $service = panelSsoService('terminated');

    $this->get(route('client.services.login', $service))->assertRedirect();

    Http::assertNothingSent();
});

it('does not ask the panel for a session for a cancelled service', function () {
    Http::fake();

    $service = panelSsoService('cancelled');

    $this->get(route('client.services.login', $service))->assertRedirect();

    Http::assertNothingSent();
});

it('still signs a customer into a service that is running', function () {
    Http::fake(['*/sso-login' => Http::response(['data' => ['url' => 'https://panel.test:8443/sso/abc']], 200)]);

    $service = panelSsoService('active');

    $this->get(route('client.services.login', $service))
        ->assertRedirect('https://panel.test:8443/sso/abc');
});

it('still refuses a service belonging to somebody else', function () {
    Http::fake();

    panelSsoService('active');

    // A second customer, signed in over the top of the first.
    $other = User::factory()->create();
    $otherClient = Client::factory()->create();
    $other->clients()->attach($otherClient->id);

    $theirs = Service::factory()->create([
        'client_id' => $otherClient->id,
        'status' => 'active',
        'domain' => 'theirs.test',
    ]);

    $this->get(route('client.services.login', $theirs))->assertForbidden();

    Http::assertNothingSent();
});
