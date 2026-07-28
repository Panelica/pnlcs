<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Honours the "Maintenance Mode" switch on the general settings page.
 *
 * The setting was written to the database but nothing read it, so an operator
 * who put the panel into maintenance kept serving the client area as usual.
 *
 * Staff are deliberately unaffected: the admin area stays reachable so the
 * operator can finish the work and switch it back off, and the client login
 * route stays open so support can verify the customer side.
 */
class MaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->enabled()) {
            return $next($request);
        }

        // Never lock the operator out of their own panel.
        if ($request->is('admin', 'admin/*', 'api/*', 'up')) {
            return $next($request);
        }

        // A logged-in admin browsing the client area is previewing it.
        if (auth('admin')->check()) {
            return $next($request);
        }

        return response()->view('errors.maintenance', [
            'companyName' => Setting::get('CompanyName', config('app.name')),
        ], 503)->header('Retry-After', 3600);
    }

    private function enabled(): bool
    {
        try {
            $value = Setting::get('MaintenanceMode');
        } catch (\Throwable) {
            // Settings unreadable — never take the site down by accident.
            return false;
        }

        if ($value === null || $value === '') {
            return false;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true);
    }
}
