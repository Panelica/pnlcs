<?php

use App\Models\BannedEmail;
use App\Models\Client;
use App\Models\Setting;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/*
 * Signing in with Google.
 *
 * Off by default and 404 until an operator supplies their own OAuth client -
 * a billing panel should not expose a login route it cannot honour, and no
 * credential of ours is ever shipped.
 *
 * The part that matters beyond convenience: Google hands over a name and an
 * email and nothing else, so an account opened this way still has no address
 * to issue an invoice to. It is sent to the profile page to give one, and
 * checkout refuses to take money until it has one.
 */
function enableGoogleLogin(): void
{
    Setting::set('GoogleLoginEnabled', '1');
    Setting::set('GoogleClientId', 'client-id.apps.googleusercontent.com');
    Setting::set('GoogleClientSecret', 'client-secret');
}

function fakeGoogleUser(string $email, string $id = 'google-1', string $name = 'Ada Lovelace'): void
{
    $user = (new SocialiteUser)->map(['id' => $id, 'name' => $name, 'email' => $email]);

    $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
    $provider->shouldReceive('user')->andReturn($user);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

test('the routes stay closed until an operator turns it on', function () {
    $this->get(route('client.social.google.redirect'))->assertNotFound();
    $this->get(route('client.social.google.callback'))->assertNotFound();
});

test('the sign-in page shows no button while it is off', function () {
    $this->get(route('client.login'))->assertOk()->assertDontSee(__('auth.continue_with_google'));
});

test('the sign-in page offers the button once it is configured', function () {
    enableGoogleLogin();

    $this->get(route('client.login'))->assertOk()->assertSee(__('auth.continue_with_google'));
});

test('a first-time visitor gets an account and is asked for an address', function () {
    enableGoogleLogin();
    fakeGoogleUser('ada@example.test');

    $this->get(route('client.social.google.callback'))
        ->assertRedirect(route('client.account.profile'));

    $user = User::where('email', 'ada@example.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->google_id)->toBe('google-1')
        // No invented password anybody could reset into.
        ->and($user->password)->toBeNull()
        ->and(auth()->id())->toBe($user->id)
        ->and(Client::where('email', 'ada@example.test')->exists())->toBeTrue();
});

test('an existing customer is linked by email instead of duplicated', function () {
    enableGoogleLogin();

    $existing = User::factory()->create(['email' => 'existing@example.test']);
    fakeGoogleUser('existing@example.test', 'google-42');

    $this->get(route('client.social.google.callback'))->assertRedirect();

    expect(User::where('email', 'existing@example.test')->count())->toBe(1)
        ->and($existing->fresh()->google_id)->toBe('google-42')
        ->and(auth()->id())->toBe($existing->id);
});

test('a returning google account is recognised by its id, not its address', function () {
    enableGoogleLogin();

    $user = User::factory()->create(['email' => 'old-address@example.test', 'google_id' => 'google-7']);
    // Google now reports a different address for the same account.
    fakeGoogleUser('new-address@example.test', 'google-7');

    $this->get(route('client.social.google.callback'))->assertRedirect();

    expect(auth()->id())->toBe($user->id)
        ->and(User::count())->toBe(1);
});

test('a banned address cannot open an account through google', function () {
    enableGoogleLogin();
    BannedEmail::create(['domain' => 'example.test', 'reason' => 'test']);
    fakeGoogleUser('banned@example.test', 'google-99');

    $this->get(route('client.social.google.callback'))
        ->assertRedirect(route('client.login'))
        ->assertSessionHas('error');

    expect(User::where('email', 'banned@example.test')->exists())->toBeFalse();
});

test('a closed account cannot come back in through google', function () {
    // The password door checks the account status; this door did not, so a
    // customer whose account had been closed could still sign in with Google.
    enableGoogleLogin();
    $user = User::factory()->create(['email' => 'closed@example.test', 'google_id' => 'google-closed']);
    $client = Client::factory()->create(['email' => 'closed@example.test', 'status' => 'closed']);
    $user->clients()->attach($client->id);
    fakeGoogleUser('closed@example.test', 'google-closed');

    $this->get(route('client.social.google.callback'))->assertRedirect(route('client.login'));

    $this->assertGuest();
});

test('an address google has not verified is never linked to an existing account', function () {
    // Linking by address is only safe when Google vouches for the address;
    // otherwise anyone who can put a victim's address on a Google account
    // would walk into the victim's customer account.
    enableGoogleLogin();
    User::factory()->create(['email' => 'victim@example.test']);
    $googleUser = (new SocialiteUser)->map(['id' => 'google-x', 'name' => 'Some Body', 'email' => 'victim@example.test']);
    $googleUser->setRaw(['email' => 'victim@example.test', 'email_verified' => false]);
    $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
    $provider->shouldReceive('user')->andReturn($googleUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(route('client.social.google.callback'))->assertRedirect(route('client.login'));

    $this->assertGuest();
    expect(User::where('email', 'victim@example.test')->value('google_id'))->toBeNull();
});
