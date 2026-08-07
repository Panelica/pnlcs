<?php

use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;

function fixSvc(string $type, array $serverAttrs = [], array $serviceAttrs = [], array $productConfig = []): Service
{
    $server  = Server::factory()->create(array_merge(['type' => $type, 'hostname' => "{$type}.test", 'access_hash' => 'secret-key'], $serverAttrs));
    $group   = ProductGroup::factory()->create();
    $product = Product::factory()->create([
        'group_id'       => $group->id,
        'server_type'    => $type,
        'config_options' => $productConfig ? json_encode($productConfig) : null,
    ]);
    $client  = Client::factory()->create();
    $order   = Order::factory()->create(['client_id' => $client->id]);

    return Service::factory()->create(array_merge([
        'client_id'  => $client->id,
        'product_id' => $product->id,
        'server_id'  => $server->id,
        'order_id'   => $order->id,
        'domain'     => 'example-fix.com',
        'status'     => 'pending',
        'username'   => null,
        'password'   => null,
        'notes'      => null,
    ], $serviceAttrs));
}

// ---------------------------------------------------------------------------
// cPanel
// ---------------------------------------------------------------------------

test('cpanel create uses POST, persists the generated password and omits unset plan', function () {
    Http::fake(['*/json-api/*' => Http::response(['metadata' => ['result' => 1, 'reason' => 'OK']], 200)]);

    $service = fixSvc('cpanel');
    $result  = (new \Modules\Servers\CPanel\CPanelModule())->create($service->fresh(['product', 'server', 'client']));

    expect($result['success'])->toBeTrue();

    $fresh = $service->fresh();
    expect($fresh->password)->not->toBeNull()
        ->and($fresh->username)->not->toBeNull()
        ->and(preg_match('/^[a-z]/', $fresh->username))->toBe(1);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), 'createacct')
            && !array_key_exists('plan', $request->data());
    });
});

test('cpanel create retries with a suffixed username when taken', function () {
    $calls = 0;
    Http::fake(function () use (&$calls) {
        $calls++;
        if ($calls === 1) {
            return Http::response(['metadata' => ['result' => 0, 'reason' => 'This username already exists']], 200);
        }
        return Http::response(['metadata' => ['result' => 1, 'reason' => 'OK']], 200);
    });

    $service = fixSvc('cpanel');
    $result  = (new \Modules\Servers\CPanel\CPanelModule())->create($service->fresh(['product', 'server', 'client']));

    expect($result['success'])->toBeTrue()
        ->and($calls)->toBe(2)
        ->and($service->fresh()->username)->toEndWith('1');
});

// ---------------------------------------------------------------------------
// Plesk
// ---------------------------------------------------------------------------

test('plesk create posts to /domains with required ftp credentials and owner_client', function () {
    Http::fake([
        '*/api/v2/clients' => Http::response(['id' => 77], 201),
        '*/api/v2/domains' => Http::response(['id' => 88], 201),
    ]);

    $service = fixSvc('plesk', [], [], ['package_name' => 'Basic']);
    $result  = (new \Modules\Servers\Plesk\PleskModule())->create($service->fresh(['product', 'server', 'client']));

    expect($result['success'])->toBeTrue()
        ->and($service->fresh()->username)->not->toBeNull()
        ->and($service->fresh()->password)->not->toBeNull();

    Http::assertSent(function ($request) {
        if (!str_contains($request->url(), '/api/v2/domains')) {
            return false;
        }
        $data = $request->data();
        return ($data['hosting_type'] ?? null) === 'virtual'
            && !empty($data['hosting_settings']['ftp_login'])
            && !empty($data['hosting_settings']['ftp_password'])
            && ($data['owner_client']['id'] ?? null) === 77
            && ($data['plan']['name'] ?? null) === 'Basic';
    });
});

test('plesk suspend and unsuspend use the dedicated endpoints', function () {
    Http::fake(['*' => Http::response(null, 200)]);

    $service = fixSvc('plesk', [], ['notes' => json_encode(['plesk_client_id' => 42, 'plesk_domain_id' => 43])]);
    $module = new \Modules\Servers\Plesk\PleskModule();

    expect($module->suspend($service->fresh(['server']), 'nonpayment')['success'])->toBeTrue();
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/clients/42/suspend') && $r->method() === 'PUT');

    expect($module->unsuspend($service->fresh(['server']))['success'])->toBeTrue();
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/clients/42/activate') && $r->method() === 'PUT');
});

test('plesk usage pulls client statistics and updates the service', function () {
    Http::fake(['*/statistics' => Http::response(['disk_space' => 104857600, 'traffic' => 52428800], 200)]);

    $service = fixSvc('plesk', [], [
        'status' => 'active',
        'notes'  => json_encode(['plesk_client_id' => 42]),
    ]);

    $totals = (new \Modules\Servers\Plesk\PleskModule())->usageUpdate($service->server);

    expect($totals['updated'])->toBe(1)
        ->and($service->fresh()->disk_usage)->toBe(100)
        ->and($service->fresh()->bw_usage)->toBe(50);
});

// ---------------------------------------------------------------------------
// HestiaCP
// ---------------------------------------------------------------------------

test('hestia failing command output is a failure, not silent success', function () {
    // Old bug: with returncode=no an error STRING was int-cast to 0 → success.
    Http::fake(['*' => Http::response('Error: user totoro exists', 200)]);

    $service = fixSvc('hestiacp');
    $result  = (new \Modules\Servers\HestiaCP\HestiaCPModule())->create($service->fresh(['product', 'server', 'client']));

    expect($result['success'])->toBeFalse()
        ->and($service->fresh()->status)->toBe('pending');
});

test('hestia nonzero exit code is a failure', function () {
    Http::fake(['*' => Http::response('5', 200)]);
    $service = fixSvc('hestiacp');

    $result = (new \Modules\Servers\HestiaCP\HestiaCPModule())->create($service->fresh(['product', 'server', 'client']));

    expect($result['success'])->toBeFalse();
});

test('hestia zero exit code succeeds and persists credentials', function () {
    Http::fake(['*' => Http::response('0', 200)]);
    $service = fixSvc('hestiacp');

    $result = (new \Modules\Servers\HestiaCP\HestiaCPModule())->create($service->fresh(['product', 'server', 'client']));

    expect($result['success'])->toBeTrue()
        ->and($service->fresh()->password)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// DirectAdmin
// ---------------------------------------------------------------------------

test('directadmin html login page response is treated as auth failure', function () {
    Http::fake(['*' => Http::response('<html><body>Login</body></html>', 200)]);

    $service = fixSvc('directadmin', ['username' => 'admin', 'password' => 'wrong']);
    $result  = (new \Modules\Servers\DirectAdmin\DirectAdminModule())->create($service->fresh(['product', 'server', 'client']));

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('authentication failed');
});

test('directadmin suspend sends the canonical button parameters', function () {
    Http::fake(['*' => Http::response('error=0&text=Success', 200)]);

    $service = fixSvc('directadmin', ['username' => 'admin'], ['notes' => json_encode(['da_username' => 'user1'])]);
    (new \Modules\Servers\DirectAdmin\DirectAdminModule())->suspend($service->fresh(['server']), 'test');

    Http::assertSent(function ($request) {
        $data = $request->data();
        return str_contains($request->url(), 'CMD_API_SELECT_USERS')
            && ($data['suspend'] ?? null) === 'Suspend'
            && ($data['select0'] ?? null) === 'user1';
    });
});

test('directadmin create persists generated credentials', function () {
    Http::fake(['*' => Http::response('error=0', 200)]);

    $service = fixSvc('directadmin', ['username' => 'admin']);
    $result  = (new \Modules\Servers\DirectAdmin\DirectAdminModule())->create($service->fresh(['product', 'server', 'client']));

    expect($result['success'])->toBeTrue()
        ->and($service->fresh()->username)->not->toBeNull()
        ->and($service->fresh()->password)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Vultr
// ---------------------------------------------------------------------------

test('vultr usage lists all instances without the broken tag filter and updates limits', function () {
    $service = fixSvc('vultr', [], [
        'status' => 'active',
        'notes'  => json_encode(['vultr_instance_id' => 'abc-123']),
    ]);

    Http::fake(['*api.vultr.com/v2/instances*' => Http::response([
        'instances' => [[
            'id' => 'abc-123', 'disk' => 25, 'ram' => 1024, 'vcpu_count' => 1, 'main_ip' => '203.0.113.5',
        ]],
    ], 200)]);

    $totals = (new \Modules\Servers\Vultr\VultrModule())->usageUpdate($service->server);

    Http::assertSent(fn ($r) => !str_contains($r->url(), 'tag='));

    $fresh = $service->fresh();
    expect($totals['updated'])->toBe(1)
        ->and($fresh->disk_limit)->toBe(25 * 1024)
        ->and(json_decode($fresh->notes, true)['vultr_main_ip'])->toBe('203.0.113.5');
});

// ---------------------------------------------------------------------------
// Proxmox
// ---------------------------------------------------------------------------

test('proxmox api calls are form-encoded for pre-7.2 compatibility', function () {
    Http::fake(['*' => Http::response(['data' => 'UPID:ok'], 200)]);

    $service = fixSvc('proxmox', ['access_hash' => 'PVEAPIToken=root@pam!x=y'], [
        'notes' => json_encode(['proxmox_vmid' => 100, 'proxmox_node' => 'pve', 'proxmox_type' => 'qemu']),
    ]);

    (new \Modules\Servers\Proxmox\ProxmoxModule())->unsuspend($service->fresh(['server']));

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/status/start')
            && str_contains($request->header('Content-Type')[0] ?? '', 'application/x-www-form-urlencoded');
    });
});
