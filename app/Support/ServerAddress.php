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
