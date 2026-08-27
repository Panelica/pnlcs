<?php

use App\Models\Admin;

/**
 * The client login was fixed to forget a stale 2fa pass before challenging;
 * the admin login carried the same latent flaw. session()->regenerate() keeps
 * the session DATA across a fresh login, so a leftover admin_2fa_verified -
 * from an admin who logged in on this browser without logging out - would let
 * the next admin through the AdminTwoFactorVerify middleware without their own
 * second factor. Ordinary logout invalidates the session, but a re-login over
 * a live session does not.
 */
it('forgets a stale admin 2FA pass when a 2FA admin logs in', function () {
    $admin = Admin::factory()->create([
        'second_factor_type' => 'totp',
        'second_factor_secret' => 'JBSWY3DPEHPK3PXP',
    ]);

    test()->withSession(['admin_2fa_verified' => true])
        ->post(route('admin.login.submit'), ['username' => $admin->username, 'password' => 'password'])
        ->assertRedirect(route('admin.2fa.verify'))
        ->assertSessionMissing('admin_2fa_verified');
});

it('still logs a non-2FA admin straight in', function () {
    $admin = Admin::factory()->create(['second_factor_type' => null, 'second_factor_secret' => null]);

    test()->post(route('admin.login.submit'), ['username' => $admin->username, 'password' => 'password'])
        ->assertRedirect(route('admin.dashboard'));
});
