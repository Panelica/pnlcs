<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\AdminTrustedDevice;
use App\Models\Language;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

/*
 * A browser that has already passed the administrator's second factor can be
 * remembered. The cookie holds a random token, the database only its hash, and
 * the trust ends on the date chosen at sign-in or the moment the password or
 * second factor changes.
 */

function trustedAdmin(): Admin
{
    return Admin::factory()->create([
        'password' => Hash::make('the-admin-password'),
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
        'second_factor_type' => 'totp',
        'second_factor_secret' => 'JBSWY3DPEHPK3PXP',
        'language' => 'en',
    ]);
}

function loginTrustedAdmin(Admin $admin)
{
    return test()->post(route('admin.login.submit'), [
        'username' => $admin->username,
        'password' => 'the-admin-password',
    ]);
}

function answerTrustedAdmin(array $extra = [])
{
    $code = (new Google2FA)->getCurrentOtp('JBSWY3DPEHPK3PXP');

    return test()->post(route('admin.2fa.verify.submit'), $extra + ['code' => $code]);
}

it('trusts a browser for the chosen number of days and skips the code next time', function () {
    $admin = trustedAdmin();

    loginTrustedAdmin($admin)->assertRedirect(route('admin.2fa.verify'));
    $response = answerTrustedAdmin(['trust_device' => '1', 'trust_days' => '7'])
        ->assertRedirect(route('admin.dashboard'));

    $device = AdminTrustedDevice::where('admin_id', $admin->id)->sole();
    $token = $response->getCookie(AdminTrustedDevice::COOKIE_NAME)?->getValue();

    expect($device->expires_at->isBetween(now()->addDays(6)->addHour(), now()->addDays(7)->addHour()))->toBeTrue()
        ->and($token)->not->toBeNull();

    test()->post(route('admin.logout'))->assertRedirect(route('admin.login'));
    test()->withCookie(AdminTrustedDevice::COOKIE_NAME, $token)
        ->post(route('admin.login.submit'), ['username' => $admin->username, 'password' => 'the-admin-password'])
        ->assertRedirect(route('admin.dashboard'));
});

it('uses 30 days when the number is left empty', function () {
    $admin = trustedAdmin();

    loginTrustedAdmin($admin)->assertRedirect(route('admin.2fa.verify'));
    answerTrustedAdmin(['trust_device' => '1', 'trust_days' => '']);

    $device = AdminTrustedDevice::where('admin_id', $admin->id)->sole();
    expect($device->expires_at->isBetween(now()->addDays(29)->addHour(), now()->addDays(30)->addHour()))->toBeTrue();
});

it('refuses a trust period outside 1 to 365 days', function () {
    $admin = trustedAdmin();

    loginTrustedAdmin($admin)->assertRedirect(route('admin.2fa.verify'));
    answerTrustedAdmin(['trust_device' => '1', 'trust_days' => '366'])
        ->assertSessionHasErrors('trust_days');

    expect(AdminTrustedDevice::where('admin_id', $admin->id)->count())->toBe(0);
});

it('asks for the code again after the device trust expires', function () {
    $admin = trustedAdmin();

    loginTrustedAdmin($admin)->assertRedirect(route('admin.2fa.verify'));
    $token = answerTrustedAdmin(['trust_device' => '1', 'trust_days' => '1'])
        ->getCookie(AdminTrustedDevice::COOKIE_NAME)?->getValue();

    AdminTrustedDevice::where('admin_id', $admin->id)->update(['expires_at' => now()->subMinute()]);

    test()->post(route('admin.logout'));
    test()->withCookie(AdminTrustedDevice::COOKIE_NAME, $token)
        ->post(route('admin.login.submit'), ['username' => $admin->username, 'password' => 'the-admin-password'])
        ->assertRedirect(route('admin.2fa.verify'));
});

it('removes remembered browsers when 2FA is turned off', function () {
    $admin = trustedAdmin();
    AdminTrustedDevice::issue($admin, 30);

    test()->withSession(['admin_2fa_verified' => true])
        ->actingAs($admin, 'admin')
        ->post(route('admin.2fa.disable'), ['password' => 'the-admin-password'])
        ->assertRedirect();

    expect(AdminTrustedDevice::where('admin_id', $admin->id)->count())->toBe(0);
});

it('removes remembered browsers when the password changes', function () {
    $admin = trustedAdmin();
    AdminTrustedDevice::issue($admin, 30);
    Language::updateOrCreate(['code' => 'en'], [
        'name' => 'English', 'native_name' => 'English', 'direction' => 'ltr',
        'is_active' => true, 'is_default' => true, 'sort_order' => 1, 'translation_progress' => 100,
    ]);

    test()->withSession(['admin_2fa_verified' => true])
        ->actingAs($admin, 'admin')
        ->post(route('admin.my-account.update'), [
            'first_name' => $admin->first_name,
            'last_name' => $admin->last_name,
            'email' => $admin->email,
            'signature' => '',
            'language' => 'en',
            'new_password' => 'a-new-admin-password',
            'new_password_confirmation' => 'a-new-admin-password',
        ])->assertRedirect();

    expect(AdminTrustedDevice::where('admin_id', $admin->id)->count())->toBe(0);
});

it('lets an administrator remove their own remembered browser but not somebody else\'s', function () {
    $admin = Admin::factory()->create([
        'password' => Hash::make('the-admin-password'),
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
        'second_factor_type' => null,
        'second_factor_secret' => null,
    ]);
    $other = trustedAdmin();
    $device = AdminTrustedDevice::create([
        'admin_id' => $admin->id,
        'token_hash' => hash('sha256', 'token-for-this-device'),
        'expires_at' => now()->addDays(30),
        'last_used_at' => now(),
    ]);
    AdminTrustedDevice::issue($other, 30);
    $foreign = AdminTrustedDevice::where('admin_id', $other->id)->sole();

    test()->withSession(['admin_2fa_verified' => true])
        ->actingAs($admin, 'admin')
        ->delete(route('admin.my-account.trusted-devices.destroy', $device))
        ->assertRedirect();

    expect(AdminTrustedDevice::find($device->id))->toBeNull();

    test()->withSession(['admin_2fa_verified' => true])
        ->actingAs($admin, 'admin')
        ->delete(route('admin.my-account.trusted-devices.destroy', $foreign))
        ->assertNotFound();

    expect(AdminTrustedDevice::find($foreign->id))->not->toBeNull();
});

it('shows the security layout and the remembered browser on My Account', function () {
    $admin = trustedAdmin();
    $device = AdminTrustedDevice::issue($admin, 30, '198.51.100.4', 'Firefox on Linux');
    $device = AdminTrustedDevice::where('admin_id', $admin->id)->sole();

    $page = test()->withSession(['admin_2fa_verified' => true])
        ->actingAs($admin, 'admin')
        ->get(route('admin.my-account'))
        ->assertOk();

    $page->assertSee('class="account-layout"', false)
        ->assertSee('class="account-side"', false)
        ->assertSee('198.51.100.4')
        ->assertSee(route('admin.my-account.trusted-devices.destroy', $device), false);
});

it('puts the QR code and the manual code in separate layout cells', function () {
    $admin = trustedAdmin();

    test()->withSession(['admin_2fa_verified' => true, '2fa_setup_secret' => 'JBSWY3DPEHPK3PXP'])
        ->actingAs($admin, 'admin')
        ->get(route('admin.2fa.enable'))
        ->assertOk()
        ->assertSee('class="twofa-setup"', false)
        ->assertSee('class="twofa-qr"', false)
        ->assertSee('class="twofa-manual"', false);
});
