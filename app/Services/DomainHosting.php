<?php

namespace App\Services;

use App\Contracts\HostsAccountDomains;
use App\Models\Domain;
use App\Models\RegistrarSettings;
use App\Models\Service;
use App\Services\Module\ModuleRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * "Set up on my hosting": put a domain the customer holds here onto one of
 * their hosting accounts, and point its nameservers there.
 *
 * Contributed by ENA Hosting as a local change for their Panelica servers and
 * made general: any server module that implements HostsAccountDomains takes
 * part, a customer with several hosting accounts chooses one, and the
 * nameservers come from the server the account lives on.
 */
class DomainHosting
{
    public function __construct(private ModuleRegistry $modules) {}

    /**
     * The customer's active services whose module can add a domain.
     *
     * @return Collection<int, Service>
     */
    public function candidates(int $clientId): Collection
    {
        return Service::with('product', 'server')
            ->where('client_id', $clientId)
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->filter(fn (Service $s) => $this->moduleFor($s) !== null)
            ->values();
    }

    public function moduleFor(Service $service): ?HostsAccountDomains
    {
        $type = (string) ($service->product?->server_type ?? '');
        $module = $type !== '' ? $this->modules->getServerModule($type) : null;

        return $module instanceof HostsAccountDomains ? $module : null;
    }

    /**
     * Where the domain should point to be served by this account: the
     * nameservers entered on its server, or else the registrar's defaults.
     *
     * @return list<string>
     */
    public function nameserversFor(Service $service, Domain $domain): array
    {
        $server = $service->server;
        $ns = $server ? array_map(fn ($i) => (string) $server->{'nameserver'.$i}, range(1, 5)) : [];
        $ns = array_values(array_filter(array_map('trim', $ns)));

        if ($ns === [] && filled($domain->registrar)) {
            $defaults = RegistrarSettings::where('registrar', strtolower((string) $domain->registrar))
                ->whereIn('setting', ['ns1', 'ns2', 'ns3', 'ns4', 'ns5'])
                ->get()
                ->sortBy('setting')
                ->pluck('value')
                ->map(fn ($v) => trim((string) $v))
                ->filter()
                ->values()
                ->all();
            $ns = $defaults;
        }

        return $ns;
    }

    /** Is the domain on this account, with its nameservers pointing there? */
    public function isSetUp(Service $service, Domain $domain): bool
    {
        $module = $this->moduleFor($service);
        if (! $module) {
            return false;
        }

        try {
            $names = array_map('strtolower', array_values($module->accountDomains($service)));
        } catch (\Throwable $e) {
            Log::warning("Hosting domain list failed for service #{$service->id}: {$e->getMessage()}");

            return false;
        }

        $target = array_map('strtolower', $this->nameserversFor($service, $domain));

        return in_array(strtolower((string) $domain->domain), $names, true)
            && $target !== []
            && $target === array_map('strtolower', array_slice($domain->nameserverList(), 0, count($target)));
    }

    /**
     * Add the domain to the account, then point it there. In that order: had
     * the nameservers moved first and the panel refused, the domain would point
     * at a server that has nothing for it.
     *
     * @return array{success: bool, stage: string, message: string}
     *               stage: hosting (the panel refused), nameservers (added,
     *               but the registrar refused the change), none (added, and
     *               no nameservers are known to point it to), done
     */
    public function attach(Service $service, Domain $domain): array
    {
        $module = $this->moduleFor($service);
        if (! $module) {
            return ['success' => false, 'stage' => 'hosting', 'message' => 'This service cannot take more domains.'];
        }

        try {
            $result = $module->createAccountDomain($service, (string) $domain->domain);
        } catch (\Throwable $e) {
            Log::error("Setting up {$domain->domain} on service #{$service->id} threw: {$e->getMessage()}");
            $result = ['success' => false, 'message' => $e->getMessage()];
        }

        if (! ($result['success'] ?? false)) {
            return ['success' => false, 'stage' => 'hosting', 'message' => (string) ($result['message'] ?? '')];
        }

        $nameservers = $this->nameserversFor($service, $domain);
        if ($nameservers === []) {
            return ['success' => true, 'stage' => 'none', 'message' => ''];
        }

        $keyed = [];
        foreach ($nameservers as $i => $ns) {
            $keyed['ns'.($i + 1)] = $ns;
        }

        $ns = app(DomainService::class)->updateNameservers($domain, $keyed);
        if (! ($ns['success'] ?? false)) {
            return ['success' => false, 'stage' => 'nameservers', 'message' => (string) ($ns['message'] ?? '')];
        }

        return ['success' => true, 'stage' => 'done', 'message' => ''];
    }
}
