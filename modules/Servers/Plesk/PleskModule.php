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
 *  - POST   /clients/{id}/suspend        suspend account
 *  - POST   /clients/{id}/activate       reactivate account
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
        return "https://{$server->hostname}:{$port}/api/v2";
    }

    private function http(Server $server)
    {
        return Http::withoutVerifying()
            ->withHeaders([
                'X-API-Key'    => $server->access_hash,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])
            ->timeout(30);
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
        if (!$server) {
            return $this->buildResult(false, 'No server assigned to this service.');
        }

        $client = $service->client;
        $domain = $service->domain ?: '';
        if (!$client || !$domain) {
            return $this->buildResult(false, 'Service is missing client or domain.');
        }

        // Plesk login: derived from the domain, must be unique on the server
        $login = preg_replace('/[^a-z0-9]/', '', strtolower(explode('.', $domain)[0]));
        $login = substr(ltrim($login, '0123456789') ?: 'u' . $service->id, 0, 16) . $service->id;

        $password = $service->password ?: bin2hex(random_bytes(9)) . 'aA1';
        $planName = $this->getRemotePackage($service);

        $base = $this->baseUrl($server);
        $http = $this->http($server);

        // Step 1 – create customer
        $clientResp = $http->post("{$base}/clients", [
            'name'     => trim($client->first_name . ' ' . $client->last_name) ?: $login,
            'login'    => $login,
            'password' => $password,
            'email'    => $client->email ?? '',
            'type'     => 'customer',
        ]);

        if (!$clientResp->successful()) {
            Log::error('Plesk create client failed', ['body' => $clientResp->body()]);
            return $this->buildResult(false, 'Failed to create Plesk client: ' . $this->errorMessage($clientResp));
        }

        $clientId = $clientResp->json('id');

        // Step 2 – create the subscription: POST /domains with virtual hosting.
        // ftp_login + ftp_password are REQUIRED when creating a subscription.
        $payload = [
            'name'             => $domain,
            'hosting_type'     => 'virtual',
            'hosting_settings' => [
                'ftp_login'    => $login,
                'ftp_password' => $password,
            ],
            'owner_client'     => ['id' => $clientId],
        ];
        if ($planName) {
            $payload['plan'] = ['name' => $planName];
        }

        $domainResp = $http->post("{$base}/domains", $payload);

        if (!$domainResp->successful()) {
            // Rollback: delete the client we just created
            Log::error('Plesk create domain failed - rolling back client', ['body' => $domainResp->body()]);
            $http->delete("{$base}/clients/{$clientId}");
            return $this->buildResult(false, 'Failed to create Plesk subscription: ' . $this->errorMessage($domainResp));
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
        $server   = $this->getServer($service);
        $clientId = $this->getClientId($service);

        if (!$server || !$clientId) {
            return $this->buildResult(false, 'Missing server or Plesk client ID.');
        }

        $resp = $this->http($server)->post("{$this->baseUrl($server)}/clients/{$clientId}/suspend");

        $result = $this->buildResult($resp->successful(), $resp->successful() ? 'Account suspended.' : $this->errorMessage($resp));
        $this->logAction($service, 'suspend', $result);
        return $result;
    }

    public function unsuspend(Service $service): array
    {
        $server   = $this->getServer($service);
        $clientId = $this->getClientId($service);

        if (!$server || !$clientId) {
            return $this->buildResult(false, 'Missing server or Plesk client ID.');
        }

        $resp = $this->http($server)->post("{$this->baseUrl($server)}/clients/{$clientId}/activate");

        $result = $this->buildResult($resp->successful(), $resp->successful() ? 'Account unsuspended.' : $this->errorMessage($resp));
        $this->logAction($service, 'unsuspend', $result);
        return $result;
    }

    public function terminate(Service $service): array
    {
        $server   = $this->getServer($service);
        $clientId = $this->getClientId($service);

        if (!$server || !$clientId) {
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
        $server   = $this->getServer($service);
        $clientId = $this->getClientId($service);

        if (!$server || !$clientId) {
            return $this->buildResult(false, 'Missing server or Plesk client ID.');
        }

        $resp = $this->http($server)->put("{$this->baseUrl($server)}/clients/{$clientId}", [
            'password' => $newPassword,
        ]);

        if ($resp->successful()) {
            $service->update(['password' => $newPassword]);
        }

        return $this->buildResult($resp->successful(), $resp->successful() ? 'Password changed.' : $this->errorMessage($resp));
    }

    public function changePackage(Service $service, array $newPackage): array
    {
        $server   = $this->getServer($service);
        $domainId = $this->getDomainId($service);

        $config = is_string($newPackage['config_options'] ?? null)
            ? json_decode($newPackage['config_options'], true)
            : ($newPackage['config_options'] ?? []);
        $planName = $config['plesk_plan'] ?? $config['package_name'] ?? $newPackage['package_name'] ?? $newPackage['name'] ?? null;

        if (!$server || !$domainId || !$planName) {
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
        $errors  = 0;

        $services = Service::where('server_id', $server->id)
            ->where('status', 'active')
            ->get();

        foreach ($services as $service) {
            $clientId = $this->getClientId($service);
            if (!$clientId) {
                continue;
            }

            try {
                $resp = $this->http($server)->get("{$this->baseUrl($server)}/clients/{$clientId}/statistics");
                if (!$resp->successful()) {
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
