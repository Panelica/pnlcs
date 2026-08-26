<?php

use App\Models\Admin;
use App\Models\Setting;

/**
 * The Automation settings must be reachable from the panel, not only readable
 * by the commands - late fees shipped exactly that way once: finished on the
 * reading side, unreachable from any screen.
 */
it('lets the operator configure suspension and termination', function () {
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->post(route('admin.settings.general.update'), [
            'CompanyName' => 'Test Co',
            'AutoSuspensionDays' => '7',
            'AutoTerminationEnabled' => '1',
            'AutoTerminationDays' => '45',
        ])->assertRedirect();

    expect((int) Setting::get('AutoSuspensionDays'))->toBe(7)
        ->and((int) Setting::get('AutoTerminationEnabled'))->toBe(1)
        ->and((int) Setting::get('AutoTerminationDays'))->toBe(45);
});

it('treats the absent checkbox as switching termination off', function () {
    Setting::set('AutoTerminationEnabled', '1');

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->post(route('admin.settings.general.update'), [
            'CompanyName' => 'Test Co',
            'AutoSuspensionDays' => '3',
            'AutoTerminationDays' => '30',
        ])->assertRedirect();

    expect((int) Setting::get('AutoTerminationEnabled'))->toBe(0);
});

it('offers the automation fields on the settings screen', function () {
    $html = $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('admin.settings.general'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('name="AutoSuspensionDays"')
        ->toContain('name="AutoTerminationEnabled"')
        ->toContain('name="AutoTerminationDays"');
});
