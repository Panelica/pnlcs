<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Modules\Servers\CPanel\CPanelModule;

/**
 * What the panel knows about a cPanel account's usage.
 *
 * It asked accountsummary once per account. That endpoint carries the disk
 * figures and no bandwidth at all - checked against a live WHM: bandwidth and
 * bwlimit both come back null - so bw_usage and bw_limit were never written
 * for any cPanel service. The customer's bandwidth graph stayed at zero, and
 * bandwidth overage, which bills from that field, could not fire however much
 * the customer used.
 *
 * WHM has it in showbw, in bytes, with a limit of zero meaning unlimited.
 */
function usageServer(): Server
{
    return Server::factory()->create([
        'type' => 'cpanel',
        'hostname' => 'whm.example.test',
        'ip_address' => null,
        'port' => 2087,
        'username' => 'root',
        'access_hash' => 'TOKEN',
        'active' => true,
    ]);
}

function hostedService(Server $server, string $username): Service
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

function fakeUsageApi(): void
{
    Http::fake([
        '*listaccts*' => Http::response([
            'metadata' => ['result' => 1],
            'data' => ['acct' => [
                ['user' => 'shopuser', 'diskused' => '512M', 'disklimit' => '1024M'],
            ]],
        ], 200),
        '*showbw*' => Http::response([
            'metadata' => ['result' => 1],
            'data' => ['acct' => [
                // 2 GB used against a 10 GB limit, both in bytes.
                ['user' => 'shopuser', 'totalbytes' => 2147483648, 'limit' => '10737418240'],
            ]],
        ], 200),
    ]);
}

test('bandwidth is recorded, in megabytes', function () {
    $server = usageServer();
    $service = hostedService($server, 'shopuser');
    fakeUsageApi();

    app(CPanelModule::class)->usageUpdate($server);

    expect((int) $service->fresh()->bw_usage)->toBe(2048)
        ->and((int) $service->fresh()->bw_limit)->toBe(10240);
});

test('disk is recorded too', function () {
    $server = usageServer();
    $service = hostedService($server, 'shopuser');
    fakeUsageApi();

    app(CPanelModule::class)->usageUpdate($server);

    expect((int) $service->fresh()->disk_usage)->toBe(512)
        ->and((int) $service->fresh()->disk_limit)->toBe(1024);
});

test('one call each, not one per account', function () {
    $server = usageServer();
    hostedService($server, 'shopuser');
    hostedService($server, 'otheruser');
    fakeUsageApi();

    app(CPanelModule::class)->usageUpdate($server);

    Http::assertSentCount(2);
});

test('an unlimited bandwidth allowance is not written as zero', function () {
    $server = usageServer();
    $service = hostedService($server, 'shopuser');

    Http::fake([
        '*listaccts*' => Http::response(['metadata' => ['result' => 1], 'data' => ['acct' => []]], 200),
        '*showbw*' => Http::response([
            'metadata' => ['result' => 1],
            'data' => ['acct' => [['user' => 'shopuser', 'totalbytes' => 1048576, 'limit' => 0]]],
        ], 200),
    ]);

    $service->update(['bw_limit' => 5000]);

    app(CPanelModule::class)->usageUpdate($server);

    expect((int) $service->fresh()->bw_usage)->toBe(1)
        ->and((int) $service->fresh()->bw_limit)->toBe(5000);
});

test('an account that is no longer on the server is counted as an error', function () {
    $server = usageServer();
    hostedService($server, 'ghostuser');
    fakeUsageApi();

    $result = app(CPanelModule::class)->usageUpdate($server);

    expect($result['errors'])->toBe(1)
        ->and($result['updated'])->toBe(0);
});
