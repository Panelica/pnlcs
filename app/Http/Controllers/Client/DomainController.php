<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function index()
    {
        $domains = Domain::where('client_id', $this->getClientId())->orderBy('id', 'desc')->paginate(25);
        return view('client.domains.index', compact('domains'));
    }

    public function show(Domain $domain)
    {
        $this->authorizeClientDomain($domain);
        return view('client.domains.show', compact('domain'));
    }

    public function updateNameservers(Request $request, Domain $domain)
    {
        $this->authorizeClientDomain($domain);

        $request->validate([
            'ns1' => 'required|string|max:255',
            'ns2' => 'required|string|max:255',
            'ns3' => 'nullable|string|max:255',
            'ns4' => 'nullable|string|max:255',
            'ns5' => 'nullable|string|max:255',
        ]);

        $nameservers = array_filter([
            'ns1' => $request->ns1,
            'ns2' => $request->ns2,
            'ns3' => $request->ns3,
            'ns4' => $request->ns4,
            'ns5' => $request->ns5,
        ]);

        $domain->update(['nameservers' => json_encode($nameservers)]);

        return redirect()->route('client.domains.show', $domain)
            ->with('success', 'Nameservers updated successfully.');
    }

    public function toggleLock(Domain $domain)
    {
        $this->authorizeClientDomain($domain);

        $newStatus = $domain->status === 'Locked' ? 'Active' : 'Locked';
        $domain->update(['status' => $newStatus]);

        $message = $newStatus === 'Locked' ? 'Domain locked successfully.' : 'Domain unlocked successfully.';
        return redirect()->route('client.domains.show', $domain)->with('success', $message);
    }

    public function toggleAutoRenew(Domain $domain)
    {
        $this->authorizeClientDomain($domain);

        $payment = $domain->payment_method === 'none' ? 'banktransfer' : 'none';
        $domain->update(['payment_method' => $payment]);

        $state = $payment !== 'none' ? 'enabled' : 'disabled';
        return redirect()->route('client.domains.show', $domain)
            ->with('success', 'Auto-renew ' . $state . '.');
    }

    public function getEppCode(Domain $domain)
    {
        $this->authorizeClientDomain($domain);

        $eppCode = $domain->notes ? md5($domain->domain . $domain->id) : null;

        return response()->json([
            'epp_code' => $eppCode ?? 'Contact support to retrieve your EPP code.',
        ]);
    }

    private function getClientId(): int
    {
        return auth()->user()->clients()->first()?->id ?? 0;
    }

    private function authorizeClientDomain(Domain $domain): void
    {
        if ($domain->client_id !== $this->getClientId()) {
            abort(403, 'This domain does not belong to your account.');
        }
    }
}
