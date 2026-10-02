<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\MarketingConsent;
use Illuminate\Http\Request;

/**
 * The link at the foot of a marketing email. Signed, so it needs no sign-in
 * and cannot be made up for someone else. Opening it shows a button rather
 * than unsubscribing at once: mail scanners open links in mail to check
 * them, and would unsubscribe people who never clicked. The POST is also
 * what the List-Unsubscribe-Post header points mail apps at (RFC 8058).
 */
class UnsubscribeController extends Controller
{
    public function show(Client $client)
    {
        return view('client.unsubscribe', [
            'email' => $client->email,
            'done' => ! MarketingConsent::optedIn($client),
            'action' => url()->full(),
        ]);
    }

    public function store(Request $request, Client $client)
    {
        MarketingConsent::record($client, false, 'unsubscribe', $request->ip());

        if ($request->wantsJson() || $request->has('List-Unsubscribe')) {
            return response()->noContent();
        }

        return view('client.unsubscribe', ['email' => $client->email, 'done' => true, 'action' => null]);
    }
}
