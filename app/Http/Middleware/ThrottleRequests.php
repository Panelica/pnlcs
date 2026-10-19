<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests as BaseThrottleRequests;
use Illuminate\Support\Facades\Auth;

/**
 * `throttle:N,M` counted per route.
 *
 * The framework keys an unnamed limit on who is asking and nothing else: the
 * signed-in user's id, or for anyone else the domain and IP. Every throttled
 * route therefore shared one counter per customer. The VPS page polls status,
 * graphs, snapshots and backups (60 a minute each), so by the time the
 * customer pressed Reinstall (3 per 10 minutes) the shared count was far past
 * 3 and the first click was refused with "Too Many Attempts". A visitor's
 * contact-form posts counted against their login attempts the same way.
 *
 * The key is now who is asking (a signed-in admin too, who is not on the
 * default guard) together with the route, so each limit counts only its own
 * route. Named limiters (`throttle:api`) are untouched.
 */
class ThrottleRequests extends BaseThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $route = $request->route();

        if ($user = $request->user()) {
            $who = 'user|'.$user->getAuthIdentifier();
        } elseif ($admin = Auth::guard('admin')->user()) {
            $who = 'admin|'.$admin->getAuthIdentifier();
        } elseif ($route) {
            $who = $route->getDomain().'|'.$request->ip();
        } else {
            return parent::resolveRequestSignature($request);
        }

        $where = $route?->getName() ?: $request->method().' '.($route?->uri() ?? $request->path());

        return sha1($who.'|'.$where);
    }
}
