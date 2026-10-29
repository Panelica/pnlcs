<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\ApiCredential;
use App\Support\ApiPermissionMap;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\IpUtils;

class ApiKeyAuth
{
    /** Password attempts allowed per username and address, as the admin login allows. */
    private const PASSWORD_ATTEMPTS = 5;

    private const PASSWORD_DECAY = 60;

    /** One answer for every failed sign-in, so none of them is an oracle. */
    private const AUTH_FAILED = 'Authentication failed';

    public function handle(Request $request, Closure $next)
    {
        // The liveness probe is public so uptime monitors can use it; it says
        // only up or down. /api/v1/gethealthstatus is its detailed twin - PHP
        // and framework versions, disk, memory, the database error - and was
        // let through here as well, handing all of that to anyone who asked.
        if ($request->is('api/health')) {
            return $next($request);
        }

        // Check for API key in header or query param (WHMCS compatible)
        $apiKey = $request->header('X-API-Key')
            ?? $request->header('Authorization')
            ?? $request->input('api_key')
            ?? $request->input('identifier');

        $apiSecret = $request->header('X-API-Secret')
            ?? $request->input('api_secret')
            ?? $request->input('secret');

        // Also accept WHMCS-style username+password auth
        $username = $request->input('username') ?? $request->input('adminuser');
        $password = $request->input('password') ?? $request->input('adminpass');

        // 1. API Key auth
        if ($apiKey) {
            $cred = ApiCredential::where('identifier', $apiKey)
                ->where('active', true)
                ->first();

            if ($cred) {
                // Secret is MANDATORY and compared in constant time. Previously a
                // request with a valid identifier but no secret was let through.
                if (! $apiSecret || ! hash_equals((string) $cred->secret, ApiCredential::hashSecret((string) $apiSecret))) {
                    return response()->json(['result' => 'error', 'message' => 'Invalid API secret'], 401);
                }
                if (! $this->ipAllowed($cred, $request->ip())) {
                    return response()->json(['result' => 'error', 'message' => 'IP address not allowed for this credential'], 403);
                }

                return $this->asAdmin($request, $next, $cred->admin);
            }
        }

        // 2. Admin username/password auth (WHMCS compatible)
        //
        // Guessing passwords here was cheap: the throttle on /api is keyed on
        // the API credential that was sent, and a username+password request
        // sends none, so attempts shared one bucket. Worse, a correct password
        // on an account with a second factor answered differently from a wrong
        // one, which told the guesser when they had found it. Both reported
        // privately in October 2026.
        //
        // Now: five attempts a minute per username and address, the same five
        // the admin login allows, and every failure says the same thing. The
        // real reason goes to the log, for the operator who has to explain it
        // to an integrator.
        if ($username && $password) {
            $key = 'api-password:'.sha1(mb_strtolower((string) $username).'|'.(string) $request->ip());

            if (RateLimiter::tooManyAttempts($key, self::PASSWORD_ATTEMPTS)) {
                return response()->json(['result' => 'error', 'message' => self::AUTH_FAILED], 429)
                    ->header('Retry-After', (string) RateLimiter::availableIn($key));
            }

            $admin = Admin::where('username', $username)->first();
            if ($admin && Hash::check($password, $admin->password)) {
                // A password is only half of what this account signs in with.
                // Accepting it alone here walked straight past the second
                // factor the admin login asks for; an API credential is the
                // way in for an account that has one.
                if ($admin->second_factor_type && $admin->second_factor_secret) {
                    RateLimiter::hit($key, self::PASSWORD_DECAY);
                    Log::info('API password sign-in refused: the account uses two-factor authentication, so an API credential is required.', [
                        'admin_id' => $admin->id,
                        'ip' => $request->ip(),
                    ]);

                    return response()->json(['result' => 'error', 'message' => self::AUTH_FAILED], 401);
                }

                RateLimiter::clear($key);

                return $this->asAdmin($request, $next, $admin);
            }

            RateLimiter::hit($key, self::PASSWORD_DECAY);

            return response()->json(['result' => 'error', 'message' => self::AUTH_FAILED], 401);
        }

        // 3. Bearer token auth (for session-based API access)
        $bearer = $request->bearerToken();
        if ($bearer) {
            // Check if it matches any API credential secret
            $cred = ApiCredential::where('secret', ApiCredential::hashSecret($bearer))->where('active', true)->first();
            if ($cred) {
                if (! $this->ipAllowed($cred, $request->ip())) {
                    return response()->json(['result' => 'error', 'message' => 'IP address not allowed for this credential'], 403);
                }

                return $this->asAdmin($request, $next, $cred->admin);
            }
        }

        // No valid authentication
        return response()->json([
            'result' => 'error',
            'message' => 'Authentication required. Provide api_key+api_secret, username+password, or Bearer token.',
        ], 401);
    }

    /**
     * Run the call as the member of staff it belongs to, with the permissions
     * they have in the panel.
     *
     * A credential is no more powerful than its owner: it is issued from their
     * account and it answers for them. A credential whose owner has been
     * removed answers for nobody and is refused.
     */
    private function asAdmin(Request $request, Closure $next, ?Admin $admin)
    {
        if (! $admin) {
            return response()->json([
                'result' => 'error',
                'message' => 'The account this credential belongs to no longer exists.',
            ], 403);
        }

        // The admin login refuses a disabled account; its credentials and its
        // password must not keep working here after the account was switched
        // off - that is usually the moment somebody left.
        if ($admin->is_disabled) {
            return response()->json([
                'result' => 'error',
                'message' => 'The account this credential belongs to is disabled.',
            ], 403);
        }

        Auth::guard('admin')->setUser($admin);

        $route = $request->route();
        $permission = ApiPermissionMap::required(
            $route?->getControllerClass(),
            $request->method(),
            (string) $route?->getActionMethod()
        );

        $allowed = $permission === null
            ? (bool) $admin->role?->is_full_admin
            : $admin->hasPermission($permission);

        if (! $allowed) {
            return response()->json([
                'result' => 'error',
                'message' => 'Your account does not have permission for this action.',
            ], 403);
        }

        return $next($request);
    }

    /**
     * An empty or unset allowed_ips list means no restriction. Otherwise the
     * request IP must match one of the entries — plain IPs or CIDR ranges,
     * IPv4/IPv6.
     */
    private function ipAllowed(ApiCredential $cred, ?string $ip): bool
    {
        $allowed = $cred->allowed_ips;
        if (is_string($allowed)) {
            $allowed = json_decode($allowed, true);
        }
        if (! is_array($allowed)) {
            return true;
        }
        $ranges = array_values(array_filter(array_map('trim', $allowed), fn ($r) => $r !== ''));
        if (empty($ranges)) {
            return true;
        }

        return $ip !== null && IpUtils::checkIp($ip, $ranges);
    }
}
