<?php

use Modules\Servers\Proxmox\ProxmoxImages;
use Modules\Servers\Proxmox\ProxmoxModule;
use Tests\Support\FakeProxmox;

/*
 * A token limited to one resource pool could not create anything that was
 * tagged at creation (#278).
 *
 * Proxmox checks the tags of a new guest on /vms/<id> and leaves the pool out
 * of that check, and the guest joins the pool only at the end of its creation.
 * A token whose rights come from /pool/<name> - the setup Test Connection
 * recommends - therefore had every image install refused with "403 Permission
 * check failed (/vms/100, VM.Config.Options)", and the same for a server made
 * from an ISO or a container. With a pool set, the tags now follow the create,
 * once the guest is in the pool; without one, nothing changes.
 */

require_once __DIR__.'/../Support/proxmox_helpers.php';

function pptPoolOnly(): FakeProxmox
{
    $pve = FakeProxmox::install();
    $pve->pools = ['pnlcs'];
    $pve->poolOnlyRights = true;
    $pve->storages[0]['content'] = 'iso,vztmpl,backup,import';
    $pve->permissions = [
        '/pool/pnlcs' => array_fill_keys(['VM.Allocate', 'VM.Audit', 'VM.Clone', 'VM.Config.Disk', 'VM.Config.Options'], 1),
        '/storage/local' => array_fill_keys(['Datastore.AllocateTemplate', 'Datastore.AllocateSpace', 'Datastore.Audit'], 1),
        '/nodes' => array_fill_keys(['Sys.AccessNetwork', 'Sys.Audit'], 1),
    ];

    return $pve;
}

it('installs a cloud image with a token limited to the pool, and the template still carries the mark', function () {
    $pve = pptPoolOnly();
    $server = pveServer(['pool' => 'pnlcs', 'vmid_min' => 100, 'vmid_max' => 199]);
    $library = ProxmoxImages::for($server);

    expect($library->install('kvm', 'debian-13')->ok)->toBeTrue();
    test()->artisan('pnlcs:proxmox-tasks')->assertSuccessful();
    test()->artisan('pnlcs:proxmox-tasks')->assertSuccessful();

    $created = $pve->sent('POST', 'nodes/pve/qemu')[0]['params'];
    $template = $pve->guests[199] ?? null;

    expect($created)->toHaveKey('pool', 'pnlcs')->not->toHaveKey('tags')
        ->and($library->jobs()['kvm:debian-13']['step'])->toBe('done')
        ->and($template['template'])->toBe(1)
        ->and($template['pool'])->toBe('pnlcs')
        ->and($template['config']['tags'])->toBe(ProxmoxImages::TAG);
});

it('makes a server from an ISO and a container with a token limited to the pool, tagged for the service', function (array $config, string $type) {
    $pve = pptPoolOnly();
    $service = pveService(pveServer(['pool' => 'pnlcs']), $config);

    $result = (new ProxmoxModule)->create($service);

    expect($result['success'])->toBeTrue($result['message']);
    $guest = $pve->guests[$result['data']['vmid']];
    expect($pve->sent('POST', "nodes/pve/{$type}")[0]['params'])->toHaveKey('pool', 'pnlcs')->not->toHaveKey('tags')
        ->and($guest['pool'])->toBe('pnlcs')
        ->and(explode(';', $guest['config']['tags']))->toContain(ProxmoxModule::TAG, 'pnlcs-s'.$service->id);
})->with([
    'virtual machine from an ISO' => [['pve_template' => '', 'pve_iso' => 'local:iso/debian-12.13.0-amd64-netinst.iso'], 'qemu'],
    'container' => [['pve_type' => 'lxc', 'pve_ostemplate' => 'local:vztmpl/debian-12-standard_12.7-1_amd64.tar.zst'], 'lxc'],
]);

it('still tags at creation when no pool is set', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer(), ['pve_type' => 'lxc', 'pve_ostemplate' => 'local:vztmpl/debian-12-standard_12.7-1_amd64.tar.zst']);

    expect((new ProxmoxModule)->create($service)['success'])->toBeTrue()
        ->and($pve->sent('POST', 'nodes/pve/lxc')[0]['params']['tags'])->toBe(ProxmoxModule::TAG.';pnlcs-s'.$service->id);
});
