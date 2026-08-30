<?php

namespace Modules\Registrars\HRD;

use App\Contracts\RegistrarModuleInterface;
use App\Contracts\SyncsDomainData;
use App\Models\Domain;
use App\Models\ModuleLog;
use App\Models\RegistrarSettings;
use App\Models\Setting;
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
 * client's PESEL (custom field) or NIP (`tax_id`) as appropriate. Domains are
 * registered asynchronously: domainCreate() returns an action id which can be
 * followed with actionInfo().
 */
class HrdRegistrar implements RegistrarModuleInterface, SyncsDomainData
{
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
            ['name' => 'pesel_field', 'label' => 'PESEL field', 'type' => 'select', 'options' => $fieldOptions, 'required' => false],
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

            $this->logAction('test', [], ['success' => true, 'balance' => $balance['balance'] ?? null]);

            return [
                'success' => true,
                'message' => 'Połączenie z HRD działa. Saldo: ' . ($balance['balance'] ?? 'n/d'),
            ];
        } catch (\Throwable $e) {
            $this->logAction('test', [], ['success' => false, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
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

            $this->logAction('register', ['domain' => $domain->domain, 'years' => $years], ['success' => true, 'action_id' => $actionId]);

            return ['success' => true, 'message' => "Domain registered via HRD (action #{$actionId})."];
        } catch (\Throwable $e) {
            Log::error("HRD register failed for {$domain->domain}: {$e->getMessage()}");

            $this->logAction('register', ['domain' => $domain->domain, 'years' => $years], ['success' => false, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function transfer(Domain $domain, string $eppCode): array
    {
        try {
            $userId = $this->createUser($domain);
            $actionId = $this->client()->domainTransfer($domain->domain, $userId, $eppCode, 0);

            $domain->update(['status' => 'pending_transfer', 'registrar' => 'hrd']);

            $this->logAction('transfer', ['domain' => $domain->domain], ['success' => true, 'action_id' => $actionId]);

            return ['success' => true, 'message' => "Transfer initiated via HRD (action #{$actionId})."];
        } catch (\Throwable $e) {
            Log::error("HRD transfer failed for {$domain->domain}: {$e->getMessage()}");

            $this->logAction('transfer', ['domain' => $domain->domain], ['success' => false, 'error' => $e->getMessage()]);

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

            $this->logAction('renew', ['domain' => $domain->domain, 'years' => $years], ['success' => true, 'action_id' => $actionId]);

            return ['success' => true, 'message' => "Domain renewed for {$years} year(s) (action #{$actionId})."];
        } catch (\Throwable $e) {
            Log::error("HRD renew failed for {$domain->domain}: {$e->getMessage()}");

            $this->logAction('renew', ['domain' => $domain->domain, 'years' => $years], ['success' => false, 'error' => $e->getMessage()]);

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

            $this->logAction('updateNameservers', ['domain' => $domain->domain, 'nameservers' => array_values(array_filter($nameservers))], ['success' => true]);

            return true;
        } catch (\Throwable $e) {
            Log::error("HRD saveNameservers failed for {$domain->domain}: {$e->getMessage()}");

            $this->logAction('updateNameservers', ['domain' => $domain->domain], ['success' => false, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function getEPPCode(Domain $domain): string
    {
        try {
            $code = $this->client()->domainTradeGetPw($domain->domain) ?: '(unavailable)';

            $this->logAction('getEPPCode', ['domain' => $domain->domain], ['success' => $code !== '(unavailable)']);

            return $code;
        } catch (\Throwable $e) {
            Log::warning("HRD getEPPCode failed for {$domain->domain}: {$e->getMessage()}");

            $this->logAction('getEPPCode', ['domain' => $domain->domain], ['success' => false, 'error' => $e->getMessage()]);

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

            $result = [
                'available' => $status === 'available' || $status === 'createOnly',
                'domain' => $domain,
                'method' => 'hrd_api',
            ];

            $this->logAction('checkAvailability', ['domain' => $domain], ['available' => $result['available'], 'state' => $status]);

            return $result;
        } catch (\Throwable $e) {
            Log::warning("HRD checkAvailability failed for {$domain}: {$e->getMessage()}");

            $this->logAction('checkAvailability', ['domain' => $domain], ['success' => false, 'error' => $e->getMessage()]);

            return ['available' => false, 'domain' => $domain, 'method' => 'hrd_api', 'error' => $e->getMessage()];
        }
    }

    public function syncDomain(Domain $domain): array
    {
        try {
            $info = $this->client()->domainInfo($domain->domain);

            $result = [
                'success' => true,
                'expiry_date' => $this->parseDate($info['exDate'] ?? null),
                'status' => $this->mapStatus($info['status'] ?? null),
                'locked' => true,
                'nameservers' => array_map(fn ($entry) => (string) ($entry['name'] ?? ''), (array) ($info['ns'] ?? [])),
            ];

            $this->logAction('sync', ['domain' => $domain->domain], ['success' => true, 'expiry_date' => $result['expiry_date'], 'status' => $result['status']]);

            return $result;
        } catch (\Throwable $e) {
            $this->logAction('sync', ['domain' => $domain->domain], ['success' => false, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Record an HRD action in the System Logs → Module viewer. Sensitive data
     * (EPP/auth codes) is never written here.
     */
    protected function logAction(string $action, array $request = [], array|string|null $response = null): void
    {
        try {
            ModuleLog::create([
                'module' => 'HRD',
                'action' => $action,
                'request' => json_encode($request, JSON_UNESCAPED_SLASHES),
                'response' => is_string($response) ? $response : json_encode($response, JSON_UNESCAPED_SLASHES),
            ]);
        } catch (\Throwable $e) {
            Log::warning("HRD module log failed: {$e->getMessage()}");
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
     * created. PESEL/NIP come from the mapped field (config) or auto-detected
     * from the client's attributes and custom fields.
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

        $isCompany = filled($client->company_name);
        $name = $isCompany
            ? $client->company_name
            : trim(($client->first_name ?? '').' '.($client->last_name ?? ''));

        $phone = $this->normalizePhone($params['phone'] ?? $client->full_phone);

        // A person registrant needs a PESEL; a company needs its NIP. The two
        // are never interchangeable, so a person never falls back to tax_id.
        $idNumber = $isCompany
            ? (string) ($client->tax_id ?? '')
            : ($this->resolveClientField($client, $this->settings['pesel_field'] ?? null, ['pesel']) ?? '');

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
        $ns = [];

        for ($i = 1; $i <= 5; $i++) {
            $value = trim((string) Setting::get('DefaultNameserver'.$i, ''));
            if ($value !== '') {
                $ns[] = $value;
            }
        }

        return $ns;
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
