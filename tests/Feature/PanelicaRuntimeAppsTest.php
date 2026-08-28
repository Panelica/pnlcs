<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Panelica\PanelicaModule;

/*
 * Runtime application tabs (Laravel / Node.js / Python). The panel's list
 * endpoint answers with every app the ROOT-scoped PNLCS key can see, so the
 * app's owner_user_id (recorded as the account's panelica_user_id) is the
 * fence that keeps one customer out of another's apps - exactly how containers
 * are scoped. Read-only: no create/deploy from billing.
 */
function raServer(): Server
{
    return Server::create([
        'name' => 'Panel', 'hostname' => 'panel.test', 'ip_address' => '10.0.0.9',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'port' => 8443, 'active' => true,
    ]);
}

/** @return array{0: User, 1: Client, 2: Service} */
function raService(Server $server): array
{
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'panelica',
    ]);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id, 'server_id' => $server->id,
        'status' => 'active', 'domain' => 'mine.example.com',
        'module_data' => ['panelica_user_id' => 'acct-1'],
    ]);

    return [$user, $client, $service];
}

/** Fake the panel's runtime app listings; each returns one owned + one foreign app. */
function fakeRuntimeApi(): void
{
    Http::fake([
        '*/v1/laravel/apps*' => Http::response(['data' => [
            ['id' => 'l1', 'name' => 'my-laravel', 'owner_user_id' => 'acct-1', 'domain' => 'app.example.com', 'php_version' => '8.3', 'laravel_version' => '11', 'app_url' => 'https://app.example.com', 'status' => 'running'],
            ['id' => 'l2', 'name' => 'someone-else', 'owner_user_id' => 'acct-OTHER', 'domain' => 'other.com', 'php_version' => '8.2', 'status' => 'running'],
        ]], 200),
        '*/v1/nodejs/apps*' => Http::response(['data' => [
            ['id' => 'n1', 'name' => 'my-node', 'owner_user_id' => 'acct-1', 'domain' => '', 'node_version' => '22', 'status' => 'running'],
            ['id' => 'n2', 'name' => 'foreign-node', 'owner_user_id' => 'acct-OTHER', 'node_version' => '20', 'status' => 'stopped'],
        ]], 200),
        '*/v1/python/apps*' => Http::response(['data' => [
            ['id' => 'p1', 'name' => 'my-py', 'owner_user_id' => 'acct-1', 'python_version' => '3.12', 'framework' => 'fastapi', 'status' => 'running'],
        ]], 200),
        '*' => Http::response(['data' => []], 200),
    ]);
}

it('returns only the account\'s own apps for each runtime', function () {
    fakeRuntimeApi();
    [, , $service] = raService(raServer());
    $module = new PanelicaModule();

    $laravel = $module->laravelApps($service);
    $node = $module->nodejsApps($service);
    $python = $module->pythonApps($service);

    expect($laravel)->toHaveCount(1)->and($laravel[0]['name'])->toBe('my-laravel')
        ->and($laravel[0]['version'])->toBe('8.3');
    expect($node)->toHaveCount(1)->and($node[0]['name'])->toBe('my-node')->and($node[0]['version'])->toBe('22');
    expect($python)->toHaveCount(1)->and($python[0]['name'])->toBe('my-py')->and($python[0]['framework'])->toBe('fastapi');
});

it('never leaks another account\'s app', function () {
    fakeRuntimeApi();
    [, , $service] = raService(raServer());
    $names = collect((new PanelicaModule())->laravelApps($service))->pluck('name');

    expect($names)->not->toContain('someone-else');
});

it('offers the runtime tabs as hosting features', function () {
    fakeRuntimeApi();
    [, , $service] = raService(raServer());
    $feat = (new PanelicaModule())->hostingFeatures($service);

    expect($feat)->toContain('laravel')->toContain('nodejs')->toContain('python');
});

it('shows the customer their laravel apps on the service page', function () {
    fakeRuntimeApi();
    [$user, $client, $service] = raService(raServer());

    $this->actingAs($user)->withSession(['active_client_id' => $client->id])
        ->get(route('client.services.laravel', $service))
        ->assertOk()
        ->assertSee('my-laravel')
        ->assertDontSee('someone-else');
});

it('shows a clean empty state when a runtime has no apps', function () {
    Http::fake(['*' => Http::response(['data' => []], 200)]);
    [$user, $client, $service] = raService(raServer());

    $this->actingAs($user)->withSession(['active_client_id' => $client->id])
        ->get(route('client.services.python', $service))
        ->assertOk()
        ->assertSee(__('client.hosting.runtime.empty_title'));
});

it('links the runtime shortcuts on the service listing row', function () {
    fakeRuntimeApi();
    [$user, $client, $service] = raService(raServer());

    $this->actingAs($user)->withSession(['active_client_id' => $client->id])
        ->get(route('client.services.index'))
        ->assertOk()
        ->assertSee(route('client.services.laravel', $service))
        ->assertSee(route('client.services.nodejs', $service))
        ->assertSee(route('client.services.python', $service));
});

it('refuses a service that is not the caller\'s', function () {
    fakeRuntimeApi();
    [, , $service] = raService(raServer());
    $stranger = User::factory()->create();
    $strangerClient = Client::factory()->create();
    $stranger->clients()->attach($strangerClient->id);

    $this->actingAs($stranger)->withSession(['active_client_id' => $strangerClient->id])
        ->get(route('client.services.laravel', $service))
        ->assertForbidden();
});
