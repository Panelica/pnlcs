<?php
namespace Modules\Servers\Panelica;

use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Servers\AbstractServerModule;

class PanelicaModule extends AbstractServerModule
{
    public function getModuleName(): string
    {
        return 'panelica';
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'api_key',    'label' => 'API Key (pk_live_...)',    'type' => 'text'],
            ['name' => 'api_secret', 'label' => 'API Secret (sk_live_...)', 'type' => 'password'],
            ['name' => 'api_port',   'label' => 'API Port',                 'type' => 'text', 'default' => '3002'],
        ];
    }

    // -------------------------------------------------------------------------
    // HTTP helpers
    // -------------------------------------------------------------------------

    private function baseUrl(Server $server): string
    {
        // The external API runs on port stored in server->port (8443) with prefix /api/external
        return "https://{$server->hostname}:{$server->port}/api/external";
    }

    private function apiKey(Server $server): string
    {
        return $server->password ?? '';   // pk_live_...
    }

    private function apiSecret(Server $server): string
    {
        return $server->access_hash ?? ''; // sk_live_...
    }

    private function buildHeaders(Server $server, string $method, string $path, string $body = ''): array
    {
        $timestamp = (string) time();
        $sigPayload = strtoupper($method) . $path . $timestamp . $body;
        $signature  = hash_hmac('sha256', $sigPayload, $this->apiSecret($server));

        return [
            'X-API-Key'    => $this->apiKey($server),
            'X-Timestamp'  => $timestamp,
            'X-Signature'  => $signature,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];
    }

    private function get(Server $server, string $path): \Illuminate\Http\Client\Response
    {
        $headers = $this->buildHeaders($server, 'GET', $path, '');
        return Http::withHeaders($headers)->withoutVerifying()->get($this->baseUrl($server) . $path);
    }

    private function post(Server $server, string $path, array $payload): \Illuminate\Http\Client\Response
    {
        $body    = json_encode($payload);
        $headers = $this->buildHeaders($server, 'POST', $path, $body);
        return Http::withHeaders($headers)->withoutVerifying()->withBody($body, 'application/json')->post($this->baseUrl($server) . $path);
    }

    private function patch(Server $server, string $path, array $payload): \Illuminate\Http\Client\Response
    {
        $body    = json_encode($payload);
        $headers = $this->buildHeaders($server, 'PATCH', $path, $body);
        return Http::withHeaders($headers)->withoutVerifying()->withBody($body, 'application/json')->patch($this->baseUrl($server) . $path);
    }

    private function delete(Server $server, string $path): \Illuminate\Http\Client\Response
    {
        $headers = $this->buildHeaders($server, 'DELETE', $path, '');
        return Http::withHeaders($headers)->withoutVerifying()->delete($this->baseUrl($server) . $path);
    }

    // -------------------------------------------------------------------------
    // Interface implementation
    // -------------------------------------------------------------------------

    public function create(Service $service): array
    {
        $server = $this->getServer($service);
        if (!$server) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }

        $client  = $service->client;
        $domain  = $service->domain;
        $product = $service->product;

        if (!$client || !$domain) {
            return $this->buildResult(false, 'Service is missing client or domain.');
        }

        // Derive username from domain (alphanumeric, max 16 chars)
        $username = preg_replace('/[^a-z0-9]/', '', strtolower(explode('.', $domain)[0]));
        $username = substr($username ?: 'user', 0, 16) . rand(100, 999);

        $password = $service->password ?: bin2hex(random_bytes(8));

        // Resolve plan ID from product config
        $config = is_string($product?->config_options)
            ? json_decode($product->config_options, true)
            : ($product->config_options ?? []);
        $planId = $config['panelica_plan_id'] ?? null;

        // Step 1: Create account
        $accountPayload = [
            'username'  => $username,
            'email'     => $client->email,
            'password'  => $password,
            'full_name' => trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')),
        ];
        if ($planId) {
            $accountPayload['plan_id'] = $planId;
        }

        $accountResp = $this->post($server, '/v1/accounts', $accountPayload);

        if (!$accountResp->successful()) {
            $msg = $accountResp->json('message') ?? $accountResp->body();
            Log::error('PanelicaModule::create account failed', ['status' => $accountResp->status(), 'body' => $accountResp->body()]);
            return $this->buildResult(false, "Account creation failed: {$msg}");
        }

        $accountData = $accountResp->json();
        $userId = $accountData['data']['id'] ?? $accountData['id'] ?? null;

        if (!$userId) {
            return $this->buildResult(false, 'Account created but user ID not found in response.');
        }

        // Step 2: Create domain
        $phpVersion = $config['php_version'] ?? '8.3';
        $webServer  = $config['web_server']  ?? 'nginx_only';

        $domainPayload = [
            'name' => $domain,
            'user_id'     => $userId,
            'php_version' => $phpVersion,
            'web_server'  => $webServer,
        ];

        $domainResp = $this->post($server, '/v1/domains', $domainPayload);

        if (!$domainResp->successful()) {
            // Rollback: delete the created account
            $this->delete($server, "/v1/accounts/{$userId}");
            $msg = $domainResp->json('message') ?? $domainResp->body();
            Log::error('PanelicaModule::create domain failed (account rolled back)', ['user_id' => $userId, 'body' => $domainResp->body()]);
            return $this->buildResult(false, "Domain creation failed (account rolled back): {$msg}");
        }

        $domainData = $domainResp->json();
        $domainId   = $domainData['data']['id'] ?? $domainData['id'] ?? null;

        // Persist module data
        $this->setModuleData($service, [
            'panelica_user_id'   => $userId,
            'panelica_domain_id' => $domainId,
        ]);

        $service->update(['username' => $username, 'status' => 'active']);

        $result = $this->buildResult(true, 'Account and domain created successfully.', [
            'panelica_user_id'   => $userId,
            'panelica_domain_id' => $domainId,
        ]);
        $this->logAction($service, 'create', $result);
        return $result;
    }

    public function suspend(Service $service, string $reason = ''): array
    {
        $server = $this->getServer($service);
        if (!$server) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }

        $data   = $this->getModuleData($service);
        $userId = $data['panelica_user_id'] ?? null;

        if (!$userId) {
            return $this->buildResult(false, 'Panelica user ID not found in service notes.');
        }

        $resp = $this->post($server, "/v1/accounts/{$userId}/suspend", ['reason' => $reason]);

        if (!$resp->successful()) {
            $msg = $resp->json('message') ?? $resp->body();
            return $this->buildResult(false, "Suspend failed: {$msg}");
        }

        $service->update(['status' => 'suspended', 'suspension_date' => now(), 'suspension_reason' => $reason]);
        $result = $this->buildResult(true, 'Account suspended.');
        $this->logAction($service, 'suspend', $result);
        return $result;
    }

    public function unsuspend(Service $service): array
    {
        $server = $this->getServer($service);
        if (!$server) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }

        $data   = $this->getModuleData($service);
        $userId = $data['panelica_user_id'] ?? null;

        if (!$userId) {
            return $this->buildResult(false, 'Panelica user ID not found in service notes.');
        }

        $resp = $this->post($server, "/v1/accounts/{$userId}/unsuspend", []);

        if (!$resp->successful()) {
            $msg = $resp->json('message') ?? $resp->body();
            return $this->buildResult(false, "Unsuspend failed: {$msg}");
        }

        $service->update(['status' => 'active', 'suspension_date' => null, 'suspension_reason' => null]);
        $result = $this->buildResult(true, 'Account unsuspended.');
        $this->logAction($service, 'unsuspend', $result);
        return $result;
    }

    public function terminate(Service $service): array
    {
        $server = $this->getServer($service);
        if (!$server) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }

        $data   = $this->getModuleData($service);
        $userId = $data['panelica_user_id'] ?? null;

        if (!$userId) {
            return $this->buildResult(false, 'Panelica user ID not found in service notes.');
        }

        $resp = $this->delete($server, "/v1/accounts/{$userId}");

        if (!$resp->successful()) {
            $msg = $resp->json('message') ?? $resp->body();
            return $this->buildResult(false, "Terminate failed: {$msg}");
        }

        $service->update(['status' => 'terminated', 'termination_date' => now()]);
        $result = $this->buildResult(true, 'Account terminated.');
        $this->logAction($service, 'terminate', $result);
        return $result;
    }

    public function changePassword(Service $service, string $newPassword): array
    {
        $server = $this->getServer($service);
        if (!$server) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }

        $data   = $this->getModuleData($service);
        $userId = $data['panelica_user_id'] ?? null;

        if (!$userId) {
            return $this->buildResult(false, 'Panelica user ID not found in service notes.');
        }

        $resp = $this->post($server, "/v1/accounts/{$userId}/change-password", ['new_password' => $newPassword]);

        if (!$resp->successful()) {
            $msg = $resp->json('message') ?? $resp->body();
            return $this->buildResult(false, "Password change failed: {$msg}");
        }

        $service->update(['password' => $newPassword]);
        $result = $this->buildResult(true, 'Password changed successfully.');
        $this->logAction($service, 'changePassword', $result);
        return $result;
    }

    public function changePackage(Service $service, array $newPackage): array
    {
        $server = $this->getServer($service);
        if (!$server) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }

        $data   = $this->getModuleData($service);
        $userId = $data['panelica_user_id'] ?? null;

        if (!$userId) {
            return $this->buildResult(false, 'Panelica user ID not found in service notes.');
        }

        $config = is_string($newPackage['config_options'] ?? null)
            ? json_decode($newPackage['config_options'], true)
            : ($newPackage['config_options'] ?? []);
        $newPlanId = $config['panelica_plan_id'] ?? null;

        if (!$newPlanId) {
            return $this->buildResult(false, 'New product does not have a panelica_plan_id configured.');
        }

        $resp = $this->patch($server, "/v1/accounts/{$userId}", ['plan_id' => $newPlanId]);

        if (!$resp->successful()) {
            $msg = $resp->json('message') ?? $resp->body();
            return $this->buildResult(false, "Package change failed: {$msg}");
        }

        $result = $this->buildResult(true, 'Package changed successfully.');
        $this->logAction($service, 'changePackage', $result);
        return $result;
    }

    public function usageUpdate(Server $server): array
    {
        $services = \App\Models\Service::where('server_id', $server->id)
            ->where('status', 'active')
            ->get();

        $updated = 0;
        $errors  = 0;

        foreach ($services as $service) {
            $data   = $this->getModuleData($service);
            $userId = $data['panelica_user_id'] ?? null;

            if (!$userId) {
                $errors++;
                continue;
            }

            $resp = $this->get($server, "/v1/accounts/{$userId}/stats");

            if (!$resp->successful()) {
                $errors++;
                continue;
            }

            $stats      = $resp->json('data') ?? $resp->json() ?? [];
            $diskUsage  = $stats['disk_used']      ?? $stats['disk_usage']      ?? null;
            $bwUsage    = $stats['bandwidth_used']  ?? $stats['bw_usage']        ?? null;

            $updateData = [];
            if ($diskUsage !== null) $updateData['disk_usage'] = $diskUsage;
            if ($bwUsage   !== null) $updateData['bw_usage']   = $bwUsage;

            if (!empty($updateData)) {
                $service->update($updateData);
                $updated++;
            }
        }

        return ['updated' => $updated, 'errors' => $errors];
    }

    public function testConnection(Server $server): bool
    {
        try {
            $resp = $this->get($server, '/v1/server/status');

            if (!$resp->successful()) {
                Log::warning('PanelicaModule::testConnection HTTP error', ['status' => $resp->status(), 'body' => $resp->body()]);
                return false;
            }

            $body   = $resp->json();
            $status = $body['data']['status'] ?? $body['status'] ?? '';
            return strtolower($status) === 'online';
        } catch (\Throwable $e) {
            Log::error('PanelicaModule::testConnection exception', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
