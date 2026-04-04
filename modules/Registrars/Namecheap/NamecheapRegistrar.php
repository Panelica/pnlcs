<?php

namespace Modules\Registrars\Namecheap;

use App\Contracts\RegistrarModuleInterface;
use App\Models\Domain;
use App\Models\RegistrarSettings;
use Illuminate\Support\Facades\Log;

class NamecheapRegistrar implements RegistrarModuleInterface
{
    protected string $apiUrl;
    protected string $apiUser;
    protected string $apiKey;
    protected string $clientIp;

    public function __construct()
    {
        $settings = $this->loadSettings();
        $testMode = ($settings['test_mode'] ?? '1') === '1';
        $this->apiUrl = $testMode
            ? 'https://api.sandbox.namecheap.com/xml.response'
            : 'https://api.namecheap.com/xml.response';
        $this->apiUser = $settings['api_user'] ?? '';
        $this->apiKey = $settings['api_key'] ?? '';
        $this->clientIp = $settings['client_ip'] ?? request()->ip();
    }

    public function getModuleName(): string
    {
        return 'Namecheap';
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'api_user', 'label' => 'API Username', 'type' => 'text', 'required' => true],
            ['name' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true],
            ['name' => 'client_ip', 'label' => 'Whitelisted IP', 'type' => 'text', 'required' => true],
            ['name' => 'test_mode', 'label' => 'Sandbox Mode', 'type' => 'yesno', 'default' => '1'],
        ];
    }

    public function register(Domain $domain, int $years, array $params = []): array
    {
        $parts = $this->splitDomain($domain->domain);

        $response = $this->call('namecheap.domains.create', [
            'DomainName' => $domain->domain,
            'Years' => $years,
            'RegistrantFirstName' => $params['firstname'] ?? 'Admin',
            'RegistrantLastName' => $params['lastname'] ?? 'Admin',
            'RegistrantAddress1' => $params['address'] ?? '123 Main St',
            'RegistrantCity' => $params['city'] ?? 'Anytown',
            'RegistrantStateProvince' => $params['state'] ?? 'CA',
            'RegistrantPostalCode' => $params['postcode'] ?? '90210',
            'RegistrantCountry' => $params['country'] ?? 'US',
            'RegistrantPhone' => $params['phone'] ?? '+1.5555555555',
            'RegistrantEmailAddress' => $params['email'] ?? '',
            // Tech/Admin/AuxBilling = same as Registrant
            'TechFirstName' => $params['firstname'] ?? 'Admin',
            'TechLastName' => $params['lastname'] ?? 'Admin',
            'TechAddress1' => $params['address'] ?? '123 Main St',
            'TechCity' => $params['city'] ?? 'Anytown',
            'TechStateProvince' => $params['state'] ?? 'CA',
            'TechPostalCode' => $params['postcode'] ?? '90210',
            'TechCountry' => $params['country'] ?? 'US',
            'TechPhone' => $params['phone'] ?? '+1.5555555555',
            'TechEmailAddress' => $params['email'] ?? '',
            'AdminFirstName' => $params['firstname'] ?? 'Admin',
            'AdminLastName' => $params['lastname'] ?? 'Admin',
            'AdminAddress1' => $params['address'] ?? '123 Main St',
            'AdminCity' => $params['city'] ?? 'Anytown',
            'AdminStateProvince' => $params['state'] ?? 'CA',
            'AdminPostalCode' => $params['postcode'] ?? '90210',
            'AdminCountry' => $params['country'] ?? 'US',
            'AdminPhone' => $params['phone'] ?? '+1.5555555555',
            'AdminEmailAddress' => $params['email'] ?? '',
            'AuxBillingFirstName' => $params['firstname'] ?? 'Admin',
            'AuxBillingLastName' => $params['lastname'] ?? 'Admin',
            'AuxBillingAddress1' => $params['address'] ?? '123 Main St',
            'AuxBillingCity' => $params['city'] ?? 'Anytown',
            'AuxBillingStateProvince' => $params['state'] ?? 'CA',
            'AuxBillingPostalCode' => $params['postcode'] ?? '90210',
            'AuxBillingCountry' => $params['country'] ?? 'US',
            'AuxBillingPhone' => $params['phone'] ?? '+1.5555555555',
            'AuxBillingEmailAddress' => $params['email'] ?? '',
            'Nameservers' => implode(',', array_filter([$params['ns1'] ?? '', $params['ns2'] ?? ''])),
        ]);

        if ($response['success']) {
            $domain->update([
                'status' => 'Active',
                'registrar' => 'Namecheap',
                'registration_date' => now(),
                'expiry_date' => now()->addYears($years),
                'next_due_date' => now()->addYears($years),
            ]);
            return ['success' => true, 'message' => 'Domain registered via Namecheap.'];
        }

        return ['success' => false, 'message' => $response['error'] ?? 'Registration failed.'];
    }

    public function transfer(Domain $domain, string $eppCode): array
    {
        $response = $this->call('namecheap.domains.transfer.create', [
            'DomainName' => $domain->domain,
            'Years' => 1,
            'EPPCode' => $eppCode,
        ]);

        if ($response['success']) {
            $domain->update(['status' => 'Pending Transfer', 'registrar' => 'Namecheap']);
            return ['success' => true, 'message' => 'Transfer initiated via Namecheap.'];
        }

        return ['success' => false, 'message' => $response['error'] ?? 'Transfer failed.'];
    }

    public function renew(Domain $domain, int $years): array
    {
        $response = $this->call('namecheap.domains.renew', [
            'DomainName' => $domain->domain,
            'Years' => $years,
        ]);

        if ($response['success']) {
            $newExpiry = ($domain->expiry_date ?? now())->addYears($years);
            $domain->update(['expiry_date' => $newExpiry, 'next_due_date' => $newExpiry]);
            return ['success' => true, 'message' => "Domain renewed for {$years} year(s)."];
        }

        return ['success' => false, 'message' => $response['error'] ?? 'Renewal failed.'];
    }

    public function getNameservers(Domain $domain): array
    {
        $response = $this->call('namecheap.domains.dns.getList', [
            'SLD' => $this->splitDomain($domain->domain)['sld'],
            'TLD' => $this->splitDomain($domain->domain)['tld'],
        ]);

        return $response['nameservers'] ?? json_decode($domain->nameservers ?? '[]', true);
    }

    public function saveNameservers(Domain $domain, array $nameservers): bool
    {
        $parts = $this->splitDomain($domain->domain);

        $response = $this->call('namecheap.domains.dns.setCustom', [
            'SLD' => $parts['sld'],
            'TLD' => $parts['tld'],
            'Nameservers' => implode(',', $nameservers),
        ]);

        if ($response['success']) {
            $domain->update(['nameservers' => json_encode($nameservers)]);
            return true;
        }

        return false;
    }

    public function getEPPCode(Domain $domain): string
    {
        // Namecheap doesn't have a direct API for EPP code retrieval
        // It's sent via email when transfer lock is disabled
        return '(Sent to registrant email)';
    }

    public function getLockStatus(Domain $domain): bool
    {
        $response = $this->call('namecheap.domains.getRegistrarLock', [
            'DomainName' => $domain->domain,
        ]);

        return $response['locked'] ?? true;
    }

    public function toggleLock(Domain $domain, bool $lock): bool
    {
        $response = $this->call('namecheap.domains.setRegistrarLock', [
            'DomainName' => $domain->domain,
            'LockAction' => $lock ? 'LOCK' : 'UNLOCK',
        ]);

        return $response['success'] ?? false;
    }

    public function checkAvailability(string $domain): array
    {
        $response = $this->call('namecheap.domains.check', [
            'DomainList' => $domain,
        ]);

        return [
            'available' => $response['available'] ?? false,
            'domain' => $domain,
            'method' => 'namecheap_api',
        ];
    }

    protected function call(string $command, array $params = []): array
    {
        $queryParams = array_merge([
            'ApiUser' => $this->apiUser,
            'ApiKey' => $this->apiKey,
            'UserName' => $this->apiUser,
            'ClientIp' => $this->clientIp,
            'Command' => $command,
        ], $params);

        $url = $this->apiUrl . '?' . http_build_query($queryParams);

        $ctx = stream_context_create([
            'http' => ['timeout' => 30, 'method' => 'GET'],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            return ['success' => false, 'error' => 'Could not connect to Namecheap API'];
        }

        return $this->parseResponse($raw, $command);
    }

    protected function parseResponse(string $xml, string $command): array
    {
        try {
            $doc = simplexml_load_string($xml);
            if ($doc === false) {
                return ['success' => false, 'error' => 'Invalid XML from Namecheap'];
            }

            $status = (string) ($doc['Status'] ?? 'ERROR');

            if ($status === 'ERROR') {
                $errors = $doc->Errors->Error ?? [];
                $errorMsg = '';
                foreach ($errors as $err) {
                    $errorMsg .= (string) $err . ' ';
                }
                return ['success' => false, 'error' => trim($errorMsg) ?: 'Unknown Namecheap error'];
            }

            $result = ['success' => true];

            // Parse domain check response
            if (str_contains($command, 'domains.check')) {
                $domainResult = $doc->CommandResponse->DomainCheckResult ?? null;
                if ($domainResult) {
                    $result['available'] = strtolower((string) ($domainResult['Available'] ?? 'false')) === 'true';
                }
            }

            // Parse nameserver list
            if (str_contains($command, 'dns.getList')) {
                $nsList = $doc->CommandResponse->DomainDNSGetListResult->Nameserver ?? [];
                $ns = [];
                foreach ($nsList as $nsItem) {
                    $ns[] = (string) $nsItem;
                }
                $result['nameservers'] = $ns;
            }

            // Parse registrar lock
            if (str_contains($command, 'getRegistrarLock')) {
                $lockResult = $doc->CommandResponse->DomainGetRegistrarLockResult ?? null;
                $result['locked'] = strtolower((string) ($lockResult['RegistrarLockStatus'] ?? 'true')) === 'true';
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error("Namecheap XML parse error: {$e->getMessage()}");
            return ['success' => false, 'error' => 'XML parse error'];
        }
    }

    protected function splitDomain(string $domain): array
    {
        $parts = explode('.', $domain, 2);
        return ['sld' => $parts[0] ?? '', 'tld' => $parts[1] ?? ''];
    }

    protected function loadSettings(): array
    {
        try {
            $rows = RegistrarSettings::where('registrar', 'namecheap')->get();
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
