<?php

namespace App\Http\Middleware;

use App\Models\Affiliate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AffiliateTracking
{
    public function handle(Request $request, Closure $next): Response
    {
        // Check for affiliate referral parameter
        $refId = $request->query('ref') ?? $request->query('aff');

        if ($refId) {
            $affiliate = Affiliate::find($refId);
            if ($affiliate) {
                // Set cookie for 90 days
                $response = $next($request);

                if ($response instanceof \Illuminate\Http\Response || $response instanceof \Illuminate\Http\RedirectResponse) {
                    $response->cookie('pnlcs_aff', $affiliate->id, 60 * 24 * 90); // 90 days
                }

                // Record visit
                $affiliate->increment('visitors');

                return $response;
            }
        }

        return $next($request);
    }
}
