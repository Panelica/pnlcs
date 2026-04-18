<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $admin = auth("admin")->user();

        if (! $admin || ! $admin->hasPermission($permission)) {
            if ($request->expectsJson()) {
                return response()->json(["message" => "Forbidden."], 403);
            }

            abort(403, "Insufficient permissions.");
        }

        return $next($request);
    }
}
