<?php

namespace Modules\Servers\Plesk;

use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Servers\AbstractServerModule;

/**
 * Plesk Obsidian REST API (/api/v2) module.
 *
 * Endpoints verified against the official openapi.yml:
 *  - POST   /clients                      create customer
 *  - POST   /domains                      create subscription (hosting_type=virtual,
 *                                         hosting_settings.ftp_login/ftp_password REQUIRED,
 *                                         owner_client assigns the customer)
 *  - PUT    /clients/{id}/suspend        suspend account
 *  - PUT    /clients/{id}/activate       reactivate account
 *  - DELETE /clients/{id}                 remove customer (cascades subscriptions)
 *  - PUT    /clients/{id}                 update (password, ...)
 *  - PUT    /domains/{id}                 update (plan change)
 *  - GET    /clients/{id}/statistics      disk/traffic usage
 */
class PleskModule extends AbstractServerModule
{
    public function getModuleName(): string
    {
        return 'plesk';
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'api_key', 'label' => 'Plesk API Secret Key', 'type' => 'password'],
        ];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function baseUrl(Server $server): string
    {
        $port = $server->port ?: 8443;

        return "https://{$this->serverHost($server)}:{$port}/api/v2";
    }

    /**
     * Plesk authenticates with an API key or with an administrator login, and
     * the server form asks for a username and a password while calling the key
     * "Optional". Sending only the key meant an operator who filled the form in
     * the ordinary way was never authenticated at all.
     */
    private function http(Server $server)
    {
        $request = Http::withoutVerifying()
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->timeout(30);

        $key = trim((string) $server->access_hash);

        if ($key !== '') {
            return $request->withHeaders(['X-API-Key' => $key]);
        }

        return $request->withBasicAuth(
            (string) ($server->username ?: 'admin'),
            (string) $server->password
        );
    }

    /**
     * A password Plesk will take.
     *
     * The API documents a client password as 5 to 14 characters; this module
     * generated 21, which Plesk is entitled to refuse outright.
     */
    private function generatePassword(): string
    {
        $lower = 'abcdefghijkmnopqrstuvwxyz';
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits = '23456789';
        $alphabet = $lower.$upper.$digits;

        $password = [
            $lower[random_int(0, strlen($lower) - 1)],
            $upper[random_int(0, strlen($upper) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
        ];

        for ($i = 0; $i < 9; $i++) {
            $password[] = $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        shuffle($password);

        return implode('', $password);
    }

    /**
     * The service plans this Plesk server offers, for the product form.
     *
     * The REST API has no plans endpoint at all - the list comes from the XML
     * API, which is where WHMCS reads it too.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function listPackages(Server $server): array
    {
        $key = trim((string) $server->access_hash);

        $headers = $key !== ''
            ? ['X-API-Key' => $key]
            : [
                'HTTP_AUTH_LOGIN' => (string) ($server->username ?: 'admin'),
                'HTTP_AUTH_PASSWD' => (string) $server->password,
            ];

        try {
            $port = (int) ($server->port ?: 8443);
            $url = "https://{$this->serverHost($server)}:{$port}/enterprise/control/agent.php";

            $response = Http::withoutVerifying()
                ->withHeaders($headers)
                ->timeout(30)
                ->withBody('<packet><service-plan><get><filter/></get></service-plan></packet>', 'text/xml')
                ->post($url);
        } catch (\Throwable $e) {
            Log::warning('Plesk listPackages failed', ['server' => $server->id, 'error' => $e->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $xml = @simplexml_load_string($response->body());

        if (! $xml) {
            return [];
        }

        $plans = [];

        foreach ($xml->xpath('//service-plan/get/result') ?: [] as $result) {
            $name = trim((string) $result->name);

            if ($name !== '') {
                $plans[$name] = ['id' => $name, 'name' => $name];
            }
        }

        ksort($plans);

        return array_values($plans);
    }

    private function getClientId(Service $service): ?string
    {
        return $this->getModuleData($service)['plesk_client_id'] ?? null;
    }

    private function getDomainId(Service $service): ?string
    {
        $data = $this->getModuleData($service);

        // plesk_webspace_id is the legacy key from the previous module version
        return $data['plesk_domain_id'] ?? $data['plesk_webspace_id'] ?? null;
    }

    private function errorMessage($response): string
    {
        return $response->json('message') ?? $response->json('errors.0.message') ?? $response->body();
    }

    // -------------------------------------------------------------------------
    // Interface methods
    // -------------------------------------------------------------------------

    public function create(Service $service): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No server assigned to this service.');
        }

        $client = $service->client;
        $domain = $service->domain ?: '';
        if (! $client || ! $domain) {
            return $this->buildResult(false, 'Service is missing client or domain.');
        }

        // Plesk login: derived from the domain, must be unique on the server
        $login = preg_replace('/[^a-z0-9]/', '', strtolower(explode('.', $domain)[0]));
        $login = substr(ltrim($login, '0123456789') ?: 'u'.$service->id, 0, 16).$service->id;

        $password = $service->password ?: $this->generatePassword();
        $planName = $this->getRemotePackage($service);

        $base = $this->baseUrl($server);
        $http = $this->http($server);

        // Step 1 – create customer
        $clientResp = $http->post("{$base}/clients", [
            'name' => trim($client->first_name.' '.$client->last_name) ?: $login,
            'login' => $login,
            'password' => $password,
            'email' => $client->email ?? '',
            'type' => 'customer',
        ]);

        if (! $clientResp->successful()) {
            Log::error('Plesk create client failed', ['body' => $clientResp->body()]);

            return $this->buildResult(false, 'Failed to create Plesk client: '.$this->errorMessage($clientResp));
        }

        $clientId = $clientResp->json('id');

        // Step 2 – create the subscription: POST /domains with virtual hosting.
        // ftp_login + ftp_password are REQUIRED when creating a subscription.
        $payload = [
            'name' => $domain,
            'hosting_type' => 'virtual',
            'hosting_settings' => [
                'ftp_login' => $login,
                'ftp_password' => $password,
            ],
            'owner_client' => ['id' => $clientId],
        ];
        if ($planName) {
            $payload['plan'] = ['name' => $planName];
        }

        $domainResp = $http->post("{$base}/domains", $payload);

        if (! $domainResp->successful()) {
            // Rollback: delete the client we just created
            Log::error('Plesk create domain failed - rolling back client', ['body' => $domainResp->body()]);
            $http->delete("{$base}/clients/{$clientId}");

            return $this->buildResult(false, 'Failed to create Plesk subscription: '.$this->errorMessage($domainResp));
        }

        $domainId = $domainResp->json('id');

        $this->setModuleData($service, [
            'plesk_client_id' => $clientId,
            'plesk_domain_id' => $domainId,
        ]);
        $service->update(['username' => $login, 'password' => $password]);

        $this->logAction($service, 'create', ['success' => true]);

        return $this->buildResult(true, 'Plesk account created successfully.', [
            'plesk_client_id' => $clientId,
            'plesk_domain_id' => $domainId,
        ]);
    }

    public function suspend(Service $service, string $reason = ''): array
    {
        $server = $this->getServer($service);
        $clientId = $this->getClientId($service);

        if (! $server || ! $clientId) {
            return $this->buildResult(false, 'Missing server or Plesk client ID.');
        }

        $resp = $this->http($server)->put("{$this->baseUrl($server)}/clients/{$clientId}/suspend");

        $result = $this->buildResult($resp->successful(), $resp->successful() ? 'Account suspended.' : $this->errorMessage($resp));
        $this->logAction($service, 'suspend', $result);

        return $result;
    }

    public function unsuspend(Service $service): array
    {
        $server = $this->getServer($service);
        $clientId = $this->getClientId($service);

        if (! $server || ! $clientId) {
            return $this->buildResult(false, 'Missing server or Plesk client ID.');
        }

        $resp = $this->http($server)->put("{$this->baseUrl($server)}/clients/{$clientId}/activate");

        $result = $this->buildResult($resp->successful(), $resp->successful() ? 'Account unsuspended.' : $this->errorMessage($resp));
        $this->logAction($service, 'unsuspend', $result);

        return $result;
    }

    public function terminate(Service $service): array
    {
        $server = $this->getServer($service);
        $clientId = $this->getClientId($service);

        if (! $server || ! $clientId) {
            return $this->buildResult(false, 'Missing server or Plesk client ID.');
        }

        // DELETE /clients/{id} cascades subscriptions (webspaces)
        $resp = $this->http($server)->delete("{$this->baseUrl($server)}/clients/{$clientId}");

        $result = $this->buildResult($resp->successful(), $resp->successful() ? 'Account terminated.' : $this->errorMessage($resp));
        $this->logAction($service, 'terminate', $result);

        return $result;
    }

    public function changePassword(Service $service, string $newPassword): array
    {
        $server = $this->getServer($service);
        $clientId = $this->getClientId($service);

        if (! $server || ! $clientId) {
            return $this->buildResult(false, 'Missing server or Plesk client ID.');
        }

        $resp = $this->http($server)->put("{$this->baseUrl($server)}/clients/{$clientId}", [
            'password' => $newPassword,
        ]);

        if (! $resp->successful()) {
            return $this->buildResult(false, $this->errorMessage($resp));
        }

        // The client password is the control panel login. The credential the
        // customer is shown - and uses to upload their site - is the
        // subscription's FTP login, which was left on the old password: the
        // panel then displayed a password that no longer worked for FTP.
        $domainId = $this->getDomainId($service);

        if ($domainId) {
            $ftp = $this->http($server)->put("{$this->baseUrl($server)}/domains/{$domainId}", [
                'hosting_settings' => ['ftp_password' => $newPassword],
            ]);

            if (! $ftp->successful()) {
                $service->update(['password' => $newPassword]);

                return $this->buildResult(false, 'Control panel password changed, but Plesk kept the old FTP password: '.$this->errorMessage($ftp));
            }
        }

        $service->update(['password' => $newPassword]);

        return $this->buildResult(true, 'Password changed.');
    }

    public function changePackage(Service $service, array $newPackage): array
    {
        $server = $this->getServer($service);
        $domainId = $this->getDomainId($service);

        $config = is_string($newPackage['config_options'] ?? null)
            ? json_decode($newPackage['config_options'], true)
            : ($newPackage['config_options'] ?? []);
        $planName = $config['plesk_plan'] ?? $config['package_name'] ?? $newPackage['package_name'] ?? $newPackage['name'] ?? null;

        if (! $server || ! $domainId || ! $planName) {
            return $this->buildResult(false, 'Missing server, domain ID, or plan name.');
        }

        $resp = $this->http($server)->put("{$this->baseUrl($server)}/domains/{$domainId}", [
            'plan' => ['name' => $planName],
        ]);

        return $this->buildResult($resp->successful(), $resp->successful() ? 'Package changed.' : $this->errorMessage($resp));
    }

    /**
     * Pull disk/traffic per client via GET /clients/{id}/statistics and update
     * the services directly (same contract as the cPanel module).
     */
    public function usageUpdate(Server $server): array
    {
        $updated = 0;
        $errors = 0;

        $services = Service::where('server_id', $server->id)
            ->where('status', 'active')
            ->get();

        foreach ($services as $service) {
            $clientId = $this->getClientId($service);
            if (! $clientId) {
                continue;
            }

            try {
                $resp = $this->http($server)->get("{$this->baseUrl($server)}/clients/{$clientId}/statistics");
                if (! $resp->successful()) {
                    $errors++;

                    continue;
                }

                $stats = $resp->json() ?? [];
                $updateData = [];
                if (isset($stats['disk_space'])) {
                    $updateData['disk_usage'] = (int) round(((int) $stats['disk_space']) / 1048576); // bytes → MB
                }
                if (isset($stats['traffic'])) {
                    $updateData['bw_usage'] = (int) round(((int) $stats['traffic']) / 1048576);
                }

                if ($updateData) {
                    $service->update($updateData);
                    $updated++;
                }
            } catch (\Throwable $e) {
                $errors++;
                Log::error('Plesk usageUpdate failed', ['service' => $service->id, 'error' => $e->getMessage()]);
            }
        }

        return ['updated' => $updated, 'errors' => $errors];
    }

    public function testConnection(Server $server): bool
    {
        try {
            $resp = $this->http($server)->get("{$this->baseUrl($server)}/server");

            return $resp->successful();
        } catch (\Throwable $e) {
            Log::warning('Plesk testConnection failed', ['server' => $server->id, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
