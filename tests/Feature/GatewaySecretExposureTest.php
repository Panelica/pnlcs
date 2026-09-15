<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\GatewaySettings;
use App\Models\RegistrarSettings;

/**
 * Secrets on the gateway and registrar configuration screens.
 *
 * Both screens printed the stored value of every field into value=", including
 * the ones their own module marks as type "password". Those values are
 * encrypted at rest but the model decrypts them on read, so the live Stripe
 * secret key and the registrar password sat in the page source of every visit -
 * readable by anything that could see the response: a browser extension, a
 * cached page, a screen share, a proxy log.
 *
 * The fix is the contract the general settings screen already used for
 * SMTPPassword: the field ships empty, and a blank submission means "keep what
 * is saved" rather than "delete my key".
 *
 * The same methods also wrote whatever they were handed. Nothing checked that a
 * value was a string, that it had a bounded length, that the key looked like a
 * setting name, or that the gateway existed at all.
 */
function secretsAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->create([
            'name' => 'Secrets',
            'permissions' => ['manage_gateways', 'manage_registrars'],
        ])->id,
    ]);
}

test('a stored gateway secret never reaches the page source', function () {
    GatewaySettings::updateOrCreate(
        ['gateway' => 'stripe', 'setting' => 'secret_key'],
        ['value' => 'sk_live_must_never_be_rendered']
    );
    GatewaySettings::updateOrCreate(
        ['gateway' => 'stripe', 'setting' => 'webhook_secret'],
        ['value' => 'whsec_must_never_be_rendered']
    );

    // It is genuinely readable: the cast decrypts it, which is exactly why the
    // blade printing it was a leak and not merely untidy.
    expect(GatewaySettings::where('gateway', 'stripe')->where('setting', 'secret_key')->value('value'))
        ->toBe('sk_live_must_never_be_rendered');

    $this->actingAs(secretsAdmin(), 'admin')
        ->get(route('admin.config.gateways'))
        ->assertOk()
        ->assertDontSee('sk_live_must_never_be_rendered', false)
        ->assertDontSee('whsec_must_never_be_rendered', false)
        // The field is still offered, just without its value.
        ->assertSee('settings[secret_key]', false);
});

test('a stored registrar secret never reaches the page source', function () {
    RegistrarSettings::updateOrCreate(
        ['registrar' => 'enom', 'setting' => 'pw'],
        ['value' => 'enom_password_must_never_be_rendered']
    );

    $this->actingAs(secretsAdmin(), 'admin')
        ->get(route('admin.config.registrars'))
        ->assertOk()
        ->assertDontSee('enom_password_must_never_be_rendered', false)
        ->assertSee('settings[pw]', false);
});

test('saving the form without retyping the secret keeps the stored key', function () {
    GatewaySettings::updateOrCreate(
        ['gateway' => 'stripe', 'setting' => 'secret_key'],
        ['value' => 'sk_live_original']
    );

    // What the browser posts once the field is no longer pre-filled: the
    // operator changed the publishable key and left the secret alone.
    $this->actingAs(secretsAdmin(), 'admin')
        ->post(route('admin.config.gateways.settings.update', 'stripe'), [
            'active' => '1',
            'settings' => [
                'publishable_key' => 'pk_live_updated',
                'secret_key' => '',
                'webhook_secret' => '',
            ],
        ])->assertRedirect();

    expect(GatewaySettings::where('gateway', 'stripe')->where('setting', 'secret_key')->value('value'))
        ->toBe('sk_live_original')
        ->and(GatewaySettings::where('gateway', 'stripe')->where('setting', 'publishable_key')->value('value'))
        ->toBe('pk_live_updated');
});

test('retyping a secret still replaces it', function () {
    GatewaySettings::updateOrCreate(
        ['gateway' => 'stripe', 'setting' => 'secret_key'],
        ['value' => 'sk_live_original']
    );

    $this->actingAs(secretsAdmin(), 'admin')
        ->post(route('admin.config.gateways.settings.update', 'stripe'), [
            'active' => '1',
            'settings' => ['secret_key' => 'sk_live_rotated'],
        ])->assertRedirect();

    expect(GatewaySettings::where('gateway', 'stripe')->where('setting', 'secret_key')->value('value'))
        ->toBe('sk_live_rotated');
});

test('settings cannot be written for a gateway that is not installed', function () {
    $this->actingAs(secretsAdmin(), 'admin')
        ->post(route('admin.config.gateways.settings.update', 'not-a-real-gateway'), [
            'active' => '1',
            'settings' => ['secret_key' => 'sk_live_junk'],
        ])->assertNotFound();

    expect(GatewaySettings::where('gateway', 'not-a-real-gateway')->count())->toBe(0);
});

test('a value that is not a string is refused instead of written', function () {
    $this->actingAs(secretsAdmin(), 'admin')
        ->post(route('admin.config.gateways.settings.update', 'stripe'), [
            'active' => '1',
            'settings' => ['secret_key' => ['an', 'array']],
        ])->assertSessionHasErrors('settings.secret_key');

    expect(GatewaySettings::where('gateway', 'stripe')->where('setting', 'secret_key')->count())->toBe(0);
});

test('an oversized value is refused instead of written', function () {
    $this->actingAs(secretsAdmin(), 'admin')
        ->post(route('admin.config.gateways.settings.update', 'stripe'), [
            'active' => '1',
            'settings' => ['publishable_key' => str_repeat('x', 9000)],
        ])->assertSessionHasErrors('settings.publishable_key');

    expect(GatewaySettings::where('gateway', 'stripe')->where('setting', 'publishable_key')->count())->toBe(0);
});

test('a key that is not shaped like a setting name is dropped', function () {
    $this->actingAs(secretsAdmin(), 'admin')
        ->post(route('admin.config.gateways.settings.update', 'stripe'), [
            'active' => '1',
            'settings' => [
                'publishable_key' => 'pk_live_kept',
                'not a setting name!' => 'dropped',
            ],
        ])->assertRedirect();

    expect(GatewaySettings::where('gateway', 'stripe')->where('setting', 'publishable_key')->value('value'))
        ->toBe('pk_live_kept')
        ->and(GatewaySettings::where('gateway', 'stripe')->where('setting', 'not a setting name!')->count())
        ->toBe(0);
});

test('a setting a module never declares is still storable', function () {
    // registrars() reads a "name" setting that no module declares - it is the
    // operator's own label for the registrar. Validating against the declared
    // fields would have made it, and any setting a third-party module reads
    // without advertising, permanently unsettable.
    $this->actingAs(secretsAdmin(), 'admin')
        ->post(route('admin.config.registrars.settings.update', 'enom'), [
            'visible' => '1',
            'settings' => ['name' => 'My Reseller Account', 'uid' => 'reseller1'],
        ])->assertRedirect();

    expect(RegistrarSettings::where('registrar', 'enom')->where('setting', 'name')->value('value'))
        ->toBe('My Reseller Account');
});
