<?php

namespace App\Http\Middleware;

use App\Support\LocaleUrl;
use Closure;
use Illuminate\Http\Request;

/**
 * Language in the address (LocaleUrl): /en/client/store is routed as
 * /client/store with English chosen. Runs before routing, so every route,
 * path check and redirect target stays the one it is today.
 *
 * - The default language has no prefix: /tr/sss answers 301 to /sss.
 * - A prefix never opens the admin area, the API, gateway callbacks or the
 *   installer: /en/admin is simply not a route.
 * - With the setting off nothing is touched.
 */
class LocaleUrlPrefix
{
    public function handle(Request $request, Closure $next)
    {
        if (! LocaleUrl::enabled()) {
            return $next($request);
        }

        $path = '/'.ltrim($request->path(), '/');
        $segments = explode('/', ltrim($path, '/'), 2);
        $first = strtolower($segments[0]);
        $rest = '/'.($segments[1] ?? '');

        if ($first === LocaleUrl::defaultLocale() && $first !== '' && ! LocaleUrl::excluded($rest) && $request->isMethod('GET')) {
            $query = $request->getQueryString();

            return redirect()->to(rtrim($request->root(), '/').$rest.($query ? '?'.$query : ''), 301);
        }

        $locale = LocaleUrl::prefixOf($path);
        if ($locale === null || LocaleUrl::excluded($rest)) {
            return $next($request);
        }

        // Routed without the prefix; the language travels as an attribute
        // for SetLocale.
        $uri = $request->getBaseUrl().$rest;
        $query = $request->getQueryString();
        $stripped = $request->duplicate(server: array_merge($request->server->all(), [
            'REQUEST_URI' => $uri.($query ? '?'.$query : ''),
        ]));
        $stripped->attributes->set('url_locale', $locale);

        return $next($stripped);
    }
}
