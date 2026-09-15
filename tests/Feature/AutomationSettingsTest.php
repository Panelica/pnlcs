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

it('treats a cleared checkbox as switching termination off', function () {
    Setting::set('AutoTerminationEnabled', '1');

    // The cleared box used to be recognised by its absence from the request.
    // Absence meant two different things - "cleared on this form" and "this
    // screen has no such switch" - and the second one let the languages screen
    // switch termination off while saving an OpenAI key. The checkbox now has a
    // hidden partner carrying '0', so this is what the browser sends.
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->post(route('admin.settings.general.update'), [
            'CompanyName' => 'Test Co',
            'AutoSuspensionDays' => '3',
            'AutoTerminationDays' => '30',
            'AutoTerminationEnabled' => '0',
        ])->assertRedirect();

    expect((int) Setting::get('AutoTerminationEnabled'))->toBe(0);
});

it('leaves termination alone when the posting screen has no such switch', function () {
    Setting::set('AutoTerminationEnabled', '1');

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->post(route('admin.settings.general.update'), ['OpenAIModel' => 'gpt-4o'])
        ->assertRedirect();

    expect((int) Setting::get('AutoTerminationEnabled'))->toBe(1);
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
