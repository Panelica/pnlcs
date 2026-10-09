<?php

namespace App\Http\Middleware;

use App\Models\Promotion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A promotion handed over in a link: any shop page opened with ?promo=CODE
 * (or ?promocode=CODE, the name WHMCS links use) remembers the code, and the
 * cart applies it once it holds something the code is for.
 *
 * A promotion only worked when the customer typed its code into the cart. A
 * campaign page, an email or a "first year at half price" button could not
 * carry the discount, so the customer had to be told a code and remember it.
 */
class PromoLink
{
    public const SESSION = 'pending_promo';

    public function handle(Request $request, Closure $next): Response
    {
        $code = $request->query('promo') ?? $request->query('promocode');

        // Only a code that exists. Whether it fits this customer and this
        // order is the cart's question, asked when it is applied.
        if (is_string($code) && $request->isMethod('GET') && preg_match('/^[A-Za-z0-9_-]{1,50}$/', $code)
            && Promotion::where('code', $code)->exists()) {
            $request->session()->put(self::SESSION, $code);
        }

        return $next($request);
    }
}
