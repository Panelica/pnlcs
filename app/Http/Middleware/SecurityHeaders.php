<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The browser-side protections every page should carry.
 *
 * None were sent, so another site could frame the admin or client area under
 * its own buttons (clickjacking), a browser could guess an uploaded file to be
 * a page, and full addresses - with any token in them - went to every site a
 * page links to. A header the web server already sets is left as it is.
 *
 * HSTS only over HTTPS, without includeSubDomains: other hosts under the same
 * domain are not this application's to decide for.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ];
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=15552000';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
