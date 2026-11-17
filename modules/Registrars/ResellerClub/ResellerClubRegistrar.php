<?php

namespace Modules\Registrars\ResellerClub;

use App\Contracts\RegistrarModuleInterface;
use App\Contracts\SyncsDomainData;
use App\Models\Domain;
use App\Models\RegistrarSettings;
use App\Support\RegistrantContact;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ResellerClubRegistrar implements RegistrarModuleInterface, SyncsDomainData
{
    protected string $apiUrl;

    protected string $resellerId;

    protected string $apiKey;

    public function __construct()
    {
        $settings = $this->loadSettings();
        $testMode = ($settings['test_mode'] ?? '1') === '1';
        $this->apiUrl = $testMode
            ? 'https://test.httpapi.com/api'
            : 'https://httpapi.com/api';
        $this->resellerId = $settings['reseller_id'] ?? '';
        $this->apiKey = $settings['api_key'] ?? '';
    }

    public function getModuleName(): string
    {
        return 'ResellerClub';
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'reseller_id', 'label' => 'Reseller ID', 'type' => 'text', 'required' => true],
            ['name' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true],
            ['name' => 'test_mode', 'label' => 'Test Mode', 'type' => 'yesno', 'default' => '1'],
        ];
    }

    public function register(Domain $domain, int $years, array $params = []): array
    {
        $missing = RegistrantContact::missing($params);
        if ($missing !== []) {
            return ['success' => false, 'message' => RegistrantContact::message($missing)];
        }

        // First, get/create customer
        $customerId = $this->getOrCreateCustomer($params);
        if (! $customerId) {
            return ['success' => false, 'message' => 'Failed to create/find customer at ResellerClub.'];
        }

        // ns is required for a registration; a placeholder would leave the
        // domain pointing nowhere, so no nameservers means no registration.
        $ns = $this->nameserversFrom($params['nameservers'] ?? [$params['ns1'] ?? null, $params['ns2'] ?? null]);
        if ($ns === []) {
            return ['success' => false, 'message' => 'No nameservers to register the domain with. Set default nameservers or the hosting server\'s.'];
        }

        $contacts = $this->contactsFor($domain->domain, $customerId, $params['contact_id'] ?? null);
        if (! $contacts) {
            return ['success' => false, 'message' => 'ResellerClub returned no default contact for the customer.'];
        }

        $response = $this->call('domains/register.json', [
            'domain-name' => $domain->domain,
            'years' => $years,
            'ns' => $ns,
            'customer-id' => $customerId,
            'reg-contact-id' => $contacts['reg'],
            'admin-contact-id' => $contacts['admin'],
            'tech-contact-id' => $contacts['tech'],
            'billing-contact-id' => $contacts['billing'],
            'invoice-option' => 'NoInvoice',
            'protect-privacy' => 'false',
            // Required by the API; renewals are this panel's job.
            'auto-renew' => 'false',
        ], 'POST');

        if (isset($response['status']) && $response['status'] === 'ERROR') {
            $error = $response['message'] ?? 'Registration failed';
            Log::error("ResellerClub register failed: {$error}");

            return ['success' => false, 'message' => $error];
        }

        $domain->update([
            'status' => 'active',
            'registrar' => 'ResellerClub',
            'registration_date' => now(),
            'expiry_date' => now()->addYears($years),
            'next_due_date' => now()->addYears($years),
        ]);

        return ['success' => true, 'message' => 'Domain registered via ResellerClub.'];
    }

    /**
     * domains/transfer.json requires the customer, the four contacts and
     * auto-renew (KB answer 758); ns and the auth code are optional.
     */
    public function transfer(Domain $domain, string $eppCode): array
    {
        $client = $domain->client;
        $params = array_filter([
            'firstname' => $client?->first_name, 'lastname' => $client?->last_name, 'email' => $client?->email,
            'phone' => $client?->phone_number, 'address' => $client?->address1, 'city' => $client?->city,
            'state' => $client?->state, 'postcode' => $client?->postcode, 'country' => $client?->country,
            'company' => $client?->company_name,
        ]);

        $missing = RegistrantContact::missing($params);
        if ($missing !== []) {
            return ['success' => false, 'message' => RegistrantContact::message($missing)];
        }

        $customerId = $this->getOrCreateCustomer($params);
        if (! $customerId) {
            return ['success' => false, 'message' => 'Failed to create/find customer at ResellerClub.'];
        }

        $contacts = $this->contactsFor($domain->domain, $customerId);
        if (! $contacts) {
            return ['success' => false, 'message' => 'ResellerClub returned no default contact for the customer.'];
        }

        $request = [
            'domain-name' => $domain->domain,
            'customer-id' => $customerId,
            'reg-contact-id' => $contacts['reg'],
            'admin-contact-id' => $contacts['admin'],
            'tech-contact-id' => $contacts['tech'],
            'billing-contact-id' => $contacts['billing'],
            'invoice-option' => 'NoInvoice',
            'auto-renew' => 'false',
        ];
        if (trim($eppCode) !== '') {
            $request['auth-code'] = $eppCode;
        }
        // Without nameservers of our own the domain keeps the ones it has.
        $ns = $this->nameserversFrom(json_decode((string) $domain->nameservers, true) ?: []);
        if ($ns !== []) {
            $request['ns'] = $ns;
        }

        $response = $this->call('domains/transfer.json', $request, 'POST');

        if (isset($response['status']) && $response['status'] === 'ERROR') {
            return ['success' => false, 'message' => $response['message'] ?? 'Transfer failed'];
        }

        $domain->update(['status' => 'Pending Transfer', 'registrar' => 'ResellerClub']);

        return ['success' => true, 'message' => 'Transfer initiated via ResellerClub.'];
    }

    public function renew(Domain $domain, int $years): array
    {
        $orderId = $this->getOrderId($domain->domain);
        if (! $orderId) {
            return ['success' => false, 'message' => 'Domain order not found at ResellerClub.'];
        }

        $response = $this->call('domains/renew.json', [
            'order-id' => $orderId,
            'years' => $years,
            'exp-date' => $domain->expiry_date?->timestamp ?? time(),
            'invoice-option' => 'NoInvoice',
        ], 'POST');

        if (isset($response['status']) && $response['status'] === 'ERROR') {
            return ['success' => false, 'message' => $response['message'] ?? 'Renewal failed'];
        }

        $newExpiry = ($domain->expiry_date ?? now())->addYears($years);
        $domain->update(['expiry_date' => $newExpiry, 'next_due_date' => $newExpiry]);

        return ['success' => true, 'message' => "Domain renewed for {$years} year(s)."];
    }

    public function getNameservers(Domain $domain): array
    {
        $orderId = $this->getOrderId($domain->domain);
        if (! $orderId) {
            return json_decode($domain->nameservers ?? '[]', true);
        }

        $response = $this->call('domains/details.json', ['order-id' => $orderId, 'options' => 'NsDetails']);

        // The nameservers come back as separate ns1, ns2, ... keys.
        $nameservers = [];
        for ($i = 1; $i <= 13; $i++) {
            if (! empty($response['ns'.$i]) && is_string($response['ns'.$i])) {
                $nameservers[] = strtolower($response['ns'.$i]);
            }
        }

        return $nameservers ?: (json_decode($domain->nameservers ?? '[]', true) ?: []);
    }

    public function saveNameservers(Domain $domain, array $nameservers): bool
    {
        $orderId = $this->getOrderId($domain->domain);
        if (! $orderId) {
            return false;
        }

        $response = $this->call('domains/modify-ns.json', [
            'order-id' => $orderId,
            'ns' => $nameservers,
        ], 'POST');

        if (! isset($response['status']) || $response['status'] !== 'ERROR') {
            $domain->update(['nameservers' => json_encode($nameservers)]);

            return true;
        }

        return false;
    }

    public function getEPPCode(Domain $domain): string
    {
        $orderId = $this->getOrderId($domain->domain);
        if (! $orderId) {
            return '(Not available)';
        }

        $response = $this->call('domains/details.json', ['order-id' => $orderId, 'options' => 'DomainSecretDetails']);

        return $response['domsecret'] ?? '(Not available)';
    }

    public function getLockStatus(Domain $domain): bool
    {
        $orderId = $this->getOrderId($domain->domain);
        if (! $orderId) {
            return true;
        }

        $response = $this->call('domains/details.json', ['order-id' => $orderId, 'options' => 'OrderDetails']);

        return ($response['orderstatus'][0] ?? '') === 'Active' && ($response['isOrderSuspendedUponExpiry'] ?? '') !== 'true';
    }

    public function toggleLock(Domain $domain, bool $lock): bool
    {
        $orderId = $this->getOrderId($domain->domain);
        if (! $orderId) {
            return false;
        }

        $endpoint = $lock ? 'domains/enable-theft-protection.json' : 'domains/disable-theft-protection.json';
        $response = $this->call($endpoint, ['order-id' => $orderId], 'POST');

        return ! isset($response['status']) || $response['status'] !== 'ERROR';
    }

    public function checkAvailability(string $domain): array
    {
        $parts = $this->splitDomain($domain);

        $response = $this->call('domains/available.json', [
            'domain-name' => [$parts['sld']],
            'tlds' => [$parts['tld']],
        ]);

        $key = "{$domain}";
        $status = $response[$key]['status'] ?? 'unknown';

        return [
            'available' => $status === 'available',
            'domain' => $domain,
            'method' => 'resellerclub_api',
        ];
    }

    protected function call(string $endpoint, array $params = [], string $method = 'GET'): array
    {
        $params['auth-userid'] = $this->resellerId;
        $params['api-key'] = $this->apiKey;

        $url = "{$this->apiUrl}/{$endpoint}";

        try {
            $response = $method === 'POST'
                ? Http::asForm()->timeout(30)->post($url, $params)
                : Http::timeout(30)->get($url, $params);

            if (! $response->successful()) {
                return ['status' => 'ERROR', 'message' => "HTTP {$response->status()}"];
            }

            $json = $response->json();

            // Most commands answer with an object, but some answer with a bare
            // value: domains/orderid.json returns the order id as a plain
            // integer. Wrap it so the array return type holds.
            return is_array($json) ? $json : ($json === null ? [] : ['value' => $json]);
        } catch (\Throwable $e) {
            Log::error("ResellerClub API error: {$e->getMessage()}");

            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    protected function getOrderId(string $domain): ?string
    {
        // Documented response: the order id as a bare integer.
        $response = $this->call('domains/orderid.json', ['domain-name' => $domain]);
        $id = $response['value'] ?? $response[0] ?? null;

        return is_numeric($id) ? (string) $id : null;
    }

    protected function getOrCreateCustomer(array $params): ?string
    {
        $email = $params['email'] ?? '';
        if (! $email) {
            return null;
        }

        // Try to find existing customer
        $response = $this->call('customers/details.json', ['username' => $email]);
        if (isset($response['customerid'])) {
            return (string) $response['customerid'];
        }

        // Create new customer
        [$phoneCc, $phone] = $this->splitPhone((string) ($params['phone'] ?? ''));

        $response = $this->call('customers/signup.json', [
            'username' => $email,
            'passwd' => bin2hex(random_bytes(8)),
            'name' => ($params['firstname'] ?? '').' '.($params['lastname'] ?? ''),
            'company' => $params['company'] ?? 'N/A',
            'address-line-1' => $params['address'] ?? '',
            'city' => $params['city'] ?? '',
            'state' => $params['state'] ?? '',
            'country' => $params['country'] ?? '',
            'zipcode' => $params['postcode'] ?? '',
            'phone-cc' => $phoneCc,
            'phone' => $phone,
            'lang-pref' => 'en',
        ], 'POST');

        // The new customer's id comes back as a bare integer, like orderid.json.
        $id = $response['value'] ?? $response['customerid'] ?? null;

        return is_numeric($id) ? (string) $id : null;
    }

    /**
     * The four contact ids for a domain: the customer's default contacts
     * (contacts/default.json, a POST per KB answer 794, answering per type
     * with registrantContactDetails, adminContactDetails, ... each carrying
     * entity.entityid), or the one id the caller passed for all four. A
     * customer id is never a contact id, so nothing found means null.
     *
     * Some registries have no admin, tech or billing contact; the API wants
     * -1 for those (KB answer 758).
     *
     * @return array{reg: string, admin: string, tech: string, billing: string}|null
     */
    protected function contactsFor(string $domain, string $customerId, ?string $contactId = null): ?array
    {
        $tld = strtolower((string) substr(strrchr($domain, '.') ?: '', 1));

        if ($contactId) {
            $ids = ['reg' => $contactId, 'admin' => $contactId, 'tech' => $contactId, 'billing' => $contactId];
        } else {
            $type = $tld === 'uk' ? 'UkContact' : 'Contact';
            $response = $this->call('contacts/default.json', ['customer-id' => $customerId, 'type' => $type], 'POST');
            $set = is_array($response[$type] ?? null) ? $response[$type] : $response;

            $id = function (string $role) use ($set): ?string {
                $details = $set[$role] ?? null;
                $value = is_array($details) ? ($details['entity.entityid'] ?? $details['entityid'] ?? $details['contactid'] ?? null) : null;

                return is_numeric($value) ? (string) $value : null;
            };

            $reg = $id('registrantContactDetails');
            if (! $reg) {
                return null;
            }
            $ids = [
                'reg' => $reg,
                'admin' => $id('adminContactDetails') ?? $reg,
                'tech' => $id('techContactDetails') ?? $reg,
                'billing' => $id('billingContactDetails') ?? $reg,
            ];
        }

        if (in_array($tld, ['uk', 'ru', 'eu'], true)) {
            $ids['admin'] = '-1';
        }
        if (in_array($tld, ['uk', 'ru'], true)) {
            $ids['tech'] = '-1';
        }
        if (in_array($tld, ['uk', 'ru', 'at', 'berlin', 'ca', 'nl', 'nz'], true)) {
            $ids['billing'] = '-1';
        }

        return $ids;
    }

    /** @return list<string> */
    protected function nameserversFrom(mixed $list): array
    {
        return array_values(array_filter(array_map(
            fn ($ns) => strtolower(trim((string) $ns)),
            is_array($list) ? $list : []
        )));
    }

    /**
     * "+44.2079460000" or "+44 20 7946 0000" into the country code and the
     * number the signup call takes separately.
     *
     * @return array{0: string, 1: string}
     */
    protected function splitPhone(string $phone): array
    {
        if (preg_match('/^\+(\d{1,3})[.\s-]*(.+)$/', trim($phone), $m)) {
            return [$m[1], preg_replace('/\D/', '', $m[2])];
        }

        return ['1', preg_replace('/\D/', '', $phone)];
    }

    protected function splitDomain(string $domain): array
    {
        $parts = explode('.', $domain, 2);

        return ['sld' => $parts[0] ?? '', 'tld' => $parts[1] ?? ''];
    }

    protected function loadSettings(): array
    {
        try {
            $rows = RegistrarSettings::where('registrar', 'resellerclub')->get();
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row->setting] = $row->value;
            }

            return $settings;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * LogicBoxes/ResellerClub exposes order state through
     * domains/details-by-name.json (options=All): endtime is the expiry as a
     * unix timestamp, currentstatus is the order state and orderstatus carries
     * the lock flags (resellerlock / transferlock).
     *
     * Verified against the LogicBoxes HTTP API command list
     * (github.com/phillipsdata/logicboxes, commands/logicboxes_domains.php).
     */
    public function syncDomain(Domain $domain): array
    {
        $response = $this->call('domains/details-by-name.json', [
            'domain-name' => $domain->domain,
            'options' => 'All',
        ]);

        if (($response['status'] ?? '') === 'ERROR' || isset($response['message'])) {
            return ['success' => false, 'message' => $response['message'] ?? 'ResellerClub lookup failed.'];
        }

        $expiry = null;
        if (! empty($response['endtime']) && is_numeric($response['endtime'])) {
            $expiry = Carbon::createFromTimestamp((int) $response['endtime'])->toDateString();
        }

        $orderStatus = (array) ($response['orderstatus'] ?? []);
        $current = strtolower((string) ($response['currentstatus'] ?? ''));

        $nameservers = [];
        for ($i = 1; $i <= 5; $i++) {
            if (! empty($response['ns'.$i])) {
                $nameservers[] = strtolower((string) $response['ns'.$i]);
            }
        }

        return [
            'success' => true,
            'expiry_date' => $expiry,
            'status' => $current === 'active' ? 'active' : ($current ?: null),
            'locked' => in_array('resellerlock', $orderStatus, true) || in_array('transferlock', $orderStatus, true),
            'nameservers' => $nameservers,
        ];
    }
}
