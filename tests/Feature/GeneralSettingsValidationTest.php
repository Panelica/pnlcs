<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;

/**
 * General Settings stored every value as typed. A word in the SMTP port, a
 * negative grace period or a time zone PHP does not know was saved and only
 * failed later, in mail delivery, in the suspension run or on every page.
 */
function settingsValidationAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

test('values the rest of the app cannot use are refused and nothing is saved', function (string $field, string $value) {
    Setting::set('CompanyName', 'Before', 'general');

    $this->actingAs(settingsValidationAdmin(), 'admin')
        ->from(route('admin.settings.general'))
        ->post(route('admin.settings.general.update'), [$field => $value, 'CompanyName' => 'After'])
        ->assertRedirect(route('admin.settings.general'))
        ->assertSessionHasErrors($field);

    expect(Setting::get('CompanyName'))->toBe('Before')
        ->and(Setting::get($field))->not->toBe($value);
})->with([
    'smtp port' => ['SMTPPort', 'twenty-five'],
    'grace days' => ['AutoSuspensionDays', '-3'],
    'time zone' => ['Timezone', 'Europe/Nowhere'],
    'company email' => ['Email', 'not an address'],
    'late fee' => ['LateFeeType', 'sometimes'],
]);

test('a valid form is saved as before', function () {
    $this->actingAs(settingsValidationAdmin(), 'admin')
        ->post(route('admin.settings.general.update'), [
            'SMTPPort' => '465', 'AutoSuspensionDays' => '0', 'Timezone' => 'Europe/Istanbul',
            'Email' => 'billing@example.test', 'LateFeeType' => 'flat',
        ])
        ->assertSessionHasNoErrors();

    expect(Setting::get('SMTPPort'))->toBe('465')
        ->and(Setting::get('Timezone'))->toBe('Europe/Istanbul');
});

test('the form names what was refused', function () {
    $this->actingAs(settingsValidationAdmin(), 'admin')
        ->from(route('admin.settings.general'))
        ->followingRedirects()
        ->post(route('admin.settings.general.update'), ['SMTPPort' => 'twenty-five'])
        ->assertSee('alert alert-danger', false);
});
