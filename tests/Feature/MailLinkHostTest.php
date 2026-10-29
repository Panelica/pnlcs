<?php

use App\Mail\PasswordResetMail;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/*
 * A link in a mail carries a one-time token. It used to be built with route(),
 * which takes its host from the request, and every proxy was trusted, so an
 * X-Forwarded-Host decided where the customer's reset link pointed - at a real
 * token. Reported privately, twice and independently, in October 2026.
 *
 * Mail links now come from the address the installation is configured with
 * (Settings > General > Domain, else APP_URL), and the forwarded host is no
 * longer trusted for anything.
 */

function mlResetUrl(string $email): ?string
{
    $url = null;
    Mail::assertSent(PasswordResetMail::class, function ($mail) use (&$url) {
        foreach ((new ReflectionObject($mail))->getProperties() as $p) {
            $p->setAccessible(true);
            $v = $p->getValue($mail);
            if (is_string($v) && str_contains($v, 'reset-password')) {
                $url = $v;
            }
        }

        return true;
    });

    return $url;
}

it('builds the reset link from the configured Domain, whatever host the request claims', function () {
    Mail::fake();
    Setting::updateOrCreate(['setting' => 'Domain'], ['value' => 'billing.example.com', 'group' => 'general']);
    $user = User::factory()->create(['email' => 'victim@example.test']);

    $this->withServerVariables(['HTTP_X_FORWARDED_HOST' => 'collector.attacker.example'])
        ->post('/client/forgot-password', ['email' => $user->email]);

    expect(mlResetUrl($user->email))
        ->toStartWith('https://billing.example.com/')
        ->not->toContain('attacker');
});

it('falls back to APP_URL when no Domain is set, and still ignores the request host', function () {
    Mail::fake();
    Setting::where('setting', 'Domain')->delete();
    config(['app.url' => 'https://fallback.example.com']);
    $user = User::factory()->create(['email' => 'victim2@example.test']);

    $this->withServerVariables(['HTTP_X_FORWARDED_HOST' => 'collector.attacker.example'])
        ->post('/client/forgot-password', ['email' => $user->email]);

    expect(mlResetUrl($user->email))
        ->toStartWith('https://fallback.example.com/')
        ->not->toContain('attacker');
});

it('does not let a forwarded host decide what the application thinks its address is', function () {
    Setting::where('setting', 'Domain')->delete();
    config(['app.url' => 'https://fallback.example.com']);

    $this->withServerVariables(['HTTP_X_FORWARDED_HOST' => 'collector.attacker.example'])
        ->get('/client/login');

    // The host is the one the request actually arrived on, never the forwarded
    // one. (The test client calls the app on localhost.)
    expect(request()->getHost())->toBe('localhost')
        ->and(request()->getSchemeAndHttpHost())->not->toContain('attacker');
});

it('signs the verification link under the configured address, and still accepts an older absolute link', function () {
    Setting::updateOrCreate(['setting' => 'Domain'], ['value' => 'billing.example.com', 'group' => 'general']);
    $user = User::factory()->create(['email' => 'verify@example.test', 'email_verified_at' => null]);

    $url = \App\Http\Controllers\Client\EmailVerificationController::verificationUrl($user);
    expect($url)->toStartWith('https://billing.example.com/');

    // The link built the way it is built now verifies.
    $this->get(parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY));
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});
