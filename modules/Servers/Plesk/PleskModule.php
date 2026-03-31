<?php

namespace Modules\Servers\Plesk;

use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Servers\AbstractServerModule;

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

    private function getWebspaceId(Service $service): ?string
    {
        return $this->getModuleData($service)['plesk_webspace_id'] ?? null;
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

        $client    = $service->client;
        $username  = $service->username ?: ($client->email ?? 'user_' . $service->id);
        $email     = $client->email ?? '';
        $password  = $service->password ?: \Illuminate\Support\Str::random(16);
        $domain    = $service->domain ?: '';
        $planName  = $this->getRemotePackage($service) ?? 'Default';

        $base = $this->baseUrl($server);
        $http = $this->http($server);

        // Step 1 – create customer
        $clientResp = $http->post("{$base}/clients", [
            'name'     => $username,
            'login'    => $username,
            'password' => $password,
            'email'    => $email,
            'type'     => 'customer',
        ]);

        if (!$clientResp->successful()) {
            Log::error('Plesk create client failed', ['body' => $clientResp->body()]);
            return $this->buildResult(false, 'Failed to create Plesk client: ' . $clientResp->body());
        }

        $clientId = $clientResp->json('id');

        // Step 2 – create subscription (webspace)
        $webspaceResp = $http->post("{$base}/webspaces", [
            'name'         => $domain,
            'owner'        => ['login' => $username],
            'hosting_type' => 'virtual',
            'plan'         => ['name' => $planName],
            'ip_address'   => ['shared'],
        ]);

        if (!$webspaceResp->successful()) {
            // Rollback: delete the client we just created
            Log::error('Plesk create webspace failed - rolling back client', ['body' => $webspaceResp->body()]);
            $http->delete("{$base}/clients/{$clientId}");
            return $this->buildResult(false, 'Failed to create Plesk webspace: ' . $webspaceResp->body());
        }

        $webspaceId = $webspaceResp->json('id');

        $this->setModuleData($service, [
            'plesk_client_id'   => $clientId,
            'plesk_webspace_id' => $webspaceId,
        ]);

        $this->logAction($service, 'create', ['success' => true]);
        return $this->buildResult(true, 'Plesk account created successfully.', [
            'plesk_client_id'   => $clientId,
            'plesk_webspace_id' => $webspaceId,
        ]);
    }

    public function suspend(Service $service, string $reason = ''): array
    {
        $server   = $this->getServer($service);
        $clientId = $this->getClientId($service);

        if (!$server || !$clientId) {
            return $this->buildResult(false, 'Missing server or Plesk client ID.');
        }

        $resp = $this->http($server)->put("{$this->baseUrl($server)}/clients/{$clientId}", [
            'status' => 16, // 16 = suspended in Plesk API
        ]);

        $result = $this->buildResult($resp->successful(), $resp->successful() ? 'Account suspended.' : $resp->body());
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

        $resp = $this->http($server)->put("{$this->baseUrl($server)}/clients/{$clientId}", [
            'status' => 0, // 0 = active
        ]);

        $result = $this->buildResult($resp->successful(), $resp->successful() ? 'Account unsuspended.' : $resp->body());
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

        $result = $this->buildResult($resp->successful(), $resp->successful() ? 'Account terminated.' : $resp->body());
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

        return $this->buildResult($resp->successful(), $resp->successful() ? 'Password changed.' : $resp->body());
    }

    public function changePackage(Service $service, array $newPackage): array
    {
        $server     = $this->getServer($service);
        $webspaceId = $this->getWebspaceId($service);
        $planName   = $newPackage['package_name'] ?? $newPackage['name'] ?? null;

        if (!$server || !$webspaceId || !$planName) {
            return $this->buildResult(false, 'Missing server, webspace ID, or plan name.');
        }

        $resp = $this->http($server)->put("{$this->baseUrl($server)}/webspaces/{$webspaceId}", [
            'plan' => ['name' => $planName],
        ]);

        return $this->buildResult($resp->successful(), $resp->successful() ? 'Package changed.' : $resp->body());
    }

    public function usageUpdate(Server $server): array
    {
        $results = [];

        try {
            $resp = $this->http($server)->get("{$this->baseUrl($server)}/webspaces");
            if (!$resp->successful()) {
                return [];
            }

            foreach ($resp->json() ?? [] as $ws) {
                $results[] = [
                    'id'         => $ws['id'] ?? null,
                    'domain'     => $ws['name'] ?? null,
                    'disk_usage' => $ws['disk_usage'] ?? 0,
                    'bw_usage'   => $ws['traffic'] ?? 0,
                ];
            }
        } catch (\Throwable $e) {
            Log::error('Plesk usageUpdate failed', ['server' => $server->id, 'error' => $e->getMessage()]);
        }

        return $results;
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
