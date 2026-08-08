<?php

use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Modules\Servers\CPanel\CPanelModule;
use Modules\Servers\DirectAdmin\DirectAdminModule;

/**
 * What DirectAdmin's own API expects, and what the rest of this panel expects
 * back.
 *
 * CMD_API_MODIFY_USER does nothing without an action - "package" to move an
 * account onto a named package - and the module sent none, so a package change
 * was reported as done and the account stayed where it was.
 *
 * usageUpdate returned a list of rows and wrote nothing to the database, while
 * every caller and both sibling modules expect ['updated' => n, 'errors' => n]
 * and the usage figures stored on the service. It also read the server's whole
 * user list with explode() on what DirectAdmin answers as list[]=..., which is
 * an array: in PHP 8 that is a TypeError, so the disk and bandwidth of a
 * DirectAdmin account have never been recorded at all.
 */
function daServer(array $attributes = []): Server
{
    return Server::factory()->create(array_merge([
        'type' => 'directadmin',
        'hostname' => 'da.test',
        'ip_address' => '',
        'port' => 2222,
        'username' => 'admin',
        'password' => 'admin-secret',
        'access_hash' => '',
    ], $attributes));
}

function daService(Server $server, array $attributes = []): Service
{
    $client = Client::factory()->create();
    $group = ProductGroup::factory()->create();
    $product = Product::factory()->create([
        'group_id' => $group->id,
        'server_type' => 'directadmin',
        'config_options' => json_encode(['package_name' => 'bronze']),
    ]);

    $service = Service::factory()->create(array_merge([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'order_id' => Order::factory()->create(['client_id' => $client->id])->id,
        'domain' => 'shop.test',
        'status' => 'active',
        'username' => 'shopuser',
    ], $attributes));

    $service->forceFill(['module_data' => ['da_username' => $service->username]])->save();

    return $service;
}

it('tells directadmin what to do when moving an account onto a package', function () {
    Http::fake(['*' => Http::response('error=0&text=ok', 200)]);

    $server = daServer();
    $service = daService($server);

    $result = (new DirectAdminModule)->changePackage($service, [
        'config_options' => json_encode(['package_name' => 'gold']),
    ]);

    expect($result['success'])->toBeTrue();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'CMD_API_MODIFY_USER')) {
            return false;
        }

        $body = $request->data();

        return ($body['action'] ?? null) === 'package'
            && ($body['user'] ?? null) === 'shopuser'
            && ($body['package'] ?? null) === 'gold';
    });
});

it('records what a directadmin account is using', function () {
    Http::fake([
        '*CMD_API_SHOW_USER_USAGE*' => Http::response('quota=512&bandwidth=2048', 200),
        '*CMD_API_SHOW_USER_CONFIG*' => Http::response('quota=1024&bandwidth=10240&suspended=no', 200),
    ]);

    $server = daServer();
    $service = daService($server);

    $totals = (new DirectAdminModule)->usageUpdate($server);

    expect($totals)->toBe(['updated' => 1, 'errors' => 0]);

    $fresh = $service->fresh();

    expect($fresh->disk_usage)->toBe(512)
        ->and($fresh->bw_usage)->toBe(2048)
        ->and($fresh->disk_limit)->toBe(1024)
        ->and($fresh->bw_limit)->toBe(10240);
});

it('counts an account directadmin will not answer for as an error, not an update', function () {
    Http::fake(['*' => Http::response('error=1&text=User does not exist', 200)]);

    $server = daServer();
    daService($server);

    expect((new DirectAdminModule)->usageUpdate($server))->toBe(['updated' => 0, 'errors' => 1]);
});

it('leaves unlimited alone rather than storing it as a number', function () {
    Http::fake([
        '*CMD_API_SHOW_USER_USAGE*' => Http::response('quota=512&bandwidth=2048', 200),
        '*CMD_API_SHOW_USER_CONFIG*' => Http::response('quota=unlimited&bandwidth=unlimited', 200),
    ]);

    $server = daServer();
    $service = daService($server, ['disk_limit' => 0, 'bw_limit' => 0]);

    (new DirectAdminModule)->usageUpdate($server);

    expect($service->fresh()->disk_limit)->toBe(0)
        ->and($service->fresh()->bw_limit)->toBe(0);
});

it('offers the packages the directadmin server has', function () {
    Http::fake(['*CMD_API_PACKAGES_USER*' => Http::response('list[]=bronze&list[]=gold', 200)]);

    expect(array_column((new DirectAdminModule)->listPackages(daServer()), 'id'))->toBe(['bronze', 'gold']);
});

it('gives directadmin a username it will accept', function () {
    Http::fake(['*' => Http::response('error=0&text=ok', 200)]);

    $server = daServer();
    $service = daService($server, ['username' => null, 'domain' => '2-Shop.Example.com']);

    expect((new DirectAdminModule)->create($service)['success'])->toBeTrue();

    $username = $service->fresh()->username;

    // Derived from the domain the way the cPanel and Plesk modules derive
    // theirs, not "u" and a row id: lower case, starting with a letter, and
    // within the length DirectAdmin accepts.
    expect($username)->toMatch('/^[a-z][a-z0-9]{3,9}$/')
        ->and($username)->toStartWith('shop');
});

it('asks whm to change the database passwords with the account password', function () {
    Http::fake(['*' => Http::response(['metadata' => ['result' => 1, 'reason' => 'OK']], 200)]);

    $server = Server::factory()->create([
        'type' => 'cpanel', 'hostname' => 'whm.test', 'ip_address' => '',
        'port' => 2087, 'username' => 'root', 'password' => 'x', 'access_hash' => 'tok',
    ]);

    $client = Client::factory()->create();
    $group = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id, 'server_type' => 'cpanel']);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id, 'server_id' => $server->id,
        'order_id' => Order::factory()->create(['client_id' => $client->id])->id,
        'domain' => 'shop.test', 'status' => 'active', 'username' => 'shopuser',
    ]);

    expect((new CPanelModule)->changePassword($service, 'Nw-Pass-42x')['success'])->toBeTrue();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'passwd')) {
            return false;
        }

        $body = $request->data();

        // Sources disagree on whether the field is pass or password; sending
        // both costs nothing and one of them is right on every version.
        return ($body['user'] ?? null) === 'shopuser'
            && ($body['pass'] ?? null) === 'Nw-Pass-42x'
            && ($body['password'] ?? null) === 'Nw-Pass-42x'
            && (string) ($body['db_pass_update'] ?? '') === '1';
    });
});

it('tells whm what to do with the dns zone when an account goes', function () {
    Http::fake(['*' => Http::response(['metadata' => ['result' => 1, 'reason' => 'OK']], 200)]);

    $server = Server::factory()->create([
        'type' => 'cpanel', 'hostname' => 'whm.test', 'ip_address' => '',
        'port' => 2087, 'username' => 'root', 'password' => 'x', 'access_hash' => 'tok',
    ]);

    $client = Client::factory()->create();
    $group = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id, 'server_type' => 'cpanel']);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id, 'server_id' => $server->id,
        'order_id' => Order::factory()->create(['client_id' => $client->id])->id,
        'domain' => 'shop.test', 'status' => 'active', 'username' => 'shopuser',
    ]);

    expect((new CPanelModule)->terminate($service)['success'])->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'removeacct')
        && (string) ($request->data()['keepdns'] ?? '') === '0');
});
