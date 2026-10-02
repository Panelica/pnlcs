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

    public function transfer(Request $request)
    {
        $domain = $request->query('domain', '');

        return view('client.domains.transfer', compact('domain'));
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

        // The hosting accounts this domain can be set up on, and where it
        // already is. One panel call per account; customers have one or two.
        $hosting = app(\App\Services\DomainHosting::class);
        $hostings = $hosting->candidates($this->getClientId())->map(fn ($service) => [
            'service' => $service,
            'nameservers' => $hosting->nameserversFor($service, $domain),
            'set_up' => $hosting->isSetUp($service, $domain),
        ]);

        // In redemption, with a restore price set for the extension.
        $restore = app(\App\Services\DomainRestoreService::class);
        $restoreAmount = $restore->offered($domain)
            ? round((float) $domain->recurring_amount + (float) $restore->restorePrice($domain), 2) : null;

        // Whether the customer can switch WHOIS privacy from here.
        $canTogglePrivacy = $module instanceof \App\Contracts\ManagesWhoisPrivacy && strtolower((string) $domain->status) === 'active';

        return view('client.domains.show', compact('domain', 'locked', 'hostings', 'restoreAmount', 'canTogglePrivacy'));
    }

    /**
     * Set the domain up on one of the customer's hosting accounts and point its
     * nameservers there, in one step. Contributed by ENA Hosting.
     */
    public function attachToHosting(Request $request, Domain $domain)
    {
        $this->authorizeClientDomain($domain);
        $request->validate(['service_id' => 'nullable|integer']);

        $hosting = app(\App\Services\DomainHosting::class);
        $candidates = $hosting->candidates($this->getClientId());
        $service = $request->filled('service_id')
            ? $candidates->firstWhere('id', (int) $request->service_id)
            : $candidates->first();

        $back = redirect()->route('client.domains.show', $domain);

        if (! $service) {
            return $back->with('error', __('client.domains.attach_no_hosting'));
        }

        $result = $hosting->attach($service, $domain);

        return match ($result['stage']) {
            'done' => $back->with('success', __('client.domains.attach_success')),
            'none' => $back->with('success', __('client.domains.attach_success_no_ns')),
            'nameservers' => $back->with('error', __('client.domains.attach_ok_ns_failed', ['reason' => $result['message']])),
            default => $back->with('error', __('client.domains.attach_failed', ['reason' => $result['message']])),
        };
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

    /**
     * Switch WHOIS privacy on or off at the registrar, and record it once the
     * registrar has done it. The page showed the setting but nothing could
     * change it.
     */
    public function togglePrivacy(Domain $domain)
    {
        $this->authorizeClientDomain($domain);

        $module = $this->registrarFor($domain);
        if (! $module instanceof \App\Contracts\ManagesWhoisPrivacy || strtolower((string) $domain->status) !== 'active') {
            return redirect()->route('client.domains.show', $domain)->with('error', __('client.domains.privacy_unavailable'));
        }

        $enable = ! $domain->id_protection;
        try {
            $result = $module->setPrivacy($domain, $enable);
        } catch (\Throwable $e) {
            Log::error("WHOIS privacy change failed for {$domain->domain}: {$e->getMessage()}");
            $result = ['success' => false];
        }

        if (! ($result['success'] ?? false)) {
            return redirect()->route('client.domains.show', $domain)->with('error', __('client.domains.privacy_failed'));
        }

        $domain->update(['id_protection' => $enable]);

        return redirect()->route('client.domains.show', $domain)
            ->with('success', $enable ? __('client.domains.privacy_on') : __('client.domains.privacy_off'));
    }

    /**
     * Renew now: raise the renewal invoice for this domain without waiting
     * for the nightly run. Paying it renews the name at the registrar
     * (RenewOnPaymentListener).
     */
    public function renew(Domain $domain)
    {
        $this->authorizeClientDomain($domain);

        if (strtolower((string) $domain->status) !== 'active' || (float) $domain->recurring_amount <= 0) {
            return back()->with('error', __('client.domains.renew_not_available'));
        }

        $open = \App\Models\Invoice::where('client_id', $domain->client_id)->outstanding()
            ->whereHas('items', fn ($q) => $q->where('type', 'Domain')->where('rel_id', $domain->id))
            ->latest('id')->first();

        $invoice = $open ?? app(\App\Services\InvoiceGenerationService::class)->generateForDomain($domain);

        if (! $invoice) {
            return back()->with('error', __('client.domains.renew_not_available'));
        }

        return redirect()->route('client.invoices.show', $invoice);
    }

    /**
     * Renew several domains on one invoice. Each must be the customer's own,
     * active and priced, like the single renewal; one already on an open
     * invoice is left there rather than billed twice.
     */
    public function renewMany(Request $request)
    {
        $ids = $request->validate([
            'domain_ids' => 'required|array|min:1|max:100',
            'domain_ids.*' => 'integer',
        ])['domain_ids'];

        $domains = Domain::where('client_id', $this->getClientId())->whereIn('id', $ids)->get();
        if ($domains->count() !== count(array_unique($ids))) {
            abort(403, __('messages.error.domain_not_yours'));
        }

        $renewable = $domains->filter(fn (Domain $d) => strtolower((string) $d->status) === 'active' && (float) $d->recurring_amount > 0);
        $billed = \App\Models\InvoiceItem::where('type', 'Domain')->whereIn('rel_id', $renewable->pluck('id'))
            ->whereHas('invoice', fn ($q) => $q->outstanding())->pluck('rel_id')->all();
        $toBill = $renewable->reject(fn (Domain $d) => in_array($d->id, $billed, true));

        if ($toBill->isEmpty()) {
            return $billed !== []
                ? redirect()->route('client.invoices.index')->with('info', __('client.domains.renew_many_already_billed'))
                : back()->with('error', __('client.domains.renew_not_available'));
        }

        $invoice = app(\App\Services\InvoiceGenerationService::class)->generateForDomains($toBill->first()->client, $toBill->values()->all());
        if (! $invoice) {
            return back()->with('error', __('client.domains.renew_not_available'));
        }

        $skipped = $domains->count() - $toBill->count();

        return redirect()->route('client.invoices.show', $invoice)
            ->with('success', $skipped > 0 ? __('client.domains.renew_many_some_skipped', ['count' => $skipped]) : __('client.domains.renew_many_done'));
    }

    /**
     * Restore a domain from the redemption period: raise (or reopen) the
     * invoice for the renewal plus the extension's restore price.
     */
    public function restore(Domain $domain)
    {
        $this->authorizeClientDomain($domain);

        $invoice = app(\App\Services\DomainRestoreService::class)->invoiceFor($domain);
        if (! $invoice) {
            return back()->with('error', __('client.domains.restore_not_available'));
        }

        return redirect()->route('client.invoices.show', $invoice);
    }

    public function toggleAutoRenew(Domain $domain)
    {
        $this->authorizeClientDomain($domain);

        $payment = $domain->payment_method === 'none' ? 'banktransfer' : 'none';
        $domain->update(['payment_method' => $payment]);

        // A translated word, not the raw English one: this sentence is shown to
        // the customer in their own language, and ':state' was being filled with
        // "enabled" regardless of locale.
        $state = $payment !== 'none'
            ? __('client.status.enabled')
            : __('client.status.disabled');

        return redirect()->route('client.domains.show', $domain)
            ->with('success', __('messages.success.auto_renew_toggled', ['state' => $state]));
    }

    public function getEppCode(Request $request, Domain $domain)
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

        // The page uses a plain link, so a browser gets the code back on the
        // domain page. A caller that asks for JSON still gets JSON: the endpoint
        // answered that way before and there is no reason to take it away.
        if ($request->wantsJson()) {
            return response()->json([
                'epp_code' => $eppCode ?? __('messages.info.contact_support_for_epp'),
            ]);
        }

        if (! $eppCode) {
            return redirect()->route('client.domains.show', $domain)
                ->with('error', __('messages.info.contact_support_for_epp'));
        }

        return redirect()->route('client.domains.show', $domain)
            ->with('epp_code', $eppCode);
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
