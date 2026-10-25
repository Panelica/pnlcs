<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Server;
use App\Services\NotificationService;
use Modules\Servers\Proxmox\ProxmoxCapacity;
use Tests\Support\FakeProxmox;

/*
 * What a Proxmox server has, and what its guests have been given (#303):
 * the admin page, and the alert past the server's capacity line.
 */

require_once __DIR__.'/../Support/proxmox_helpers.php';

/** A node with 8 threads, 32 GB and 300 GB of guest storage; one PNLCS guest and one made by hand. */
function pveCapacityLab(array $settings = []): array
{
    $pve = FakeProxmox::install();
    $pve->storages[2]['total'] = 300 * 1073741824;
    $pve->storages[2]['used'] = 40 * 1073741824;
    $server = pveServer($settings);
    pveOwnedGuest($pve, pveService($server), 101, config: ['cores' => 4, 'memory' => 16384]);
    $pve->guests[102] = ['node' => 'pve', 'type' => 'qemu', 'template' => 0, 'status' => 'stopped', 'config' => ['name' => 'ours-by-hand', 'cores' => 2, 'memory' => 8192]];
    $pve->guests[9000] = ['node' => 'pve', 'type' => 'qemu', 'template' => 1, 'status' => 'stopped', 'config' => ['name' => 'debian-12-cloud', 'cores' => 2, 'memory' => 2048]];

    return [$pve, $server];
}

function capacityAlerts(): ArrayObject
{
    $seen = new ArrayObject;
    $spy = Mockery::mock(NotificationService::class);
    $spy->shouldReceive('dispatch')->andReturnUsing(function ($event, $data = []) use ($seen) {
        $seen[] = ['event' => $event] + $data;
    });
    app()->instance(NotificationService::class, $spy);

    return $seen;
}

it('counts what the guests on a node have been given, not what the host uses now, and leaves templates out', function () {
    [, $server] = pveCapacityLab();

    $node = ProxmoxCapacity::for($server)->overview()['nodes'][0];

    expect($node['node'])->toBe('pve')
        ->and($node['cpu'])->toMatchArray(['total' => 8, 'given' => 6, 'ours' => 4])
        ->and($node['memory'])->toMatchArray(['total' => 32 * 1073741824, 'given' => 24 * 1073741824, 'ours' => 16 * 1073741824, 'used' => 4 * 1073741824])
        ->and($node['disk'])->toMatchArray(['total' => 300 * 1073741824, 'given' => 40 * 1073741824, 'used' => 40 * 1073741824])
        ->and($node['share'])->toBe(['cpu' => 75.0, 'memory' => 75.0, 'disk' => 13.3])
        ->and($node['guests'])->toBe(['running' => 1, 'stopped' => 1, 'ours' => 1]);
});

it('tells staff once a day per node and resource when the line is passed, never for CPU', function () {
    [, $server] = pveCapacityLab(['capacity_alert' => 70]);
    $seen = capacityAlerts();

    test()->artisan('pnlcs:proxmox-capacity')->assertSuccessful();
    test()->artisan('pnlcs:proxmox-capacity')->assertSuccessful();

    expect(collect($seen)->pluck('event')->unique()->all())->toBe(['server.capacity_high'])
        ->and(collect($seen)->pluck('subject')->all())->toHaveCount(1)
        ->and($seen[0]['subject'])->toContain('memory at 75')
        ->and($seen[0]['server_id'])->toBe($server->id);
});

it('stays quiet under the line, and when the line is 0', function (int $line) {
    pveCapacityLab(['capacity_alert' => $line]);
    $seen = capacityAlerts();

    test()->artisan('pnlcs:proxmox-capacity')->assertSuccessful();

    expect($seen)->toHaveCount(0);
})->with([80, 0]);

it('offers the event to notification rules', function () {
    expect(NotificationService::eventTypes())->toContain('server.capacity_high');
});

it('gives the admin a capacity page for each Proxmox server, behind the server permission', function () {
    [, $server] = pveCapacityLab();
    $admin = pveAdmin();

    test()->actingAs($admin, 'admin')->get(route('admin.config.servers'))->assertOk()
        ->assertSee(route('admin.config.servers.capacity', $server), false);
    test()->actingAs($admin, 'admin')->get(route('admin.config.servers.capacity', $server))->assertOk()
        ->assertSee('75%')->assertSee('24.0 GB');

    $viewer = Admin::factory()->create(['role_id' => AdminRole::factory()->create(['permissions' => ['view_services']])->id]);
    test()->actingAs($viewer, 'admin')->get(route('admin.config.servers.capacity', $server))->assertForbidden();

    $panelica = Server::factory()->create(['type' => 'panelica']);
    test()->actingAs($admin, 'admin')->get(route('admin.config.servers.capacity', $panelica))->assertNotFound();
});

it('shows what went wrong when Proxmox cannot be read', function () {
    [$pve, $server] = pveCapacityLab();
    $pve->fail('GET nodes', 401, 'authentication failure');

    $o = ProxmoxCapacity::for($server)->overview();

    expect($o['ok'])->toBeFalse()->and($o['error'])->not->toBe('');
});

it('saves the capacity line with the server, empty meaning the default', function () {
    $server = pveServer();
    $admin = pveAdmin();
    $form = fn ($v) => ['name' => $server->name, 'hostname' => $server->hostname, 'ip_address' => $server->ip_address, 'type' => 'proxmox', 'port' => 8006,
        'username' => 'root@pam!pnlcs', 'max_accounts' => 100, 'active' => 1, 'settings' => ['node' => 'pve', 'capacity_alert' => $v]];

    test()->actingAs($admin, 'admin')->put(route('admin.config.servers.update', $server), $form('90'))->assertRedirect();
    expect($server->fresh()->setting('capacity_alert'))->toBe(90)
        ->and(ProxmoxCapacity::for($server->fresh())->threshold())->toBe(90);

    test()->actingAs($admin, 'admin')->put(route('admin.config.servers.update', $server), $form(''))->assertRedirect();
    expect(ProxmoxCapacity::for($server->fresh())->threshold())->toBe(80);
});
