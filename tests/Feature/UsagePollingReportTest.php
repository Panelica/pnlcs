<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;

/**
 * The hourly usage run reporting that it did nothing.
 *
 * Every server module answers usageUpdate with ['updated' => n, 'errors' => n]
 * and writes the figures onto the services itself. This command still expected
 * the shape from before that - a list of rows keyed by username, which it would
 * then write itself - so it walked an array of two integers, matched nothing,
 * and told the operator "updated 0 service(s)" every hour while the modules
 * were quietly doing the work.
 *
 * The errors the modules count - an account the server no longer has, a VM
 * deleted on the hypervisor, a service with no remote id recorded - were
 * dropped on the floor. Those are exactly what an operator needs to be told.
 */
function pollingServer(): Server
{
    return Server::factory()->create([
        'type' => 'cpanel',
        'name' => 'whm-1',
        'hostname' => 'whm.polling.test',
        'ip_address' => '',
        'port' => 2087,
        'username' => 'root',
        'access_hash' => 'TOKEN',
        'active' => true,
    ]);
}

function polledService(Server $server, string $username): Service
{
    return Service::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'product_id' => Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id])->id,
        'server_id' => $server->id,
        'status' => 'active',
        'domain' => $username.'.example',
        'username' => $username,
    ]);
}

function fakePollingApi(
    array $accounts = [['user' => 'shopuser', 'diskused' => '512M', 'disklimit' => '1024M']],
    array $bandwidth = [['user' => 'shopuser', 'totalbytes' => 2147483648, 'limit' => 10737418240]]
): void {
    Http::fake([
        '*listaccts*' => Http::response([
            'metadata' => ['result' => 1],
            'data' => ['acct' => $accounts],
        ], 200),
        '*showbw*' => Http::response([
            'metadata' => ['result' => 1],
            'data' => ['acct' => $bandwidth],
        ], 200),
        '*' => Http::response(['metadata' => ['result' => 1]], 200),
    ]);
}

it('reports the services the module actually updated', function () {
    fakePollingApi();

    $server = pollingServer();
    $service = polledService($server, 'shopuser');

    $this->artisan('pnlcs:usage-polling')
        ->expectsOutputToContain('updated 1')
        ->assertSuccessful();

    expect($service->fresh()->disk_usage)->toBe(512);
});

it('tells the operator about the accounts the server could not answer for', function () {
    // Neither listing knows anything about this account: the module counts it.
    fakePollingApi([], []);

    $server = pollingServer();
    polledService($server, 'shopuser');

    $this->artisan('pnlcs:usage-polling')
        ->expectsOutputToContain('1 error')
        ->assertSuccessful();
});

it('says so when a server has no module of its own', function () {
    Server::factory()->create(['type' => 'nosuchpanel', 'name' => 'orphan', 'active' => true]);

    $this->artisan('pnlcs:usage-polling')
        ->expectsOutputToContain('No module found')
        ->assertSuccessful();
});

it('carries on to the next server when one of them throws', function () {
    Http::fake(fn () => throw new RuntimeException('connection refused'));

    $first = pollingServer();
    polledService($first, 'shopuser');

    $this->artisan('pnlcs:usage-polling')->assertSuccessful();
});
