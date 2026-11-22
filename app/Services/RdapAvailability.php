<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Is a domain name registered, asked of its registry over RDAP (RFC 9082/9083).
 *
 * RDAP answers with a status code instead of free text: 404 for a name the
 * registry does not hold, 200 and a domain object for one it does. Port-43
 * WHOIS has to be read phrase by phrase, every registry words it differently,
 * and ICANN no longer requires gTLD registries to run it, so several of them
 * have switched it off.
 *
 * The registry's RDAP server comes from IANA's bootstrap file (RFC 9224), not
 * from a redirector: rdap.org answers 404 for a TLD it cannot route as well as
 * for a free name, and here 404 means "free".
 *
 * Anything else - no RDAP for the TLD, a refused or rate-limited request, a
 * timeout, an answer that is not a domain object, a 404 that is not JSON (a
 * proxy's error page is not the registry saying "no such name") - is null:
 * "not answered". The caller then tries port-43 WHOIS.
 */
class RdapAvailability
{
    private const CACHE_KEY = 'rdap.dns-bootstrap';

    /** IANA publishes the file with a one-day max-age. */
    private const CACHE_SECONDS = 86400;

    /** After a failed download, wait this long before asking IANA again. */
    private const RETRY_SECONDS = 3600;

    /** Seconds for the connection, and for the whole request. */
    private const CONNECT_TIMEOUT = 3;

    private const TIMEOUT = 5;

    /**
     * A server that could not be reached is left alone this long, so a search
     * with several suggestions on one dead server waits for it once, not once
     * per name.
     */
    private const DOWN_SECONDS = 300;

    /**
     * @return array{available: bool, checked: bool}|null
     */
    public function check(string $domain): ?array
    {
        $name = $this->asciiName($domain);
        $base = $name === null ? null : $this->serverFor($name);

        if ($base === null) {
            return null;
        }

        $downKey = 'rdap.down.'.md5($base);
        if (Cache::has($downKey)) {
            return null;
        }

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->connectTimeout(self::CONNECT_TIMEOUT)
                ->withHeaders([
                    'Accept' => 'application/rdap+json',
                    'User-Agent' => 'PNLCS',
                ])
                ->get($base.'domain/'.$name);
        } catch (\Throwable $e) {
            Cache::put($downKey, true, self::DOWN_SECONDS);
            Log::info('RDAP availability lookup failed', ['domain' => $name, 'message' => $e->getMessage()]);

            return null;
        }

        if ($response->status() === 404 && str_contains(strtolower($response->header('Content-Type')), 'json')) {
            return ['available' => true, 'checked' => true];
        }

        if ($response->successful() && $response->json('objectClassName') === 'domain') {
            return ['available' => false, 'checked' => true];
        }

        return null;
    }

    /**
     * The RDAP base URL (with its trailing slash) that serves a name, or null.
     * The bootstrap file lists top-level labels, so a second-level name
     * (example.co.uk) is served by its last label's server.
     */
    public function serverFor(string $domain): ?string
    {
        $labels = explode('.', strtolower(trim($domain, '. ')));
        $services = $this->services();

        for ($i = 1; $i < count($labels); $i++) {
            $suffix = implode('.', array_slice($labels, $i));
            if (isset($services[$suffix])) {
                return $services[$suffix];
            }
        }

        return null;
    }

    /**
     * TLD => RDAP base URL, from IANA's bootstrap file, cached for a day.
     *
     * @return array<string, string>
     */
    private function services(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $services = $this->download();

        // An empty map is cached too, for an hour, so a search does not wait
        // on IANA every time while it is unreachable.
        Cache::put(self::CACHE_KEY, $services, $services === [] ? self::RETRY_SECONDS : self::CACHE_SECONDS);

        return $services;
    }

    /** @return array<string, string> */
    private function download(): array
    {
        $url = (string) config('services.rdap.bootstrap', 'https://data.iana.org/rdap/dns.json');

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->connectTimeout(self::CONNECT_TIMEOUT)
                ->withHeaders(['User-Agent' => 'PNLCS'])
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning('RDAP bootstrap could not be downloaded', ['message' => $e->getMessage()]);

            return [];
        }

        $map = [];

        foreach ((array) ($response->successful() ? $response->json('services', []) : []) as $service) {
            [$tlds, $urls] = array_pad((array) $service, 2, []);

            // RFC 9224: prefer an HTTPS URL when the registry lists several.
            $urls = array_values(array_filter((array) $urls, 'is_string'));
            usort($urls, fn ($a, $b) => (int) str_starts_with($b, 'https://') <=> (int) str_starts_with($a, 'https://'));
            $server = $urls[0] ?? null;

            if (! $server) {
                continue;
            }

            foreach ((array) $tlds as $tld) {
                if (is_string($tld) && $tld !== '') {
                    $map[strtolower($tld)] = rtrim($server, '/').'/';
                }
            }
        }

        if ($map === []) {
            Log::warning('RDAP bootstrap held no services', ['status' => $response->status()]);
        }

        return $map;
    }

    /**
     * The name in its ASCII (punycode) form, which is how RDAP and the
     * bootstrap file spell it, or null when it is not a host name. The name
     * goes into the request path: "a/b.com" would ask about something else
     * and come back 404.
     */
    private function asciiName(string $domain): ?string
    {
        $name = strtolower(trim($domain, ". \t\n\r\0\x0B"));

        if (preg_match('/[^\x20-\x7e]/', $name)) {
            if (! function_exists('idn_to_ascii')) {
                return null;
            }

            $name = idn_to_ascii($name, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
            if ($name === false) {
                return null;
            }
            $name = strtolower($name);
        }

        return preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $name) ? $name : null;
    }
}
