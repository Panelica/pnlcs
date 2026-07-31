<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;

/**
 * What the general settings form is allowed to write.
 *
 * It stored every field of the request, whatever it was called. Two rows on
 * this installation - "test" and "test_key_xyz" - are what that looks like
 * afterwards. Worse, Setting::set() writes the group as well as the value, so
 * a field named after a setting belonging to another group both overwrote it
 * and moved it into "general", where the screen that owns it no longer looks.
 */
function generalSettingsAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

test('a field the form does not own is not stored', function () {
    $this->actingAs(generalSettingsAdmin(), 'admin')
        ->post(route('admin.settings.general.update'), [
            'CompanyName' => 'Real Company',
            'whatever_this_is' => 'junk',
        ])->assertRedirect();

    expect(Setting::where('setting', 'whatever_this_is')->exists())->toBeFalse()
        ->and(Setting::get('CompanyName'))->toBe('Real Company');
});

test('a setting belonging to another screen is left where it is', function () {
    Setting::set('dark_mode_enabled', '1', 'appearance');

    $this->actingAs(generalSettingsAdmin(), 'admin')
        ->post(route('admin.settings.general.update'), [
            'CompanyName' => 'Real Company',
            'dark_mode_enabled' => '0',
        ])->assertRedirect();

    $setting = Setting::where('setting', 'dark_mode_enabled')->firstOrFail();

    expect($setting->value)->toBe('1')
        ->and($setting->group)->toBe('appearance');
});

test('the fields the form does own still save', function () {
    $this->actingAs(generalSettingsAdmin(), 'admin')
        ->post(route('admin.settings.general.update'), [
            'CompanyName' => 'Real Company',
            'Domain' => 'example.test',
            'SMTPHost' => 'smtp.example.test',
            'DateFormat' => 'Y-m-d',
        ])->assertRedirect();

    expect(Setting::get('CompanyName'))->toBe('Real Company')
        ->and(Setting::get('Domain'))->toBe('example.test')
        ->and(Setting::get('SMTPHost'))->toBe('smtp.example.test')
        ->and(Setting::get('DateFormat'))->toBe('Y-m-d');
});

test('an unticked mail switch still turns mail off', function () {
    Setting::set('MailEnabled', '1', 'general');

    $this->actingAs(generalSettingsAdmin(), 'admin')
        ->post(route('admin.settings.general.update'), ['CompanyName' => 'Real Company'])
        ->assertRedirect();

    expect(Setting::get('MailEnabled'))->toBe('0');
});
