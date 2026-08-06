<?php

namespace App\Http\Controllers\Client;

use App\Contracts\RegistrarModuleInterface;
use App\Http\Controllers\Concerns\ResolvesClient;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Services\DomainService;
use App\Services\Module\ModuleRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DomainController extends Controller
{
    use ResolvesClient;

    public function index()
    {
        $domains = Domain::where('client_id', $this->getClientId())->orderBy('id', 'desc')->paginate(25);

        return view('client.domains.index', compact('domains'));
    }

    public function show(Domain $domain)
    {
        $this->authorizeClientDomain($domain);

        // The registrar holds the lock. The page used to read it off the status
        // column, comparing against a value nothing writes, so it always said
        // unlocked however many times the customer had locked it.
        $locked = null;
        $module = $this->registrarFor($domain);

        if ($module) {
            try {
                $locked = $module->getLockStatus($domain);
            } catch (\Throwable $e) {
                Log::warning("Lock status lookup failed for {$domain->domain}: {$e->getMessage()}");
            }
        }

        return view('client.domains.show', compact('domain', 'locked'));
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

        // r132-client: through the service, which tells the registrar. Writing
        // the column here reported success for a change the registry never saw.
        $result = app(DomainService::class)->updateNameservers($domain, $nameservers);

        if (! $result['success']) {
            return redirect()->route('client.domains.show', $domain)
                ->with('error', $result['message']);
        }

        return redirect()->route('client.domains.show', $domain)
            ->with('success', __('messages.success.nameservers_updated'));
    }

    public function toggleLock(Domain $domain)
    {
        $this->authorizeClientDomain($domain);

        $module = $this->registrarFor($domain);

        if (! $module) {
            return redirect()->route('client.domains.show', $domain)
                ->with('error', __('client.domains.lock_unavailable'));
        }

        try {
            // The registrar holds the lock, not us. Writing it into the status
            // column lost what the domain actually was — and unlocking set it
            // back to active, which put an expired domain in front of the
            // renewal generator.
            $locked = $module->getLockStatus($domain);
            $ok = $module->toggleLock($domain, ! $locked);
        } catch (\Throwable $e) {
            Log::error("Domain lock toggle failed for {$domain->domain}: {$e->getMessage()}");
            $ok = false;
        }

        if (! $ok) {
            return redirect()->route('client.domains.show', $domain)
                ->with('error', __('client.domains.lock_failed'));
        }

        return redirect()->route('client.domains.show', $domain)
            ->with('success', $locked ? __('messages.success.domain_unlocked') : __('messages.success.domain_locked'));
    }

    public function toggleAutoRenew(Domain $domain)
    {
        $this->authorizeClientDomain($domain);

        $payment = $domain->payment_method === 'none' ? 'banktransfer' : 'none';
        $domain->update(['payment_method' => $payment]);

        $state = $payment !== 'none' ? 'enabled' : 'disabled';

        return redirect()->route('client.domains.show', $domain)
            ->with('success', __('messages.success.auto_renew_toggled', ['state' => $state]));
    }

    public function getEppCode(Domain $domain)
    {
        $this->authorizeClientDomain($domain);

        $module = $this->registrarFor($domain);
        $eppCode = null;

        if ($module) {
            try {
                // An md5 of the domain name and its row id is not a transfer
                // code. No registrar would have accepted it.
                $eppCode = trim($module->getEPPCode($domain)) ?: null;
            } catch (\Throwable $e) {
                Log::error("EPP code lookup failed for {$domain->domain}: {$e->getMessage()}");
            }
        }

        return response()->json([
            'epp_code' => $eppCode ?? __('messages.info.contact_support_for_epp'),
        ]);
    }

    private function registrarFor(Domain $domain): ?RegistrarModuleInterface
    {
        if (! filled($domain->registrar)) {
            return null;
        }

        return app(ModuleRegistry::class)->getRegistrarModule((string) $domain->registrar);
    }

    private function authorizeClientDomain(Domain $domain): void
    {
        if ($domain->client_id !== $this->getClientId()) {
            abort(403, __('messages.error.domain_not_yours'));
        }
    }
}
