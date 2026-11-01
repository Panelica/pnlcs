<?php

use App\Models\Client;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/*
 * The second factor stands between a password (or a Google/GitHub account)
 * and the customer's account.
 *
 * - Signing in with Google or GitHub starts unverified. 2fa_verified is a
 *   plain session key; a session that had already answered a second factor
 *   for one login must not answer it for the next.
 * - The cart and the checkout send a login that has given its password but
 *   not its second factor to answer it, as the client area does. Visitors
 *   are not affected.
 */
function socialTwoFactorUser(string $email): User
{
    $user = User::factory()->create(['email' => $email, 'second_factor_type' => 'totp', 'second_factor_secret' => 'JBSWY3DPEHPK3PXP']);
    $user->clients()->attach(Client::factory()->create()->id, ['owner' => true]);

    return $user;
}

function fakeSocialProviderUser(string $driver, string $email, string $id): void
{
    $user = (new SocialiteUser)->map(['id' => $id, 'nickname' => 'someone', 'name' => 'Ada Lovelace', 'email' => $email]);

    $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
    $provider->shouldReceive('user')->andReturn($user);
    Socialite::shouldReceive('driver')->with($driver)->andReturn($provider);
}

test('a Google sign-in does not inherit a second factor answered earlier in the session', function () {
    Setting::set('GoogleLoginEnabled', '1');
    Setting::set('GoogleClientId', 'client-id.apps.googleusercontent.com');
    Setting::set('GoogleClientSecret', 'client-secret');
    $victim = socialTwoFactorUser('victim@example.test');
    fakeSocialProviderUser('google', 'victim@example.test', 'google-7');

    $this->withSession(['2fa_verified' => true])->get(route('client.social.google.callback'));

    expect(auth()->id())->toBe($victim->id);
    $this->get(route('client.home'))->assertRedirect(route('client.2fa.verify'));
});

test('a GitHub sign-in does not inherit a second factor answered earlier in the session', function () {
    Setting::set('GithubLoginEnabled', '1');
    Setting::set('GithubClientId', 'Iv1.github-client');
    Setting::set('GithubClientSecret', 'github-secret');
    $victim = socialTwoFactorUser('victim@example.test');
    fakeSocialProviderUser('github', 'victim@example.test', '99001');

    $this->withSession(['2fa_verified' => true])->get(route('client.social.github.callback'));

    expect(auth()->id())->toBe($victim->id);
    $this->get(route('client.home'))->assertRedirect(route('client.2fa.verify'));
});

test('a login that has not answered its second factor is sent to it from the cart and the checkout', function () {
    $user = socialTwoFactorUser('pending@example.test');

    $this->actingAs($user)->get(route('client.cart.index'))->assertRedirect(route('client.2fa.verify'));
    $this->actingAs($user)->get(route('client.cart.checkout'))->assertRedirect(route('client.2fa.verify'));
    $this->actingAs($user)->post(route('client.cart.process'), ['payment_method' => 'banktransfer', 'terms' => '1'])
        ->assertRedirect(route('client.2fa.verify'));

    expect(Order::count())->toBe(0);
});

test('the cart opens once the second factor is answered, and for visitors', function () {
    $user = socialTwoFactorUser('done@example.test');

    $this->get(route('client.cart.index'))->assertOk();
    $this->actingAs($user)->withSession(['2fa_verified' => true])->get(route('client.cart.index'))->assertOk();
});
