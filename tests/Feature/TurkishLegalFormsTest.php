<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;

/*
 * The Turkish statutory forms for a seller outside Turkey.
 *
 * They were published only when the company's country was Turkey, so a
 * seller registered elsewhere that sells to consumers in Turkey had no
 * distance sales contract, preliminary information form or KVKK notice: the
 * pages were 404 and the hub did not list them.
 */

it('keeps the forms off for a seller outside Turkey by default', function () {
    Setting::set('Country', 'GB', 'general');

    test()->get(route('legal.show', 'kvkk'))->assertNotFound();
    test()->get(route('legal.show', 'distance-sales'))->assertNotFound();
    test()->get(route('legal.index'))->assertOk()->assertDontSee(route('legal.show', 'kvkk'), false);
});

it('publishes them when the setting is on, without changing the country', function () {
    Setting::set('Country', 'GB', 'general');
    Setting::set('TurkishLegalForms', '1', 'general');

    test()->get(route('legal.show', 'kvkk'))->assertOk();
    test()->get(route('legal.show', 'distance-sales'))->assertOk();
    test()->get(route('legal.index'))->assertOk()->assertSee(route('legal.show', 'kvkk'), false);
    expect(Setting::get('Country'))->toBe('GB');
});

it('still publishes them for a Turkish seller', function () {
    Setting::set('Country', 'TR', 'general');
    Setting::set('TurkishLegalForms', '0', 'general');

    test()->get(route('legal.show', 'kvkk'))->assertOk();
});

it('is switched on from the general settings', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => ['manage_settings']])->id]);

    test()->actingAs($admin, 'admin')->get(route('admin.settings.general'))->assertOk()->assertSee('name="TurkishLegalForms"', false);
    test()->actingAs($admin, 'admin')->post(route('admin.settings.general.update'), ['TurkishLegalForms' => '1'])->assertRedirect();

    expect((string) Setting::get('TurkishLegalForms'))->toBe('1');
});
