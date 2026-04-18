<?php

namespace Modules\Registrars\ResellerClub;

use App\Contracts\RegistrarModuleInterface;
use App\Models\Domain;
use App\Models\RegistrarSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ResellerClubRegistrar implements RegistrarModuleInterface
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
        // First, get/create customer
        $customerId = $this->getOrCreateCustomer($params);
        if (!$customerId) {
            return ['success' => false, 'message' => 'Failed to create/find customer at ResellerClub.'];
        }

        $ns = array_filter([$params['ns1'] ?? 'ns1.example.com', $params['ns2'] ?? 'ns2.example.com']);

        $response = $this->call('domains/register.json', [
            'domain-name' => $domain->domain,
            'years' => $years,
            'ns' => $ns,
            'customer-id' => $customerId,
            'reg-contact-id' => $params['contact_id'] ?? $this->getDefaultContact($customerId),
            'admin-contact-id' => $params['contact_id'] ?? $this->getDefaultContact($customerId),
            'tech-contact-id' => $params['contact_id'] ?? $this->getDefaultContact($customerId),
            'billing-contact-id' => $params['contact_id'] ?? $this->getDefaultContact($customerId),
            'invoice-option' => 'NoInvoice',
            'protect-privacy' => 'false',
        ], 'POST');

        if (isset($response['status']) && $response['status'] === 'ERROR') {
            $error = $response['message'] ?? 'Registration failed';
            Log::error("ResellerClub register failed: {$error}");
            return ['success' => false, 'message' => $error];
        }

        $domain->update([
            'status' => 'Active',
            'registrar' => 'ResellerClub',
            'registration_date' => now(),
            'expiry_date' => now()->addYears($years),
            'next_due_date' => now()->addYears($years),
        ]);

        return ['success' => true, 'message' => 'Domain registered via ResellerClub.'];
    }

    public function transfer(Domain $domain, string $eppCode): array
    {
        $response = $this->call('domains/transfer.json', [
            'domain-name' => $domain->domain,
            'auth-code' => $eppCode,
            'ns' => ['ns1.example.com', 'ns2.example.com'],
            'invoice-option' => 'NoInvoice',
        ], 'POST');

        if (isset($response['status']) && $response['status'] === 'ERROR') {
            return ['success' => false, 'message' => $response['message'] ?? 'Transfer failed'];
        }

        $domain->update(['status' => 'Pending Transfer', 'registrar' => 'ResellerClub']);
        return ['success' => true, 'message' => 'Transfer initiated via ResellerClub.'];
    }

    public function renew(Domain $domain, int $years): array
    {
        $orderId = $this->getOrderId($domain->domain);
        if (!$orderId) {
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
        if (!$orderId) {
            return json_decode($domain->nameservers ?? '[]', true);
        }

        $response = $this->call('domains/details.json', ['order-id' => $orderId, 'options' => 'NsDetails']);

        return $response['ns1'] ?? json_decode($domain->nameservers ?? '[]', true);
    }

    public function saveNameservers(Domain $domain, array $nameservers): bool
    {
        $orderId = $this->getOrderId($domain->domain);
        if (!$orderId) {
            return false;
        }

        $response = $this->call('domains/modify-ns.json', [
            'order-id' => $orderId,
            'ns' => $nameservers,
        ], 'POST');

        if (!isset($response['status']) || $response['status'] !== 'ERROR') {
            $domain->update(['nameservers' => json_encode($nameservers)]);
            return true;
        }

        return false;
    }

    public function getEPPCode(Domain $domain): string
    {
        $orderId = $this->getOrderId($domain->domain);
        if (!$orderId) {
            return '(Not available)';
        }

        $response = $this->call('domains/details.json', ['order-id' => $orderId, 'options' => 'DomainSecretDetails']);

        return $response['domsecret'] ?? '(Not available)';
    }

    public function getLockStatus(Domain $domain): bool
    {
        $orderId = $this->getOrderId($domain->domain);
        if (!$orderId) {
            return true;
        }

        $response = $this->call('domains/details.json', ['order-id' => $orderId, 'options' => 'OrderDetails']);

        return ($response['orderstatus'][0] ?? '') === 'Active' && ($response['isOrderSuspendedUponExpiry'] ?? '') !== 'true';
    }

    public function toggleLock(Domain $domain, bool $lock): bool
    {
        $orderId = $this->getOrderId($domain->domain);
        if (!$orderId) {
            return false;
        }

        $endpoint = $lock ? 'domains/enable-theft-protection.json' : 'domains/disable-theft-protection.json';
        $response = $this->call($endpoint, ['order-id' => $orderId], 'POST');

        return !isset($response['status']) || $response['status'] !== 'ERROR';
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

            if (!$response->successful()) {
                return ['status' => 'ERROR', 'message' => "HTTP {$response->status()}"];
            }

            return $response->json() ?? [];
        } catch (\Throwable $e) {
            Log::error("ResellerClub API error: {$e->getMessage()}");
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    protected function getOrderId(string $domain): ?string
    {
        $response = $this->call('domains/orderid.json', ['domain-name' => $domain]);

        if (is_numeric($response[0] ?? null)) {
            return (string) $response[0];
        }

        // Response might be a direct number
        if (is_numeric($response)) {
            return (string) $response;
        }

        return null;
    }

    protected function getOrCreateCustomer(array $params): ?string
    {
        $email = $params['email'] ?? '';
        if (!$email) {
            return null;
        }

        // Try to find existing customer
        $response = $this->call('customers/details.json', ['username' => $email]);
        if (isset($response['customerid'])) {
            return (string) $response['customerid'];
        }

        // Create new customer
        $response = $this->call('customers/signup.json', [
            'username' => $email,
            'passwd' => bin2hex(random_bytes(8)),
            'name' => ($params['firstname'] ?? 'Admin') . ' ' . ($params['lastname'] ?? ''),
            'company' => $params['company'] ?? 'N/A',
            'address-line-1' => $params['address'] ?? '123 Main St',
            'city' => $params['city'] ?? 'Anytown',
            'state' => $params['state'] ?? 'CA',
            'country' => $params['country'] ?? 'US',
            'zipcode' => $params['postcode'] ?? '90210',
            'phone-cc' => '1',
            'phone' => $params['phone'] ?? '5555555555',
            'lang-pref' => 'en',
        ], 'POST');

        return isset($response['customerid']) ? (string) $response['customerid'] : null;
    }

    protected function getDefaultContact(string $customerId): string
    {
        $response = $this->call('contacts/default.json', ['customer-id' => $customerId, 'type' => 'Contact']);

        return (string) ($response['Contact']['contactid'] ?? $customerId);
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
}
