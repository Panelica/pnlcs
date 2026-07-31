<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        if (file_exists(storage_path('installed.lock'))) {
            throw new NotFoundHttpException;
        }

        // Is there an administrator already? That question comes before the
        // session flag: the flag is handed to whoever opens /install first on a
        // fresh deployment, and it used to keep working after the system had an
        // owner.
        try {
            if (Schema::hasTable('admins')) {
                $admins = DB::table('admins')->count();

                if ($admins > 0) {
                    $mine = (int) $request->session()->get('install.admin_id');
                    $ownsTheOnlyAdmin = $admins === 1
                        && $mine > 0
                        && DB::table('admins')->where('id', $mine)->exists();

                    // The session that created the administrator may finish the
                    // last two steps; anything else is a finished installation.
                    if (! $ownsTheOnlyAdmin) {
                        $this->lock();

                        throw new NotFoundHttpException;
                    }

                    return $next($request);
                }
            }
        } catch (NotFoundHttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // DB not yet configured — wizard allowed (fresh install scenario).
        }

        // Active wizard session on a system with no administrator yet.
        if ($request->session()->get('install.in_progress') === true) {
            return $next($request);
        }

        // Fresh install path — start the wizard session.
        $request->session()->put('install.in_progress', true);

        return $next($request);
    }

    /**
     * Close the wizard for good.
     *
     * The write used to be silenced, so a storage directory that could not be
     * written left the wizard reachable and nobody knew.
     */
    private function lock(): void
    {
        if (@file_put_contents(storage_path('installed.lock'), date('c')) === false) {
            Log::error('EnsureNotInstalled: could not write installed.lock — the install wizard is being kept closed by the database check alone', [
                'path' => storage_path('installed.lock'),
            ]);
        }
    }
}
