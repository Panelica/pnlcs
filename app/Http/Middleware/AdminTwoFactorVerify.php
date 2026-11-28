<?php

namespace App\Http\Middleware;

use App\Models\AdminTrustedDevice;
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
            if (! session('admin_2fa_verified')) {
                // The login action already uses a trusted cookie, but a
                // session can also be started from a "remember me" login or
                // survive a redirect chain. Check the cookie here as well so
                // one valid device never has to pass the code twice.
                $trusted = AdminTrustedDevice::findValid(
                    $admin,
                    $request->cookie(AdminTrustedDevice::COOKIE_NAME)
                );

                if ($trusted) {
                    $trusted->markUsed();
                    session(['admin_2fa_verified' => true]);
                } else {
                    session(['admin_2fa_intended' => $request->url()]);
                    return redirect()->route('admin.2fa.verify');
                }
            }
        }

        return $next($request);
    }
}
