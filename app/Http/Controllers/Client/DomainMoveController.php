<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Concerns\ResolvesClient;
use App\Http\Controllers\Controller;
use App\Mail\DomainMoveOfferedMail;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Domain;
use App\Models\DomainMoveRequest;
use App\Services\DomainMove;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * A customer gives a domain to another client account - a site sold, a
 * business that changed hands, a freelancer handing over to the client.
 * Only staff could move a domain between accounts. Now the owner offers it,
 * the other account accepts or declines, and nothing moves until it accepts.
 * Nothing changes at the registry; the WHOIS contact is the new owner's.
 */
class DomainMoveController extends Controller
{
    use ResolvesClient;

    public function offer(Request $request, Domain $domain)
    {
        abort_unless((int) $domain->client_id === (int) $this->getClientId(), 403);

        $email = strtolower(trim((string) $request->validate(['email' => 'required|email|max:255'])['email']));
        $to = Client::whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $to) {
            return back()->withErrors(['email' => __('client.domain_move.no_account')])->withInput();
        }

        $problem = app(DomainMove::class)->problem($domain, $to);
        if ($problem !== null) {
            return back()->withErrors(['email' => __('client.domain_move.problem_'.$problem)])->withInput();
        }
        if (DomainMoveRequest::where('domain_id', $domain->id)->open()->exists()) {
            return back()->withErrors(['email' => __('client.domain_move.already_offered')]);
        }

        $offer = DomainMoveRequest::create([
            'domain_id' => $domain->id, 'from_client_id' => $domain->client_id, 'to_client_id' => $to->id,
            'status' => 'pending', 'expires_at' => now()->addDays(DomainMoveRequest::DAYS),
        ]);
        ActivityLog::log("Domain {$domain->domain} offered to client #{$to->id}", auth()->user()?->email, $domain->client_id);

        if ($to->email) {
            Mail::to($to->email)->queue(new DomainMoveOfferedMail($offer));
        }

        return back()->with('success', __('client.domain_move.offered', ['email' => $to->email]));
    }

    public function cancel(DomainMoveRequest $offer)
    {
        abort_unless((int) $offer->from_client_id === (int) $this->getClientId() && $offer->status === 'pending', 404);
        $offer->update(['status' => 'cancelled', 'decided_at' => now()]);

        return back()->with('success', __('client.domain_move.cancelled'));
    }

    public function decline(DomainMoveRequest $offer)
    {
        abort_unless((int) $offer->to_client_id === (int) $this->getClientId() && $offer->status === 'pending', 404);
        $offer->update(['status' => 'declined', 'decided_at' => now()]);
        ActivityLog::log("Domain {$offer->domain?->domain} offer declined", auth()->user()?->email, $offer->from_client_id);

        return back()->with('success', __('client.domain_move.declined'));
    }

    public function accept(DomainMoveRequest $offer)
    {
        abort_unless((int) $offer->to_client_id === (int) $this->getClientId(), 404);
        $offer = DomainMoveRequest::whereKey($offer->id)->open()->first();
        abort_unless($offer !== null, 404, __('client.domain_move.expired'));

        $domain = $offer->domain;
        $to = $offer->toClient;
        // Checked again now: the domain may have changed hands or been billed
        // since it was offered.
        if (! $domain || ! $to || (int) $domain->client_id !== (int) $offer->from_client_id) {
            $offer->update(['status' => 'cancelled', 'decided_at' => now()]);

            return back()->with('error', __('client.domain_move.expired'));
        }
        $problem = app(DomainMove::class)->problem($domain, $to);
        if ($problem !== null) {
            return back()->with('error', __('client.domain_move.problem_'.$problem));
        }

        app(DomainMove::class)->move($domain, $to, auth()->user()?->email);
        $offer->update(['status' => 'accepted', 'decided_at' => now()]);

        return redirect()->route('client.domains.show', $domain)->with('success', __('client.domain_move.accepted', ['domain' => $domain->domain]));
    }
}
