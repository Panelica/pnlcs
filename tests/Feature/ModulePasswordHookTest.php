<?php

use App\Services\HookManager;
use Tests\Support\FakeProxmox;

require_once __DIR__.'/../Support/proxmox_helpers.php';

/**
 * A changed service password runs AfterModulePassword (ProvisioningService).
 * Two gaps: WHMCS calls the same point AfterModuleChangePassword, and PNLCS
 * did not run it under that name, unlike the other hooks it aliases; and the
 * VPS panel changed the root password on the module directly, so no password
 * hook ran for a VPS at all.
 */
beforeEach(function () {
    \App\Models\Currency::getDefault() ?? \App\Models\Currency::factory()->default()->create();
});

function passwordHookCalls(): object
{
    $calls = new class { public array $names = []; };
    foreach (['AfterModulePassword', 'AfterModuleChangePassword'] as $name) {
        add_hook($name, 1, function (array $vars) use ($calls, $name) {
            $calls->names[] = [$name, $vars['service']->id ?? null];
        });
    }

    return $calls;
}

it('runs both password hooks when the customer changes a VPS root password', function () {
    $pve = FakeProxmox::install();
    $pve->agentRunning = true;
    $service = pveService(pveServer());
    pveOwnedGuest($pve, $service, 730);
    $calls = passwordHookCalls();

    test()->actingAs(pveCustomer($service))
        ->postJson(route('client.services.vps.password', $service), ['password' => 'N3w-passw0rd!', 'password_confirmation' => 'N3w-passw0rd!'])
        ->assertOk()->assertJsonPath('success', true);

    expect($calls->names)->toBe([['AfterModulePassword', $service->id], ['AfterModuleChangePassword', $service->id]])
        ->and($service->fresh()->password)->toBe('N3w-passw0rd!');
});

it('runs no password hook when the change fails', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer());
    pveOwnedGuest($pve, $service, 731, 'lxc');
    $calls = passwordHookCalls();

    test()->actingAs(pveCustomer($service))
        ->postJson(route('client.services.vps.password', $service), ['password' => 'N3w-passw0rd!', 'password_confirmation' => 'N3w-passw0rd!']);

    expect($calls->names)->toBe([]);
});
