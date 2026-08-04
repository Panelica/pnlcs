<?php

use App\Models\Admin;
use App\Models\Setting;

/**
 * The terms a customer agrees to when they sign up.
 *
 * The registration form asks them to agree to the Terms of Service and the
 * Privacy Policy, and links both. The links read two settings that no screen
 * could write, so they fell back to "#": every customer agreed to documents
 * the form would not show them.
 */
it('lets the operator say where the terms are', function () {
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->post(route('admin.settings.general.update'), [
            'CompanyName' => 'Test Co',
            'TOSUrl' => 'https://example.com/terms',
            'PrivacyUrl' => 'https://example.com/privacy',
        ])->assertRedirect();

    expect(Setting::get('TOSUrl'))->toBe('https://example.com/terms')
        ->and(Setting::get('PrivacyUrl'))->toBe('https://example.com/privacy');
});

it('offers the fields on the settings screen', function () {
    $html = $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('admin.settings.general'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('name="TOSUrl"')
        ->and($html)->toContain('name="PrivacyUrl"');
});

it('links the terms the operator gave', function () {
    Setting::set('TOSUrl', 'https://example.com/terms', 'general');
    Setting::set('PrivacyUrl', 'https://example.com/privacy', 'general');

    $html = $this->get(route('client.register'))->assertOk()->getContent();

    expect($html)->toContain('https://example.com/terms')
        ->and($html)->toContain('https://example.com/privacy');
});

it('does not offer a link to nowhere when no terms are configured', function () {
    Setting::set('TOSUrl', '', 'general');
    Setting::set('PrivacyUrl', '', 'general');

    $html = $this->get(route('client.register'))->assertOk()->getContent();

    expect($html)->not->toContain('href="#"');
});
