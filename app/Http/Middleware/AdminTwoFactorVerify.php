<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminTwoFactorVerify
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin) {
            return $next($request);
        }

        if ($admin->second_factor_type && $admin->second_factor_secret) {
            if (!session('admin_2fa_verified')) {
                session(['admin_2fa_intended' => $request->url()]);
                return redirect()->route('admin.2fa.verify');
            }
        }

        return $next($request);
    }
}
