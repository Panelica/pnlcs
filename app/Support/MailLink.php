<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * An absolute link that goes into a mail, built from the address this
 * installation is configured with - never from the request that triggered it.
 *
 * WHY
 *
 * A password reset, an invitation and an address verification all carry a
 * one-time token in a link. Those links were built with route(), which takes
 * its host from the current request. The request's host can be set by whoever
 * sends it: bootstrap/app.php trusts every proxy, so an X-Forwarded-Host
 * reached the generated link, and a plain Host header does the same wherever
 * the web server does not pin a name. The mail then went to the real customer
 * with a real token pointing at somebody else's host - the token was valid
 * there, and whoever collected it could finish the reset.
 *
 * Reported privately, twice and independently, in October 2026.
 *
 * WHERE THE ADDRESS COMES FROM
 *
 *  1. Settings -> General -> Domain, the address the operator entered. (The
 *     same value AppServiceProvider pins every generated URL to, so a
 *     configured installation already agreed with this.)
 *  2. APP_URL, for an installation that has not set the Domain yet.
 *  3. Neither: the request, as before, with a warning in the log. A brand new
 *     installation can still invite its first login; it is told to set the
 *     address.
 *
 * The path comes from route() as a relative path, so the route names and
 * their parameters stay the single source of what the link points at.
 */
final class MailLink
{
    /** @param  array<string, mixed>|string  $parameters */
    public static function route(string $name, array|string $parameters = []): string
    {
        $path = route($name, $parameters, false);
        $root = self::root();

        if ($root === null) {
            Log::warning('A mail link was built from the request address: set Settings > General > Domain, or APP_URL.', [
                'route' => $name,
            ]);

            return route($name, $parameters);
        }

        return $root.$path;
    }

    /**
     * A signed link for a mail. Signed relatively and then put under the
     * configured address, so the signature does not depend on the host the
     * request arrived with. Links signed absolutely before this change keep
     * working: the verifier accepts both.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function signedRoute(string $name, \DateTimeInterface $expiration, array $parameters = []): string
    {
        $root = self::root();

        if ($root === null) {
            Log::warning('A signed mail link was built from the request address: set Settings > General > Domain, or APP_URL.', [
                'route' => $name,
            ]);

            return URL::temporarySignedRoute($name, $expiration, $parameters);
        }

        return $root.URL::temporarySignedRoute($name, $expiration, $parameters, false);
    }

    /** The configured address, without a trailing slash, or null when there is none. */
    public static function root(): ?string
    {
        foreach ([self::domainSetting(), (string) config('app.url')] as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }
            if (! preg_match('#^https?://#i', $candidate)) {
                $candidate = 'https://'.$candidate;
            }
            if (filter_var($candidate, FILTER_VALIDATE_URL)) {
                return rtrim($candidate, '/');
            }
        }

        return null;
    }

    private static function domainSetting(): string
    {
        try {
            return (string) Setting::get('Domain', '');
        } catch (\Throwable) {
            // Install and migrate run before the table exists.
            return '';
        }
    }
}
