<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiKeyAuth
{
    public function handle(Request $request, Closure $next)
    {
        // Allow health endpoint without auth
        if ($request->is('api/health') || $request->is('api/v1/gethealthstatus')) {
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
            $cred = \App\Models\ApiCredential::where('identifier', $apiKey)
                ->where('active', true)
                ->first();
            
            if ($cred) {
                // Secret is MANDATORY and compared in constant time. Previously a
                // request with a valid identifier but no secret was let through.
                if (!$apiSecret || !hash_equals((string) $cred->secret, (string) $apiSecret)) {
                    return response()->json(['result' => 'error', 'message' => 'Invalid API secret'], 401);
                }
                if (!$this->ipAllowed($cred, $request->ip())) {
                    return response()->json(['result' => 'error', 'message' => 'IP address not allowed for this credential'], 403);
                }
                return $next($request);
            }
        }

        // 2. Admin username/password auth (WHMCS compatible)
        if ($username && $password) {
            $admin = \App\Models\Admin::where('username', $username)->first();
            if ($admin && \Illuminate\Support\Facades\Hash::check($password, $admin->password)) {
                return $next($request);
            }
            return response()->json(['result' => 'error', 'message' => 'Authentication failed'], 401);
        }

        // 3. Bearer token auth (for session-based API access)
        $bearer = $request->bearerToken();
        if ($bearer) {
            // Check if it matches any API credential secret
            $cred = \App\Models\ApiCredential::where('secret', $bearer)->where('active', true)->first();
            if ($cred) {
                if (!$this->ipAllowed($cred, $request->ip())) {
                    return response()->json(['result' => 'error', 'message' => 'IP address not allowed for this credential'], 403);
                }
                return $next($request);
            }
        }

        // No valid authentication
        return response()->json([
            'result' => 'error',
            'message' => 'Authentication required. Provide api_key+api_secret, username+password, or Bearer token.',
        ], 401);
    }

    /**
     * An empty or unset allowed_ips list means no restriction. Otherwise the
     * request IP must match one of the entries — plain IPs or CIDR ranges,
     * IPv4/IPv6.
     */
    private function ipAllowed(\App\Models\ApiCredential $cred, ?string $ip): bool
    {
        $allowed = $cred->allowed_ips;
        if (is_string($allowed)) {
            $allowed = json_decode($allowed, true);
        }
        if (!is_array($allowed)) {
            return true;
        }
        $ranges = array_values(array_filter(array_map('trim', $allowed), fn ($r) => $r !== ''));
        if (empty($ranges)) {
            return true;
        }
        return $ip !== null && \Symfony\Component\HttpFoundation\IpUtils::checkIp($ip, $ranges);
    }
}
