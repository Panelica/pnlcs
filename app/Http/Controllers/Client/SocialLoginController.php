<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\BannedEmail;
use App\Models\Setting;
use App\Models\User;
use App\Services\ClientRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

/**
 * Signing in with Google.
 *
 * Off unless an operator turns it on and supplies their own OAuth client, so
 * an installation that never configures it shows no button and exposes no
 * route that can do anything.
 *
 * Google hands over a name and an email address and nothing else. It cannot
 * tell us where to send an invoice, so an account opened this way lands on
 * the profile page and is asked for a billing address; checkout asks again
 * and refuses to take money without one. The convenience is in not choosing a
 * password, not in skipping what an invoice legally needs.
 */
class SocialLoginController extends Controller
{
    public static function enabled(): bool
    {
        return (string) Setting::get('GoogleLoginEnabled', '0') === '1'
            && trim((string) Setting::get('GoogleClientId', '')) !== ''
            && trim((string) Setting::get('GoogleClientSecret', '')) !== '';
    }

    public function redirect()
    {
        abort_unless(self::enabled(), 404);

        $this->configure();

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request)
    {
        abort_unless(self::enabled(), 404);

        $this->configure();

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            // Whatever went wrong out there - a cancelled consent screen, a
            // stale state token, a misconfigured client - the visitor gets a
            // login page and a sentence, never a stack trace.
            Log::warning('Google sign-in failed: '.$e->getMessage());

            return redirect()->route('client.login')->with('error', __('auth.social_failed'));
        }

        $email = (string) $googleUser->getEmail();
        $googleId = (string) $googleUser->getId();

        if ($email === '' || $googleId === '') {
            return redirect()->route('client.login')->with('error', __('auth.social_failed'));
        }

        // Recognised by the Google account id, never by the email alone: an
        // address can be claimed elsewhere, an id cannot.
        $user = User::where('google_id', $googleId)->first();

        if (! $user) {
            $user = User::where('email', $email)->first();

            if ($user) {
                // Same person, already a customer here. Linking is safe
                // because Google verified this address before handing it over.
                $user->update(['google_id' => $googleId]);
            }
        }

        if (! $user) {
            if (BannedEmail::blocks($email)) {
                return redirect()->route('client.login')->with('error', __('auth.email_not_accepted'));
            }

            [$user] = app(ClientRegistrationService::class)->register([
                'first_name' => $this->firstName($googleUser->getName(), $email),
                'last_name' => $this->lastName($googleUser->getName()),
                'email' => $email,
                'google_id' => $googleId,
            ], $request);

            Auth::login($user);
            $request->session()->regenerate();

            // Google gave a name and an email; an invoice needs an address.
            return redirect()->route('client.account.profile')
                ->with('success', __('auth.social_complete_profile'));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('client.home'));
    }

    /**
     * Point Socialite at the operator's own OAuth client.
     *
     * The credentials live in the settings table like every other integration
     * in this application, not in a deployment's .env, so they are set from
     * the admin screen and travel with a database restore.
     */
    private function configure(): void
    {
        config([
            'services.google' => [
                'client_id' => trim((string) Setting::get('GoogleClientId', '')),
                'client_secret' => trim((string) Setting::get('GoogleClientSecret', '')),
                'redirect' => route('client.social.google.callback'),
            ],
        ]);
    }

    private function firstName(?string $name, string $email): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return strstr($email, '@', true) ?: $email;
        }

        return explode(' ', $name)[0];
    }

    private function lastName(?string $name): string
    {
        $parts = explode(' ', trim((string) $name));
        array_shift($parts);

        return trim(implode(' ', $parts)) ?: '-';
    }
}
