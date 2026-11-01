<?php

namespace Modules\Registrars\OVH;

use App\Contracts\RegistrarModuleInterface;
use App\Contracts\SyncsDomainData;
use App\Models\Client;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Models\ModuleLog;
use App\Models\RegistrarSettings;
use App\Services\DomainHosting;
use App\Support\MapsClientFields;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * OVHcloud domain registrar.
 *
 * Speaks to the OVHcloud REST API (api.ovh.com) using the self-contained
 * OvhClient in this module, so there is no third-party SDK dependency.
 *
 * Registration and transfers go through OVH's order-cart flow, so they return
 * an order id rather than a finished domain; the domain is kept pending and
 * syncDomain() flips it to active once OVH reports it. The simple operations
 * (nameservers, auth code, transfer lock, renew, info) hit the domain service
 * endpoints directly.
 */
class OvhRegistrar implements RegistrarModuleInterface, SyncsDomainData
{
    use MapsClientFields;

    protected ?OvhClient $client = null;

    protected array $settings = [];

    public function __construct()
    {
        $this->settings = $this->loadSettings();
    }

    public function getModuleName(): string
    {
        return 'OVH';
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'app_key', 'label' => 'Application Key', 'type' => 'text', 'required' => true],
            ['name' => 'app_secret', 'label' => 'Application Secret', 'type' => 'password', 'required' => true],
            ['name' => 'consumer_key', 'label' => 'Consumer Key', 'type' => 'password', 'required' => true],
            ['name' => 'region', 'label' => 'API Region', 'type' => 'select', 'options' => ['eu' => 'Europe (EU)', 'ca' => 'Canada (CA)', 'us' => 'United States (US)'], 'default' => 'eu'],
            ['name' => 'margin_percent', 'label' => 'Margin (%)', 'type' => 'text', 'required' => false, 'default' => '0'],
        ];
    }

    /** Verify the credentials and reach the API, for the registrars screen. */
    public function testConnection(): array
    {
        try {
            $me = $this->client()->get('/me');

            return [
                'success' => true,
                'message' => 'Połączenie z OVH działa. Konto: '.($me['nichandle'] ?? ($me['firstname'] ?? 'n/d')),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** The prepaid account (OVH points) balance, for the dashboard balance watch. */
    public function getBalance(): array
    {
        try {
            $accounts = $this->client()->get('/me/prepaid/account');
            $account = is_array($accounts) ? ($accounts[0] ?? null) : null;

            $balance = null;
            $currency = 'eur';

            if (is_array($account)) {
                $raw = $account['balance'] ?? $account['value'] ?? null;

                if (is_array($raw)) {
                    $balance = $raw['value'] ?? null;
                    $currency = strtolower((string) ($raw['currencyCode'] ?? 'eur'));
                } else {
                    $balance = $raw;
                }
            }

            return ['success' => true, $currency => $balance, 'message' => null];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function register(Domain $domain, int $years, array $params = []): array
    {
        try {
            $orderId = $this->orderDomain($domain, max(1, $years), $params);

            $domain->update([
                'status' => 'pending',
                'registrar' => 'ovh',
                'registration_date' => now()->toDateString(),
                'next_due_date' => now()->addYears($years)->toDateString(),
            ]);

            $this->logAction('register', $domain, ['order_id' => $orderId], ['success' => true, 'order_id' => $orderId]);

            return ['success' => true, 'message' => "Domain ordered at OVH (order #{$orderId})."];
        } catch (\Throwable $e) {
            Log::error("OVH register failed for {$domain->domain}: {$e->getMessage()}");
            $this->logAction('register', $domain, [], ['success' => false, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function transfer(Domain $domain, string $eppCode): array
    {
        try {
            $orderId = $this->orderDomain($domain, max(1, (int) ($domain->registration_period ?? 1)), [], $eppCode);

            $domain->update(['status' => 'pending_transfer', 'registrar' => 'ovh']);

            return ['success' => true, 'message' => "Transfer ordered at OVH (order #{$orderId})."];
        } catch (\Throwable $e) {
            Log::error("OVH transfer failed for {$domain->domain}: {$e->getMessage()}");

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function renew(Domain $domain, int $years): array
    {
        try {
            $this->client()->post('/domain/'.$domain->domain.'/renew', ['duration' => 'P'.$years.'Y']);

            $newExpiry = ($domain->expiry_date ?? now())->addYears($years);
            $domain->update(['expiry_date' => $newExpiry, 'next_due_date' => $newExpiry]);

            return ['success' => true, 'message' => "Domain renewed for {$years} year(s).", 'expiry_date' => $newExpiry->toDateString()];
        } catch (\Throwable $e) {
            Log::error("OVH renew failed for {$domain->domain}: {$e->getMessage()}");

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getNameservers(Domain $domain): array
    {
        try {
            $info = $this->client()->get('/domain/'.$domain->domain);

            $names = array_map(
                fn ($ns) => is_array($ns) ? (string) ($ns['nameServer'] ?? '') : (string) $ns,
                is_array($info['nameServers'] ?? null) ? $info['nameServers'] : []
            );

            return array_values(array_filter(array_map('strtolower', array_map('trim', $names))));
        } catch (\Throwable $e) {
            Log::warning("OVH getNameservers failed for {$domain->domain}: {$e->getMessage()}");

            return $domain->nameserverList();
        }
    }

    public function saveNameservers(Domain $domain, array $nameservers): bool
    {
        try {
            $this->client()->post('/domain/'.$domain->domain.'/nameServers/update', [
                'nameServers' => array_map(
                    fn ($ns) => ['host' => (string) $ns],
                    array_values(array_filter($nameservers))
                ),
            ]);

            $domain->update(['nameservers' => json_encode(array_values($nameservers))]);

            return true;
        } catch (\Throwable $e) {
            Log::error("OVH saveNameservers failed for {$domain->domain}: {$e->getMessage()}");

            return false;
        }
    }

    public function getEPPCode(Domain $domain): string
    {
        try {
            $result = $this->client()->get('/domain/'.$domain->domain.'/authInfo');

            if (is_string($result) && $result !== '') {
                return $result;
            }

            if (is_array($result)) {
                return (string) ($result['authInfo'] ?? $result['authcode'] ?? $result['transferAuthInfo'] ?? '(unavailable)');
            }

            return '(unavailable)';
        } catch (\Throwable $e) {
            Log::warning("OVH getEPPCode failed for {$domain->domain}: {$e->getMessage()}");

            return '(unavailable)';
        }
    }

    public function getLockStatus(Domain $domain): bool
    {
        try {
            $info = $this->client()->get('/domain/'.$domain->domain);

            return ($info['transferLockStatus'] ?? 'locked') !== 'unlocked';
        } catch (\Throwable $e) {
            Log::warning("OVH getLockStatus failed for {$domain->domain}: {$e->getMessage()}");

            return true;
        }
    }

    public function toggleLock(Domain $domain, bool $lock): bool
    {
        try {
            $this->client()->put('/domain/'.$domain->domain, [
                'transferLockStatus' => $lock ? 'locked' : 'unlocked',
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error("OVH toggleLock failed for {$domain->domain}: {$e->getMessage()}");

            return false;
        }
    }

    public function checkAvailability(string $domain): array
    {
        try {
            $modes = array_column($this->domainOffers($domain), 'pricingMode');

            return [
                'available' => collect($modes)->contains(fn ($m) => str_starts_with((string) $m, 'create')),
                'domain' => $domain,
                'method' => 'ovh_api',
            ];
        } catch (\Throwable $e) {
            Log::warning("OVH checkAvailability failed for {$domain}: {$e->getMessage()}");

            return ['available' => false, 'domain' => $domain, 'method' => 'ovh_api', 'error' => $e->getMessage()];
        }
    }

    public function syncDomain(Domain $domain): array
    {
        try {
            $info = $this->client()->get('/domain/'.$domain->domain);
            $serviceInfos = $this->client()->get('/domain/'.$domain->domain.'/serviceInfos');

            // OVH returns nameServers as objects ({id, nameServerType, nameServer});
            // the sync contract wants the hostnames alone.
            $nameservers = array_values(array_filter(array_map(
                fn ($ns) => is_array($ns) ? (string) ($ns['nameServer'] ?? '') : (string) $ns,
                is_array($info['nameServers'] ?? null) ? $info['nameServers'] : []
            )));

            return [
                'success' => true,
                'expiry_date' => $this->parseDate($serviceInfos['expiration'] ?? null),
                'status' => $this->mapStatus($info['status'] ?? null),
                'locked' => isset($info['transferLockStatus']) ? $info['transferLockStatus'] === 'locked' : null,
                'nameservers' => $nameservers,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Pull the OVH purchase prices — with the configured margin applied and
     * rounded up to a whole unit of the shop currency — onto the TLDs whose
     * auto registrar is OVH. Refused unless OVH quotes in that currency.
     * Run manually from the registrars screen.
     */
    public function syncPrices(): array
    {
        try {
            $margin = (float) ($this->settings['margin_percent'] ?? 0);
            $shop = strtoupper((string) (\App\Models\Currency::getDefault()?->code ?? ''));

            $updated = 0;
            $unchanged = 0;
            $skipped = 0;
            $changes = [];
            $skippedExtensions = [];

            foreach (DomainPricing::whereNotNull('auto_registrar')->get() as $tld) {
                if (strtolower(trim((string) $tld->auto_registrar)) !== 'ovh') {
                    continue;
                }

                $ext = ltrim((string) $tld->extension, '.');
                if ($ext === '') {
                    continue;
                }

                $price = $this->tldPrice($ext);
                if ($price === null) {
                    $skipped++;
                    $skippedExtensions[] = $tld->extension;

                    continue;
                }

                // The cart's currency is the OVH account's. Written into a
                // price list the shop reads in another one, every number would
                // be wrong by the exchange rate and nothing would say so.
                $quoted = (string) ($price['currency'] ?? '');
                if ($quoted !== '' && $quoted !== $shop) {
                    return ['success' => false, 'message' => __('admin.registrars.sync_currency_mismatch', [
                        'registrar' => $quoted,
                        'shop' => $shop !== '' ? $shop : '—',
                    ])];
                }

                // Only the prices OVH quotes. One it does not is not a price
                // of zero and not a null the column refuses: it is one the
                // panel does not know, so the operator's own number stays.
                $next = [];
                foreach (['register' => 'register_price', 'renew' => 'renew_price', 'transfer' => 'transfer_price'] as $from => $column) {
                    $value = $this->withMargin($price[$from] ?? null, $margin);
                    if ($value !== null) {
                        $next[$column] = $value;
                    }
                }

                if ($next === []) {
                    $skipped++;
                    $skippedExtensions[] = $tld->extension;

                    continue;
                }

                $current = [];
                foreach (array_keys($next) as $column) {
                    $current[$column] = (float) $tld->{$column};
                }

                if ($next == $current) {
                    $unchanged++;

                    continue;
                }

                $tld->update($next);
                $changes[] = ['extension' => $tld->extension, 'before' => $current, 'after' => $next];
                $updated++;
            }

            $this->logSync($updated, $unchanged, $skipped, $changes, $skippedExtensions, $margin);

            RegistrarSettings::updateOrCreate(
                ['registrar' => 'ovh', 'setting' => 'last_price_sync'],
                ['value' => now()->toDateTimeString()],
            );

            return ['success' => true, 'updated' => $updated, 'unchanged' => $unchanged, 'skipped' => $skipped];
        } catch (\Throwable $e) {
            Log::error("OVH syncPrices failed: {$e->getMessage()}");

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ---------------------------------------------------------------------
    // Cart flow
    // ---------------------------------------------------------------------

    /**
     * The offers OVH returns for a domain: create-default / create-premium
     * (available), transfer-* (taken but transferable), aftermarket.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function domainOffers(string $domain): array
    {
        $cart = $this->client()->post('/order/cart');
        $cartId = (string) ($cart['cartId'] ?? '');

        if ($cartId === '') {
            throw new \RuntimeException('OVH: could not create an order cart.');
        }

        return $this->client()->get('/order/cart/'.$cartId.'/domain', ['domain' => $domain]);
    }

    /**
     * Order a domain (registration or transfer) through OVH's cart and return
     * the order id. The registrant contact is created once per client and
     * reused via the CSA custom field.
     */
    protected function orderDomain(Domain $domain, int $years, array $params = [], ?string $eppCode = null): int
    {
        $client = $domain->client;
        if (! $client) {
            throw new \RuntimeException('Domain has no client — cannot create the OVH registrant.');
        }

        $offers = $this->domainOffers($domain->domain);
        $prefix = $eppCode !== null ? 'transfer' : 'create';
        $offer = collect($offers)->first(fn ($o) => str_starts_with((string) ($o['pricingMode'] ?? ''), $prefix));

        if (! $offer) {
            throw new \RuntimeException('OVH: no '.($eppCode !== null ? 'transfer' : 'registration').' offer for '.$domain->domain.'.');
        }

        $isCompany = ($client->client_type ?? null) === 'company'
            || (($client->client_type ?? null) === null && filled($client->company_name));

        $contactId = $this->registrantContactId($client, $isCompany, $params);

        $cart = $this->client()->post('/order/cart');
        $cartId = (string) ($cart['cartId'] ?? '');
        if ($cartId === '') {
            throw new \RuntimeException('OVH: could not create an order cart.');
        }

        $item = $this->client()->post('/order/cart/'.$cartId.'/domain', [
            'domain' => $domain->domain,
            'offerId' => $offer['offerId'] ?? null,
            'duration' => 'P'.$years.'Y',
            'quantity' => 1,
        ]);
        $itemId = (int) ($item['itemId'] ?? 0);

        $this->configureCart($cartId, $itemId, $contactId, $this->nameserversFor($domain, $params), $eppCode);

        $this->client()->post('/order/cart/'.$cartId.'/assign');
        $checkout = $this->client()->post('/order/cart/'.$cartId.'/checkout');

        return (int) ($checkout['orderId'] ?? ($checkout['order']['orderId'] ?? 0));
    }

    /** Add the required configurations (owner contact, nameservers, auth code). */
    protected function configureCart(string $cartId, int $itemId, int $contactId, array $nameservers, ?string $eppCode): void
    {
        $required = $this->client()->get('/order/cart/'.$cartId.'/item/'.$itemId.'/requiredConfiguration');

        foreach ($required as $config) {
            $label = strtolower((string) ($config['label'] ?? ''));

            $value = match (true) {
                str_contains($label, 'owner') || str_contains($label, 'contact') || str_contains($label, 'registrant') => (string) $contactId,
                str_contains($label, 'dns') || str_contains($label, 'nameserver') => implode(',', $nameservers),
                str_contains($label, 'auth') || str_contains($label, 'epp') => (string) $eppCode,
                default => null,
            };

            if ($value === null) {
                continue;
            }

            $this->client()->post('/order/cart/'.$cartId.'/item/'.$itemId.'/configuration', [
                'label' => $config['label'],
                'value' => $value,
            ]);
        }
    }

    /** The OVH contact id for a client, created once and remembered on the CSA field. */
    protected function registrantContactId(Client $client, bool $isCompany, array $params): int
    {
        $csa = $this->resolveClientField($client, $this->settings['csa_field'] ?? null, ['csa', 'ovh_contact_id']);
        if ($csa !== null && ctype_digit(trim($csa))) {
            return (int) trim($csa);
        }

        $contact = [
            'firstName' => (string) ($client->first_name ?? ''),
            'lastName' => (string) ($client->last_name ?? ''),
            'email' => (string) ($client->email ?? ''),
            'phone' => (string) ($client->phone_number ?? ''),
            'address' => [
                'line1' => (string) ($params['address'] ?? $client->address1 ?? ''),
                'city' => (string) ($params['city'] ?? $client->city ?? ''),
                'zip' => (string) ($params['postcode'] ?? $client->postcode ?? ''),
                'country' => (string) ($params['country'] ?? $client->country ?? ''),
            ],
            'legalForm' => $isCompany ? 'corporation' : 'individual',
        ];

        if ($isCompany) {
            $contact['organisationName'] = (string) ($client->company_name ?? '');
            $contact['vat'] = (string) ($client->tax_id ?? '');
        } else {
            $contact['nationalIdentificationNumber'] = (string) ($client->national_id ?? '');
        }

        $created = $this->client()->post('/me/contact', $contact);
        $id = (int) ($created['id'] ?? 0);

        $this->storeClientField($client, $this->settings['csa_field'] ?? null, (string) $id, ['csa', 'ovh_contact_id']);

        return $id;
    }

    /** The nameservers a new domain should point at: the order's, then General Settings → Domains. */
    protected function nameserversFor(Domain $domain, array $params): array
    {
        $provided = array_values(array_filter([
            $params['ns1'] ?? null,
            $params['ns2'] ?? null,
            $params['ns3'] ?? null,
            $params['ns4'] ?? null,
            $params['ns5'] ?? null,
        ]));

        if (count($provided) >= 2) {
            return $provided;
        }

        return DomainHosting::defaultNameservers();
    }

    /**
     * The price OVH quotes for a TLD, read from a synthetic domain ("example.tld")
     * through the cart. Null when the TLD is not orderable.
     *
     * @return array{register: ?float, renew: ?float, transfer: ?float, currency: ?string}|null
     */
    protected function tldPrice(string $tld): ?array
    {
        $domain = 'example'.$tld;

        try {
            $offers = $this->domainOffers($domain);
            $offer = collect($offers)->first(fn ($o) => str_starts_with((string) ($o['pricingMode'] ?? ''), 'create'));
            if (! $offer) {
                return null;
            }

            $cart = $this->client()->post('/order/cart');
            $cartId = (string) ($cart['cartId'] ?? '');
            if ($cartId === '') {
                return null;
            }

            $item = $this->client()->post('/order/cart/'.$cartId.'/domain', [
                'domain' => $domain,
                'offerId' => $offer['offerId'] ?? null,
                'duration' => 'P1Y',
                'quantity' => 1,
            ]);

            $prices = ['register' => null, 'renew' => null, 'transfer' => null, 'currency' => null];

            foreach ($item['prices'] ?? [] as $p) {
                $label = strtoupper((string) ($p['label'] ?? ''));
                $value = $p['price']['value'] ?? null;
                // Which currency the cart quotes in: the account's, not the
                // shop's, and the two have to agree before a number is kept.
                $prices['currency'] ??= ($p['price']['currencyCode'] ?? null) ? strtoupper((string) $p['price']['currencyCode']) : null;

                if (in_array($label, ['FEE', 'TOTAL'], true)) {
                    $prices['register'] = $value !== null ? (float) $value : null;
                } elseif ($label === 'RENEW') {
                    $prices['renew'] = $value !== null ? (float) $value : null;
                } elseif ($label === 'TRANSFER') {
                    $prices['transfer'] = $value !== null ? (float) $value : null;
                }
            }

            return $prices;
        } catch (\Throwable) {
            return null;
        }
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    protected function client(): OvhClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $this->client = new OvhClient(
            (string) ($this->settings['app_key'] ?? ''),
            (string) ($this->settings['app_secret'] ?? ''),
            (string) ($this->settings['consumer_key'] ?? ''),
            (string) ($this->settings['region'] ?? 'eu'),
        );

        return $this->client;
    }

    /** A purchase price with the margin applied, rounded up to a whole unit. */
    protected function withMargin(?float $price, float $margin): ?float
    {
        if ($price === null) {
            return null;
        }

        return $price > 0 ? ceil($price * (1 + $margin / 100)) : 0.0;
    }

    protected function loadSettings(): array
    {
        try {
            return RegistrarSettings::where('registrar', 'ovh')->pluck('value', 'setting')->all();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function parseDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function mapStatus(?string $status): ?string
    {
        return match (strtolower((string) $status)) {
            'ok' => 'active',
            'expired' => 'expired',
            'pendingdelete' => 'expired',
            'pendingoutgoingtransfer' => 'transferred_away',
            default => $status,
        };
    }

    protected function logAction(string $action, Domain $domain, array $request, array $response): void
    {
        try {
            ModuleLog::create([
                'module' => $this->getModuleName(),
                'action' => $action,
                'request' => json_encode(['domain' => $domain->domain] + $request, JSON_UNESCAPED_SLASHES),
                'response' => json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'service_id' => $domain->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('OVH module log failed: '.$e->getMessage());
        }
    }

    protected function logSync(int $updated, int $unchanged, int $skipped, array $changes, array $skippedExtensions, float $margin): void
    {
        $details = collect($changes)->map(function (array $c): string {
            return $c['extension']
                .' reg:'.($c['before']['register_price'] ?? '-').'→'.($c['after']['register_price'] ?? '-')
                .' renew:'.($c['before']['renew_price'] ?? '-').'→'.($c['after']['renew_price'] ?? '-')
                .' transfer:'.($c['before']['transfer_price'] ?? '-').'→'.($c['after']['transfer_price'] ?? '-');
        })->implode(' | ');

        Log::info('OVH price sync: '.$updated.' changed, '.$unchanged.' unchanged, '.$skipped.' skipped'
            .($details !== '' ? ' — '.$details : ''));

        try {
            ModuleLog::create([
                'module' => $this->getModuleName(),
                'action' => 'sync_prices',
                'request' => json_encode(['margin_percent' => $margin], JSON_UNESCAPED_SLASHES),
                'response' => json_encode([
                    'updated' => $updated,
                    'unchanged' => $unchanged,
                    'skipped' => $skipped,
                    'changed_extensions' => array_column($changes, 'extension'),
                    'skipped_extensions' => $skippedExtensions,
                    'changes' => $changes,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            Log::warning('OVH module log failed: '.$e->getMessage());
        }
    }
}
