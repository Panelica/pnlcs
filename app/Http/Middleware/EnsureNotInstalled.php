<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Blocks the install wizard once the application has been installed.
 *
 * "Installed" = either:
 *   - storage/installed.lock file exists (written by step 5), OR
 *   - admins table exists AND has at least one row (existing installations)
 *
 * Returns 404 to avoid leaking the existence of the wizard endpoint.
 */
class EnsureNotInstalled
{
    public function handle(Request $request, Closure $next)
    {
        // Permanent lock — wizard finished or pre-existing installation auto-locked.
        // Exception: the finish page is shown once if the wizard just completed
        // (session flag set in step 4), so users see the success screen.
        if (file_exists(storage_path('installed.lock'))) {
            if ($request->is('install/finish') && $request->session()->pull('install.completed') === true) {
                return $next($request);
            }
            throw new NotFoundHttpException();
        }

        // Active wizard session — allow even if admin was just created in step 3.
        if ($request->session()->get('install.in_progress') === true) {
            // First time visiting any wizard page sets/extends the flag.
            return $next($request);
        }

        // No active wizard session: if an existing admin row is present, this is
        // a pre-existing install — auto-lock and refuse access.
        try {
            if (Schema::hasTable('admins') && DB::table('admins')->limit(1)->count() > 0) {
                @file_put_contents(storage_path('installed.lock'), date('c'));
                throw new NotFoundHttpException();
            }
        } catch (NotFoundHttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // DB not yet configured — wizard allowed (fresh install scenario).
        }

        // Fresh install path — start the wizard session.
        $request->session()->put('install.in_progress', true);

        return $next($request);
    }
}
