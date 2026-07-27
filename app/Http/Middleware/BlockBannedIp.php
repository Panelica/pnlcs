<?php

namespace App\Http\Middleware;

use App\Models\BannedIp;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks requests from banned IP addresses (banned_ips table).
 *
 * Applied to the client area only — deliberately NOT to admin routes, so an
 * administrator who bans their own address can still reach the panel and
 * remove the ban.
 *
 * Entries match exactly, or as a prefix wildcard when they end with '*'
 * (e.g. "198.51.100.*").
 */
class BlockBannedIp
{
    public const CACHE_KEY = 'banned_ips.list';

    public function handle(Request $request, Closure $next): Response
    {
        $ip = (string) $request->ip();

        foreach ($this->bannedPatterns() as $pattern) {
            if ($this->matches($pattern, $ip)) {
                abort(403, __('messages.error.ip_banned'));
            }
        }

        return $next($request);
    }

    /**
     * @return array<int, string>
     */
    private function bannedPatterns(): array
    {
        return Cache::remember(self::CACHE_KEY, 60, fn () => BannedIp::pluck('ip')->all());
    }

    private function matches(string $pattern, string $ip): bool
    {
        $pattern = trim($pattern);
        if ($pattern === '') {
            return false;
        }
        if (str_ends_with($pattern, '*')) {
            return str_starts_with($ip, rtrim($pattern, '*'));
        }

        return $pattern === $ip;
    }
}
