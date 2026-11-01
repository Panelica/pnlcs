<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A password change ends every other session of that login.
 *
 * Changing or resetting a password already stopped "remember me" cookies,
 * but a session that was simply open elsewhere carried on: somebody who was
 * in the account stayed in it after its owner had changed the password to
 * get them out.
 *
 * Each session remembers a fingerprint of the password it was opened with,
 * per guard (customers and staff sign in separately and may share a
 * browser). When the account's password no longer matches it, that guard is
 * signed out; the page's own auth middleware then sends the visitor to sign
 * in. The session that made the change is given the new fingerprint as the
 * request finishes, so it stays signed in.
 */
class EndSessionAfterPasswordChange
{
    /** guard => what else belongs to that sign-in in the session */
    private const GUARDS = [
        'web' => ['2fa_verified', '2fa_pending', 'active_client_id'],
        'admin' => ['admin_2fa_verified', 'admin_2fa_pending'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return $next($request);
        }

        foreach (self::GUARDS as $guard => $sessionKeys) {
            $user = auth($guard)->user();
            $stored = $request->session()->get($this->key($guard));
            $current = $user ? $this->fingerprint($user) : null;

            if ($user && $current !== null && is_string($stored)
                && str_starts_with($stored, $user->getAuthIdentifier().'|')
                && ! hash_equals($stored, $current)) {
                auth($guard)->logout();
                $request->session()->forget([$this->key($guard), ...$sessionKeys]);
            }
        }

        $response = $next($request);

        foreach (array_keys(self::GUARDS) as $guard) {
            $user = auth($guard)->user();
            $current = $user ? $this->fingerprint($user) : null;

            if ($current !== null) {
                $request->session()->put($this->key($guard), $current);
            } else {
                $request->session()->forget($this->key($guard));
            }
        }

        return $response;
    }

    private function key(string $guard): string
    {
        return 'password_fingerprint_'.$guard;
    }

    /** Null for a login without a password (signed in with Google or GitHub only). */
    private function fingerprint($user): ?string
    {
        $hash = (string) $user->getAuthPassword();
        if ($hash === '') {
            return null;
        }

        return $user->getAuthIdentifier().'|'.hash_hmac('sha256', $hash, (string) config('app.key'));
    }
}
