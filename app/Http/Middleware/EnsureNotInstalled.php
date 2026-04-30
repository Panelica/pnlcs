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
        if (file_exists(storage_path('installed.lock'))) {
            throw new NotFoundHttpException();
        }

        try {
            if (Schema::hasTable('admins') && DB::table('admins')->limit(1)->count() > 0) {
                @file_put_contents(storage_path('installed.lock'), date('c'));
                throw new NotFoundHttpException();
            }
        } catch (NotFoundHttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // DB not yet configured — wizard is allowed.
        }

        return $next($request);
    }
}
