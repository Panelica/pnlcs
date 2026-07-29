<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\User;
use App\Services\TwoFactorService;
use PragmaRX\Google2FA\Google2FA;

/**
 * Two-factor authentication has to actually stand between the password and
 * the account.
 *
 * Logging in authenticates the session first and only then redirects to the
 * code page, which is fine as long as something stops the session going
 * anywhere else until the code is in. Both middlewares for that existed and
 * were registered under an alias — and were applied to no route at all, on
 * either side of the panel.
 */
function userWithTwoFactor(): User
{
    $secret = app(TwoFactorService::class)->generateSecret();

    $user = User::factory()->create([
        'email' => 'twofactor@example.com',
        'password' => bcrypt('Secret123!'),
        'second_factor_type' => 'totp',
        'second_factor_secret' => $secret,
    ]);

    $user->clients()->attach(Client::factory()->create()->id);

    return $user;
}

test('logging in with 2FA on lands on the code page', function () {
    $user = userWithTwoFactor();

    $this->post(route('client.login.submit'), [
        'email' => $user->email,
        'password' => 'Secret123!',
    ])->assertRedirect(route('client.2fa.verify'));
});

test('the code page cannot be walked around', function () {
    $user = userWithTwoFactor();

    $this->post(route('client.login.submit'), [
        'email' => $user->email,
        'password' => 'Secret123!',
    ])->assertRedirect(route('client.2fa.verify'));

    // The session is authenticated at this point. Without the code it must not
    // be able to reach anything.
    $blocked = [];

    foreach ([
        'home' => route('client.home'),
        'services' => route('client.services.index'),
        'invoices' => route('client.invoices.index'),
        'account' => route('client.account.profile'),
    ] as $label => $url) {
        $response = $this->get($url);

        if (! $response->isRedirect(route('client.2fa.verify'))) {
            $blocked[] = $label.' → HTTP '.$response->status();
        }
    }

    expect($blocked)->toBe([]);
});

test('the code lets the customer in', function () {
    $user = userWithTwoFactor();

    $this->post(route('client.login.submit'), [
        'email' => $user->email,
        'password' => 'Secret123!',
    ])->assertRedirect(route('client.2fa.verify'));

    $this->post(route('client.2fa.verify.submit'), [
        'code' => (new Google2FA)->getCurrentOtp($user->second_factor_secret),
    ])->assertRedirect();

    $this->get(route('client.home'))->assertOk();
});

test('a customer without 2FA is not sent to the code page', function () {
    $user = User::factory()->create(['password' => bcrypt('Secret123!')]);
    $user->clients()->attach(Client::factory()->create()->id);

    $this->post(route('client.login.submit'), [
        'email' => $user->email,
        'password' => 'Secret123!',
    ])->assertRedirect(route('client.home'));

    $this->get(route('client.home'))->assertOk();
});

test('an admin cannot walk around their own code page either', function () {
    $secret = app(TwoFactorService::class)->generateSecret();

    $admin = Admin::factory()->create([
        'username' => 'admin2fa',
        'email' => 'admin2fa@example.com',
        'password' => bcrypt('Secret123!'),
        'second_factor_type' => 'totp',
        'second_factor_secret' => $secret,
    ]);

    $this->post(route('admin.login.submit'), [
        'username' => $admin->username,
        'password' => 'Secret123!',
    ])->assertRedirect(route('admin.2fa.verify'));

    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.2fa.verify'));
});
