<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorVerify
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return $next($request);
        }

        // Check if user has 2FA enabled
        if ($user->second_factor_type && $user->second_factor_secret) {
            // Check if 2FA is already verified this session
            if (!session('2fa_verified')) {
                // Store intended URL
                session(['2fa_intended' => $request->url()]);
                return redirect()->route('client.2fa.verify');
            }
        }

        return $next($request);
    }
}
