<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\ClientSsoToken;
use Illuminate\Http\Request;

/**
 * Follow a one-time sign-in link issued through the API's createssotoken.
 *
 * Signs in the way "log in as this client" does in the admin area: the
 * customer's second factor is not asked for (a member of staff with the right
 * to edit the customer issued the link), the account the link was made for is
 * opened, and the visit is written to the customer's activity log.
 */
class SsoController extends Controller
{
    public function __invoke(Request $request, string $token)
    {
        $row = ClientSsoToken::where('token_hash', hash('sha256', $token))->first();

        // Claimed atomically: two tabs opening the same link get one sign-in.
        $claimed = $row
            && $row->expires_at->isFuture()
            && ClientSsoToken::whereKey($row->id)->whereNull('used_at')->update(['used_at' => now()]) === 1;

        if (! $claimed || ! $row->user || ! $row->user->clients()->whereKey($row->client_id)->exists()) {
            return redirect()->route('client.login')->withErrors(['email' => __('client.sso.invalid')]);
        }

        auth()->login($row->user);
        $request->session()->regenerate();
        session(['2fa_verified' => true, 'active_client_id' => $row->client_id]);

        ActivityLog::log(
            'Signed in with a single sign-on link',
            Admin::whereKey($row->admin_id)->value('username') ?? 'API',
            $row->client_id
        );

        return redirect($row->redirect_path);
    }
}
