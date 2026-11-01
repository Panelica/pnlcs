<?php

namespace App\Support;

use Closure;

/**
 * What a server record's hostname resolves to, and whether that disagrees with
 * its IP address the way a server behind NAT does (#334).
 *
 * A panel on a private address, reached from outside through a router that
 * forwards a public one, is recorded with both: the private IP, which PNLCS
 * on the same network calls, and the public hostname, which customers' links
 * (webmail, phpMyAdmin, the file manager) need. The two never agree, and that
 * is the setup working, not one of them pointing at the wrong machine.
 */
class ServerAddress
{
    /** Stands in for DNS in tests. */
    private static ?Closure $resolver = null;

    /** @return array<int, string> the hostname's IPv4 addresses; empty when it does not resolve */
    public static function resolve(string $hostname): array
    {
        if (self::$resolver) {
            return (self::$resolver)($hostname);
        }

        return @gethostbynamel($hostname) ?: [];
    }

    /**
     * A private (or reserved) IP beside a hostname that resolves only to public
     * addresses.
     *
     * @param  array<int, string>  $resolved
     */
    public static function behindNat(string $ip, array $resolved): bool
    {
        if ($resolved === [] || ! filter_var($ip, FILTER_VALIDATE_IP) || self::isPublic($ip)) {
            return false;
        }

        foreach ($resolved as $address) {
            if (! self::isPublic($address)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The public IPv4 address to connect to for an http(s) URL, or null when
     * the URL is not one the panel may fetch: a scheme it does not speak,
     * credentials in the address, a name that does not resolve, or any address
     * behind it that is this server, its own network or a metadata service.
     *
     * Returned so the caller can pin the connection to the address that was
     * checked, rather than to whatever the name resolves to a moment later.
     */
    public static function publicAddressFor(string $url): ?string
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(trim($parts['host'] ?? '', '[]'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::resolve($host);

        if ($addresses === []) {
            return null;
        }

        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return null;
            }
        }

        return $addresses[0];
    }

    /** host:port:ip for CURLOPT_RESOLVE, so the connection goes to the address that was checked. */
    public static function resolveEntry(string $url, string $ip): string
    {
        $parts = parse_url($url);
        $port = $parts['port'] ?? (strtolower($parts['scheme'] ?? '') === 'https' ? 443 : 80);

        return strtolower($parts['host'] ?? '').':'.$port.':'.$ip;
    }

    /** @param  (Closure(string): array<int, string>)|null  $resolver */
    public static function resolveUsing(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    private static function isPublic(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
