<?php

namespace App\Services;

use App\Events\ClientLoggedIn;
use App\Mail\NewDeviceLoginMail;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Models\UserLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Keeps the client area's sign-in history and warns about new devices.
 *
 * A browser is recognised by a random token in a long-lived cookie; only its
 * hash is stored. A sign-in from a browser without a known token, by a login
 * that has signed in before, mails the login's address (the "New Device
 * Sign-in" template, which the operator can reword or switch off). The very
 * first recorded sign-in mails nobody, so an install upgrading to this does
 * not warn every customer the next time they come back.
 */
class LoginRecorder
{
    public const COOKIE = 'pnlcs_device';

    /** Rows kept per login; older ones are dropped as new ones arrive. */
    public const KEEP = 50;

    public function succeeded(User $user, Request $request, string $method = 'password'): UserLogin
    {
        $token = (string) $request->cookie(self::COOKIE, '');
        if (strlen($token) !== 40) {
            $token = Str::random(40);
        }
        // Renewed on every sign-in, so a browser used at least once a year
        // stays known.
        Cookie::queue(Cookie::make(self::COOKIE, $token, 60 * 24 * 365, null, null, null, true, false, 'lax'));

        $device = hash('sha256', $token);
        $seenBefore = UserLogin::where('user_id', $user->id)->where('successful', true)->exists();
        $knownDevice = $seenBefore && UserLogin::where('user_id', $user->id)
            ->where('successful', true)->where('device', $device)->exists();
        $newDevice = $seenBefore && ! $knownDevice;

        $login = $this->record($user, $request, true, $method, $device);

        if ($newDevice) {
            $this->warn($user, $login);
        }

        ClientLoggedIn::dispatch($user, $method, $request->ip(), $newDevice);

        return $login;
    }

    public function failed(User $user, Request $request, string $method = 'password'): UserLogin
    {
        return $this->record($user, $request, false, $method, null);
    }

    /** "Chrome on Windows" from a user agent, for people rather than parsers. */
    public static function describe(?string $userAgent): string
    {
        $ua = (string) $userAgent;
        if ($ua === '') {
            return '-';
        }

        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => null,
        };

        $os = match (true) {
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'CrOS') => 'ChromeOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => null,
        };

        if ($browser && $os) {
            return __('client.security.browser_on_os', ['browser' => $browser, 'os' => $os]);
        }

        return $browser ?? $os ?? Str::limit($ua, 60);
    }

    private function record(User $user, Request $request, bool $successful, string $method, ?string $device): UserLogin
    {
        $login = UserLogin::create([
            'user_id' => $user->id,
            'successful' => $successful,
            'method' => $method,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 497),
            'device' => $device,
        ]);

        $cutoff = UserLogin::where('user_id', $user->id)->orderByDesc('id')->skip(self::KEEP)->value('id');
        if ($cutoff) {
            UserLogin::where('user_id', $user->id)->where('id', '<=', $cutoff)->delete();
        }

        return $login;
    }

    /**
     * Whether the new-device mail goes out: its template exists and the
     * operator has switched it on. It is seeded switched off.
     */
    public static function mailEnabled(): bool
    {
        try {
            return EmailTemplate::where('name', 'New Device Sign-in')->where('disabled', false)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    private function warn(User $user, UserLogin $login): void
    {
        if (! $user->email || ! self::mailEnabled()) {
            return;
        }

        try {
            Mail::to($user->email)->send(new NewDeviceLoginMail(
                $user->email,
                (string) $login->ip_address,
                self::describe($login->user_agent),
                $login->created_at->format(date_fmt().' H:i'),
            ));
        } catch (\Throwable $e) {
            // A mail server that is down must not stand between a customer
            // and the account they have just signed in to.
            Log::warning('New device sign-in mail not sent: '.$e->getMessage());
        }
    }
}
