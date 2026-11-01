<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;
use App\Services\InvoicePdfService;

/**
 * The company block on an invoice.
 *
 * Settings — General asks for the address, phone and email and saves them as
 * Address / PhoneNumber / Email. The invoice read CompanyAddress,
 * CompanyPhone and CompanyEmail — names nothing writes — so the header stayed
 * blank however carefully the operator filled the form. City and Tax ID had a
 * place on the invoice and no field on the screen at all.
 */
test('the invoice prints what the settings screen was given', function () {
    Setting::set('CompanyName', 'Acme Hosting', 'general');
    Setting::set('Address', '12 Example Street', 'general');
    Setting::set('PhoneNumber', '+44 20 7946 0000', 'general');
    Setting::set('Email', 'billing@acme.test', 'general');
    Setting::set('CompanyCity', 'London', 'general');
    Setting::set('TaxID', 'GB123456789', 'general');

    expect(app(InvoicePdfService::class)->companyDetails())
        ->toMatchArray([
            'name' => 'Acme Hosting',
            'address' => '12 Example Street',
            'phone' => '+44 20 7946 0000',
            'email' => 'billing@acme.test',
            'city' => 'London',
            'tax_id' => 'GB123456789',
        ]);
});

test('an installation that set the older names by hand keeps them', function () {
    Setting::set('CompanyAddress', '5 Legacy Road', 'general');
    Setting::set('CompanyPhone', '+1 555 0100', 'general');
    Setting::set('CompanyEmail', 'accounts@legacy.test', 'general');

    expect(app(InvoicePdfService::class)->companyDetails())
        ->toMatchArray([
            'address' => '5 Legacy Road',
            'phone' => '+1 555 0100',
            'email' => 'accounts@legacy.test',
        ]);
});

test('what the screen was given wins over the older name', function () {
    Setting::set('CompanyAddress', '5 Legacy Road', 'general');
    Setting::set('Address', '12 Example Street', 'general');

    expect(app(InvoicePdfService::class)->companyDetails()['address'])
        ->toBe('12 Example Street');
});

function settingsAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->create([
            'name' => 'Settings',
            'permissions' => ['manage_settings'],
        ])->id,
    ]);
}

test('the settings screen asks for the city, postcode and the tax id', function () {
    $admin = settingsAdmin();

    $html = $this->actingAs($admin, 'admin')->get(route('admin.settings.general'))
        ->assertOk()
        ->assertSee('name="CompanyCity"', false)
        ->assertSee('name="Postcode"', false)
        ->assertSee('name="TaxID"', false)
        ->getContent();

    // The postal address is asked for in one place and in the order it is
    // written: the address, then the postcode, the city and the state, all in
    // the company card rather than among the registry identifiers (#342).
    $company = strpos($html, 'id="settings-company_information"');
    $legal = strpos($html, 'id="settings-legal_identity"');
    foreach (['name="Address"', 'name="Postcode"', 'name="CompanyCity"', 'name="State"'] as $field) {
        $at = strpos($html, $field);
        expect($at)->toBeGreaterThan($company, "{$field} is not in the company card")
            ->and($at)->toBeLessThan($legal, "{$field} is not in the company card");
    }
    expect(strpos($html, 'name="Postcode"'))->toBeLessThan(strpos($html, 'name="CompanyCity"'));
});

test('saving them from the screen reaches the invoice', function () {
    $admin = settingsAdmin();

    $this->actingAs($admin, 'admin')->post(route('admin.settings.general.update'), [
        'CompanyName' => 'Acme Hosting',
        'Address' => '12 Example Street',
        'CompanyCity' => 'London',
        'TaxID' => 'GB123456789',
    ])->assertRedirect();

    expect(app(InvoicePdfService::class)->companyDetails())
        ->toMatchArray([
            'address' => '12 Example Street',
            'city' => 'London',
            'tax_id' => 'GB123456789',
        ]);
});

/*
 * The panel address and the company's own website are two different things
 * (#365). Domain is where the panel answers — password reset links are built
 * from it — and it was also the only thing an invoice could print as the
 * company's website, so an operator who wanted their shop's address on the
 * document had to put it where the mail links are made.
 */
test('the invoice prints the company website', function () {
    Setting::set('Domain', 'https://panel.acme.test', 'general');
    Setting::set('CompanyWebsite', 'https://acme.test', 'general');

    expect(app(InvoicePdfService::class)->companyDetails()['domain'])
        ->toBe('https://acme.test');
});

test('it falls back to the panel address when no website is set', function () {
    Setting::set('Domain', 'https://panel.acme.test', 'general');
    Setting::set('CompanyWebsite', '', 'general');

    expect(app(InvoicePdfService::class)->companyDetails()['domain'])
        ->toBe('https://panel.acme.test');
});

test('the settings screen asks for both addresses', function () {
    $this->actingAs(settingsAdmin(), 'admin')->get(route('admin.settings.general'))
        ->assertOk()
        ->assertSee('name="Domain"', false)
        ->assertSee('name="CompanyWebsite"', false);
});

test('saving the website from the screen reaches the invoice', function () {
    $this->actingAs(settingsAdmin(), 'admin')->post(route('admin.settings.general.update'), [
        'Domain' => 'https://panel.acme.test',
        'CompanyWebsite' => 'https://acme.test',
    ])->assertRedirect();

    expect(Setting::get('CompanyWebsite'))->toBe('https://acme.test')
        ->and(app(InvoicePdfService::class)->companyDetails()['domain'])->toBe('https://acme.test');
});
