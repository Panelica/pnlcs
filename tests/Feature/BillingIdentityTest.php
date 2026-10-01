<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;
use App\Support\BillingIdentity;
use App\Support\Countries;

/**
 * Country-specific billing identity and the forms that render it.
 *
 * The tax office only exists as an invoicing field in Turkey, the Polish
 * registration asks for a voivodeship rather than free text, and the client
 * forms preselect the country from the operator's own setting.
 */

function billingIdentityAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

test('the tax office is required only when the seller is in Turkey', function () {
    Setting::set('Country', 'TR');
    expect(BillingIdentity::required('company'))->toContain('tax_office');

    Setting::set('Country', 'PL');
    expect(BillingIdentity::required('company'))->not->toContain('tax_office');
    expect(BillingIdentity::required('company'))->toContain('tax_id');
});

test('a Turkish company buyer needs company name, tax office and tax id', function () {
    Setting::set('Country', 'TR');

    expect(BillingIdentity::required('company'))->toBe([
        'first_name', 'last_name', 'address1', 'city', 'country',
        'phone_number', 'client_type', 'company_name', 'tax_office', 'tax_id',
    ]);
});

test('the voivodeship list exists and is complete', function () {
    expect(Countries::PL_STATES)->toContain('Mazowieckie');
    expect(count(Countries::PL_STATES))->toBe(16);
});

test('the client registration preselects the country from the setting', function () {
    Setting::set('Country', 'PL');

    $this->get(route('client.register'))
        ->assertOk()
        ->assertSee('<option value="PL" selected>', false);
});

test('the admin create form renders the voivodeship select and a free text state', function () {
    $this->actingAs(billingIdentityAdmin(), 'admin')
        ->get(route('admin.clients.create'))
        ->assertOk()
        ->assertSee('id="state-select"', false)
        ->assertSee('Mazowieckie', false)
        ->assertSee('name="state"', false);
});
