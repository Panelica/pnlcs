<?php

use App\Models\Client;
use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

/*
 * The backup codes are shown once, right after two-factor sign-in is
 * switched on.
 *
 * AuthController::enable2fa() generates eight backup codes, stores them and
 * flashes them to the security page, and the sign-in step accepts them. The
 * security page never printed them, so a customer who lost their phone held
 * codes they had never seen and could not get back in.
 */

function tfbCustomer(): User
{
    $user = User::factory()->create();
    $user->clients()->attach(Client::factory()->create()->id);

    return $user;
}

it('shows the backup codes after switching two-factor sign-in on', function () {
    $user = tfbCustomer();
    $this->actingAs($user);

    $this->get(route('client.2fa.enable'))->assertOk();
    $secret = session('2fa_setup_secret');
    $code = (new Google2FA)->getCurrentOtp($secret);

    $this->post(route('client.2fa.enable'), ['code' => $code])
        ->assertRedirect(route('client.account.security'));

    $codes = $user->fresh()->backup_codes;
    expect($codes)->toHaveCount(8);

    $page = $this->get(route('client.account.security'))->assertOk();
    foreach ($codes as $backupCode) {
        $page->assertSee($backupCode);
    }
});

it('does not show them again on a later visit', function () {
    $user = tfbCustomer();
    $user->update(['second_factor_type' => 'totp', 'second_factor_secret' => (new Google2FA)->generateSecretKey(), 'backup_codes' => ['AAAA-1111']]);

    $this->actingAs($user)->withSession(['2fa_verified' => true])
        ->get(route('client.account.security'))
        ->assertOk()
        ->assertDontSee('AAAA-1111');
});
