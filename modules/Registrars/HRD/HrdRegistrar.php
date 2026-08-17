<?php

namespace Modules\Registrars\HRD;

use App\Contracts\RegistrarModuleInterface;
use App\Contracts\SyncsDomainData;
use App\Models\Domain;
use App\Models\RegistrarSettings;
use App\Models\Setting;
use App\Support\MapsClientFields;
use Carbon\Carbon;
use HRDBase\Api\HRDApi;
use Illuminate\Support\Facades\Log;

/**
 * HRD (hrd.pl) domain registrar.
 *
 * HRD exposes a PHP SDK (hrd/hrd-api) that speaks to api.hrd.pl over a raw
 * socket. The SDK is pulled in through Composer as a VCS repository, so this
 * module only wires the SDK's HRDApi class to the registrar contract.
 *
 * The panel does not store a PESEL, so a person registrant is created with the
 * client's NIP (`tax_id`) when present. Domains are registered asynchronously:
 * domainCreate() returns an action id which can be followed with actionInfo().
 */
class HrdRegistrar implements RegistrarModuleInterface, SyncsDomainData
{
    use MapsClientFields;

    protected ?HRDApi $api = null;

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
     * Verify the credentials and reach the HRD API. Used by the "Test"
     * button on the registrars screen.
     */
    public function testConnection(): array
    {
        try {
            $balance = $this->api()->partnerGetBalance();

            return [
                'success' => true,
                'message' => 'Połączenie z HRD działa. Saldo: ' . ($balance['balance'] ?? 'n/d'),
            ];
        } catch (\Throwable $e) {
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

            $actionId = $this->api()->domainCreate($domain->domain, $userId, $ns, max(1, $years), false);

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
            $actionId = $this->api()->domainTransfer($domain->domain, $userId, $eppCode, 0);

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
            $actionId = $this->api()->domainRenew($domain->domain, $currentExpiry, max(1, $years));

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
            $info = $this->api()->domainInfo($domain->domain);
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
            $this->api()->domainUpdate($domain->domain, $ns);
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
            return $this->api()->domainTradeGetPw($domain->domain) ?: '(unavailable)';
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
            foreach ($this->api()->domainCheck([$domain]) as $name => $state) {
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
            $info = $this->api()->domainInfo($domain->domain);

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
     * Instantiate the HRD SDK once per request.
     */
    protected function api(): HRDApi
    {
        if ($this->api !== null) {
            return $this->api;
        }

        $this->api = HRDApi::getInstance([
            'apiHash' => (string) ($this->settings['api_hash'] ?? ''),
            'apiLogin' => (string) ($this->settings['api_login'] ?? ''),
            'apiPass' => (string) ($this->settings['api_pass'] ?? ''),
        ]);

        return $this->api;
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

        // A person registrant needs a PESEL; a company needs its NIP.
        $idNumber = $isCompany
            ? (string) ($client->tax_id ?? '')
            : ($this->resolveClientField($client, $this->settings['pesel_field'] ?? null, ['pesel', 'tax_id']) ?? '');

        $userId = $this->api()->userCreate(
            $isCompany ? HRDApi::COMPANY : HRDApi::PERSON,
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
