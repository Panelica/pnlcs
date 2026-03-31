<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth("admin")->check()) {
            if ($request->expectsJson()) {
                return response()->json(["message" => "Unauthenticated."], 401);
            }

            return redirect()->route("admin.login");
        }

        $admin = auth("admin")->user();

        if ($admin->is_disabled) {
            auth("admin")->logout();

            return redirect()->route("admin.login")
                ->withErrors(["username" => __("auth.disabled")]);
        }

        return $next($request);
    }
}
