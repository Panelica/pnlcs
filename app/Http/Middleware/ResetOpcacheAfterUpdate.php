<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * After an update (App\Services\Updates\UpdateRunner leaves the flag), the
 * first request PHP-FPM serves clears its compiled code, so no visitor gets
 * the old version from the cache - also where opcache.validate_timestamps is
 * off. The command-line updater cannot do it: the CLI has its own cache.
 * Costs one file check per request.
 */
class ResetOpcacheAfterUpdate
{
    public function handle(Request $request, Closure $next)
    {
        $flag = rtrim((string) config('updates.path'), '/').'/opcache-reset-pending';

        if (is_file($flag)) {
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            @unlink($flag);
        }

        return $next($request);
    }
}
