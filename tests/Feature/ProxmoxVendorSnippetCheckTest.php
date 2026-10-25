<?php

use Modules\Servers\Proxmox\ProxmoxModule;
use Tests\Support\FakeProxmox;

/*
 * Test Connection called an existing vendor snippet missing (#286).
 *
 * Proxmox lists a snippet only to a caller with Datastore.Allocate on the
 * storage (check_volume_access is called without a content type, and a
 * snippet is refused then), while putting one on a guest takes only
 * Datastore.AllocateSpace. For the token Test Connection itself recommends,
 * the storage's snippet list is always empty: the check failed the server
 * while every guest was created with the snippet. Now the list is believed
 * only from a token that can see snippets; for the others what is checked is
 * the right to use one.
 */

require_once __DIR__.'/../Support/proxmox_helpers.php';

function pvsLab(array $localRights): FakeProxmox
{
    $pve = FakeProxmox::install()->template(9000);
    $pve->storages[0]['content'] = 'iso,vztmpl,backup,snippets';
    $pve->volumes['local'][] = ['volid' => 'local:snippets/pnlcs-vendor.yaml', 'content' => 'snippets', 'size' => 412];
    $pve->permissions['/storage/local'] = array_fill_keys($localRights, 1);

    return $pve;
}

function pvsCheck(array $report, string $level): string
{
    return collect($report['checks'])->where('level', $level)->pluck('text')->implode(' ');
}

it('does not call a snippet missing when the token cannot see snippets, and says it could not check', function () {
    pvsLab(['Datastore.AllocateSpace', 'Datastore.Audit']);

    $report = (new ProxmoxModule)->diagnose(pveServer(['ci_vendor' => 'local:snippets/pnlcs-vendor.yaml']));

    expect($report['ok'])->toBeTrue(json_encode($report['checks']))
        ->and(pvsCheck($report, 'fail'))->not->toContain('vendor')
        ->and(pvsCheck($report, 'info'))->toContain(__('proxmox.check.vendor_unverified', ['volume' => 'local:snippets/pnlcs-vendor.yaml', 'storage' => 'local']));
});

it('fails a token that could not give the snippet to a guest', function () {
    // Rights on the disk storage, none to use what is on "local".
    $pve = pvsLab(['Datastore.Audit']);
    $pve->permissions['/'] = array_diff_key($pve->permissions['/'], ['Datastore.AllocateSpace' => 1, 'Datastore.Audit' => 1]);
    $pve->permissions['/storage/local-lvm'] = ['Datastore.AllocateSpace' => 1, 'Datastore.Audit' => 1];

    $report = (new ProxmoxModule)->diagnose(pveServer(['ci_vendor' => 'local:snippets/pnlcs-vendor.yaml']));

    expect($report['ok'])->toBeFalse()
        ->and(collect($report['checks'])->where('level', 'fail')->pluck('text')->all())
        ->toBe([__('proxmox.check.vendor_rights', ['volume' => 'local:snippets/pnlcs-vendor.yaml', 'storage' => 'local'])]);
});

it('still trusts the list from a token that can see snippets', function (bool $there, string $level) {
    $pve = pvsLab(['Datastore.Allocate', 'Datastore.AllocateSpace', 'Datastore.Audit']);
    if (! $there) {
        $pve->volumes['local'] = array_values(array_filter($pve->volumes['local'], fn ($v) => $v['content'] !== 'snippets'));
    }

    $report = (new ProxmoxModule)->diagnose(pveServer(['ci_vendor' => 'local:snippets/pnlcs-vendor.yaml']));

    expect(pvsCheck($report, $level))->toContain('local:snippets/pnlcs-vendor.yaml')
        ->and($report['ok'])->toBe($there);
})->with([
    'there' => [true, 'ok'],
    'missing' => [false, 'fail'],
]);
