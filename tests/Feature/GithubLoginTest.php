<?php

use App\Enums\ClientStatus;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\AuthAccountLink;
use App\Models\Client;
use App\Models\Setting;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/*
 * Signing in with GitHub, by the same rules as Google.
 *
 * Off and 404 until an operator supplies their own OAuth app. A login is
 * recognised by its GitHub id (auth_account_links), and an existing account
 * is linked by address only when GitHub verified it: Socialite returns the
 * primary, verified address and none otherwise.
 */
function enableGithubLogin(): void
{
    Setting::set('GithubLoginEnabled', '1');
    Setting::set('GithubClientId', 'Iv1.github-client');
    Setting::set('GithubClientSecret', 'github-secret');
}

function fakeGithubUser(?string $email, string $id = '583231', string $name = 'Ada Lovelace', string $login = 'ada'): void
{
    $user = (new SocialiteUser)->map(['id' => $id, 'nickname' => $login, 'name' => $name, 'email' => $email]);

    $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
    $provider->shouldReceive('user')->andReturn($user);
    Socialite::shouldReceive('driver')->with('github')->andReturn($provider);
}

test('the routes and the button stay hidden until an operator turns it on', function () {
    $this->get(route('client.social.github.redirect'))->assertNotFound();
    $this->get(route('client.social.github.callback'))->assertNotFound();
    $this->get(route('client.login'))->assertOk()->assertDontSee(__('auth.continue_with_github'));

    enableGithubLogin();
    $this->get(route('client.login'))->assertOk()->assertSee(__('auth.continue_with_github'))->assertDontSee(__('auth.continue_with_google'));
    $this->get(route('client.register'))->assertOk()->assertSee(__('auth.continue_with_github'));
});

test('a first-time visitor gets an account, linked by GitHub id, and is asked for an address', function () {
    enableGithubLogin();
    fakeGithubUser('ada@example.test');

    $this->get(route('client.social.github.callback'))->assertRedirect(route('client.account.profile'));

    $user = User::where('email', 'ada@example.test')->sole();
    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and(AuthAccountLink::where('provider', 'github')->where('provider_user_id', '583231')->value('user_id'))->toBe($user->id)
        ->and(auth()->id())->toBe($user->id);
});

test('an existing customer is linked by verified address and recognised by id afterwards', function () {
    enableGithubLogin();
    $user = User::factory()->create(['email' => 'ada@example.test']);
    $user->clients()->attach(Client::factory()->create()->id, ['owner' => true]);

    fakeGithubUser('ada@example.test');
    $this->get(route('client.social.github.callback'))->assertRedirect(route('client.home'));
    expect(auth()->id())->toBe($user->id);
    auth()->logout();

    // The address on GitHub changed since: the id still finds the same login.
    fakeGithubUser('ada-new@example.test');
    $this->get(route('client.social.github.callback'))->assertRedirect(route('client.home'));
    expect(auth()->id())->toBe($user->id)
        ->and(User::where('email', 'ada-new@example.test')->exists())->toBeFalse();
});

test('a GitHub account without a verified address is turned away', function () {
    enableGithubLogin();
    User::factory()->create(['email' => 'ada@example.test']);
    fakeGithubUser(null);

    $this->get(route('client.social.github.callback'))
        ->assertRedirect(route('client.login'))->assertSessionHas('error', __('auth.github_no_verified_email'));

    expect(auth()->check())->toBeFalse()->and(AuthAccountLink::count())->toBe(0);
});

test('a closed account stays closed', function () {
    enableGithubLogin();
    $closed = User::factory()->create(['email' => 'closed@example.test']);
    $closed->clients()->attach(Client::factory()->create(['status' => ClientStatus::Closed->value])->id, ['owner' => true]);
    fakeGithubUser('closed@example.test');

    $this->get(route('client.social.github.callback'))->assertRedirect(route('client.login'));
    expect(auth()->check())->toBeFalse();
});

test('the second factor is still asked for', function () {
    enableGithubLogin();
    $user = User::factory()->create(['email' => 'tf@example.test', 'second_factor_type' => 'totp', 'second_factor_secret' => 'JBSWY3DPEHPK3PXP']);
    $user->clients()->attach(Client::factory()->create()->id, ['owner' => true]);
    fakeGithubUser('tf@example.test');

    $this->get(route('client.social.github.callback'));
    $this->get(route('client.home'))->assertRedirect(route('client.2fa.verify'));
});

test('the secret is kept when the settings form leaves it blank', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'S', 'permissions' => ['manage_settings']])->id]);
    Setting::set('GithubClientSecret', 'kept-secret');

    $this->actingAs($admin, 'admin')->post(route('admin.settings.general.update'), [
        'GithubLoginEnabled' => '1', 'GithubClientId' => 'Iv1.x', 'GithubClientSecret' => '',
    ])->assertRedirect();

    expect(Setting::get('GithubClientSecret'))->toBe('kept-secret')->and(Setting::get('GithubClientId'))->toBe('Iv1.x');
});
