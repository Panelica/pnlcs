<?php

use App\Models\Setting;

/*
 * The admin sign-in page printed "PNLCS" in its title, heading and footer
 * and a hard-coded "P" badge, whatever the white-label settings said
 * (#457). It now uses company_name(), the uploaded logo and the theme
 * colour, like the client sign-in page.
 */

function brandAs(string $name, string $logo = ''): void
{
    Setting::set('whitelabel_company_name', $name, 'appearance');
    Setting::set('custom_logo_path', $logo, 'appearance');
    app()->forgetInstance('pnlcs.company_name');
}

test('the admin sign-in page carries the white-label name, not PNLCS', function () {
    brandAs('Acme Hosting');

    $html = $this->get(route('admin.login'))->assertOk()->getContent();

    expect($html)->toContain('<title>')
        ->and($html)->toContain('Acme Hosting')
        ->and($html)->not->toContain('PNLCS')
        ->and($html)->toContain('>A<');
});

test('an uploaded logo takes the place of the initial', function () {
    brandAs('Acme Hosting', '/branding/logo_1.png');

    $html = $this->get(route('admin.login'))->assertOk()->getContent();

    expect($html)->toContain('src="/branding/logo_1.png"')
        ->and($html)->not->toContain('>A<');
});
