<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;

/*
 * Where the company name is, and what happens when it is saved.
 *
 * Reported 2026-08-20: "I uploaded the logo, where is the company name?" It was
 * on the same screen but behind the White-label tab, which is not where anyone
 * looks for what their company is called - the appearance screen opens on
 * Themes, and that is where the logo upload is. The name now sits next to the
 * logo, and the tab is addressable so a link can point straight at it.
 */

function appearanceAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

test('the company name can be set from the same place as the logo', function () {
    $html = $this->actingAs(appearanceAdmin(), 'admin')
        ->get(route('admin.settings.appearance'))
        ->assertOk()
        ->getContent();

    // The first tab is the one that opens; the field has to be reachable there.
    $firstTab = substr($html, 0, (int) strpos($html, 'id="tab-colors"') ?: strlen($html));

    expect($firstTab)->toContain('name="company_name"');
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
