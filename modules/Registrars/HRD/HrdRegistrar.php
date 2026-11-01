<?php

namespace Modules\Registrars\HRD;

use App\Contracts\RegistrarModuleInterface;
use App\Contracts\SyncsDomainData;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Models\ModuleLog;
use App\Models\RegistrarSettings;
use App\Services\DomainHosting;
use App\Support\MapsClientFields;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * HRD (hrd.pl) domain registrar.
 *
 * Speaks to api.hrd.pl over a raw SSL socket using the self-contained
 * HrdClient in this module — the XML protocol is implemented here, so there is
 * no third-party SDK dependency.
 *
 * The panel does not store a PESEL, so a person registrant is created with the
 * client's National ID, a company with its NIP (`tax_id`). Domains are
 * registered asynchronously: domainCreate() returns an action id which can be
 * followed with actionInfo().
 */
class HrdRegistrar implements RegistrarModuleInterface, SyncsDomainData
{
    /** HRD publishes its partner prices in złoty. */
    private const QUOTE_CURRENCY = 'PLN';

    use MapsClientFields;

    protected ?HrdClient $client = null;

    protected array $settings = [];

    public function __construct()
    {
        $this->settings = $this->loadSettings();
    }

    public function getModuleName(): string
    {
        return 'HRD';
    }

    public function getConfigFields(): array
    {
        $clientFields = $this->clientCustomFieldOptions();
        $fieldOptions = ['' => '— Auto —'] + $clientFields;

        return [
            ['name' => 'api_login', 'label' => 'Login', 'type' => 'text', 'required' => true],
            ['name' => 'api_hash', 'label' => 'API Hash', 'type' => 'password', 'required' => true],
            ['name' => 'api_pass', 'label' => 'API Password', 'type' => 'password', 'required' => true],
            ['name' => 'default_ns_group', 'label' => 'Default NS Group ID', 'type' => 'text', 'required' => false],
            ['name' => 'margin_percent', 'label' => __('admin.registrars.margin_percent'), 'type' => 'text', 'required' => false, 'default' => '0'],
            ['name' => 'csa_field', 'label' => 'CSA field', 'type' => 'select', 'options' => $fieldOptions, 'required' => false],
        ];
    }

    /**
     * Optional setup guidance shown as a tooltip next to the module name on
     * the registrars screen.
     */
    public function getConfigHelp(): ?string
    {
        return __('admin.registrars.hrd_instructions');
    }

    /**
     * Verify the credentials and reach the HRD API. Used by the "Test"
     * button on the registrars screen.
     */
    public function testConnection(): array
    {
        try {
            $balance = $this->client()->partnerGetBalance();

            return [
                'success' => true,
                'message' => 'Połączenie z HRD działa. Saldo: ' . ($balance['balance'] ?? 'n/d'),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * The partner's balance, for the dashboard and the low-balance warning.
     * HRD reports a single PLN float; renewals stop once it runs dry, so this
     * is worth surfacing instead of discovering through a failed renewal.
     */
    public function getBalance(): array
    {
        try {
            $balance = $this->client()->partnerGetBalance();

            return [
                'success' => true,
                'pln' => $balance['balance'] ?? null,
                'message' => null,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Pull the partner purchase prices from HRD and write them — with the
     * configured margin applied and rounded up to a whole złoty — onto the TLDs
     * whose auto registrar is HRD. Run manually from the registrars screen.
     *
     * @return array{success: bool, updated?: int, unchanged?: int, skipped?: int, message?: string}
     */
    public function syncPrices(): array
    {
        // HRD quotes in złoty. Written into a price list the shop reads in
        // another currency, the numbers would be wrong by the exchange rate
        // and nothing would say so.
        $shop = strtoupper((string) (\App\Models\Currency::getDefault()?->code ?? ''));
        if ($shop !== self::QUOTE_CURRENCY) {
            return ['success' => false, 'message' => __('admin.registrars.sync_currency_mismatch', [
                'registrar' => self::QUOTE_CURRENCY,
                'shop' => $shop !== '' ? $shop : '—',
            ])];
        }

        try {
            $prices = $this->client()->partnerGetPriceList();
            $margin = (float) ($this->settings['margin_percent'] ?? 0);

            $updated = 0;
            $unchanged = 0;
            $skipped = 0;
            $changes = [];
            $skippedExtensions = [];

            foreach (DomainPricing::whereNotNull('auto_registrar')->get() as $tld) {
                if (strtolower(trim((string) $tld->auto_registrar)) !== 'hrd') {
                    continue;
                }

                $ext = ltrim((string) $tld->extension, '.');
                if (! array_key_exists($ext, $prices)) {
                    $skipped++;
                    $skippedExtensions[] = $tld->extension;
                    continue;
                }

                // Only the prices HRD publishes. One it does not ("(brak)")
                // is not a price of zero and not a null the column would
                // refuse: it is one the panel does not know, so the
                // operator's own number stays.
                $next = [];
                foreach (['register' => 'register_price', 'renew' => 'renew_price', 'transfer' => 'transfer_price', 'restore' => 'restore_price'] as $from => $column) {
                    $value = $this->withMargin($prices[$ext][$from] ?? null, $margin);
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

            $labels = ['register_price' => 'reg', 'renew_price' => 'renew', 'transfer_price' => 'transfer', 'restore_price' => 'restore'];
            $details = collect($changes)->map(function (array $c) use ($labels): string {
                $parts = [];
                foreach ($c['after'] as $column => $value) {
                    $parts[] = ($labels[$column] ?? $column).':'.($c['before'][$column] ?? '-').'→'.$value;
                }

                return $c['extension'].' '.implode(' ', $parts);
            })->implode(' | ');

            Log::info('HRD price sync: '.$updated.' changed, '.$unchanged.' unchanged, '.$skipped.' skipped'
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
                Log::warning('HRD module log failed: '.$e->getMessage());
            }

            RegistrarSettings::updateOrCreate(
                ['registrar' => 'hrd', 'setting' => 'last_price_sync'],
                ['value' => now()->toDateTimeString()],
            );

            return ['success' => true, 'updated' => $updated, 'unchanged' => $unchanged, 'skipped' => $skipped];
        } catch (\Throwable $e) {
            Log::error("HRD syncPrices failed: {$e->getMessage()}");

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** A purchase price with the margin applied, rounded up to a whole złoty. */
    protected function withMargin(?string $price, float $margin): ?float
    {
        if ($price === null || trim($price) === '') {
            return null;
        }

        $value = (float) $price;

        return $value > 0 ? ceil($value * (1 + $margin / 100)) : 0.0;
    }

    public function register(Domain $domain, int $years, array $params = []): array
    {
        try {
            $userId = $this->createUser($domain, $params);
            $ns = $this->nameserversFor($domain, $params);

            if ($ns === null) {
                return ['success' => false, 'message' => 'No nameservers supplied and no default NS group configured.'];
            }

            $actionId = $this->client()->domainCreate($domain->domain, $userId, $ns, max(1, $years), false);

            $domain->update([
                'status' => 'pending',
                'registrar' => 'hrd',
                'registration_date' => now()->toDateString(),
                'expiry_date' => now()->addYears($years)->toDateString(),
                'next_due_date' => now()->addYears($years)->toDateString(),
            ]);

            return ['success' => true, 'message' => "Domain registered via HRD (action #{$actionId})."];
        } catch (\Throwable $e) {
            Log::error("HRD register failed for {$domain->domain}: {$e->getMessage()}");

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function transfer(Domain $domain, string $eppCode): array
    {
        try {
            $userId = $this->createUser($domain);
            $actionId = $this->client()->domainTransfer($domain->domain, $userId, $eppCode, 0);

            $domain->update(['status' => 'pending_transfer', 'registrar' => 'hrd']);

            return ['success' => true, 'message' => "Transfer initiated via HRD (action #{$actionId})."];
        } catch (\Throwable $e) {
            Log::error("HRD transfer failed for {$domain->domain}: {$e->getMessage()}");

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function renew(Domain $domain, int $years): array
    {
        try {
            $currentExpiry = ($domain->expiry_date ?? now())->format('Y-m-d');
            $actionId = $this->client()->domainRenew($domain->domain, $currentExpiry, max(1, $years));

            $newExpiry = ($domain->expiry_date ?? now())->addYears($years);
            $domain->update(['expiry_date' => $newExpiry, 'next_due_date' => $newExpiry]);

            return ['success' => true, 'message' => "Domain renewed for {$years} year(s) (action #{$actionId})."];
        } catch (\Throwable $e) {
            Log::error("HRD renew failed for {$domain->domain}: {$e->getMessage()}");

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getNameservers(Domain $domain): array
    {
        try {
            $info = $this->client()->domainInfo($domain->domain);
            $ns = $info['ns'] ?? null;

            if (is_array($ns) && isset($ns['group'])) {
                return [];
            }

            if (! is_array($ns)) {
                return [];
            }

            return array_map(fn ($entry) => (string) ($entry['name'] ?? ''), $ns);
        } catch (\Throwable $e) {
            Log::warning("HRD getNameservers failed for {$domain->domain}: {$e->getMessage()}");

            return json_decode($domain->nameservers ?? '[]', true) ?: [];
        }
    }

    public function saveNameservers(Domain $domain, array $nameservers): bool
    {
        try {
            $ns = array_map(fn ($name) => ['name' => $name], array_filter($nameservers));
            $this->client()->domainUpdate($domain->domain, $ns);
            $domain->update(['nameservers' => json_encode(array_values($nameservers))]);

            return true;
        } catch (\Throwable $e) {
            Log::error("HRD saveNameservers failed for {$domain->domain}: {$e->getMessage()}");

            return false;
        }
    }

    public function getEPPCode(Domain $domain): string
    {
        try {
            return $this->client()->domainTradeGetPw($domain->domain) ?: '(unavailable)';
        } catch (\Throwable $e) {
            Log::warning("HRD getEPPCode failed for {$domain->domain}: {$e->getMessage()}");

            return '(unavailable)';
        }
    }

    public function getLockStatus(Domain $domain): bool
    {
        // HRD exposes no registrar-lock endpoint; assume locked (safe default).
        return true;
    }

    public function toggleLock(Domain $domain, bool $lock): bool
    {
        // No lock endpoint in the HRD API — nothing to toggle.
        return true;
    }

    public function checkAvailability(string $domain): array
    {
        try {
            $status = null;
            foreach ($this->client()->domainCheck([$domain]) as $name => $state) {
                $status = $state;
            }

            return [
                'available' => $status === 'available' || $status === 'createOnly',
                'domain' => $domain,
                'method' => 'hrd_api',
            ];
        } catch (\Throwable $e) {
            Log::warning("HRD checkAvailability failed for {$domain}: {$e->getMessage()}");

            return ['available' => false, 'domain' => $domain, 'method' => 'hrd_api', 'error' => $e->getMessage()];
        }
    }

    public function syncDomain(Domain $domain): array
    {
        try {
            $info = $this->client()->domainInfo($domain->domain);

            return [
                'success' => true,
                'expiry_date' => $this->parseDate($info['exDate'] ?? null),
                'status' => $this->mapStatus($info['status'] ?? null),
                'locked' => true,
                'nameservers' => array_map(fn ($entry) => (string) ($entry['name'] ?? ''), (array) ($info['ns'] ?? [])),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Instantiate the HRD client once per request and authenticate it.
     */
    protected function client(): HrdClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $this->client = new HrdClient(
            (string) ($this->settings['api_login'] ?? ''),
            (string) ($this->settings['api_pass'] ?? ''),
            (string) ($this->settings['api_hash'] ?? ''),
        );
        $this->client->login();

        return $this->client;
    }

    /**
     * Create (or reuse) the HRD user/registrant for this domain's client.
     *
     * HRD keys registrants to a user id (CSA), so a domain cannot be
     * registered without one. A stored CSA is reused; otherwise a user is
     * created. A private person is identified by their National ID (PESEL); a
     * company by its NIP.
     */
    protected function createUser(Domain $domain, array $params = []): int
    {
        $client = $domain->client;

        if (! $client) {
            throw new \RuntimeException('Domain has no client — cannot create HRD registrant.');
        }

        // Reuse an existing HRD user id when one is mapped and stored.
        $csa = $this->resolveClientField($client, $this->settings['csa_field'] ?? null, ['csa', 'hrd_user_id']);
        if ($csa !== null && ctype_digit(trim($csa))) {
            return (int) trim($csa);
        }

        $isCompany = ($client->client_type ?? null) === 'company'
            || (($client->client_type ?? null) === null && filled($client->company_name));
        $name = $isCompany
            ? $client->company_name
            : trim(($client->first_name ?? '').' '.($client->last_name ?? ''));

        $phone = $this->normalizePhone($params['phone'] ?? $client->full_phone);

        // A private person is identified by PESEL (the National ID field); a
        // company by its NIP. The two are never interchangeable, so a person
        // never falls back to tax_id.
        //
        // An installation that mapped a PESEL custom field before this is
        // still read, while National ID is empty: the option is gone from the
        // screen, but the value its customers' records hold is not, and
        // sending an empty PESEL means the registry refuses the domain.
        $idNumber = $isCompany
            ? (string) ($client->tax_id ?? '')
            : (string) ($client->national_id ?: $this->resolveClientField($client, $this->settings['pesel_field'] ?? null, ['pesel']) ?? '');

        $userId = $this->client()->userCreate(
            $isCompany ? HrdClient::COMPANY : HrdClient::PERSON,
            $idNumber,
            (string) ($client->email ?? ''),
            $phone,
            $phone,
            null,
            $name ?: 'Klient',
            (string) ($params['address'] ?? $client->address1 ?? ''),
            (string) ($params['postcode'] ?? $client->postcode ?? ''),
            (string) ($params['city'] ?? $client->city ?? ''),
            $this->normalizeCountry($params['country'] ?? $client->country),
            $isCompany ? $client->full_name : null,
        );

        // Remember the CSA so the next domain reuses the same registrant.
        $this->storeClientField($client, $this->settings['csa_field'] ?? null, (string) $userId, ['csa', 'hrd_user_id']);

        return $userId;
    }

    /**
     * Build the `ns` argument for domainCreate. The client's own nameservers
     * win; otherwise the defaults from General Settings are used; otherwise a
     * preconfigured HRD group. Returns null when nothing is available.
     *
     * @return array|string|null
     */
    protected function nameserversFor(Domain $domain, array $params): array|string|null
    {
        $provided = array_values(array_filter([
            $params['ns1'] ?? null,
            $params['ns2'] ?? null,
            $params['ns3'] ?? null,
            $params['ns4'] ?? null,
            $params['ns5'] ?? null,
        ]));

        if (count($provided) >= 2) {
            return array_map(fn ($name) => ['name' => $name], $provided);
        }

        $defaults = $this->defaultNameservers();
        if (count($defaults) >= 2) {
            return array_map(fn ($name) => ['name' => $name], $defaults);
        }

        $groupId = (int) ($this->settings['default_ns_group'] ?? 0);
        if ($groupId > 0) {
            return ['group' => $groupId];
        }

        return null;
    }

    /**
     * The default nameservers from General Settings (the Domains section).
     *
     * @return array<int, string>
     */
    protected function defaultNameservers(): array
    {
        return DomainHosting::defaultNameservers();
    }

    protected function loadSettings(): array
    {
        try {
            $rows = RegistrarSettings::where('registrar', 'hrd')->get();
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row->setting] = $row->value;
            }

            return $settings;
        } catch (\Throwable) {
            return [];
        }
    }

    protected function normalizePhone(?string $phone): ?string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return null;
        }

        $digits = preg_replace('/[^0-9+]/', '', $phone) ?? $phone;

        if (preg_match('/^\+(\d{1,3})(\d+)$/', $digits, $m)) {
            return '+'.$m[1].'.'.$m[2];
        }

        return $digits;
    }

    protected function normalizeCountry(?string $country): string
    {
        $country = strtoupper(trim((string) $country));

        return strlen($country) === 2 ? $country : 'PL';
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
        return match ($status) {
            'registered' => 'active',
            'awaitingRegistration' => 'pending',
            'awaitingBooking' => 'pending',
            'expired' => 'expired',
            'booked' => 'active',
            'bookedExpired' => 'expired',
            default => $status,
        };
    }
}
