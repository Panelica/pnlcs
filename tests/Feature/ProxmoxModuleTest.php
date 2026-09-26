<?php

use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Modules\Servers\Proxmox\ProxmoxClient;
use Modules\Servers\Proxmox\ProxmoxModule;
use Modules\Servers\Proxmox\ProxmoxPlan;
use Tests\Support\FakeProxmox;

/*
 * The Proxmox module against an in-memory cluster (tests/Support/FakeProxmox).
 *
 * The rule every destructive test checks: PNLCS touches only guests that
 * carry its mark for the service in question. The VM id in the billing
 * records is not proof - a wrong one must never stop or delete a machine the
 * operator runs for other reasons.
 */

function pveServer(array $settings = [], array $attrs = []): Server
{
    return Server::factory()->create(array_merge([
        'type' => 'proxmox',
        'name' => 'PVE lab',
        'hostname' => 'pve.example.com',
        'ip_address' => '',
        'port' => 8006,
        'username' => 'pnlcs@pve!billing',
        'password' => '',
        'access_hash' => '11111111-2222-3333-4444-555555555555',
        'settings' => $settings ?: null,
    ], $attrs));
}

function pveService(Server $server, array $config = [], array $moduleData = [], array $attrs = []): Service
{
    $client = Client::factory()->create();
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'type' => 'vps',
        'server_type' => 'proxmox',
        'config_options' => json_encode(array_merge([
            'pve_type' => 'qemu', 'pve_template' => '9000', 'pve_storage' => 'local-lvm', 'pve_bridge' => 'vmbr0',
            'pve_cores' => 2, 'pve_memory' => 2048, 'pve_disk' => 20,
        ], $config)),
    ]);

    $service = Service::factory()->create(array_merge([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'order_id' => Order::factory()->create(['client_id' => $client->id])->id,
        'domain' => 'vps1.example.com',
        'status' => 'pending',
        'username' => null,
        'password' => null,
    ], $attrs));

    if ($moduleData) {
        $service->forceFill(['module_data' => $moduleData])->save();
    }

    return $service->fresh(['server', 'product']);
}

/** A guest PNLCS made for this service, as it would be after create. */
function pveOwnedGuest(FakeProxmox $pve, Service $service, int $vmid, string $type = 'qemu', string $status = 'running', array $config = []): void
{
    $pve->guests[$vmid] = [
        'node' => 'pve', 'type' => $type, 'template' => 0, 'status' => $status,
        'config' => array_merge([
            'name' => 'vps1', 'cores' => 2, 'memory' => 2048,
            ($type === 'lxc' ? 'rootfs' : 'scsi0') => "local-lvm:vm-{$vmid}-disk-0,size=20G",
            'boot' => 'order=scsi0', 'ide2' => "local-lvm:vm-{$vmid}-cloudinit,media=cdrom",
            'net0' => $type === 'lxc' ? 'name=eth0,bridge=vmbr0,hwaddr=BC:24:11:12:34:56,ip=dhcp' : 'virtio=BC:24:11:12:34:56,bridge=vmbr0',
            'tags' => 'pnlcs;pnlcs-s'.$service->id, 'protection' => 1,
            'description' => "Managed by PNLCS\npnlcs:service={$service->id}",
        ], $config),
    ];
    $service->forceFill(['module_data' => ['proxmox_vmid' => $vmid, 'proxmox_node' => 'pve', 'proxmox_type' => $type], 'status' => 'active'])->save();
}

// ---------------------------------------------------------------------------
// Signing in
// ---------------------------------------------------------------------------

it('builds the token header from every shape an operator might paste', function () {
    $secret = '11111111-2222-3333-4444-555555555555';

    expect(ProxmoxClient::tokenHeader('pnlcs@pve!billing', $secret))->toBe("PVEAPIToken=pnlcs@pve!billing={$secret}")
        ->and(ProxmoxClient::tokenHeader('', "PVEAPIToken=root@pam!x={$secret}"))->toBe("PVEAPIToken=root@pam!x={$secret}")
        ->and(ProxmoxClient::tokenHeader('', "root@pam!test={$secret}"))->toBe("PVEAPIToken=root@pam!test={$secret}")
        // As Proxmox shows it: the ID and the secret next to each other.
        ->and(ProxmoxClient::tokenHeader('root@pam', "root@pam!test {$secret}"))->toBe("PVEAPIToken=root@pam!test={$secret}")
        // A password login has no token.
        ->and(ProxmoxClient::tokenHeader('root@pam', ''))->toBeNull()
        // The secret alone with a plain user name cannot be a token.
        ->and(ProxmoxClient::tokenHeader('root@pam', $secret))->toBeNull();
});

it('sends the token on every call', function () {
    $pve = FakeProxmox::install();
    (new ProxmoxModule)->diagnose(pveServer());

    expect(collect($pve->log)->pluck('auth')->unique()->all())
        ->toBe(['PVEAPIToken=pnlcs@pve!billing=11111111-2222-3333-4444-555555555555']);
});

// ---------------------------------------------------------------------------
// Creating
// ---------------------------------------------------------------------------

it('clones the template into the pool, marks it, sizes it and starts it', function () {
    $pve = FakeProxmox::install()->template(9000);
    $server = pveServer(['pool' => 'pnlcs']);
    $service = pveService($server);

    $result = (new ProxmoxModule)->create($service);

    expect($result['success'])->toBeTrue($result['message']);
    $vmid = $result['data']['vmid'];
    $clone = $pve->sent('POST', 'nodes/pve/qemu/9000/clone')[0]['params'];
    expect($clone)->toMatchArray(['newid' => $vmid, 'full' => 1, 'storage' => 'local-lvm', 'pool' => 'pnlcs', 'name' => 'vps1.example.com']);

    $guest = $pve->guests[$vmid];
    expect($guest['status'])->toBe('running')
        ->and($guest['config']['tags'])->toContain('pnlcs-s'.$service->id)
        ->and($guest['config']['description'])->toContain('pnlcs:service='.$service->id)
        ->and($guest['config']['scsi0'])->toContain('size=20G')
        ->and((int) $guest['config']['cores'])->toBe(2)
        ->and((int) $guest['config']['memory'])->toBe(2048)
        ->and($guest['config']['ipconfig0'])->toBe('ip=dhcp')
        ->and((int) $guest['config']['ciupgrade'])->toBe(0)
        ->and((int) $guest['config']['protection'])->toBe(1)
        // The clone got a MAC of its own, and it is kept.
        ->and($guest['config']['net0'])->toStartWith('virtio=BC:24:11:00:20:');

    $service->refresh();
    expect($service->module_data)->toMatchArray(['proxmox_vmid' => $vmid, 'proxmox_node' => 'pve', 'proxmox_type' => 'qemu', 'pve_state' => 'ready'])
        ->and($service->username)->toBe('root')
        ->and($guest['config']['cipassword'])->toBe($service->password);
});

it('does not make a second machine when a failed create is retried', function () {
    $pve = FakeProxmox::install()->template(9000);
    $service = pveService(pveServer());
    $pve->fail('PUT nodes/pve/qemu/\d+/config', 500, 'got timeout');

    $first = (new ProxmoxModule)->create($service);
    expect($first['success'])->toBeFalse()->and($first['message'])->toContain('got timeout');

    $second = (new ProxmoxModule)->create($service->fresh(['server', 'product']));

    expect($second['success'])->toBeTrue($second['message'])
        ->and($pve->sent('POST', 'nodes/pve/qemu/9000/clone'))->toHaveCount(1)
        ->and(collect($pve->guests)->where('template', 0))->toHaveCount(1);
});

it('keeps to its own VM id range and asks about ids it cannot see', function () {
    $pve = FakeProxmox::install()->template(9000)->foreignGuest(5000)->foreignGuest(5001);
    $service = pveService(pveServer(['vmid_min' => 5000, 'vmid_max' => 5010]));

    $result = (new ProxmoxModule)->create($service);

    expect($result['data']['vmid'])->toBe(5002);
});

it('gives each server its own address from the pool and takes it back when the service ends', function () {
    $pve = FakeProxmox::install()->template(9000);
    $server = pveServer(['ipv4_pool' => "203.0.113.1-203.0.113.3/24 gw 203.0.113.1\n"]);
    $a = pveService($server, ['pve_ipv4' => 'pool']);
    $b = pveService($server, ['pve_ipv4' => 'pool']);

    (new ProxmoxModule)->create($a);
    (new ProxmoxModule)->create($b);

    $ipA = $a->fresh()->module_data['pve_ipv4'];
    $ipB = $b->fresh()->module_data['pve_ipv4'];
    expect($ipA)->toBe('203.0.113.2')->and($ipB)->toBe('203.0.113.3');
    expect($pve->guests[$a->fresh()->module_data['proxmox_vmid']]['config']['ipconfig0'])->toBe('ip=203.0.113.2/24,gw=203.0.113.1');

    // Pool used up: the third order is refused, not given a taken address.
    $c = pveService($server, ['pve_ipv4' => 'pool']);
    $third = (new ProxmoxModule)->create($c);
    expect($third['success'])->toBeFalse()->and($third['message'])->toContain('no free address');

    // The first service ends; its address is free again.
    $a->fresh()->forceFill(['status' => 'terminated'])->save();
    expect((new ProxmoxModule)->create($c->fresh(['server', 'product']))['success'])->toBeTrue();
    expect($c->fresh()->module_data['pve_ipv4'])->toBe('203.0.113.2');
});

it('creates an unprivileged container with the marks, the pool and a fixed address', function () {
    $pve = FakeProxmox::install();
    $server = pveServer(['pool' => 'pnlcs', 'ipv4_pool' => '198.51.100.10/28 gw 198.51.100.1']);
    $service = pveService($server, [
        'pve_type' => 'lxc', 'pve_ostemplate' => 'local:vztmpl/debian-12-standard_12.7-1_amd64.tar.zst',
        'pve_ipv4' => 'pool', 'pve_nesting' => 1, 'pve_memory' => 512, 'pve_disk' => 8,
    ]);

    $result = (new ProxmoxModule)->create($service);

    expect($result['success'])->toBeTrue($result['message']);
    $body = $pve->sent('POST', 'nodes/pve/lxc')[0]['params'];
    expect($body)->toMatchArray([
        'unprivileged' => 1, 'pool' => 'pnlcs', 'rootfs' => 'local-lvm:8', 'memory' => 512, 'features' => 'nesting=1',
        'ostemplate' => 'local:vztmpl/debian-12-standard_12.7-1_amd64.tar.zst',
    ])->and($body['tags'])->toBe('pnlcs;pnlcs-s'.$service->id)
        ->and($body['net0'])->toBe('name=eth0,bridge=vmbr0,ip=198.51.100.10/28,gw=198.51.100.1')
        ->and($body['password'])->toBe($service->fresh()->password);
    expect($pve->guests[$result['data']['vmid']]['status'])->toBe('running');
});

it('says what Proxmox said when it refuses to create', function () {
    FakeProxmox::install()->template(9000)
        ->fail('POST nodes/pve/qemu/9000/clone', 403, 'Permission check failed (/pool/pnlcs, VM.Allocate)');

    $result = (new ProxmoxModule)->create(pveService(pveServer(['pool' => 'pnlcs'])));

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Permission check failed (/pool/pnlcs, VM.Allocate)');
});

it('reports a clone task that failed after it started', function () {
    $pve = FakeProxmox::install()->template(9000);
    $pve->nextTaskFails('clone failed: storage local-lvm full');

    $result = (new ProxmoxModule)->create(pveService(pveServer()));

    expect($result['success'])->toBeFalse()->and($result['message'])->toContain('storage local-lvm full');
});

// ---------------------------------------------------------------------------
// The mark: nothing is touched without it
// ---------------------------------------------------------------------------

it('refuses to delete a guest that is not marked for the service', function () {
    $pve = FakeProxmox::install()->foreignGuest(100);
    $service = pveService(pveServer(), [], ['proxmox_vmid' => 100, 'proxmox_node' => 'pve', 'proxmox_type' => 'qemu'], ['status' => 'active']);

    $result = (new ProxmoxModule)->terminate($service);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('pnlcs-s'.$service->id)
        ->and($pve->guests)->toHaveKey(100)
        ->and($pve->guests[100]['status'])->toBe('running')
        ->and($pve->sent('DELETE', '.*'))->toBe([])
        ->and($pve->sent('POST', '.*status/.*'))->toBe([]);
});

it('refuses a guest marked for a different service', function () {
    $pve = FakeProxmox::install();
    $server = pveServer();
    $mine = pveService($server);
    $other = pveService($server);
    pveOwnedGuest($pve, $other, 700);
    $mine->forceFill(['module_data' => ['proxmox_vmid' => 700, 'proxmox_node' => 'pve', 'proxmox_type' => 'qemu'], 'status' => 'active'])->save();

    foreach (['terminate', 'suspend', 'reboot'] as $action) {
        expect((new ProxmoxModule)->{$action}($mine->fresh(['server', 'product']))['success'])->toBeFalse();
    }
    expect($pve->guests[700]['status'])->toBe('running')->and($pve->sent('DELETE', '.*'))->toBe([]);
});

it('removes its own guest: stops it, lifts the protection, deletes it with its disks', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer());
    pveOwnedGuest($pve, $service, 701);

    $result = (new ProxmoxModule)->terminate($service->fresh(['server', 'product']));

    expect($result['success'])->toBeTrue($result['message'])
        ->and($pve->guests)->not->toHaveKey(701)
        ->and($pve->sent('DELETE', 'nodes/pve/qemu/701')[0]['params'])->toMatchArray(['purge' => '1', 'destroy-unreferenced-disks' => '1']);
});

it('treats a guest that is already gone as removed', function () {
    FakeProxmox::install();
    $service = pveService(pveServer(), [], ['proxmox_vmid' => 702, 'proxmox_node' => 'pve', 'proxmox_type' => 'qemu'], ['status' => 'active']);

    expect((new ProxmoxModule)->terminate($service)['success'])->toBeTrue();
});

it('finds a guest that moved to another node', function () {
    $pve = FakeProxmox::install();
    $pve->nodes['pve2'] = ['status' => 'online', 'mem' => 1, 'maxmem' => 2];
    $service = pveService(pveServer());
    pveOwnedGuest($pve, $service, 703);
    $pve->guests[703]['node'] = 'pve2';

    $status = (new ProxmoxModule)->vmStatus($service->fresh(['server', 'product']));

    expect($status['available'])->toBeTrue()->and($status['node'])->toBe('pve2')
        ->and($service->fresh()->module_data['proxmox_node'])->toBe('pve2');
});

// ---------------------------------------------------------------------------
// Suspension and power
// ---------------------------------------------------------------------------

it('keeps a suspended guest off across a host reboot, and brings it back', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer());
    pveOwnedGuest($pve, $service, 710, 'qemu', 'running', ['onboot' => 1]);

    expect((new ProxmoxModule)->suspend($service->fresh(['server', 'product']))['success'])->toBeTrue();
    expect($pve->guests[710]['status'])->toBe('stopped')
        ->and((int) $pve->guests[710]['config']['onboot'])->toBe(0)
        ->and($pve->sent('POST', 'nodes/pve/qemu/710/status/shutdown')[0]['params'])->toMatchArray(['forceStop' => 1]);

    expect((new ProxmoxModule)->unsuspend($service->fresh(['server', 'product']))['success'])->toBeTrue();
    expect($pve->guests[710]['status'])->toBe('running')->and((int) $pve->guests[710]['config']['onboot'])->toBe(1);
});

it('waits for a power action and reports its outcome', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer());
    pveOwnedGuest($pve, $service, 711, 'qemu', 'stopped');
    $pve->nextTaskFails('start failed: not enough memory');

    $result = (new ProxmoxModule)->power($service->fresh(['server', 'product']), 'start');

    expect($result['success'])->toBeFalse()->and($result['message'])->toContain('not enough memory');
});

// ---------------------------------------------------------------------------
// Passwords, plan changes, reinstall
// ---------------------------------------------------------------------------

it('sets a password through the guest agent at once when it runs', function () {
    $pve = FakeProxmox::install();
    $pve->agentRunning = true;
    $service = pveService(pveServer());
    pveOwnedGuest($pve, $service, 720);

    $result = (new ProxmoxModule)->changePassword($service->fresh(['server', 'product']), 'N3w-passw0rd!');

    expect($result['success'])->toBeTrue()->and($result['data'])->not->toHaveKey('needs_reboot')
        ->and($pve->sent('POST', 'nodes/pve/qemu/720/agent/set-user-password')[0]['params'])->toMatchArray(['username' => 'root'])
        ->and($service->fresh()->password)->toBe('N3w-passw0rd!');
});

it('falls back to cloud-init and says a reboot is needed', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer());
    pveOwnedGuest($pve, $service, 721);

    $result = (new ProxmoxModule)->changePassword($service->fresh(['server', 'product']), 'N3w-passw0rd!');

    expect($result['success'])->toBeTrue()->and($result['data']['needs_reboot'])->toBeTrue()
        ->and($pve->guests[721]['config']['cipassword'])->toBe('N3w-passw0rd!');
});

it('tells the truth about container passwords instead of sending a setting Proxmox rejects', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer(), ['pve_type' => 'lxc', 'pve_ostemplate' => 'local:vztmpl/x.tar.zst']);
    pveOwnedGuest($pve, $service, 722, 'lxc');

    $result = (new ProxmoxModule)->changePassword($service->fresh(['server', 'product']), 'N3w-passw0rd!');

    expect($result['success'])->toBeFalse()->and($result['message'])->toContain('passwd')
        ->and($pve->sent('PUT', 'nodes/pve/lxc/722/config'))->toBe([]);
});

it('says so when proxmox refuses to enlarge the disk on an upgrade', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer());
    pveOwnedGuest($pve, $service, 730);
    $pve->fail('PUT nodes/pve/qemu/730/resize', 500, 'unable to allocate space: storage full');

    $result = (new ProxmoxModule)->changePackage($service->fresh(['server', 'product']), ['config_options' => json_encode(['pve_cores' => 4, 'pve_memory' => 8192, 'pve_disk' => 100])]);

    expect($result['success'])->toBeFalse()->and($result['message'])->toContain('storage full');
});

it('upgrades cores, memory and disk and never shrinks the disk', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer());
    pveOwnedGuest($pve, $service, 731);

    $up = (new ProxmoxModule)->changePackage($service->fresh(['server', 'product']), ['config_options' => ['pve_cores' => 4, 'pve_memory' => 8192, 'pve_disk' => 100]]);
    expect($up['success'])->toBeTrue()
        ->and((int) $pve->guests[731]['config']['cores'])->toBe(4)
        ->and((int) $pve->guests[731]['config']['memory'])->toBe(8192)
        ->and($pve->guests[731]['config']['scsi0'])->toContain('size=100G');

    (new ProxmoxModule)->changePackage($service->fresh(['server', 'product']), ['config_options' => ['pve_cores' => 1, 'pve_memory' => 1024, 'pve_disk' => 10]]);
    expect($pve->sent('PUT', 'nodes/pve/qemu/731/resize'))->toHaveCount(1)
        ->and($pve->guests[731]['config']['scsi0'])->toContain('size=100G');
});

it('reinstalls in place: same VM id, same MAC, new password', function () {
    $pve = FakeProxmox::install()->template(9000)->template(9001, 'ubuntu24-cloud');
    $service = pveService(pveServer(), ['pve_os_choices' => [['id' => '9001', 'name' => 'Ubuntu 24.04']]]);
    pveOwnedGuest($pve, $service, 740);

    $result = (new ProxmoxModule)->reinstall($service->fresh(['server', 'product']), '9001', 'Fresh-passw0rd');

    expect($result['success'])->toBeTrue($result['message'])
        ->and($pve->guests)->toHaveKey(740)
        ->and($pve->sent('POST', 'nodes/pve/qemu/9001/clone')[0]['params']['newid'])->toBe(740)
        ->and($pve->guests[740]['config']['net0'])->toStartWith('virtio=BC:24:11:12:34:56')
        ->and($pve->guests[740]['config']['cipassword'])->toBe('Fresh-passw0rd')
        ->and($pve->guests[740]['status'])->toBe('running')
        ->and($service->fresh()->module_data['pve_image'])->toBe('9001');
});

it('refuses to reinstall with an image the plan does not offer', function () {
    $pve = FakeProxmox::install()->template(9000)->template(9005);
    $service = pveService(pveServer());
    pveOwnedGuest($pve, $service, 741);

    $result = (new ProxmoxModule)->reinstall($service->fresh(['server', 'product']), '9005', 'Fresh-passw0rd');

    expect($result['success'])->toBeFalse()->and($pve->guests)->toHaveKey(741)->and($pve->sent('DELETE', '.*'))->toBe([]);
});

// ---------------------------------------------------------------------------
// Linking an existing guest
// ---------------------------------------------------------------------------

it('links an unmarked guest to a service and marks it', function () {
    $pve = FakeProxmox::install()->foreignGuest(750);
    $service = pveService(pveServer(), [], [], ['status' => 'active']);

    $result = (new ProxmoxModule)->claim($service, 750);

    expect($result['success'])->toBeTrue()
        ->and($pve->guests[750]['config']['tags'])->toContain('pnlcs-s'.$service->id)
        ->and($service->fresh()->module_data['proxmox_vmid'])->toBe(750);
});

it('will not link a guest another service already has', function () {
    $pve = FakeProxmox::install();
    $server = pveServer();
    $owner = pveService($server);
    pveOwnedGuest($pve, $owner, 751);
    $thief = pveService($server, [], [], ['status' => 'active']);

    expect((new ProxmoxModule)->claim($thief, 751)['message'])->toContain('#'.$owner->id);

    // Unmarked, but the other service's records point at it.
    $pve->guests[751]['config']['tags'] = '';
    $pve->guests[751]['config']['description'] = '';
    expect((new ProxmoxModule)->claim($thief, 751)['success'])->toBeFalse();
});

it('will not link a template', function () {
    FakeProxmox::install()->template(9000);

    expect((new ProxmoxModule)->claim(pveService(pveServer()), 9000)['success'])->toBeFalse();
});

// ---------------------------------------------------------------------------
// The Test button
// ---------------------------------------------------------------------------

it('catches a token that signs in but has no permissions, and prints the fix', function () {
    $pve = FakeProxmox::install()->template(9000);
    $pve->permissions = [];

    $report = (new ProxmoxModule)->diagnose(pveServer());

    expect($report['ok'])->toBeFalse()
        ->and(collect($report['checks'])->pluck('text')->implode(' '))->toContain('no permissions at all')
        ->and($report['commands'])->toContain('pveum role add PNLCS')
        ->and($report['commands'])->toContain('pveum user token add pnlcs@pve billing --privsep 0');
});

it('warns when the token can do far more than billing needs', function () {
    $pve = FakeProxmox::install()->template(9000);
    $pve->permissions['/'] += ['Sys.Modify' => 1, 'Permissions.Modify' => 1];

    $report = (new ProxmoxModule)->diagnose(pveServer());

    expect($report['ok'])->toBeTrue()
        ->and(collect($report['checks'])->where('level', 'warn')->pluck('text')->implode(' '))->toContain('Sys.Modify');
});

it('passes a token limited to its pool, and reads inherited and exact rights correctly', function () {
    $pve = FakeProxmox::install()->template(9000);
    $pve->pools = ['pnlcs'];
    $all = $pve->permissions['/'];
    $pve->permissions = [
        '/pool/pnlcs' => array_intersect_key($all, array_flip(array_filter(array_keys($all), fn ($p) => str_starts_with($p, 'VM.') || $p === 'Pool.Audit'))),
        '/storage/local-lvm' => ['Datastore.AllocateSpace' => 1, 'Datastore.Audit' => 1],
        '/sdn/zones/localnetwork' => ['SDN.Use' => 1],
        '/nodes' => ['Sys.Audit' => 1],
    ];

    $report = (new ProxmoxModule)->diagnose(pveServer(['pool' => 'pnlcs']));

    expect($report['ok'])->toBeTrue(json_encode($report['checks']))
        ->and(collect($report['checks'])->where('level', 'fail'))->toBeEmpty();
});

it('fails a token that cannot use the bridge', function () {
    $pve = FakeProxmox::install()->template(9000);
    unset($pve->permissions['/']['SDN.Use']);

    $report = (new ProxmoxModule)->diagnose(pveServer());

    expect($report['ok'])->toBeFalse()->and(collect($report['checks'])->pluck('text')->implode(' '))->toContain('SDN.Use');
});

it('names the node when the configured one does not exist', function () {
    FakeProxmox::install();

    $report = (new ProxmoxModule)->diagnose(pveServer(['node' => 'pve9']));

    expect($report['ok'])->toBeFalse()->and(collect($report['checks'])->pluck('text')->implode(' '))->toContain('"pve9"');
});

// ---------------------------------------------------------------------------
// Plans and usage
// ---------------------------------------------------------------------------

it('lets the customer\'s configurable options choose memory and operating system', function () {
    FakeProxmox::install();
    $service = pveService(pveServer());
    $group = \App\Models\ConfigOptionGroup::create(['name' => 'VPS']);
    $memory = \App\Models\ConfigOption::create(['group_id' => $group->id, 'option_name' => 'memory|RAM', 'option_type' => 'dropdown']);
    $four = \App\Models\ConfigOptionSub::create(['config_id' => $memory->id, 'option_name' => '4096|4 GB']);
    $os = \App\Models\ConfigOption::create(['group_id' => $group->id, 'option_name' => 'os|Operating system', 'option_type' => 'dropdown']);
    $ubuntu = \App\Models\ConfigOptionSub::create(['config_id' => $os->id, 'option_name' => '9001|Ubuntu 24.04']);
    $cores = \App\Models\ConfigOption::create(['group_id' => $group->id, 'option_name' => 'cores|CPU cores', 'option_type' => 'quantity']);
    \App\Models\ServiceConfigOption::create(['service_id' => $service->id, 'config_id' => $memory->id, 'option_id' => $four->id, 'qty' => 1]);
    \App\Models\ServiceConfigOption::create(['service_id' => $service->id, 'config_id' => $os->id, 'option_id' => $ubuntu->id, 'qty' => 1]);
    \App\Models\ServiceConfigOption::create(['service_id' => $service->id, 'config_id' => $cores->id, 'qty' => 6]);

    $plan = ProxmoxPlan::forService($service);

    expect($plan->memory)->toBe(4096)->and($plan->template)->toBe('9001')->and($plan->cores)->toBe(6)->and($plan->disk)->toBe(20);
});

it('reads products set up for the first version of the module', function () {
    $service = pveService(pveServer(), ['pve_type' => null, 'pve_template' => null, 'pve_cores' => null, 'pve_memory' => null, 'pve_disk' => null,
        'type' => 'lxc', 'os_template' => 'local:vztmpl/old.tar.zst', 'cores' => 3, 'memory' => 3072, 'disk' => 30]);

    $plan = ProxmoxPlan::forService($service);

    expect($plan->isLxc())->toBeTrue()->and($plan->ostemplate)->toBe('local:vztmpl/old.tar.zst')
        ->and($plan->cores)->toBe(3)->and($plan->memory)->toBe(3072)->and($plan->disk)->toBe(30);
});

it('bills traffic from the month\'s averages and counts a vanished guest', function () {
    $pve = FakeProxmox::install();
    $server = pveServer();
    $kept = pveService($server, ['pve_bandwidth' => 500]);
    pveOwnedGuest($pve, $kept, 760);
    $gone = pveService($server, [], ['proxmox_vmid' => 761, 'proxmox_node' => 'pve', 'proxmox_type' => 'qemu'], ['status' => 'active']);

    $result = (new ProxmoxModule)->usageUpdate($server);

    expect($result)->toBe(['updated' => 1, 'errors' => 1]);
    $kept->refresh();
    expect($kept->bw_limit)->toBe(500 * 1024)->and($kept->disk_limit)->toBe(20480)->and($kept->bw_usage)->toBeGreaterThanOrEqual(0);
});

// ---------------------------------------------------------------------------
// The customer's controls
// ---------------------------------------------------------------------------

function pveCustomer(Service $service): \App\Models\User
{
    $user = \App\Models\User::factory()->create(['email_verified_at' => now()]);
    $user->clients()->attach($service->client_id);

    return $user;
}

it('shows the owner the server and refuses everyone else', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer());
    pveOwnedGuest($pve, $service, 770);

    test()->actingAs(pveCustomer($service))->getJson(route('client.services.vps.status', $service))
        ->assertOk()->assertJson(['available' => true, 'vmid' => 770, 'status' => 'running']);

    $stranger = pveService(pveServer());
    test()->actingAs(pveCustomer($stranger))->getJson(route('client.services.vps.status', $service))->assertForbidden();
});

it('lets the customer reboot, but not switch on a suspended server', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer());
    pveOwnedGuest($pve, $service, 771);
    $user = pveCustomer($service);

    test()->actingAs($user)->postJson(route('client.services.vps.power', $service), ['action' => 'reboot'])->assertOk()->assertJson(['success' => true]);
    test()->actingAs($user)->postJson(route('client.services.vps.power', $service), ['action' => 'destroy'])->assertUnprocessable();

    $service->forceFill(['status' => 'suspended'])->save();
    $pve->guests[771]['status'] = 'stopped';
    test()->actingAs($user)->postJson(route('client.services.vps.power', $service), ['action' => 'start'])->assertStatus(409);
    expect($pve->guests[771]['status'])->toBe('stopped');
    // Reading still works while suspended.
    test()->actingAs($user)->getJson(route('client.services.vps.status', $service))->assertOk();
});

it('reinstalls only when the customer types the confirmation', function () {
    $pve = FakeProxmox::install()->template(9000);
    $service = pveService(pveServer());
    pveOwnedGuest($pve, $service, 772);
    $user = pveCustomer($service);

    test()->actingAs($user)->postJson(route('client.services.vps.reinstall', $service), ['image' => '9000', 'confirm' => 'yes'])
        ->assertUnprocessable();
    expect($pve->sent('DELETE', '.*'))->toBe([]);

    test()->actingAs($user)->postJson(route('client.services.vps.reinstall', $service), ['image' => '9000', 'confirm' => 'vps1.example.com'])
        ->assertOk()->assertJsonPath('success', true)->assertJsonStructure(['data' => ['password']]);
});

it('puts the virtual server panel on the service page', function () {
    $pve = FakeProxmox::install()->template(9000);
    $service = pveService(pveServer());
    pveOwnedGuest($pve, $service, 773);

    test()->actingAs(pveCustomer($service))->get(route('client.services.show', $service))
        ->assertOk()->assertSee('id="vps"', false)->assertSee('data-power="reboot"', false)
        ->assertSee(json_encode(route('client.services.vps.power', $service)), false)
        ->assertSee('id="vps-reinstall-form"', false);
});

// ---------------------------------------------------------------------------
// The admin screens
// ---------------------------------------------------------------------------

function pveAdmin(): \App\Models\Admin
{
    return \App\Models\Admin::factory()->create(['role_id' => \App\Models\AdminRole::factory()->fullAdmin()->create()->id]);
}

it('saves a Proxmox server with its node, pool, id range and addresses, and no nameservers', function () {
    FakeProxmox::install();

    test()->actingAs(pveAdmin(), 'admin')->post(route('admin.config.servers.store'), [
        'name' => 'PVE 1', 'hostname' => 'pve1.example.com', 'type' => 'proxmox', 'port' => 8006, 'active' => 1,
        'username' => 'pnlcs@pve!billing', 'access_hash' => '11111111-2222-3333-4444-555555555555',
        'settings' => ['node' => 'pve', 'pool' => 'pnlcs', 'vmid_min' => 5000, 'vmid_max' => 5999,
            'ipv4_pool' => "203.0.113.10-203.0.113.20/24 gw 203.0.113.1\r\n"],
    ])->assertSessionHasNoErrors();

    $server = Server::where('name', 'PVE 1')->firstOrFail();
    expect($server->settings)->toMatchArray(['node' => 'pve', 'pool' => 'pnlcs', 'vmid_min' => 5000, 'vmid_max' => 5999,
        'ipv4_pool' => '203.0.113.10-203.0.113.20/24 gw 203.0.113.1', 'verify_tls' => false]);
});

it('refuses an address pool it cannot read, and a backwards id range', function () {
    FakeProxmox::install();
    $base = ['name' => 'PVE 2', 'hostname' => 'pve2.example.com', 'type' => 'proxmox', 'active' => 1,
        'username' => 'pnlcs@pve!billing', 'access_hash' => 'x'];

    test()->actingAs(pveAdmin(), 'admin')->post(route('admin.config.servers.store'), $base + ['settings' => ['ipv4_pool' => 'lots of addresses']])
        ->assertSessionHasErrors('settings');
    test()->actingAs(pveAdmin(), 'admin')->post(route('admin.config.servers.store'), $base + ['settings' => ['vmid_min' => 900, 'vmid_max' => 800]])
        ->assertSessionHasErrors('settings');

    expect(Server::where('name', 'PVE 2')->exists())->toBeFalse();
});

it('shows the full check with the fix when the Test button finds a powerless token', function () {
    $pve = FakeProxmox::install();
    $pve->permissions = [];
    $server = pveServer();

    // The port check reaches out on its own; stand a listener up for it.
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    $server->update(['hostname' => '127.0.0.1', 'port' => $port]);

    test()->actingAs(pveAdmin(), 'admin')->post(route('admin.config.servers.test', $server))
        ->assertSessionHas('server_report', fn ($r) => $r['ok'] === false && str_contains($r['commands'], 'pveum acl modify'));
    fclose($socket);

    test()->actingAs(pveAdmin(), 'admin')->withSession(['server_report' => ['server_id' => $server->id, 'name' => 'PVE lab', 'ok' => false,
        'version' => '9.1.1', 'identity' => 'x', 'checks' => [['level' => 'fail', 'text' => 'no permissions at all']], 'commands' => 'pveum role add PNLCS']])
        ->get(route('admin.config.servers'))->assertOk()->assertSee('no permissions at all')->assertSee('pveum role add PNLCS');
});

it('fills the product form lists from the cluster', function () {
    FakeProxmox::install()->template(9000);
    $server = pveServer();

    test()->actingAs(pveAdmin(), 'admin')->getJson(route('admin.products.proxmox-catalog', ['server_id' => $server->id]))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('node', 'pve')
        ->assertJsonPath('templates.0.id', '9000')
        ->assertJsonPath('ostemplates.0.id', 'local:vztmpl/debian-12-standard_12.7-1_amd64.tar.zst')
        ->assertJsonPath('bridges.0.id', 'vmbr0')
        ->assertJsonPath('storages.0.id', 'local-lvm');
});

it('saves the Proxmox settings of a product, and checks them before saving anything', function () {
    FakeProxmox::install();
    $service = pveService(pveServer());
    $product = $service->product;
    $admin = pveAdmin();
    $form = [
        'name' => 'VPS S', 'group_id' => $product->group_id, 'type' => 'vps', 'pay_type' => 'recurring', 'server_type' => 'proxmox',
        'pve_section' => 1, 'pve_type' => 'lxc', 'pve_storage' => 'local-lvm', 'pve_bridge' => 'vmbr0',
        'pve_cores' => 2, 'pve_memory' => 1024, 'pve_disk' => 15, 'pve_ipv4' => 'pool',
    ];

    // A container without a template: refused, and the name is not changed either.
    test()->actingAs($admin, 'admin')->put(route('admin.products.update', $product), $form)->assertSessionHasErrors('pve_ostemplate');
    expect($product->fresh()->name)->not->toBe('VPS S');

    test()->actingAs($admin, 'admin')->put(route('admin.products.update', $product), $form + [
        'pve_ostemplate' => 'local:vztmpl/debian-12-standard_12.7-1_amd64.tar.zst', 'pve_nesting' => 1,
        'pve_os_choices' => ['local:vztmpl/debian-12-standard_12.7-1_amd64.tar.zst'],
        'pve_os_names' => ['local:vztmpl/debian-12-standard_12.7-1_amd64.tar.zst' => 'Debian 12'],
    ])->assertSessionHasNoErrors();

    $cfg = json_decode((string) $product->fresh()->getRawOriginal('config_options'), true) ?: $product->fresh()->config_options;
    expect($cfg)->toMatchArray(['pve_type' => 'lxc', 'pve_disk' => 15, 'pve_ipv4' => 'pool', 'pve_nesting' => 1])
        ->and($cfg['pve_os_choices'])->toBe([['id' => 'local:vztmpl/debian-12-standard_12.7-1_amd64.tar.zst', 'name' => 'Debian 12']]);

    test()->actingAs($admin, 'admin')->get(route('admin.products.edit', $product))
        ->assertOk()->assertSee('data-module-card="proxmox"', false)->assertSee('Debian 12');
});

it('lets the admin link an existing guest and run power actions from the service page', function () {
    $pve = FakeProxmox::install()->foreignGuest(780, 'qemu', 'stopped');
    $service = pveService(pveServer(), [], [], ['status' => 'active']);
    $admin = pveAdmin();

    test()->actingAs($admin, 'admin')->post(route('admin.services.module-action', [$service, 'pve_claim']), ['vmid' => 780])
        ->assertSessionHas('success');
    test()->actingAs($admin, 'admin')->post(route('admin.services.module-action', [$service, 'pve_start']))
        ->assertSessionHas('success');

    expect($pve->guests[780]['status'])->toBe('running');
    test()->actingAs($admin, 'admin')->get(route('admin.services.show', $service))->assertOk()->assertSee('id="pve-admin"', false);
    test()->actingAs($admin, 'admin')->getJson(route('admin.services.vps-status', $service))->assertOk()->assertJsonPath('vmid', 780);
});

// ---------------------------------------------------------------------------
// Snapshots
// ---------------------------------------------------------------------------

it('takes snapshots up to the plan\'s number and no further', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer(), ['pve_snapshots' => 2]);
    pveOwnedGuest($pve, $service, 790);
    $module = new ProxmoxModule;

    expect($module->snapshotCreate($service->fresh(['server', 'product']), 'before-upgrade')['success'])->toBeTrue()
        ->and($module->snapshotCreate($service->fresh(['server', 'product']), 'second')['success'])->toBeTrue();

    $third = $module->snapshotCreate($service->fresh(['server', 'product']), 'third');
    expect($third['success'])->toBeFalse()->and($third['message'])->toContain('2')
        ->and(array_keys($pve->snapshots[790]))->toBe(['before-upgrade', 'second'])
        ->and($module->snapshotCreate($service->fresh(['server', 'product']), '1bad name')['success'])->toBeFalse();
});

it('rolls back and starts again a guest that was running', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer(), ['pve_snapshots' => 3]);
    pveOwnedGuest($pve, $service, 791);
    $pve->snapshots[791] = ['clean' => [1790000000, '']];

    $result = (new ProxmoxModule)->snapshotRollback($service->fresh(['server', 'product']), 'clean');

    expect($result['success'])->toBeTrue()
        ->and($pve->sent('POST', 'nodes/pve/qemu/791/snapshot/clean/rollback'))->toHaveCount(1)
        ->and($pve->guests[791]['status'])->toBe('running')
        ->and($service->fresh()->module_data['pve_job'] ?? null)->toBeNull();
});

it('offers no snapshots on a plan without them', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer());
    pveOwnedGuest($pve, $service, 792);

    expect((new ProxmoxModule)->snapshotCreate($service->fresh(['server', 'product']), 'nope')['success'])->toBeFalse()
        ->and((new ProxmoxModule)->vpsFeatures($service->fresh(['server', 'product'])))->not->toContain('snapshots');
});

// ---------------------------------------------------------------------------
// Backups
// ---------------------------------------------------------------------------

it('backs up to the server\'s backup storage, lists only this guest\'s backups, and keeps to the limit', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer(['backup_storage' => 'backup-nfs']), ['pve_backups' => 2]);
    pveOwnedGuest($pve, $service, 800);
    $pve->foreignGuest(801);
    $pve->backup(801);
    $module = new ProxmoxModule;

    $first = $module->backupCreate($service->fresh(['server', 'product']));
    expect($first['success'])->toBeTrue()
        ->and($pve->sent('POST', 'nodes/pve/vzdump')[0]['params'])->toMatchArray(['vmid' => 800, 'storage' => 'backup-nfs', 'mode' => 'snapshot', 'notes-template' => 'PNLCS service '.$service->id]);

    $list = $module->backups($service->fresh(['server', 'product']));
    expect($list)->toHaveCount(1)->and($list[0]['volid'])->toContain('vzdump-qemu-800-');

    $module->backupCreate($service->fresh(['server', 'product']));
    expect($module->backupCreate($service->fresh(['server', 'product']))['success'])->toBeFalse()
        ->and($module->vpsFeatures($service->fresh(['server', 'product'])))->toContain('backups');
});

it('restores a backup: lifts the protection first, brings the settings back and starts the guest again', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer(['backup_storage' => 'backup-nfs']), ['pve_backups' => 3]);
    pveOwnedGuest($pve, $service, 810);
    $volid = $pve->backup(810);
    $pve->snapshots[810] = ['old' => [1, '']];

    $result = (new ProxmoxModule)->backupRestore($service->fresh(['server', 'product']), $volid);

    expect($result['success'])->toBeTrue($result['message'])
        ->and($pve->restored)->toBe([$volid])
        ->and($pve->sent('PUT', 'nodes/pve/qemu/810/config')[0]['params'])->toMatchArray(['protection' => 0])
        ->and((int) $pve->guests[810]['config']['protection'])->toBe(1)
        ->and($pve->guests[810]['status'])->toBe('running')
        ->and($pve->snapshots[810])->toBe([]);
});

it('will not restore or delete a backup of another guest', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer(['backup_storage' => 'backup-nfs']), ['pve_backups' => 3]);
    pveOwnedGuest($pve, $service, 820);
    $pve->foreignGuest(821);
    $theirs = $pve->backup(821);
    $module = new ProxmoxModule;

    expect($module->backupRestore($service->fresh(['server', 'product']), $theirs)['success'])->toBeFalse()
        ->and($module->backupDelete($service->fresh(['server', 'product']), $theirs)['success'])->toBeFalse()
        ->and($pve->restored)->toBe([])
        ->and(collect($pve->volumes['backup-nfs'])->pluck('volid'))->toContain($theirs);
});

it('will not delete a protected backup', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer(['backup_storage' => 'backup-nfs']), ['pve_backups' => 3]);
    pveOwnedGuest($pve, $service, 830);
    $kept = $pve->backup(830, protected: true);
    $gone = $pve->backup(830);
    $module = new ProxmoxModule;

    expect($module->backupDelete($service->fresh(['server', 'product']), $kept)['success'])->toBeFalse()
        ->and($module->backupDelete($service->fresh(['server', 'product']), $gone)['success'])->toBeTrue()
        ->and(collect($pve->volumes['backup-nfs'])->pluck('volid')->all())->toBe([$kept]);
});

it('reports a restore Proxmox refused and puts the protection back', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer(['backup_storage' => 'backup-nfs']), ['pve_backups' => 3]);
    pveOwnedGuest($pve, $service, 840);
    $volid = $pve->backup(840);
    $pve->nextTaskFails('unable to restore VM 840 - no space left on device');
    // The stop is the first task; fail the one after it.
    $pve->guests[840]['status'] = 'stopped';

    $result = (new ProxmoxModule)->backupRestore($service->fresh(['server', 'product']), $volid);

    expect($result['success'])->toBeFalse()->and($result['message'])->toContain('no space left')
        ->and((int) $pve->guests[840]['config']['protection'])->toBe(1)
        ->and($service->fresh()->module_data['pve_state'])->toBe('restore_failed');
});

// ---------------------------------------------------------------------------
// Work that outlasts a web request
// ---------------------------------------------------------------------------

it('starts a reinstall without waiting for it, refuses other work meanwhile, and the scheduler finishes it', function () {
    config(['pnlcs.proxmox_wait_cap' => 0]);
    $pve = FakeProxmox::install()->template(9000);
    $service = pveService(pveServer());
    pveOwnedGuest($pve, $service, 850);
    $pve->holdTasks = true;

    $started = (new ProxmoxModule)->reinstall($service->fresh(['server', 'product']), '9000', 'Later-passw0rd');

    expect($started['success'])->toBeTrue()->and($started['data'])->toMatchArray(['job' => 'reinstall'])
        ->and($service->fresh()->module_data['pve_job']['step'])->toBe('stopping');

    $status = (new ProxmoxModule)->vmStatus($service->fresh(['server', 'product']));
    expect($status)->toMatchArray(['busy' => true, 'job' => 'reinstall']);
    expect((new ProxmoxModule)->power($service->fresh(['server', 'product']), 'reboot')['success'])->toBeFalse();

    // Proxmox finishes each task; the minute job sends the next: remove, clone, start.
    $pve->holdTasks = false;
    $steps = [];
    for ($i = 0; $i < 3; $i++) {
        test()->artisan('pnlcs:proxmox-tasks')->assertSuccessful();
        $steps[] = $service->fresh()->module_data['pve_job']['step'] ?? 'done';
    }
    expect($steps)->toBe(['removing', 'creating', 'done']);

    $service->refresh();
    expect($service->module_data['pve_job'] ?? null)->toBeNull()
        ->and($service->module_data['pve_state'])->toBe('ready')
        ->and($pve->guests[850]['status'])->toBe('running')
        ->and($pve->guests[850]['config']['cipassword'])->toBe('Later-passw0rd')
        ->and($pve->guests[850]['config']['net0'])->toStartWith('virtio=BC:24:11:12:34:56');
});

it('shows the customer the job while it runs and the reason when it fails', function () {
    config(['pnlcs.proxmox_wait_cap' => 0]);
    $pve = FakeProxmox::install()->template(9000);
    $service = pveService(pveServer());
    pveOwnedGuest($pve, $service, 860);
    $user = pveCustomer($service);
    $pve->holdTasks = true;

    test()->actingAs($user)->postJson(route('client.services.vps.reinstall', $service), ['image' => '9000', 'confirm' => 'vps1.example.com'])
        ->assertOk()->assertJsonPath('success', true);
    test()->actingAs($user)->getJson(route('client.services.vps.status', $service))
        ->assertOk()->assertJson(['busy' => true, 'job' => 'reinstall']);

    // The clone fails.
    $pve->holdTasks = false;
    test()->artisan('pnlcs:proxmox-tasks');
    $pve->nextTaskFails('clone failed: storage full');
    $pve->guests[860] = null;
    unset($pve->guests[860]);
    $upid = $service->fresh()->module_data['pve_job']['upid'];
    $pve->tasks[$upid] = 'clone failed: storage full';
    test()->artisan('pnlcs:proxmox-tasks');

    test()->actingAs($user)->getJson(route('client.services.vps.status', $service))
        ->assertOk()->assertJsonPath('available', false)->assertJsonFragment(['error' => __('proxmox.error.job_failed', ['step' => __('proxmox.job.reinstall'), 'error' => 'clone failed: storage full'])]);
});

// ---------------------------------------------------------------------------
// The customer's snapshot and backup pages
// ---------------------------------------------------------------------------

it('lets the customer list and take snapshots, and asks for the name before a restore', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer(['backup_storage' => 'backup-nfs']), ['pve_snapshots' => 1, 'pve_backups' => 1]);
    pveOwnedGuest($pve, $service, 870);
    $volid = $pve->backup(870);
    $user = pveCustomer($service);

    test()->actingAs($user)->postJson(route('client.services.vps.snapshots.action', $service), ['action' => 'create', 'name' => 'first'])->assertOk();
    test()->actingAs($user)->postJson(route('client.services.vps.snapshots.action', $service), ['action' => 'create', 'name' => 'x y'])->assertUnprocessable();
    test()->actingAs($user)->getJson(route('client.services.vps.snapshots', $service))
        ->assertOk()->assertJsonPath('limit', 1)->assertJsonPath('snapshots.0.name', 'first');

    test()->actingAs($user)->getJson(route('client.services.vps.backups', $service))->assertOk()->assertJsonPath('backups.0.volid', $volid);
    test()->actingAs($user)->postJson(route('client.services.vps.backups.action', $service), ['action' => 'restore', 'volid' => $volid, 'confirm' => 'yes'])
        ->assertUnprocessable();
    expect($pve->restored)->toBe([]);
});

it('checks the backup storage on the Test button', function () {
    $pve = FakeProxmox::install()->template(9000);

    $ok = (new ProxmoxModule)->diagnose(pveServer(['backup_storage' => 'backup-nfs']));
    $missing = (new ProxmoxModule)->diagnose(pveServer(['backup_storage' => 'local-lvm']));

    expect(collect($ok['checks'])->pluck('text')->implode(' '))->toContain('backup-nfs')
        ->and($missing['ok'])->toBeFalse();
});
