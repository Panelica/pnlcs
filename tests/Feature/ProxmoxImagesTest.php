<?php

use Modules\Servers\Proxmox\ProxmoxImages;
use Modules\Servers\Proxmox\ProxmoxModule;
use Tests\Support\FakeProxmox;

/*
 * The image library: official cloud images and container templates put on a
 * Proxmox server from the admin panel, through the API.
 */

require_once __DIR__.'/../Support/proxmox_helpers.php';

/** A cluster whose token may fetch images, with a storage that holds imports. */
function pveImageLab(array $settings = ['pool' => 'pnlcs', 'vmid_min' => 9000, 'vmid_max' => 9099]): array
{
    $pve = FakeProxmox::install();
    $pve->pools = ['pnlcs'];
    $pve->storages[0]['content'] = 'iso,vztmpl,backup,import';
    $pve->permissions = [
        '/pool/pnlcs' => array_fill_keys(['VM.Allocate', 'VM.Audit', 'VM.Clone', 'VM.Config.Disk'], 1),
        '/storage/local' => array_fill_keys(['Datastore.AllocateTemplate', 'Datastore.AllocateSpace', 'Datastore.Audit'], 1),
        '/nodes' => array_fill_keys(['Sys.AccessNetwork', 'Sys.Audit'], 1),
    ];

    return [$pve, pveServer($settings)];
}

it('lists the cloud images and the amd64 system containers, and says which are already there', function () {
    [$pve, $server] = pveImageLab();
    $pve->volumes['local'][] = ['volid' => 'local:vztmpl/ubuntu-24.04-standard_24.04-2_amd64.tar.zst', 'content' => 'vztmpl', 'size' => 1];

    $o = ProxmoxImages::for($server)->overview();

    expect($o['ok'])->toBeTrue()
        ->and($o['missing'])->toBe([])
        ->and($o['import_storages'])->toBe(['local'])
        ->and(collect($o['kvm'])->pluck('id')->all())->toBe(['debian-12', 'debian-13', 'ubuntu-24.04', 'ubuntu-22.04', 'almalinux-9', 'rocky-9'])
        ->and(collect($o['lxc'])->pluck('id')->all())->toBe(['debian-12-standard_12.12-1_amd64.tar.zst', 'ubuntu-24.04-standard_24.04-2_amd64.tar.zst'])
        ->and(collect($o['lxc'])->firstWhere('id', 'ubuntu-24.04-standard_24.04-2_amd64.tar.zst')['installed'])->toBeTrue();
});

it('says which rights the token lacks for the library, and prints the commands', function () {
    [$pve, $server] = pveImageLab();
    $pve->permissions = ['/pool/pnlcs' => ['VM.Allocate' => 1]];

    expect(ProxmoxImages::for($server)->overview()['missing'])
        ->toBe(['Datastore.AllocateTemplate (/storage/local)', 'Sys.AccessNetwork (/nodes/pve)'])
        ->and(ProxmoxImages::rightsCommands())->toContain('Sys.AccessNetwork')->toContain('pveum acl modify /nodes');

    $report = (new ProxmoxModule)->diagnose($server);
    expect(collect($report['checks'])->pluck('text')->implode(' '))->toContain('Sys.AccessNetwork')
        ->and($report['commands'])->toContain('PNLCSImages');
});

it('turns a cloud image into a template in the pool, at the top of the VM id range, step by step', function () {
    [$pve, $server] = pveImageLab();
    $pve->holdTasks = true;
    $library = ProxmoxImages::for($server);

    expect($library->install('kvm', 'debian-12')->ok)->toBeTrue();
    $download = $pve->sent('POST', 'nodes/pve/storage/local/download-url')[0]['params'];
    expect($download)->toMatchArray(['content' => 'import', 'filename' => 'debian-12.qcow2'])
        ->and($download['url'])->toContain('cloud.debian.org')
        ->and($library->jobs()['kvm:debian-12']['step'])->toBe('downloading');

    // A second press while it runs starts nothing.
    expect($library->install('kvm', 'debian-12')->status)->toBe(409);

    // The download finishes; the scheduler takes it from there.
    $pve->holdTasks = false;
    test()->artisan('pnlcs:proxmox-tasks')->assertSuccessful();
    test()->artisan('pnlcs:proxmox-tasks')->assertSuccessful();

    $vm = $pve->guests[9099] ?? null;
    expect($vm)->not->toBeNull()
        ->and($vm['template'])->toBe(1)
        ->and($vm['pool'])->toBe('pnlcs')
        ->and($vm['config']['name'])->toBe('debian-12-cloud')
        ->and($vm['config']['scsi0'])->toBe('local-lvm:0,import-from=local:import/debian-12.qcow2,iothread=1,discard=on')
        ->and($vm['config']['ide2'])->toBe('local-lvm:cloudinit')
        ->and($vm['config']['tags'])->toBe(ProxmoxImages::TAG)
        ->and($library->jobs()['kvm:debian-12']['step'])->toBe('done');

    $row = collect(ProxmoxImages::for($server)->overview()['kvm'])->firstWhere('id', 'debian-12');
    expect($row['template'])->toBe(9099);

    // The product form names it the way a customer would.
    $catalog = (new ProxmoxModule)->catalog($server);
    expect(collect($catalog['templates'])->firstWhere('id', '9099')['name'])->toContain('Debian 12');
});

it('does not carry the library\'s mark onto a customer\'s server cloned from it', function () {
    [$pve, $server] = pveImageLab();
    ProxmoxImages::for($server)->install('kvm', 'ubuntu-24.04');
    test()->artisan('pnlcs:proxmox-tasks')->assertSuccessful();
    $templateId = collect($pve->guests)->filter(fn ($g) => ($g['config']['name'] ?? '') === 'ubuntu-24.04-cloud')->keys()->first();
    expect($templateId)->not->toBeNull();

    $service = pveService($server, ['pve_template' => (string) $templateId]);
    expect((new ProxmoxModule)->create($service)['success'])->toBeTrue();

    $vmid = (int) $service->fresh()->module_data['proxmox_vmid'];
    expect($pve->guests[$vmid]['config']['tags'])->not->toContain(ProxmoxImages::TAG)->toContain('pnlcs-s'.$service->id);
});

it('fetches a container template from the Proxmox catalogue', function () {
    [$pve, $server] = pveImageLab();

    expect(ProxmoxImages::for($server)->install('lxc', 'debian-12-standard_12.12-1_amd64.tar.zst')->ok)->toBeTrue();

    expect($pve->sent('POST', 'nodes/pve/aplinfo')[0]['params'])->toBe(['storage' => 'local', 'template' => 'debian-12-standard_12.12-1_amd64.tar.zst'])
        ->and(collect(ProxmoxImages::for($server)->overview()['lxc'])->firstWhere('id', 'debian-12-standard_12.12-1_amd64.tar.zst')['installed'])->toBeTrue();
});

it('refuses an image it does not know, and says what is missing when no storage takes imports', function () {
    [$pve, $server] = pveImageLab();
    $library = ProxmoxImages::for($server);

    expect($library->install('kvm', 'windows-11')->status)->toBe(422)
        ->and($library->install('lxc', 'not-in-the-catalogue.tar.zst')->status)->toBe(422);

    $pve->storages[0]['content'] = 'iso,vztmpl,backup';
    $answer = $library->install('kvm', 'debian-12');
    expect($answer->ok)->toBeFalse()->and($answer->error)->toContain('import')
        ->and($pve->sent('POST', 'nodes/pve/storage/.*/download-url'))->toBe([]);
});

it('reports a download Proxmox gave up on, and lets it be tried again', function () {
    [$pve, $server] = pveImageLab();
    $pve->nextTaskFails('download failed: 404 Not Found');
    $library = ProxmoxImages::for($server);

    $library->install('kvm', 'rocky-9');
    $job = $library->jobs()['kvm:rocky-9'];
    expect($job['step'])->toBe('failed')->and($job['error'])->toContain('404');

    expect($library->install('kvm', 'rocky-9')->ok)->toBeTrue();
});

it('gives the admin a library page for each Proxmox server, behind the server permission', function () {
    [$pve, $server] = pveImageLab();
    $admin = pveAdmin();

    test()->actingAs($admin, 'admin')->get(route('admin.config.servers'))->assertOk()
        ->assertSee(route('admin.config.servers.images', $server), false);
    test()->actingAs($admin, 'admin')->get(route('admin.config.servers.images', $server))->assertOk()->assertSee('pim-kvm-body', false);
    test()->actingAs($admin, 'admin')->getJson(route('admin.config.servers.images.status', $server))->assertOk()->assertJsonPath('ok', true);
    test()->actingAs($admin, 'admin')->postJson(route('admin.config.servers.images.install', $server), ['kind' => 'kvm', 'id' => 'debian-13'])
        ->assertOk()->assertJsonPath('success', true);

    $viewer = \App\Models\Admin::factory()->create(['role_id' => \App\Models\AdminRole::factory()->create(['permissions' => ['view_services']])->id]);
    test()->actingAs($viewer, 'admin')->postJson(route('admin.config.servers.images.install', $server), ['kind' => 'kvm', 'id' => 'debian-12'])->assertForbidden();

    $panelica = \App\Models\Server::factory()->create(['type' => 'panelica']);
    test()->actingAs($admin, 'admin')->get(route('admin.config.servers.images', $panelica))->assertNotFound();
});
