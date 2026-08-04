<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminPermission
{
    /**
     * Several permissions may be given, separated by a pipe, and any one of
     * them is enough. A screen that only shows things can then accept either
     * the permission to see them or the permission to manage them, which is
     * what lets a read-only role exist without taking the screen away from
     * everybody who already has the managing one.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $admin = auth('admin')->user();

        $allowed = collect(explode('|', $permission))
            ->map(fn ($p) => trim($p))
            ->filter()
            ->contains(fn ($p) => $admin?->hasPermission($p));

        if (! $admin || ! $allowed) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            abort(403, 'Insufficient permissions.');
        }

        return $next($request);
    }
}
