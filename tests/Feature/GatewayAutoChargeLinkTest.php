<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;

/**
 * Finding automatic payment from the screen where you set up the card gateway.
 *
 * The switch for automatic payment lives on the general settings screen, and
 * the gateway that makes it possible is configured somewhere else entirely. An
 * operator who has just entered their Stripe keys had no way of learning that
 * the feature exists, let alone that it is off. The gateway card now says so,
 * and only for the gateways that can actually store a card - offering it under
 * bank transfer would be a promise nothing can keep.
 */
function autoChargeLinkAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->create([
            'name' => 'Gateways',
            'permissions' => ['manage_gateways'],
        ])->id,
    ]);
}

test('a gateway that stores cards points at the automatic payment switch', function () {
    $html = $this->actingAs(autoChargeLinkAdmin(), 'admin')
        ->get(route('admin.config.gateways'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain(route('admin.settings.general').'#auto-charge')
        ->and($html)->toContain(__('admin.gateways.auto_charge_link'));
});

test('the link says whether automatic payment is on or off', function () {
    Setting::set('AutoChargeEnabled', '0', 'general');

    $off = $this->actingAs(autoChargeLinkAdmin(), 'admin')
        ->get(route('admin.config.gateways'))->getContent();

    // The badge sits inside the same block as the link, so slice from there.
    $block = substr($off, strpos($off, 'auto-charge') - 400, 600);
    expect($block)->toContain(__('common.status.inactive'));

    Setting::set('AutoChargeEnabled', '1', 'general');

    $on = $this->actingAs(autoChargeLinkAdmin(), 'admin')
        ->get(route('admin.config.gateways'))->getContent();

    $block = substr($on, strpos($on, 'auto-charge') - 400, 600);
    expect($block)->toContain(__('common.status.active'));
});

test('a gateway that cannot store a card does not offer it', function () {
    $html = $this->actingAs(autoChargeLinkAdmin(), 'admin')
        ->get(route('admin.config.gateways'))
        ->assertOk()
        ->getContent();

    // Bank transfer keeps no card. Count the notes and the vaulting gateways:
    // there must be exactly as many notes as there are gateways that vault.
    $vaulting = collect(app(\App\Services\Module\ModuleRegistry::class)->getGatewayModules())
        ->filter(fn ($n) => app(\App\Services\Module\ModuleRegistry::class)->getGatewayModule($n)
            instanceof \App\Contracts\TokenizableGatewayInterface)
        ->count();

    expect(substr_count($html, '#auto-charge'))->toBe($vaulting)
        ->and($vaulting)->toBeGreaterThan(0);
});

test('the settings screen has the anchor the link points at', function () {
    $html = $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('admin.settings.general'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('id="auto-charge"');
});
