<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Setting;
use App\Models\User;

/**
 * White-labelling the customer portal.
 *
 * Settings -> Appearance offers a white-label company name and a "Remove PNLCS
 * / Panelica branding references" switch. The marketing pages honoured the
 * name, but the pages a customer actually logs into every day had the product
 * name written into them, and nothing anywhere read the switch. On the live
 * install the name is set to a reseller's own and the switch is on, and the
 * portal still says PNLCS.
 */
function whiteLabelled(string $name = 'PANELICA LLC'): void
{
    Setting::set('whitelabel_company_name', $name, 'whitelabel');
    Setting::set('whitelabel_remove_branding', '1', 'whitelabel');

    app()->forgetInstance('pnlcs.company_name');
}

it('does not put the product name on the login page', function () {
    whiteLabelled();

    $html = $this->get(route('client.login'))->assertOk()->getContent();

    expect($html)->toContain('PANELICA LLC')
        ->and($html)->not->toContain('PNLCS');
});

it('does not put the product name on the customer portal', function () {
    whiteLabelled();

    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);

    $html = $this->actingAs($user)->get(route('client.home'))->assertOk()->getContent();

    expect($html)->toContain('PANELICA LLC')
        ->and($html)->not->toContain('PNLCS');
});

// The operator's own screens still name the product - they bought it, and the
// dashboard reports its version. What white-labelling covers is the signature
// under the page, which is what a reseller shows in a screen share.
it('signs the admin footer with the operator company, not the product', function () {
    whiteLabelled();

    $html = $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('&copy; '.date('Y').' PANELICA LLC')
        ->and($html)->not->toContain('&copy; '.date('Y').' PNLCS');
});

// Nobody asked for anything to be replaced: the pages carry the name the
// installation runs under, not an empty space.
it('keeps the installation name when nobody asked for it to go', function () {
    Setting::set('whitelabel_company_name', '', 'whitelabel');
    Setting::set('whitelabel_remove_branding', '0', 'whitelabel');
    Setting::set('CompanyName', '', 'general');

    app()->forgetInstance('pnlcs.company_name');

    $html = $this->get(route('client.login'))->assertOk()->getContent();

    expect($html)->toContain((string) config('app.name'));
});
