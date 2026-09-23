<?php

namespace App\Http\Controllers\Api;

use App\Models\Client;
use App\Enums\DomainStatus;
use Illuminate\Validation\Rule;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Services\DomainService;
use App\Services\Module\ModuleRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DomainApiController extends BaseApiController
{
    public function getClientsDomains(Request $request)
    {
        $query = Domain::with('client');
        // clientid is the WHMCS name for this filter; only userid was read,
        // so asking for one customer's domains returned everyone's.
        $clientId = $request->input('clientid', $request->input('userid'));
        if (filled($clientId)) {
            $query->where('client_id', $clientId);
        }
        if ($request->filled('domainid')) {
            $query->whereKey($request->domainid);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('domain')) {
            $query->where('domain', 'like', '%'.$request->domain.'%');
        }
        $domains = $query->orderBy('id', 'desc')->paginate($this->getPerPage(), ['*'], 'page', $this->getPage());

        return $this->paginated($domains);
    }

    public function getDomainDetails(Request $request)
    {
        $domain = Domain::with('client')->find($request->domainid);
        if (! $domain) {
            return $this->error('Domain Not Found', 404);
        }

        return $this->success(['domain' => $domain->toArray()]);
    }

    public function updateDomain(Request $request)
    {
        $domain = Domain::find($request->domainid);
        if (! $domain) {
            return $this->error('Domain Not Found', 404);
        }
        // The names the reference screen and WHMCS use for these fields.
        $this->alias($request, 'expirydate', 'expiry_date');
        $this->alias($request, 'nextduedate', 'next_due_date');
        $this->alias($request, 'regdate', 'registration_date');
        $this->alias($request, 'paymentmethod', 'payment_method');
        // The fields the renewal run reads back as facts. domains.status is not
        // cast to the enum, so a typo was written as it stood and the domain
        // dropped out of the billing run for good - it bills domains that are
        // active or in grace - while the customer kept the name and the
        // registry kept charging for it. The two date columns were taking any
        // string at all, which reached Carbon and came back as a 500.
        $request->validate([
            'status' => ['sometimes', Rule::enum(DomainStatus::class)],
            'expiry_date' => ['sometimes', 'date'],
            'next_due_date' => ['sometimes', 'date'],
            'registration_date' => ['sometimes', 'date'],
            // A registrar this installation has: the renewal and every lock or
            // nameserver change go to the module named here.
            'registrar' => ['sometimes', 'string', function ($attribute, $value, $fail) {
                if (! app(ModuleRegistry::class)->getRegistrarModule((string) $value)) {
                    $fail('The registrar is not a registrar module installed here.');
                }
            }],
            'dns_management' => ['sometimes', 'boolean'],
            'email_forwarding' => ['sometimes', 'boolean'],
            'id_protection' => ['sometimes', 'boolean'],
            // A gateway this installation has; an unknown name is a method
            // nothing can take the payment through.
            'payment_method' => ['sometimes', 'nullable', 'string', function ($attribute, $value, $fail) {
                if ($value !== null && $value !== '' && ! app(\App\Services\Module\ModuleRegistry::class)->getGatewayModule((string) $value)) {
                    $fail('The payment method is not a payment gateway installed here.');
                }
            }],
        ]);

        $fields = ['status', 'expiry_date', 'next_due_date', 'registration_date', 'registrar', 'notes', 'dns_management', 'email_forwarding', 'id_protection', 'payment_method'];
        foreach ($fields as $f) {
            if ($request->has($f)) {
                $domain->$f = $request->$f;
            }
        }
        $domain->save();

        return $this->success(['domainid' => $domain->id]);
    }

    // WHMCS compat aliases
    public function updateClientDomain(Request $request)
    {
        return $this->updateDomain($request);
    }

    public function getNameservers(Request $request)
    {
        $domain = Domain::find($request->domainid);
        if (! $domain) {
            return $this->error('Domain Not Found', 404);
        }
        $ns = $domain->nameservers;
        if (is_string($ns)) {
            $decoded = json_decode($ns, true);
            $ns = is_array($decoded) ? $decoded : explode(',', $ns);
        }
        $ns = array_values(array_filter((array) $ns));

        return $this->success(['domainid' => $domain->id, 'ns1' => $ns[0] ?? null, 'ns2' => $ns[1] ?? null, 'ns3' => $ns[2] ?? null, 'ns4' => $ns[3] ?? null, 'ns5' => $ns[4] ?? null]);
    }

    public function domainGetNameservers(Request $request)
    {
        return $this->getNameservers($request);
    }

    public function updateNameservers(Request $request)
    {
        $domain = Domain::find($request->domainid);
        if (! $domain) {
            return $this->error('Domain Not Found', 404);
        }
        $ns = array_values(array_filter([$request->ns1, $request->ns2, $request->ns3, $request->ns4, $request->ns5]));

        // r132-api: the same door the client area uses, so the registrar hears
        // about it here too rather than only the database.
        $result = app(\App\Services\DomainService::class)->updateNameservers($domain, $ns);

        if (! $result['success']) {
            return $this->error($result['message'] ?? 'The registrar did not accept the nameservers.', 502);
        }

        return $this->success(['domainid' => $domain->id]);
    }

    public function domainUpdateNameservers(Request $request)
    {
        return $this->updateNameservers($request);
    }

    private function registrarFor(Domain $domain): ?\App\Contracts\RegistrarModuleInterface
    {
        if (! filled($domain->registrar)) {
            return null;
        }

        return app(\App\Services\Module\ModuleRegistry::class)->getRegistrarModule((string) $domain->registrar);
    }
    public function getLockStatus(Request $request)
    {
        $domain = Domain::find($request->domainid);
        if (! $domain) {
            return $this->error('Domain Not Found', 404);
        }

        $module = $this->registrarFor($domain);

        if (! $module) {
            return $this->error('No registrar module is configured for this domain.', 422);
        }

        try {
            return $this->success(['domainid' => $domain->id, 'lockstatus' => $module->getLockStatus($domain)]);
        } catch (\Throwable $e) {
            Log::error("Domain lock status lookup failed for {$domain->domain}: {$e->getMessage()}");

            return $this->error('The registrar could not be reached.', 502);
        }
    }

    public function domainGetLockingStatus(Request $request)
    {
        return $this->getLockStatus($request);
    }

    public function domainUpdateLockingStatus(Request $request)
    {
        $domain = Domain::find($request->domainid);
        if (! $domain) {
            return $this->error('Domain Not Found', 404);
        }

        $module = $this->registrarFor($domain);

        if (! $module) {
            return $this->error('No registrar module is configured for this domain.', 422);
        }

        $lock = $request->boolean('lockstatus');

        try {
            // Echoing the request back said the domain was locked while the
            // registrar had never been told.
            if (! $module->toggleLock($domain, $lock)) {
                return $this->error('The registrar refused the lock change.', 422);
            }
        } catch (\Throwable $e) {
            Log::error("Domain lock change failed for {$domain->domain}: {$e->getMessage()}");

            return $this->error('The registrar could not be reached.', 502);
        }

        return $this->success(['domainid' => $domain->id, 'lockstatus' => $lock]);
    }

    public function domainGetWhoisInfo(Request $request)
    {
        $domain = Domain::with('client')->find($request->domainid);
        if (! $domain) {
            return $this->error('Domain Not Found', 404);
        }
        $client = $domain->client;
        $whois = ['Registrant' => ['Name' => $client?->first_name.' '.$client?->last_name, 'Organisation' => $client?->company_name ?? '', 'Address1' => $client?->address1 ?? '', 'City' => $client?->city ?? '', 'State' => $client?->state ?? '', 'Postcode' => $client?->postcode ?? '', 'Country' => $client?->country ?? '', 'Phone Number' => $client?->phone_number ?? '', 'Email Address' => $client?->email ?? '']];

        return $this->success(['domainid' => $domain->id, 'whois' => $whois]);
    }

    public function domainUpdateWhoisInfo(Request $request)
    {
        // No registrar module implements a whois update. Reporting success and
        // changing nothing is worse than saying so.
        return $this->error('Updating whois contact details is not implemented. Change them at the registrar.', 501);
    }

    public function domainRequestEpp(Request $request)
    {
        $domain = Domain::find($request->domainid);
        if (! $domain) {
            return $this->error('Domain Not Found', 404);
        }

        $module = $this->registrarFor($domain);

        if (! $module) {
            return $this->error('No registrar module is configured for this domain.', 422);
        }

        try {
            // Eight random characters is not a transfer code. Handing one out
            // sends the customer to their new registrar with a code that
            // cannot work.
            $eppCode = trim($module->getEPPCode($domain));
        } catch (\Throwable $e) {
            Log::error("EPP code lookup failed for {$domain->domain}: {$e->getMessage()}");

            return $this->error('The registrar could not be reached.', 502);
        }

        // A transfer code has no spaces in it; registrars that keep none
        // answer with a sentence saying so, which is a message and not a code.
        if ($eppCode === '' || str_contains($eppCode, ' ')) {
            return $this->error($eppCode !== ''
                ? $eppCode
                : 'The registrar did not return a transfer code for this domain.', 422);
        }

        return $this->success(['domainid' => $domain->id, 'eppcode' => $eppCode]);
    }

    public function domainToggleIdProtect(Request $request)
    {
        $domain = Domain::find($request->domainid);
        if (! $domain) {
            return $this->error('Domain Not Found', 404);
        }
        // WHMCS takes the wanted state in idprotect; flipping whatever was
        // stored meant two identical calls cancelled each other out.
        $request->validate(['idprotect' => 'sometimes|boolean']);
        $domain->id_protection = $request->has('idprotect') ? $request->boolean('idprotect') : ! $domain->id_protection;
        $domain->save();

        return $this->success(['domainid' => $domain->id, 'idprotection' => $domain->id_protection]);
    }

    public function domainRelease(Request $request)
    {
        // No registrar module implements a release, so the domain stayed
        // exactly where it was while the caller was told otherwise.
        return $this->error('Releasing a domain to another registrar is not implemented. Request the transfer code instead.', 501);
    }

    public function domainRegister(Request $request)
    {
        $validated = $request->validate([
            'clientid' => 'required|exists:clients,id',
            'domain' => 'required|string|max:253',
            'years' => 'nullable|integer|min:1|max:10',
            // A registrar this installation has; an unknown name left a domain
            // that no module would ever register or renew.
            'registrar' => ['nullable', 'string', 'max:100', function ($attribute, $value, $fail) {
                if (! app(ModuleRegistry::class)->getRegistrarModule((string) $value)) {
                    $fail('The registrar is not a registrar module installed here.');
                }
            }],
        ]);

        $name = strtolower(trim($validated['domain']));
        if (! filter_var($name, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || ! str_contains($name, '.')) {
            return $this->error('Invalid domain name', 422);
        }

        $client = Client::findOrFail($validated['clientid']);

        $domain = app(DomainService::class)->registerDomain($client, [
            'domain' => strtolower(trim($validated['domain'])),
            'registration_period' => (int) ($validated['years'] ?? 1),
            'registrar' => $validated['registrar'] ?? 'Manual',
        ]);

        return $this->success(['domainid' => $domain->id, 'status' => $domain->status]);
    }

    public function domainTransfer(Request $request)
    {
        $validated = $request->validate([
            'domainid' => 'required|exists:domains,id',
            'eppcode' => 'required|string|max:255',
        ]);

        $domain = Domain::findOrFail($validated['domainid']);
        $module = app(ModuleRegistry::class)->getRegistrarModule((string) $domain->registrar);

        if (! $module) {
            return $this->error('No registrar module is configured for this domain.', 422);
        }

        $result = $module->transfer($domain, $validated['eppcode']);

        if (! ($result['success'] ?? false)) {
            return $this->error($result['message'] ?? 'The registrar refused the transfer.', 422);
        }

        return $this->success(['domainid' => $domain->id, 'message' => $result['message'] ?? 'Transfer started.']);
    }

    public function domainRenew(Request $request)
    {
        $validated = $request->validate([
            'domainid' => 'required|exists:domains,id',
            'years' => 'nullable|integer|min:1|max:10',
        ]);

        $domain = Domain::findOrFail($validated['domainid']);
        $years = (int) ($validated['years'] ?? 1);

        $renewed = app(DomainService::class)->renewDomain($domain, $years);

        return $this->success([
            'domainid' => $renewed->id,
            'expiry_date' => $renewed->expiry_date?->toDateString(),
        ]);
    }

    /**
     * Is this domain free to register (WHMCS DomainWhois)?
     *
     * This asked whois.iana.org, which answers about the TLD's registry, not
     * about the name - so every lookup came back "success" with the .com
     * registry's details, whatever the name. It also read the socket with no
     * deadline. It now asks what the domain search asks, and says so when
     * nobody could be asked rather than guessing.
     */
    public function domainWhois(Request $request, \App\Services\DomainAvailability $availability)
    {
        $request->validate(['domain' => 'required|string|max:253']);
        $domain = strtolower(trim((string) $request->domain));

        if (! filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || ! str_contains($domain, '.')) {
            return $this->error('Invalid domain name', 422);
        }

        $result = $availability->check($domain);

        if (! $result['checked']) {
            return $this->error('The availability of this domain could not be checked: no registrar or WHOIS server answered.', 503);
        }

        return $this->success([
            'domain' => $result['domain'],
            'status' => $result['available'] ? 'available' : 'unavailable',
        ]);
    }

    public function createOrUpdateTld(Request $request)
    {
        // Prices reached the table as sent: a word became a 500, and a negative
        // price sold the name at a loss.
        $validated = $request->validate([
            'extension' => ['required', 'string', 'max:63', 'regex:/^\\.?[a-z0-9-]+(\\.[a-z0-9-]+)*$/i'],
            'register_price' => 'sometimes|nullable|numeric|min:0',
            'transfer_price' => 'sometimes|nullable|numeric|min:0',
            'renew_price' => 'sometimes|nullable|numeric|min:0',
            'enabled' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ]);
        // Stored with the leading dot, as the pricing screen and the domain
        // search look it up.
        $extension = '.'.ltrim(strtolower($validated['extension']), '.');
        unset($validated['extension']);
        $tld = DomainPricing::updateOrCreate(['extension' => $extension], $validated);

        return $this->success(['tldid' => $tld->id]);
    }

    public function getTldPricing(Request $request)
    {
        $pricing = DomainPricing::where('enabled', true)->orderBy('sort_order')->get();

        return $this->success(['pricing' => $pricing->toArray()]);
    }
}
