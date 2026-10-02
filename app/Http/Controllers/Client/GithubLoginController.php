<?php

namespace App\Http\Controllers\Client;

use App\Enums\ClientStatus;
use App\Http\Controllers\Controller;
use App\Models\AuthAccountLink;
use App\Models\BannedEmail;
use App\Models\Setting;
use App\Models\User;
use App\Services\ClientRegistrationService;
use App\Services\LoginRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

/**
 * Signing in with GitHub, for customers who live there (developers buying a
 * VPS or an app host). The same rules as the Google door
 * (SocialLoginController):
 *
 * - off until an operator turns it on with their own OAuth app;
 * - a login is recognised by its GitHub account id (auth_account_links),
 *   never by the email alone;
 * - an existing account is linked by address only when GitHub has verified
 *   that address. Socialite asks GitHub for the primary, verified address and
 *   returns none otherwise, so an empty address is refused;
 * - a closed account stays closed, a banned address is not opened, and the
 *   second factor is still asked for.
 */
class GithubLoginController extends Controller
{
    public const PROVIDER = 'github';

    public static function enabled(): bool
    {
        return (string) Setting::get('GithubLoginEnabled', '0') === '1'
            && trim((string) Setting::get('GithubClientId', '')) !== ''
            && trim((string) Setting::get('GithubClientSecret', '')) !== '';
    }

    public function redirect()
    {
        abort_unless(self::enabled(), 404);

        $this->configure();

        return Socialite::driver('github')->redirect();
    }

    public function callback(Request $request)
    {
        abort_unless(self::enabled(), 404);

        $this->configure();

        try {
            $githubUser = Socialite::driver('github')->user();
        } catch (\Throwable $e) {
            Log::warning('GitHub sign-in failed: '.$e->getMessage());

            return redirect()->route('client.login')->with('error', __('auth.social_failed_github'));
        }

        $githubId = (string) $githubUser->getId();
        $email = strtolower(trim((string) $githubUser->getEmail()));

        if ($githubId === '') {
            return redirect()->route('client.login')->with('error', __('auth.social_failed_github'));
        }

        $link = AuthAccountLink::where('provider', self::PROVIDER)->where('provider_user_id', $githubId)->first();
        $user = $link?->user;

        if (! $user) {
            // No primary, verified address on the GitHub account: nothing safe
            // to link by or to open an account for.
            if ($email === '') {
                return redirect()->route('client.login')->with('error', __('auth.github_no_verified_email'));
            }

            $user = User::where('email', $email)->first();

            if (! $user) {
                if (BannedEmail::blocks($email)) {
                    return redirect()->route('client.login')->with('error', __('auth.email_not_accepted'));
                }

                [$user] = app(ClientRegistrationService::class)->register([
                    'first_name' => $this->firstName($githubUser->getName(), (string) $githubUser->getNickname(), $email),
                    'last_name' => $this->lastName($githubUser->getName()),
                    'email' => $email,
                ], $request);
                $user->markEmailAsVerified();
                $this->link($user, $githubId, $githubUser->getNickname());

                Auth::login($user);
                $request->session()->regenerate();
                app(LoginRecorder::class)->succeeded($user, $request, self::PROVIDER);

                return redirect()->route('client.account.profile')
                    ->with('success', __('auth.social_complete_profile'));
            }

            // Same person, already a customer here, and GitHub verified the
            // address: link, and settle any verification still outstanding.
            $this->link($user, $githubId, $githubUser->getNickname());
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }
        }

        if ($user->clients()->exists() && ! $user->clients()->where('status', '!=', ClientStatus::Closed->value)->exists()) {
            return redirect()->route('client.login')->with('error', __('auth.account_closed'));
        }

        Auth::login($user);
        $request->session()->regenerate();

        if ($user->second_factor_type && $user->second_factor_secret) {
            session(['login_method' => self::PROVIDER]);
        } else {
            app(LoginRecorder::class)->succeeded($user, $request, self::PROVIDER);
        }

        return redirect()->intended(route('client.home'));
    }

    private function link(User $user, string $githubId, ?string $nickname): void
    {
        AuthAccountLink::create([
            'user_id' => $user->id,
            'provider' => self::PROVIDER,
            'provider_user_id' => $githubId,
            'data' => ['login' => $nickname],
        ]);
    }

    private function configure(): void
    {
        config([
            'services.github' => [
                'client_id' => trim((string) Setting::get('GithubClientId', '')),
                'client_secret' => trim((string) Setting::get('GithubClientSecret', '')),
                'redirect' => route('client.social.github.callback'),
            ],
        ]);
    }

    private function firstName(?string $name, string $nickname, string $email): string
    {
        $name = trim((string) $name);
        if ($name !== '') {
            return explode(' ', $name)[0];
        }

        return $nickname !== '' ? $nickname : (strstr($email, '@', true) ?: $email);
    }

    private function lastName(?string $name): string
    {
        $parts = explode(' ', trim((string) $name));
        array_shift($parts);

        return trim(implode(' ', $parts)) ?: '-';
    }
}
