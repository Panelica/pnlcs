<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Panelica\PanelicaModule;

/**
 * An account with a password nobody has.
 *
 * Creating an account makes up a password, sends it to the panel, and writes
 * back only the username. The password is not recorded anywhere: not on the
 * service, not in the welcome email. The customer has an account they cannot
 * sign in to, and nobody - not even the operator - can tell them what the
 * password is.
 *
 * Every other server module here writes it back: cPanel, Plesk, DirectAdmin
 * and HestiaCP all store username and password after creating the account, and
 * the provisioning service does the same when the password is changed later.
 * Only this one drops it.
 */
function credentialServer(): Server
{
    return Server::create([
        'name' => 'Panel', 'hostname' => 'panel.credentials.test', 'ip_address' => '10.0.0.9',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk_test_key',
        'access_hash' => 'sk_test_secret', 'port' => 8443, 'active' => true,
    ]);
}

function serviceOnPanel(Server $server, ?string $password = null): Service
{
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'panelica',
    ]);

    return Service::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'domain' => 'newsite.test',
        'status' => 'pending',
        'password' => $password,
    ]);
}

function fakePanelApi(): void
{
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/v1/accounts')) {
            return Http::response(['data' => ['id' => 'acct-1']], 200);
        }
        if (str_contains($request->url(), '/v1/domains')) {
            return Http::response(['data' => ['id' => 'dom-1']], 200);
        }

        return Http::response(['data' => []], 200);
    });
}

it('keeps the password it gave the account', function () {
    fakePanelApi();
    $service = serviceOnPanel(credentialServer());

    app(PanelicaModule::class)->create($service);

    expect($service->fresh()->password)->not->toBeNull();
    expect(trim((string) $service->fresh()->password))->not->toBe('');
});

it('gives the account the password the customer already had', function () {
    fakePanelApi();
    $service = serviceOnPanel(credentialServer(), 'ChosenByTheCustomer1');

    app(PanelicaModule::class)->create($service);

    expect($service->fresh()->password)->toBe('ChosenByTheCustomer1');

    // And that is the password the panel was told to use.
    Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/accounts')
        && ($request->data()['password'] ?? null) === 'ChosenByTheCustomer1');
});

it('still records the username', function () {
    fakePanelApi();
    $service = serviceOnPanel(credentialServer());

    app(PanelicaModule::class)->create($service);

    expect($service->fresh()->username)->not->toBeNull();
});

it('records the very password the panel was given', function () {
    fakePanelApi();
    $service = serviceOnPanel(credentialServer());

    app(PanelicaModule::class)->create($service);

    $stored = $service->fresh()->password;

    Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/accounts')
        && ($request->data()['password'] ?? null) === $stored);
});
