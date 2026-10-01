<?php

use App\Mail\NewDeviceLoginMail;
use App\Models\Client;
use App\Models\User;
use App\Models\UserLogin;
use App\Services\LoginRecorder;
use Illuminate\Support\Facades\Mail;
use PragmaRX\Google2FA\Google2FA;

/*
 * The client area keeps a sign-in history and warns about new devices.
 *
 * users.last_login / last_login_ip held only the latest sign-in: a customer
 * could not see that someone else had been in, wrong passwords left no trace
 * they could see, and nothing told a new browser from a known one.
 */

const LHN_UA_MAC = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36';
const LHN_UA_WIN = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0';

function lhnUser(array $attributes = []): User
{
    $user = User::factory()->create($attributes + ['email' => 'owner@example.test']);
    $user->clients()->attach(Client::factory()->create()->id);

    return $user;
}

/** Sign in through the login form, from a browser that may carry the device cookie. */
function lhnSignIn(?string $device = null, string $ua = LHN_UA_MAC, string $password = 'password')
{
    $t = test()->withHeader('User-Agent', $ua)->withServerVariables(['REMOTE_ADDR' => '203.0.113.7']);
    if ($device !== null) {
        $t = $t->withCookie(LoginRecorder::COOKIE, $device);
    }
    $response = $t->post(route('client.login.submit'), ['email' => 'owner@example.test', 'password' => $password]);
    auth()->logout();

    return $response;
}

it('records a sign-in and mails nobody the first time', function () {
    Mail::fake();
    $user = lhnUser();

    $response = lhnSignIn();

    $login = UserLogin::where('user_id', $user->id)->sole();
    expect($login->successful)->toBeTrue()
        ->and($login->ip_address)->toBe('203.0.113.7')
        ->and($login->device)->toHaveLength(64)
        ->and($response->getCookie(LoginRecorder::COOKIE))->not->toBeNull();
    Mail::assertNothingQueued();
});

it('stays quiet on a browser it has seen and warns about a new one', function () {
    Mail::fake();
    $user = lhnUser();

    $token = lhnSignIn()->getCookie(LoginRecorder::COOKIE)->getValue();
    lhnSignIn($token);
    Mail::assertNothingQueued();

    // Another browser: its own token, one this login has never used. (The
    // test client keeps cookies between requests, so "no cookie" cannot be
    // said here; an unknown token is the same thing to the recorder.)
    lhnSignIn(str_repeat('n', 40), LHN_UA_WIN);

    Mail::assertQueued(NewDeviceLoginMail::class, fn ($mail) => $mail->hasTo('owner@example.test')
        && $mail->loginIp === '203.0.113.7'
        && $mail->loginDevice === 'Firefox on Windows');
    expect(UserLogin::where('user_id', $user->id)->where('successful', true)->count())->toBe(3);
});

it('keeps a wrong password in the history of the login it was for', function () {
    Mail::fake();
    $user = lhnUser();

    lhnSignIn(null, LHN_UA_MAC, 'not-the-password')->assertSessionHasErrors('email');

    $login = UserLogin::where('user_id', $user->id)->sole();
    expect($login->successful)->toBeFalse()->and($login->device)->toBeNull();
    Mail::assertNothingQueued();
});

it('counts a sign-in with a second factor once it is answered', function () {
    Mail::fake();
    $secret = (new Google2FA)->generateSecretKey();
    $user = lhnUser(['second_factor_type' => 'totp', 'second_factor_secret' => $secret]);

    test()->post(route('client.login.submit'), ['email' => 'owner@example.test', 'password' => 'password'])
        ->assertRedirect(route('client.2fa.verify'));
    expect(UserLogin::where('user_id', $user->id)->count())->toBe(0);

    test()->post(route('client.2fa.verify.submit'), ['code' => (new Google2FA)->getCurrentOtp($secret)])
        ->assertRedirect();
    expect(UserLogin::where('user_id', $user->id)->where('successful', true)->count())->toBe(1);
});

it('shows the history on the security page', function () {
    $user = lhnUser();
    UserLogin::create(['user_id' => $user->id, 'successful' => true, 'ip_address' => '198.51.100.4', 'user_agent' => LHN_UA_WIN]);
    UserLogin::create(['user_id' => $user->id, 'successful' => false, 'ip_address' => '192.0.2.99', 'user_agent' => LHN_UA_MAC]);

    test()->actingAs($user)->get(route('client.account.security'))
        ->assertOk()
        ->assertSee('198.51.100.4')->assertSee('Firefox on Windows')
        ->assertSee('192.0.2.99')->assertSee(__('client.security.login_failed'));
});

it('keeps only the newest rows for a login', function () {
    $user = lhnUser();
    foreach (range(1, LoginRecorder::KEEP + 5) as $i) {
        UserLogin::create(['user_id' => $user->id, 'successful' => false, 'ip_address' => "10.0.0.{$i}"]);
    }

    lhnSignIn();

    expect(UserLogin::where('user_id', $user->id)->count())->toBe(LoginRecorder::KEEP)
        ->and(UserLogin::where('user_id', $user->id)->where('ip_address', '10.0.0.1')->exists())->toBeFalse();
});

it('runs the UserLogin and ClientLogin hooks', function () {
    $user = lhnUser();
    $seen = [];
    add_hook('UserLogin', 1, function ($vars) use (&$seen) {
        $seen[] = ['UserLogin', $vars['user']->id, $vars['method']];
    });
    add_hook('ClientLogin', 1, function ($vars) use (&$seen) {
        $seen[] = ['ClientLogin', $vars['user']->id, $vars['method']];
    });

    lhnSignIn();

    expect($seen)->toBe([['UserLogin', $user->id, 'password'], ['ClientLogin', $user->id, 'password']]);
});
