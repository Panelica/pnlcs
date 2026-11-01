<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

/*
 * Wrong second-factor codes are counted per account.
 *
 * The routes allow ten a minute per visitor; somebody who already has the
 * password can sign in again, from another address, and start over. After
 * five wrong codes the account waits fifteen minutes, whichever session or
 * address the next code comes from - even the right one.
 */
function attemptsTotpUser(): User
{
    $user = User::factory()->create(['second_factor_type' => 'totp', 'second_factor_secret' => 'JBSWY3DPEHPK3PXP']);
    $user->clients()->attach(Client::factory()->create()->id, ['owner' => true]);

    return $user;
}

function attemptsTotpAdmin(): Admin
{
    return Admin::factory()->create(['second_factor_type' => 'totp', 'second_factor_secret' => 'JBSWY3DPEHPK3PXP']);
}

test('a customer account waits after five wrong codes, across sessions and addresses', function () {
    $user = attemptsTotpUser();

    foreach (range(1, 5) as $i) {
        $this->actingAs($user)->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$i}"])
            ->post(route('client.2fa.verify.submit'), ['code' => '000000']);
    }

    // A fresh session, another address, the right code: still not now.
    $this->flushSession();
    $this->actingAs($user)->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->post(route('client.2fa.verify.submit'), ['code' => (new Google2FA)->getCurrentOtp('JBSWY3DPEHPK3PXP')])
        ->assertSessionHasErrors('code');
    expect(session('2fa_verified'))->toBeNull();

    $this->travel(16)->minutes();
    $this->actingAs($user)->post(route('client.2fa.verify.submit'), ['code' => (new Google2FA)->getCurrentOtp('JBSWY3DPEHPK3PXP')])
        ->assertSessionHasNoErrors();
    expect(session('2fa_verified'))->toBeTrue();
});

test('an admin account waits after five wrong codes, across sessions and addresses', function () {
    $admin = attemptsTotpAdmin();

    foreach (range(1, 5) as $i) {
        $this->actingAs($admin, 'admin')->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$i}"])
            ->post(route('admin.2fa.verify.submit'), ['code' => '000000']);
    }

    $this->flushSession();
    $this->actingAs($admin, 'admin')->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->post(route('admin.2fa.verify.submit'), ['code' => (new Google2FA)->getCurrentOtp('JBSWY3DPEHPK3PXP')])
        ->assertSessionHasErrors('code');
    expect(session('admin_2fa_verified'))->toBeNull();
});

test('a right code clears the count', function () {
    $user = attemptsTotpUser();

    foreach (range(1, 4) as $i) {
        $this->actingAs($user)->post(route('client.2fa.verify.submit'), ['code' => '000000']);
    }
    $this->actingAs($user)->post(route('client.2fa.verify.submit'), ['code' => (new Google2FA)->getCurrentOtp('JBSWY3DPEHPK3PXP')])
        ->assertSessionHasNoErrors();

    // Four more wrong ones are four, not eight.
    $this->flushSession();
    foreach (range(1, 4) as $i) {
        $this->actingAs($user)->post(route('client.2fa.verify.submit'), ['code' => '000000']);
    }
    $this->actingAs($user)->post(route('client.2fa.verify.submit'), ['code' => (new Google2FA)->getCurrentOtp('JBSWY3DPEHPK3PXP')])
        ->assertSessionHasNoErrors();
});
