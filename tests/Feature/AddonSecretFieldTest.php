<?php

use App\Models\AddonSetting;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;

/*
 * The KSeF private key is stored encrypted and its hint says it never leaves
 * the backend, but the addon settings page put it, decrypted, back into its
 * textarea for anyone who opened the page. A field marked secret is now shown
 * only as "a value is set", and a blank save keeps the stored one.
 */

function secretStaff(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'Addons '.uniqid(), 'permissions' => ['manage_products']])->id]);
}

const KSEF_TEST_KEY = "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC7\n-----END PRIVATE KEY-----";

test('the stored KSeF private key is not put back into the settings page', function () {
    Setting::set('addon_ksef_active', '1');
    AddonSetting::setSetting('ksef', 'private_key', KSEF_TEST_KEY);

    $page = $this->actingAs(secretStaff(), 'admin')->get(route('admin.config.addons.modules.show', 'ksef'))->assertOk()->getContent();

    expect($page)->not->toContain('BEGIN PRIVATE KEY')
        ->and($page)->toContain(e(__('admin.addon_modules.key_is_set')));
});

test('saving the page with the key left blank keeps it; a new key replaces it', function () {
    Setting::set('addon_ksef_active', '1');
    AddonSetting::setSetting('ksef', 'private_key', KSEF_TEST_KEY);
    $staff = secretStaff();

    $this->actingAs($staff, 'admin')->post(route('admin.config.addons.modules.settings', 'ksef'), ['nip' => '5261040828', 'private_key' => ''])->assertSessionHas('success');
    expect(AddonSetting::getForAddon('ksef')['private_key'])->toBe(KSEF_TEST_KEY)
        ->and(AddonSetting::getForAddon('ksef')['nip'])->toBe('5261040828');

    $this->actingAs($staff, 'admin')->post(route('admin.config.addons.modules.settings', 'ksef'), ['private_key' => 'NEW KEY']);
    expect(AddonSetting::getForAddon('ksef')['private_key'])->toBe('NEW KEY');
});

test('a field marked secret counts as secret wherever module settings are saved', function () {
    expect(\App\Support\ModuleSettings::secretFieldNames([
        ['name' => 'token', 'type' => 'password'],
        ['name' => 'private_key', 'type' => 'textarea', 'secret' => true],
        ['name' => 'certificate', 'type' => 'textarea'],
    ]))->toBe(['token', 'private_key']);
});
