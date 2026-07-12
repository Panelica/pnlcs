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

    /**
     * Build a panel plan spec from a product's managed resource config. Mirrors
     * the Panelica WHMCS module contract exactly (POST /v1/plans basic columns +
     * PATCH /v1/plans/{id} advanced columns), so a PNLCS "managed" product
     * enforces the same cgroups/quota limits as WHMCS. Returns null when the
     * product does not use managed mode (res_managed falsy).
     *
     * @return array{basic: array, advanced: array}|null
     */
    private function managedPlanSpec(array $c): ?array
    {
        if (empty($c['res_managed'])) {
            return null;
        }
        $int = fn ($k, $d) => (isset($c[$k]) && is_numeric($c[$k])) ? (int) $c[$k] : $d;
        $str = fn ($k, $d) => (isset($c[$k]) && trim((string) $c[$k]) !== '') ? trim((string) $c[$k]) : $d;

        $ssh = $str('res_ssh_level', 'none');
        if (!in_array($ssh, ['none', 'jailed', 'full'], true)) { $ssh = 'none'; }
        $quotaMode = $str('res_quota_mode', 'strict');
        if (!in_array($quotaMode, ['strict', 'monitor', 'oversell'], true)) { $quotaMode = 'strict'; }

        $ioMbs     = max(0, $int('res_io_mbs', 0));
        $netMbit   = max(0, $int('res_network_mbit', 0));
        $maxCron   = $int('res_max_cron', 5);
        $phpUpload = $int('res_php_upload', 64);

        return [
            'basic' => [
                'disk_quota_mb'        => $int('res_disk_mb', 5120),
                'monthly_bandwidth_mb' => $int('res_bandwidth_mb', 51200),
                'max_domains'          => $int('res_max_domains', 1),
                'max_subdomains'       => $int('res_max_subdomains', 10),
                'max_email_accounts'   => $int('res_max_email', 10),
                'max_databases'        => $int('res_max_db', 5),
                'max_ftp_accounts'     => $int('res_max_ftp', 5),
                'max_cron_jobs'        => $maxCron,
                'ssh_access_enabled'   => ($ssh !== 'none'),
                'ftp_access_enabled'   => true,
                'mysql_access_enabled' => true,
                'cron_jobs_enabled'    => ($maxCron !== 0),
                'ssl_enabled'          => true,
                'backup_enabled'       => $str('res_backup', 'on') !== 'off',
            ],
            'advanced' => [
                'cpu_limit_percent'          => $int('res_cpu_percent', 100),
                'memory_limit_mb'            => $int('res_memory_mb', 1024),
                'process_limit'              => $int('res_process_limit', 100),
                'io_read_bps'                => $ioMbs * 1048576,
                'io_write_bps'               => $ioMbs * 1048576,
                'network_bps_limit'          => $netMbit * 125000,
                'max_containers'             => $int('res_max_containers', 0),
                'inode_quota'                => $int('res_inode_quota', -1),
                'iops_limit'                 => $int('res_iops', 0),
                'quota_mode'                 => $quotaMode,
                'ssh_access_level'           => $ssh,
                'modsecurity_enabled'        => $str('res_modsec', 'on') !== 'off',
                'php_memory_limit_mb'        => $int('res_php_memory_mb', 256),
                'php_max_execution_time'     => $int('res_php_exec', 30),
                'php_upload_max_filesize_mb' => $phpUpload,
                'php_post_max_size_mb'       => $phpUpload,
            ],
        ];
    }

    /** Find an existing panel plan id by name, or null. */
    private function findPlanByName(Server $server, string $name): ?string
    {
        $resp = $this->get($server, '/v1/plans');
        if (!$resp->successful()) {
            return null;
        }
        foreach (($resp->json('data') ?? $resp->json() ?? []) as $p) {
            if (($p['name'] ?? null) === $name) {
                return (string) ($p['id'] ?? '') ?: null;
            }
        }
        return null;
    }

    /**
     * Create or update the managed plan for this product on the panel and return
     * its id. Basic columns go via POST /v1/plans (create) or PATCH (update);
     * advanced columns always via PATCH /v1/plans/{id}.
     */
    private function ensureManagedPlan(Server $server, Service $service, array $spec): ?string
    {
        $name   = 'pnlcs-p' . ($service->product_id ?? 'x');
        $planId = $this->findPlanByName($server, $name);

        if (!$planId) {
            $resp = $this->post($server, '/v1/plans', array_merge($spec['basic'], ['name' => $name]));
            if (!$resp->successful()) {
                Log::error('PanelicaModule::ensureManagedPlan create failed', ['body' => $resp->body()]);
                return null;
            }
            $planId = (string) ($resp->json('data.id') ?? $resp->json('id') ?? '') ?: null;
        } else {
            // Keep an existing managed plan in sync with the product config.
            $this->patch($server, "/v1/plans/{$planId}", $spec['basic']);
        }

        if ($planId) {
            $this->patch($server, "/v1/plans/{$planId}", $spec['advanced']);
        }
        return $planId;
    }

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

        // Managed mode: build/sync a panel plan from the product's resource
        // config (cpu/ram/inode/iops/...) and use it — full Panelica parity.
        $spec = $this->managedPlanSpec($config);
        if ($spec) {
            $managed = $this->ensureManagedPlan($server, $service, $spec);
            if ($managed) {
                $planId = $managed;
            }
        }

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

            $updateData = [];

            // Disk: dedicated endpoint returns real used + quota (MB).
            $diskResp = $this->get($server, "/v1/accounts/{$userId}/disk-usage");
            if ($diskResp->successful()) {
                $d = $diskResp->json('data') ?? [];
                if (isset($d['used_mb']))  { $updateData['disk_usage'] = (int) $d['used_mb']; }
                if (isset($d['quota_mb'])) { $updateData['disk_limit'] = (int) $d['quota_mb']; }
            }

            // Bandwidth: the stats endpoint reports this month's usage as bandwidth_mb.
            $statResp = $this->get($server, "/v1/accounts/{$userId}/stats");
            if ($statResp->successful()) {
                $st = $statResp->json('data') ?? [];
                if (isset($st['bandwidth_mb'])) { $updateData['bw_usage'] = (int) $st['bandwidth_mb']; }
            }

            if (!empty($updateData)) {
                $service->update($updateData);
                $updated++;
            } else {
                $errors++;
            }
        }

        return ['updated' => $updated, 'errors' => $errors];
    }

    /**
     * List the hosting plans defined on the panel (GET /v1/plans) for the
     * product editor's plan dropdown.
     *
     * @return array<int, array>
     */
    public function listPlans(Server $server): array
    {
        $resp = $this->get($server, '/v1/plans');
        if (!$resp->successful()) {
            return [];
        }
        $plans = $resp->json('data') ?? $resp->json() ?? [];
        return is_array($plans) ? $plans : [];
    }

    /**
     * Live resource usage for a service, pulled straight from the panel:
     * disk (GET /v1/accounts/{id}/disk-usage) and bandwidth + account counts
     * (GET /v1/accounts/{id}/stats). Used to render the client usage graphs.
     */
    public function liveUsage(Service $service): array
    {
        $server = $this->getServer($service);
        $userId = $this->getModuleData($service)['panelica_user_id'] ?? null;
        if (!$server || !$userId) {
            return ['available' => false];
        }

        $out = ['available' => true, 'disk' => null, 'bandwidth' => null, 'counts' => null];

        $diskResp = $this->get($server, "/v1/accounts/{$userId}/disk-usage");
        if ($diskResp->successful()) {
            $d = $diskResp->json('data') ?? [];
            $out['disk'] = ['used_mb' => (int) ($d['used_mb'] ?? 0), 'quota_mb' => (int) ($d['quota_mb'] ?? 0)];
        }

        $statResp = $this->get($server, "/v1/accounts/{$userId}/stats");
        if ($statResp->successful()) {
            $st = $statResp->json('data') ?? [];
            $out['bandwidth'] = ['used_mb' => (int) ($st['bandwidth_mb'] ?? 0)];
            $out['counts'] = [
                'domains'   => (int) ($st['domain_count'] ?? 0),
                'emails'    => (int) ($st['email_count'] ?? 0),
                'ftp'       => (int) ($st['ftp_count'] ?? 0),
                'databases' => (int) ($st['database_count'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Mint a one-time single-sign-on URL so the customer can jump straight into
     * their hosting control panel (POST /v1/accounts/{id}/sso-login).
     */
    public function ssoLogin(Service $service): array
    {
        $server = $this->getServer($service);
        if (!$server) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }
        $userId = $this->getModuleData($service)['panelica_user_id'] ?? null;
        if (!$userId) {
            return $this->buildResult(false, 'No panel account is linked to this service.');
        }

        $resp = $this->post($server, "/v1/accounts/{$userId}/sso-login", []);
        if (!$resp->successful()) {
            Log::error('PanelicaModule::ssoLogin failed', ['user_id' => $userId, 'body' => $resp->body()]);
            return $this->buildResult(false, 'Could not create a panel login session.');
        }
        $url = $resp->json('data.url') ?? $resp->json('url') ?? $resp->json('data.login_url') ?? null;
        if (!$url) {
            return $this->buildResult(false, 'Panel did not return a login URL.');
        }
        return $this->buildResult(true, 'SSO URL issued.', ['url' => $url]);
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
