<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Concerns\ResolvesClient;
use App\Support\ClientPermissions;
use Closure;
use Illuminate\Http\Request;

/**
 * Keep a login to the parts of the account it was given (ClientPermissions).
 * Guests, owners and unrestricted logins pass straight through.
 */
class CheckClientPermission
{
    use ResolvesClient;

    public function handle(Request $request, Closure $next)
    {
        $needed = ClientPermissions::forRoute($request->route()?->getName());
        $user = auth()->user();

        if ($needed === null || ! $user) {
            return $next($request);
        }

        $client = $this->currentClient();
        if ($client && ! ClientPermissions::allows($user, $client, $needed)) {
            abort(403, __('client.permissions.denied'));
        }

        return $next($request);
    }
}
