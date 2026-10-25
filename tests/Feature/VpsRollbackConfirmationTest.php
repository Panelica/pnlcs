<?php

use App\Http\Controllers\Client\VpsController;
use Tests\Support\FakeProxmox;

/*
 * Rolling a server back to a snapshot throws away everything written since,
 * yet it was the one destructive VPS action that asked only "OK?": a reinstall
 * and a backup restore make the customer type the server's name, and check it
 * on the server too. A rollback now does the same, from the customer's panel
 * and from the admin's.
 */

require_once __DIR__.'/../Support/proxmox_helpers.php';

function vrcLab(): array
{
    $pve = FakeProxmox::install();
    $service = pveService(pveServer(), ['pve_snapshots' => 3], [], ['domain' => 'vps1.example.com']);
    pveOwnedGuest($pve, $service, 795);
    $pve->snapshots[795] = ['clean' => [1790000000, '']];

    return [$pve, $service];
}

it('rolls back only once the server\'s name is typed, for the customer and for the admin', function (string $who) {
    [$pve, $service] = vrcLab();
    $as = $who === 'admin' ? test()->actingAs(pveAdmin(), 'admin') : test()->actingAs(pveCustomer($service));
    $url = route("{$who}.services.vps.snapshots.action", $service);

    $as->postJson($url, ['action' => 'rollback', 'name' => 'clean'])->assertUnprocessable()
        ->assertJsonPath('message', __('proxmox.client.confirm_mismatch', ['word' => 'vps1.example.com']));
    $as->postJson($url, ['action' => 'rollback', 'name' => 'clean', 'confirm' => 'yes'])->assertUnprocessable();
    expect($pve->sent('POST', 'nodes/pve/qemu/795/snapshot/clean/rollback'))->toBeEmpty();

    $as->postJson($url, ['action' => 'rollback', 'name' => 'clean', 'confirm' => ' VPS1.example.com '])
        ->assertOk()->assertJsonPath('success', true);
    expect($pve->sent('POST', 'nodes/pve/qemu/795/snapshot/clean/rollback'))->toHaveCount(1);
})->with(['client', 'admin']);

it('still takes and deletes a snapshot without the word', function () {
    [$pve, $service] = vrcLab();
    $url = route('client.services.vps.snapshots.action', $service);

    test()->actingAs(pveCustomer($service))->postJson($url, ['action' => 'delete', 'name' => 'clean'])->assertOk();
    expect($pve->snapshots[795] ?? [])->not->toHaveKey('clean');
});

it('asks for the word in the panel before a rollback', function () {
    [, $service] = vrcLab();

    $html = test()->actingAs(pveCustomer($service))->get(route('client.services.show', $service))->assertOk()->getContent();

    expect($html)->toContain('word: confirmWord, ok: T.snapshot_rollback')
        ->toContain("action: 'rollback', name: n, confirm: typed")
        ->and(VpsController::confirmationWord($service))->toBe('vps1.example.com');
});
