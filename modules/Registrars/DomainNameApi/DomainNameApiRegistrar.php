<?php

namespace Modules\Registrars\DomainNameApi;

use App\Contracts\RegistrarModuleInterface;
use App\Contracts\SyncsDomainData;
use App\Models\Domain;
use App\Models\RegistrarSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * DomainNameAPI (Atak Domain) reseller module.
 *
 * Talks to the REST API documented at api.domainresellerapi.com. Auth is a
 * reseller UUID plus an API key, both sent as headers on every call — there is
 * no session to keep. The OTE host mirrors production and takes the separate
 * test key, so test_mode swaps the host and nothing else.
 *
 * Availability comes from the registry rather than WHOIS, which is why this
 * module can answer for .dev and .app: Google Registry runs no port-43 WHOIS
 * server for them, so a whois-based lookup cannot.
 */
class DomainNameApiRegistrar implements RegistrarModuleInterface, SyncsDomainData
{
    /** Dialling codes for the countries this reseller actually sells into. */
    private const DIAL_CODES = [
        'TR' => '90',  'DE' => '49',  'GB' => '44',  'US' => '1',   'NL' => '31',
        'FR' => '33',  'AT' => '43',  'BE' => '32',  'CH' => '41',  'IT' => '39',
        'ES' => '34',  'AZ' => '994', 'CY' => '357', 'BG' => '359', 'RO' => '40',
        'RU' => '7',   'UA' => '380', 'SE' => '46',  'DK' => '45',  'NO' => '47',
        'PL' => '48',  'CA' => '1',   'AU' => '61',  'AE' => '971', 'SA' => '966',
    ];

    protected string $apiUrl;

    protected string $resellerId;

    protected string $apiKey;

    public function __construct()
    {
        $settings = $this->loadSettings();
        $testMode = ($settings['test_mode'] ?? '0') === '1';

        $this->apiUrl = $testMode
            ? 'https://ote.domainresellerapi.com/api/v1'
            : 'https://api.domainresellerapi.com/api/v1';

        $this->resellerId = $settings['reseller_id'] ?? '';
        $this->apiKey     = $testMode
            ? ($settings['api_key_test'] ?? $settings['api_key'] ?? '')
            : ($settings['api_key'] ?? '');
    }

    public function getModuleName(): string
    {
        return 'DomainNameAPI';
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'reseller_id', 'label' => 'Reseller ID (UUID)', 'type' => 'text', 'required' => true],
            ['name' => 'api_key', 'label' => 'API Key (live)', 'type' => 'password', 'required' => true],
            ['name' => 'api_key_test', 'label' => 'API Key (OTE / test)', 'type' => 'password', 'required' => false],
            ['name' => 'test_mode', 'label' => 'Test Mode (OTE)', 'type' => 'yesno', 'default' => '0'],
            ['name' => 'ns1', 'label' => 'Default Nameserver 1', 'type' => 'text', 'required' => false],
            ['name' => 'ns2', 'label' => 'Default Nameserver 2', 'type' => 'text', 'required' => false],
        ];
    }

    // ---------------------------------------------------------------- lookups

    public function checkAvailability(string $domain): array
    {
        $response = $this->call('POST', 'domains/bulk-search', [['domainName' => $domain]]);

        // The registry answers per domain; a transport failure has no infos at
        // all and must not be reported as "taken".
        $info = $response['infos'][0] ?? null;
        if (! is_array($info)) {
            return [
                'available' => false,
                'domain'    => $domain,
                'method'    => 'domainnameapi',
                'error'     => $response['_error'] ?? 'No answer from DomainNameAPI.',
            ];
        }

        return [
            'available'  => strtoupper((string) ($info['status'] ?? '')) === 'AVAILABLE',
            'domain'     => $domain,
            'method'     => 'domainnameapi',
            'price'      => $info['price'] ?? null,
            'currency'   => $info['currency'] ?? null,
            'premium'    => (bool) ($info['isPremium'] ?? false),
            'needs_docs' => (bool) ($info['isDocumentRequired'] ?? false),
            'reason'     => $info['reason'] ?? null,
        ];
    }

    public function syncDomain(Domain $domain): array
    {
        $info = $this->details($domain->domain);
        if ($info === null) {
            return ['success' => false, 'message' => 'Could not read the domain from DomainNameAPI.'];
        }

        return [
            'success'     => true,
            'expiry_date' => $this->parseDate($info['expirationDate'] ?? $info['expirationDateTime'] ?? null),
            'status'      => $info['status'] ?? null,
            'locked'      => $this->readLock($info),
            'nameservers' => $this->readNameservers($info),
        ];
    }

    // ----------------------------------------------------------- registration

    public function register(Domain $domain, int $years, array $params = []): array
    {
        $settings = $this->loadSettings();
        $contact  = $this->contactPayload($domain, $params);

        $nameservers = array_values(array_filter([
            $params['ns1'] ?? $settings['ns1'] ?? null,
            $params['ns2'] ?? $settings['ns2'] ?? null,
        ]));

        // The API takes one array of contacts, each tagged with its role -
        // not four separately named fields.
        $contacts = [];
        foreach (['Registrant', 'Administrative', 'Technical', 'Billing'] as $role) {
            $contacts[] = array_merge($contact, ['contactType' => $role]);
        }

        $response = $this->call('POST', 'domains/register-with-contacts', [
            'domainName'        => $domain->domain,
            'period'            => $years,
            'nameServers'       => $nameservers,
            'privacyProtection' => (bool) ($params['privacy'] ?? false),
            'lockStatus'        => true,
            'contacts'          => $contacts,
        ]);

        if (! $this->ok($response)) {
            $error = $this->errorOf($response, 'Registration failed');
            Log::error("DomainNameAPI register failed: {$error}", ['domain' => $domain->domain]);

            return ['success' => false, 'message' => $error];
        }

        $domain->update([
            'status'            => 'active',
            'registrar'         => 'DomainNameAPI',
            'registration_date' => now(),
            'expiry_date'       => now()->addYears($years),
            'next_due_date'     => now()->addYears($years),
        ]);

        return ['success' => true, 'message' => 'Domain registered via DomainNameAPI.'];
    }

    public function renew(Domain $domain, int $years): array
    {
        $response = $this->call('POST', 'domains/renew', [
            'domainName' => $domain->domain,
            'period'     => $years,
        ]);

        if (! $this->ok($response)) {
            return ['success' => false, 'message' => $this->errorOf($response, 'Renewal failed')];
        }

        return ['success' => true, 'message' => 'Domain renewed via DomainNameAPI.'];
    }

    public function transfer(Domain $domain, string $eppCode): array
    {
        // A transfer normally carries a year with it; an operator who ordered
        // more than one should get what was paid for rather than a hard 1.
        $period = max(1, (int) ($domain->registration_period ?: 1));

        $response = $this->call('POST', 'domains/transfer', [
            'domainName' => $domain->domain,
            'authCode'   => $eppCode,
            'period'     => $period,
        ]);

        if (! $this->ok($response)) {
            return ['success' => false, 'message' => $this->errorOf($response, 'Transfer failed')];
        }

        $domain->update(['status' => 'pending_transfer', 'registrar' => 'DomainNameAPI']);

        return ['success' => true, 'message' => 'Transfer started at DomainNameAPI.'];
    }

    // ------------------------------------------------------------ nameservers

    public function getNameservers(Domain $domain): array
    {
        $info = $this->details($domain->domain);
        if ($info === null) {
            // Fall back to what we last stored rather than blanking the form.
            return json_decode($domain->nameservers ?? '[]', true) ?: [];
        }

        return $this->readNameservers($info);
    }

    public function saveNameservers(Domain $domain, array $nameservers): bool
    {
        $response = $this->call('POST', 'domains/dns/name-server', [
            'domainName'  => $domain->domain,
            'nameServers' => array_values(array_filter($nameservers)),
        ]);

        return $this->ok($response);
    }

    // ------------------------------------------------------------ epp & locks

    public function getEPPCode(Domain $domain): string
    {
        $info = $this->details($domain->domain);

        return (string) ($info['authCode'] ?? $info['authcode'] ?? '(Not available)');
    }

    public function getLockStatus(Domain $domain): bool
    {
        $info = $this->details($domain->domain);
        if ($info === null) {
            // Assume locked: the safe answer when we cannot read the registry.
            return true;
        }

        return $this->readLock($info) ?? true;
    }

    public function toggleLock(Domain $domain, bool $lock): bool
    {
        $response = $this->call('POST', $lock ? 'domains/lock' : 'domains/unlock', [
            'domainName' => $domain->domain,
        ]);

        return $this->ok($response);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * The reseller's balance, for the dashboard and the low-balance warning.
     * Auto-renew silently stops working on an empty balance, so this is worth
     * surfacing rather than discovering through a failed renewal.
     */
    public function getBalance(): array
    {
        $response = $this->call('GET', 'deposit/accounts/me');

        return [
            'success'  => ! isset($response['_error']),
            'try'      => $response['tryBalance'] ?? null,
            'usd'      => $response['usdBalance'] ?? null,
            'group'    => $response['resellerGroupName'] ?? null,
            'name'     => $response['resellerName'] ?? null,
            'message'  => $response['_error'] ?? null,
        ];
    }

    protected function details(string $domain): ?array
    {
        $response = $this->call('GET', 'domains/info', ['domainName' => $domain]);
        if (! $this->ok($response)) {
            return null;
        }

        return $response['info'] ?? $response['domain'] ?? $response;
    }

    protected function readNameservers(array $info): array
    {
        $ns = $info['nameServers'] ?? $info['nameservers'] ?? [];

        return is_array($ns) ? array_values(array_filter($ns)) : [];
    }

    protected function readLock(array $info): ?bool
    {
        foreach (['lockStatus', 'locked', 'isLocked'] as $key) {
            if (array_key_exists($key, $info)) {
                return filter_var($info[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return null;
    }

    protected function parseDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * One contact in the shape DomainNameAPI validates against.
     *
     * The API names its fields in PascalCase and wants the dialling code apart
     * from the number; sending camelCase keys, as this did, meant every field
     * arrived empty and registration was refused on validation.
     */
    protected function contactPayload(Domain $domain, array $params): array
    {
        $client = $domain->client ?? null;

        // Callers spell these both ways; read either rather than silently
        // falling through to a blank.
        $pick = function (array $keys, $fallback = '') use ($params, $client) {
            foreach ($keys as $key) {
                if (! empty($params[$key])) {
                    return (string) $params[$key];
                }
            }

            return (string) ($fallback ?? '');
        };

        $country = strtoupper($pick(['country'], $client->country ?? 'TR')) ?: 'TR';
        [$dialCode, $number] = $this->splitPhone(
            $pick(['phone', 'phone_number'], $client->phone_number ?? ''),
            $country
        );

        return [
            'FirstName'        => $pick(['firstname', 'first_name'], $client->first_name ?? ''),
            'LastName'         => $pick(['lastname', 'last_name'], $client->last_name ?? ''),
            'Company'          => $pick(['company', 'company_name'], $client->company_name ?? ''),
            'EMail'            => $pick(['email'], $client->email ?? ''),
            'PhoneCountryCode' => $dialCode,
            'Phone'            => $number,
            'Address'          => $pick(['address', 'address1'], $client->address1 ?? ''),
            'City'             => $pick(['city'], $client->city ?? ''),
            'State'            => $pick(['state'], $client->state ?? '') ?: $pick(['city'], $client->city ?? ''),
            'PostalCode'       => $pick(['postcode', 'zip', 'zipcode'], $client->postcode ?? ''),
            'Country'          => $country,
        ];
    }

    /**
     * The dialling code and the rest of the number, kept apart.
     *
     * @return array{0: string, 1: string}
     */
    protected function splitPhone(string $raw, string $country): array
    {
        $raw = trim($raw);

        // Written international: the leading digits are the dialling code.
        if (str_starts_with($raw, '+')) {
            $digits = preg_replace('/\D/', '', $raw);

            foreach ([3, 2, 1] as $length) {
                $candidate = substr($digits, 0, $length);

                if (in_array($candidate, self::DIAL_CODES, true)) {
                    return [$candidate, substr($digits, $length)];
                }
            }

            // Unknown code: two digits is the common case.
            return [substr($digits, 0, 2), substr($digits, 2)];
        }

        $digits = preg_replace('/\D/', '', $raw);
        $code = self::DIAL_CODES[$country] ?? '90';

        // A local number written with its trunk zero: drop it.
        if (str_starts_with($digits, '0')) {
            $digits = ltrim($digits, '0');
        }

        return [$code, $digits];
    }

    /**
     * A response is only a success when the API says so. Transport failures are
     * tagged with _error so callers can tell "the registry said no" apart from
     * "we never reached the registry".
     */
    protected function ok(array $response): bool
    {
        if (isset($response['_error'])) {
            return false;
        }

        if (array_key_exists('success', $response)) {
            return (bool) $response['success'];
        }

        return true;
    }

    protected function errorOf(array $response, string $fallback): string
    {
        return (string) ($response['_error']
            ?? $response['operationMessage']
            ?? $response['reason']
            ?? $fallback);
    }

    protected function call(string $method, string $endpoint, array $data = []): array
    {
        if ($this->resellerId === '' || $this->apiKey === '') {
            return ['_error' => 'DomainNameAPI credentials are not configured.'];
        }

        try {
            $request = Http::timeout(30)
                ->withHeaders([
                    'X-API-KEY'  => $this->apiKey,
                    '__reseller' => $this->resellerId,
                    'Accept'     => 'application/json',
                ]);

            $url = "{$this->apiUrl}/{$endpoint}";

            $response = $method === 'GET'
                ? $request->get($url, $data)
                : $request->send($method, $url, ['json' => $data]);

            $body = $response->json();
            if (! is_array($body)) {
                return ['_error' => "Unreadable response from DomainNameAPI (HTTP {$response->status()})."];
            }

            // 404 on domains/info means "not registered here", which callers
            // read from success=false — it is not a transport error.
            if ($response->failed() && ! array_key_exists('success', $body)) {
                // The registry's own words, whichever field it used. A bare
                // status code tells an operator nothing about why a paid-for
                // registration was refused.
                // The error may be a string or a nested object; flatten
                // whatever shape it arrives in into one readable sentence.
                $reason = null;
                foreach (['operationMessage', 'message', 'error', 'detail', 'title'] as $field) {
                    $candidate = $body[$field] ?? null;

                    if (is_string($candidate) && $candidate !== '') {
                        $reason = $candidate;
                        break;
                    }

                    if (is_array($candidate)) {
                        $parts = array_filter([
                            $candidate['message'] ?? null,
                            $candidate['details'] ?? null,
                        ], 'is_string');

                        if ($parts) {
                            $reason = implode(' — ', $parts);
                            break;
                        }
                    }
                }

                if ($reason === null && isset($body['errors']) && is_array($body['errors'])) {
                    $flat = [];
                    array_walk_recursive($body['errors'], function ($v) use (&$flat) {
                        if (is_scalar($v)) {
                            $flat[] = (string) $v;
                        }
                    });
                    $reason = $flat ? implode(' ', array_slice($flat, 0, 4)) : null;
                }

                Log::warning('DomainNameAPI refused a call', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => mb_substr(json_encode($body, JSON_UNESCAPED_UNICODE), 0, 600),
                ]);

                return ['_error' => $reason !== null
                    ? "DomainNameAPI: {$reason} (HTTP {$response->status()})"
                    : "DomainNameAPI returned HTTP {$response->status()}."];
            }

            return $body;
        } catch (\Throwable $e) {
            Log::error('DomainNameAPI call failed', ['endpoint' => $endpoint, 'message' => $e->getMessage()]);

            return ['_error' => 'Could not reach DomainNameAPI: '.$e->getMessage()];
        }
    }

    protected function loadSettings(): array
    {
        try {
            $rows = RegistrarSettings::where('registrar', 'domainnameapi')->get();
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row->setting] = $row->value;
            }

            return $settings;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
