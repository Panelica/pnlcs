<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;

/*
 * Where the company name is, and what happens when it is saved.
 *
 * Reported 2026-08-20: "I uploaded the logo, where is the company name?" The
 * answer turned out to be two answers - there is a CompanyName on the general
 * settings screen and a white-label override here - and company_name() already
 * decides between them. So nothing new was added: the checklist was taught the
 * same precedence and now links at whichever screen owns the missing half. What
 * this screen needed was the tab being addressable, and saving returning to the
 * tab the form was on.
 */

function appearanceAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

test('the white-label tab holds the override, and nothing else duplicates it', function () {
    $html = $this->actingAs(appearanceAdmin(), 'admin')
        ->get(route('admin.settings.appearance'))
        ->assertOk()
        ->getContent();

    // One field on this screen: the white-label override. The company's name
    // itself belongs to the general settings, and company_name() already reads
    // the override first and falls back to it. A second field here would be a
    // third value for one idea.
    expect(substr_count($html, 'name="company_name"'))->toBe(1);
});

test('saving just the name leaves the rest of the white-label settings alone', function () {
    Setting::set('whitelabel_company_url', 'https://myhosting.example', 'whitelabel');
    Setting::set('whitelabel_support_email', 'support@myhosting.example', 'whitelabel');
    Setting::set('whitelabel_copyright', 'MyHosting LLC', 'whitelabel');

    $this->actingAs(appearanceAdmin(), 'admin')
        ->post(route('admin.settings.appearance.whitelabel'), [
            'company_name' => 'MyHosting',
            'return_tab'   => 'themes',
        ])
        ->assertRedirect();

    // The small form carries one field. Reading every field with a default of
    // '' would have wiped the other three.
    expect(Setting::get('whitelabel_company_name'))->toBe('MyHosting')
        ->and(Setting::get('whitelabel_company_url'))->toBe('https://myhosting.example')
        ->and(Setting::get('whitelabel_support_email'))->toBe('support@myhosting.example')
        ->and(Setting::get('whitelabel_copyright'))->toBe('MyHosting LLC');
});

test('the full form still clears a field the operator emptied', function () {
    Setting::set('whitelabel_copyright', 'Old Text', 'whitelabel');

    $this->actingAs(appearanceAdmin(), 'admin')
        ->post(route('admin.settings.appearance.whitelabel'), [
            'whitelabel_full_form' => '1',
            'company_name'  => 'MyHosting',
            'company_url'   => '',
            'support_email' => '',
            'copyright'     => '',
        ])
        ->assertRedirect();

    // Emptying a field on the real form must still mean emptying it.
    expect(Setting::get('whitelabel_copyright'))->toBe('');
});

test('saving returns to the tab the form was on', function () {
    $this->actingAs(appearanceAdmin(), 'admin')
        ->post(route('admin.settings.appearance.whitelabel'), [
            'company_name' => 'MyHosting',
            'return_tab'   => 'whitelabel',
        ])
        ->assertSessionHas('appearance_tab', 'whitelabel');
});

test('the tab can be addressed directly', function () {
    $html = $this->actingAs(appearanceAdmin(), 'admin')
        ->get(route('admin.settings.appearance'))
        ->assertOk()
        ->getContent();

    // Without this a link to the screen always lands on the first tab, which is
    // how someone ends up hunting for a field that is one click away.
    expect($html)->toContain('window.location.hash');
});

test('the branding name is what the panel shows as the brand', function () {
    Setting::set('whitelabel_company_name', 'MyHosting', 'whitelabel');

    // Saved under the key the rest of the panel reads, not a new one.
    expect(Setting::get('whitelabel_company_name'))->toBe('MyHosting');
});
