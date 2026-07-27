<?php

namespace App\Http\Middleware;

use App\Models\Affiliate;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class AffiliateTracking
{
    /** Referral cookie name and lifetime (90 days, in minutes). */
    public const COOKIE = 'pnlcs_aff';

    public const TTL = 60 * 24 * 90;

    public function handle(Request $request, Closure $next): Response
    {
        $refId = $request->query('ref') ?? $request->query('aff');

        if (! $refId) {
            return $next($request);
        }

        $affiliate = Affiliate::find($refId);
        if (! $affiliate) {
            return $next($request);
        }

        $response = $next($request);

        // Queue the cookie instead of touching the response object: the old
        // code only attached it to Response/RedirectResponse instances, so a
        // plain view response (the common case for a landing page) silently
        // dropped the referral.
        Cookie::queue(Cookie::make(self::COOKIE, (string) $affiliate->id, self::TTL));

        // Count one visit per referral cookie, not once per page view.
        if ($request->cookie(self::COOKIE) != $affiliate->id) {
            $affiliate->increment('visitors');
        }

        return $response;
    }
}
