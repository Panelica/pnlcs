<?php

use App\Models\Admin;
use App\Models\Setting;

/**
 * The mail password on the settings screen.
 *
 * The field is a password field, but the stored password was written into its
 * value attribute, so the plain text sat in the page source of every settings
 * page load - readable by anything looking at the response, and kept in any
 * cache or proxy that saw it.
 *
 * Not sending it means the form comes back empty, so an empty field has to
 * mean "leave it alone" rather than "clear it", the way the mailbox import
 * password on the departments screen already works.
 */
function settingsScreen(): string
{
    return route('admin.settings.general');
}

it('does not write the mail password into the page', function () {
    Setting::set('SMTPPassword', 'the-mail-password', 'general');

    $html = $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(settingsScreen())
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('the-mail-password');
});

it('keeps the stored password when the field is left empty', function () {
    Setting::set('SMTPPassword', 'the-mail-password', 'general');

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->post(route('admin.settings.general.update'), [
            'CompanyName' => 'Test Co',
            'SMTPPassword' => '',
        ])->assertRedirect();

    expect(Setting::get('SMTPPassword'))->toBe('the-mail-password');
});

it('replaces the password when a new one is given', function () {
    Setting::set('SMTPPassword', 'the-mail-password', 'general');

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->post(route('admin.settings.general.update'), [
            'CompanyName' => 'Test Co',
            'SMTPPassword' => 'a-new-one',
        ])->assertRedirect();

    expect(Setting::get('SMTPPassword'))->toBe('a-new-one');
});
