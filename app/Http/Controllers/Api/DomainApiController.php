<?php

namespace App\Http\Controllers\Api;

use App\Models\Client;
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
        if ($request->filled('userid')) {
            $query->where('client_id', $request->userid);
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
        $fields = ['status', 'expiry_date', 'next_due_date', 'notes', 'dns_management', 'email_forwarding', 'id_protection', 'payment_method'];
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
        $domain->nameservers = json_encode($ns);
        $domain->save();

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
        $domain->id_protection = ! $domain->id_protection;
        $domain->save();

        return $this->success(['domainid' => $domain->id, 'idprotection' => $domain->id_protection]);
    }

    public function domainRelease(Request $request)
    {
        $domain = Domain::find($request->domainid);
        if (! $domain) {
            return $this->error('Domain Not Found', 404);
        }

        return $this->success(['domainid' => $domain->id]);
    }

    public function domainRegister(Request $request)
    {
        $validated = $request->validate([
            'clientid' => 'required|exists:clients,id',
            'domain' => 'required|string|max:253',
            'years' => 'nullable|integer|min:1|max:10',
            'registrar' => 'nullable|string|max:100',
        ]);

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

    public function domainWhois(Request $request)
    {
        $domain = $request->domain;
        if (! filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return $this->error('Invalid domain name');
        }        $whois = '';
        try {
            $fp = @fsockopen('whois.iana.org', 43, $errno, $errstr, 5);
            if ($fp) {
                fwrite($fp, $domain.'
');
                while (! feof($fp)) {
                    $whois .= fgets($fp, 128);
                }                fclose($fp);
            }
        } catch (\Exception $e) {
            $whois = 'WHOIS lookup failed';
        }

return $this->success(['domain' => $domain, 'whois' => $whois ?: 'No data available', 'status' => 'success']);
    }

    public function createOrUpdateTld(Request $request)
    {
        $validated = $request->validate(['extension' => 'required|string']);
        $tld = DomainPricing::updateOrCreate(['extension' => $validated['extension']], $request->only(['register_price', 'transfer_price', 'renew_price', 'enabled', 'sort_order']));

        return $this->success(['tldid' => $tld->id]);
    }

    public function getTldPricing(Request $request)
    {
        $pricing = DomainPricing::where('enabled', true)->orderBy('sort_order')->get();

        return $this->success(['pricing' => $pricing->toArray()]);
    }
}
