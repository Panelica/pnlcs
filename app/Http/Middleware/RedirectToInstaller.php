<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Redirects all web traffic to the install wizard when PNLCS has not been
 * installed yet. Once installed (storage/installed.lock exists OR the admins
 * table has at least one row), the middleware becomes a no-op.
 *
 * Whitelisted paths always pass through (wizard routes, health check, built
 * assets, gateway webhooks).
 */
class RedirectToInstaller
{
    public function handle(Request $request, Closure $next)
    {
        // Already installed — never redirect.
        if (file_exists(storage_path('installed.lock'))) {
            return $next($request);
        }

        // Whitelisted paths that should be reachable even pre-install.
        if ($request->is(
            'install',
            'install/*',
            'up',
            'build/*',
            'gateway/*/webhook',
            'favicon.ico',
            'robots.txt',
        )) {
            return $next($request);
        }

        // Detect "installed" state without lock file (legacy / restored DB).
        try {
            if (Schema::hasTable('admins') && DB::table('admins')->limit(1)->count() > 0) {
                @file_put_contents(storage_path('installed.lock'), date('c'));
                return $next($request);
            }
        } catch (\Throwable $e) {
            // DB unreachable — fresh install path.
        }

        return redirect('/install');
    }
}
