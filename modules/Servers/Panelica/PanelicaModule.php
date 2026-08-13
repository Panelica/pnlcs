<?php

namespace Modules\Servers\Panelica;

use App\Models\Server;
use App\Models\Service;
use Illuminate\Http\Client\Response;
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
        // Credentials are entered through the standard server fields:
        //   Port -> 8443, Password -> API Key (pk_live_...), Access Hash -> API Secret (sk_live_...).
        return [];
    }

    // -------------------------------------------------------------------------
    // HTTP helpers
    // -------------------------------------------------------------------------

    private function baseUrl(Server $server): string
    {
        // The external API runs on port stored in server->port (8443) with prefix /api/external
        return "https://{$this->serverHost($server)}:{$server->port}/api/external";
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
        $sigPayload = strtoupper($method).$path.$timestamp.$body;
        $signature = hash_hmac('sha256', $sigPayload, $this->apiSecret($server));

        return [
            'X-API-Key' => $this->apiKey($server),
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    private function get(Server $server, string $path): Response
    {
        $headers = $this->buildHeaders($server, 'GET', $path, '');

        return Http::withHeaders($headers)->withoutVerifying()->get($this->baseUrl($server).$path);
    }

    /**
     * Whether a domain is already present on the server. Used as a pre-flight so
     * account creation is never attempted for a domain that would fail. A check
     * failure (network or scope) returns false and does not block - the create
     * call still guards against a duplicate.
     */
    private function domainExistsOnServer(Server $server, string $domain): bool
    {
        try {
            $resp = $this->get($server, '/v1/domains');
            if (! $resp->successful()) {
                return false;
            }
            $needle = strtolower(trim($domain));
            foreach (($resp->json('data') ?? []) as $d) {
                $name = strtolower((string) ($d['domain_name'] ?? $d['name'] ?? $d['domain'] ?? ''));
                if ($name !== '' && $name === $needle) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('PanelicaModule::domainExistsOnServer failed', ['error' => $e->getMessage()]);
        }

        return false;
    }

    private function post(Server $server, string $path, array $payload): Response
    {
        $body = json_encode($payload);
        $headers = $this->buildHeaders($server, 'POST', $path, $body);

        return Http::withHeaders($headers)->withoutVerifying()->withBody($body, 'application/json')->post($this->baseUrl($server).$path);
    }

    private function patch(Server $server, string $path, array $payload): Response
    {
        $body = json_encode($payload);
        $headers = $this->buildHeaders($server, 'PATCH', $path, $body);

        return Http::withHeaders($headers)->withoutVerifying()->withBody($body, 'application/json')->patch($this->baseUrl($server).$path);
    }

    private function delete(Server $server, string $path): Response
    {
        $headers = $this->buildHeaders($server, 'DELETE', $path, '');

        return Http::withHeaders($headers)->withoutVerifying()->delete($this->baseUrl($server).$path);
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
        if (! in_array($ssh, ['none', 'jailed', 'full'], true)) {
            $ssh = 'none';
        }
        $quotaMode = $str('res_quota_mode', 'strict');
        if (! in_array($quotaMode, ['strict', 'monitor', 'oversell'], true)) {
            $quotaMode = 'strict';
        }

        $ioMbs = max(0, $int('res_io_mbs', 0));
        $netMbit = max(0, $int('res_network_mbit', 0));
        $maxCron = $int('res_max_cron', 5);
        $phpUpload = $int('res_php_upload', 64);

        return [
            'basic' => [
                'disk_quota_mb' => $int('res_disk_mb', 5120),
                'monthly_bandwidth_mb' => $int('res_bandwidth_mb', 51200),
                'max_domains' => $int('res_max_domains', 1),
                'max_subdomains' => $int('res_max_subdomains', 10),
                'max_email_accounts' => $int('res_max_email', 10),
                'max_databases' => $int('res_max_db', 5),
                'max_ftp_accounts' => $int('res_max_ftp', 5),
                'max_cron_jobs' => $maxCron,
                'ssh_access_enabled' => ($ssh !== 'none'),
                'ftp_access_enabled' => true,
                'mysql_access_enabled' => true,
                'cron_jobs_enabled' => ($maxCron !== 0),
                'ssl_enabled' => true,
                'backup_enabled' => $str('res_backup', 'on') !== 'off',
            ],
            'advanced' => [
                'cpu_limit_percent' => $int('res_cpu_percent', 100),
                'memory_limit_mb' => $int('res_memory_mb', 1024),
                'process_limit' => $int('res_process_limit', 100),
                'io_read_bps' => $ioMbs * 1048576,
                'io_write_bps' => $ioMbs * 1048576,
                'network_bps_limit' => $netMbit * 125000,
                'max_containers' => $int('res_max_containers', 0),
                'inode_quota' => $int('res_inode_quota', -1),
                'iops_limit' => $int('res_iops', 0),
                'quota_mode' => $quotaMode,
                'ssh_access_level' => $ssh,
                'modsecurity_enabled' => $str('res_modsec', 'on') !== 'off',
                'php_memory_limit_mb' => $int('res_php_memory_mb', 256),
                'php_max_execution_time' => $int('res_php_exec', 30),
                'php_upload_max_filesize_mb' => $phpUpload,
                'php_post_max_size_mb' => $phpUpload,
            ],
        ];
    }

    /** Find an existing panel plan id by name, or null. */
    private function findPlanByName(Server $server, string $name): ?string
    {
        $resp = $this->get($server, '/v1/plans');
        if (! $resp->successful()) {
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
        $name = 'pnlcs-p'.($service->product_id ?? 'x');
        $planId = $this->findPlanByName($server, $name);

        if (! $planId) {
            $resp = $this->post($server, '/v1/plans', array_merge($spec['basic'], ['name' => $name]));
            if (! $resp->successful()) {
                Log::error('PanelicaModule::ensureManagedPlan create failed', ['body' => $resp->body()]);

                return null;
            }
            $planId = (string) ($resp->json('data.id') ?? $resp->json('id') ?? '') ?: null;
        } else {
            // Keep an existing managed plan in sync with the product config.
            // The answer used to be thrown away, so a refused update left the
            // plan on its old limits and every account opened on it afterwards
            // got resources the customer had not bought.
            $sync = $this->patch($server, "/v1/plans/{$planId}", $spec['basic']);

            if (! $sync->successful()) {
                Log::error('PanelicaModule::ensureManagedPlan sync failed', ['plan' => $planId, 'body' => $sync->body()]);

                return null;
            }
        }

        if ($planId) {
            // The cgroup limits - cpu, io, inodes, ssh level - are the whole
            // point of a managed plan.
            $limits = $this->patch($server, "/v1/plans/{$planId}", $spec['advanced']);

            if (! $limits->successful()) {
                Log::error('PanelicaModule::ensureManagedPlan limits failed', ['plan' => $planId, 'body' => $limits->body()]);

                return null;
            }
        }

        return $planId;
    }

    public function create(Service $service): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }

        $client = $service->client;
        $domain = $service->domain;
        $product = $service->product;

        if (! $client || ! $domain) {
            return $this->buildResult(false, 'Service is missing client or domain.');
        }

        // Pre-flight: refuse before creating anything if the domain already
        // exists on the server. Otherwise the account gets created, the domain
        // POST fails, and the account is rolled back - the operator only learns
        // "domain already exists" after the fact. Checking first means no
        // orphaned account and the reason is known up front.
        if ($this->domainExistsOnServer($server, $domain)) {
            return $this->buildResult(false, "The domain \"{$domain}\" already exists on this server. Use a different domain, or remove it from the panel first.");
        }

        // Derive username from domain (alphanumeric, max 16 chars)
        $username = preg_replace('/[^a-z0-9]/', '', strtolower(explode('.', $domain)[0]));
        $username = substr($username ?: 'user', 0, 16).rand(100, 999);

        $password = $service->password ?: bin2hex(random_bytes(8));

        // Resolve plan ID from product config
        $config = is_string($product?->config_options)
            ? json_decode($product->config_options, true)
            : ($product->config_options ?? []);
        // package_name is what every module reads now; the older key
        // still works for products configured before that.
        $planId = $config['package_name'] ?? $config['panelica_plan_id'] ?? null;

        // Managed mode: build/sync a panel plan from the product's resource
        // config (cpu/ram/inode/iops/...) and use it — full Panelica parity.
        $spec = $this->managedPlanSpec($config);
        if ($spec) {
            $managed = $this->ensureManagedPlan($server, $service, $spec);

            // A managed product is sold on the resources in its plan. Opening
            // the account on whatever plan the product happens to name - or on
            // none at all - would hand the customer limits nobody chose, and
            // report it as a success.
            if (! $managed) {
                return $this->buildResult(false, 'Managed plan could not be prepared on the panel; the account was not created.');
            }

            $planId = $managed;
        }

        // Step 1: Create account
        $accountPayload = [
            'username' => $username,
            'email' => $client->email,
            'password' => $password,
            'full_name' => trim(($client->first_name ?? '').' '.($client->last_name ?? '')),
        ];
        if ($planId) {
            $accountPayload['plan_id'] = $planId;
        }

        $accountResp = $this->post($server, '/v1/accounts', $accountPayload);

        if (! $accountResp->successful()) {
            $msg = $accountResp->json('message') ?? $accountResp->body();
            Log::error('PanelicaModule::create account failed', ['status' => $accountResp->status(), 'body' => $accountResp->body()]);

            return $this->buildResult(false, "Account creation failed: {$msg}");
        }

        $accountData = $accountResp->json();
        $userId = $accountData['data']['id'] ?? $accountData['id'] ?? null;

        if (! $userId) {
            return $this->buildResult(false, 'Account created but user ID not found in response.');
        }

        // Step 2: Create domain
        $phpVersion = $config['php_version'] ?? '8.3';
        $webServer = $config['web_server'] ?? 'nginx_only';

        $domainPayload = [
            'name' => $domain,
            'user_id' => $userId,
            'php_version' => $phpVersion,
            'web_server' => $webServer,
        ];

        $domainResp = $this->post($server, '/v1/domains', $domainPayload);

        if (! $domainResp->successful()) {
            // Rollback: delete the created account
            $this->delete($server, "/v1/accounts/{$userId}");
            $msg = $domainResp->json('message') ?? $domainResp->body();
            Log::error('PanelicaModule::create domain failed (account rolled back)', ['user_id' => $userId, 'body' => $domainResp->body()]);

            return $this->buildResult(false, "Domain creation failed (account rolled back): {$msg}");
        }

        $domainData = $domainResp->json();
        $domainId = $domainData['data']['id'] ?? $domainData['id'] ?? null;

        // Persist module data
        $this->setModuleData($service, [
            'panelica_user_id' => $userId,
            'panelica_domain_id' => $domainId,
        ]);

        // r134-credentials: keep the password the account was given.
        //
        // This wrote back only the username, so the password made up a few
        // lines above existed nowhere afterwards - not on the service, not in
        // the welcome email. The customer had an account they could not sign
        // in to and nobody could tell them the password. cPanel, Plesk,
        // DirectAdmin and HestiaCP all store it, and so does the provisioning
        // service when the password is changed later.
        $service->update(['username' => $username, 'password' => $password, 'status' => 'active']);

        $result = $this->buildResult(true, 'Account and domain created successfully.', [
            'panelica_user_id' => $userId,
            'panelica_domain_id' => $domainId,
        ]);
        $this->logAction($service, 'create', $result);

        return $result;
    }

    public function suspend(Service $service, string $reason = ''): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }

        $data = $this->getModuleData($service);
        $userId = $data['panelica_user_id'] ?? null;

        if (! $userId) {
            return $this->buildResult(false, 'Panelica user ID not found in service notes.');
        }

        $resp = $this->post($server, "/v1/accounts/{$userId}/suspend", ['reason' => $reason]);

        if (! $resp->successful()) {
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
        if (! $server) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }

        $data = $this->getModuleData($service);
        $userId = $data['panelica_user_id'] ?? null;

        if (! $userId) {
            return $this->buildResult(false, 'Panelica user ID not found in service notes.');
        }

        $resp = $this->post($server, "/v1/accounts/{$userId}/unsuspend", []);

        if (! $resp->successful()) {
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
        if (! $server) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }

        $data = $this->getModuleData($service);
        $userId = $data['panelica_user_id'] ?? null;

        if (! $userId) {
            return $this->buildResult(false, 'Panelica user ID not found in service notes.');
        }

        $resp = $this->delete($server, "/v1/accounts/{$userId}");

        if (! $resp->successful()) {
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
        if (! $server) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }

        $data = $this->getModuleData($service);
        $userId = $data['panelica_user_id'] ?? null;

        if (! $userId) {
            return $this->buildResult(false, 'Panelica user ID not found in service notes.');
        }

        $resp = $this->post($server, "/v1/accounts/{$userId}/change-password", ['new_password' => $newPassword]);

        if (! $resp->successful()) {
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
        if (! $server) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }

        $data = $this->getModuleData($service);
        $userId = $data['panelica_user_id'] ?? null;

        if (! $userId) {
            return $this->buildResult(false, 'Panelica user ID not found in service notes.');
        }

        $config = is_string($newPackage['config_options'] ?? null)
            ? json_decode($newPackage['config_options'], true)
            : ($newPackage['config_options'] ?? []);
        $newPlanId = $config['panelica_plan_id'] ?? null;

        if (! $newPlanId) {
            return $this->buildResult(false, 'New product does not have a panelica_plan_id configured.');
        }

        $resp = $this->patch($server, "/v1/accounts/{$userId}", ['plan_id' => $newPlanId]);

        if (! $resp->successful()) {
            $msg = $resp->json('message') ?? $resp->body();

            return $this->buildResult(false, "Package change failed: {$msg}");
        }

        $result = $this->buildResult(true, 'Package changed successfully.');
        $this->logAction($service, 'changePackage', $result);

        return $result;
    }

    public function usageUpdate(Server $server): array
    {
        $services = Service::where('server_id', $server->id)
            ->where('status', 'active')
            ->get();

        $updated = 0;
        $errors = 0;

        foreach ($services as $service) {
            $data = $this->getModuleData($service);
            $userId = $data['panelica_user_id'] ?? null;

            if (! $userId) {
                $errors++;

                continue;
            }

            $updateData = [];

            // Disk: dedicated endpoint returns real used + quota (MB).
            $diskResp = $this->get($server, "/v1/accounts/{$userId}/disk-usage");
            if ($diskResp->successful()) {
                $d = $diskResp->json('data') ?? [];
                if (isset($d['used_mb'])) {
                    $updateData['disk_usage'] = (int) $d['used_mb'];
                }
                if (isset($d['quota_mb'])) {
                    $updateData['disk_limit'] = (int) $d['quota_mb'];
                }
            }

            // Bandwidth: the stats endpoint reports this month's usage as bandwidth_mb.
            $statResp = $this->get($server, "/v1/accounts/{$userId}/stats");
            if ($statResp->successful()) {
                $st = $statResp->json('data') ?? [];
                if (isset($st['bandwidth_mb'])) {
                    $updateData['bw_usage'] = (int) $st['bandwidth_mb'];
                }
            }

            if (! empty($updateData)) {
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
    /**
     * The panel's plans, in the shape every module answers with.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function listPackages(Server $server): array
    {
        $out = [];

        foreach ($this->listPlans($server) as $plan) {
            $id = $plan['id'] ?? null;

            if ($id === null) {
                continue;
            }

            $out[] = [
                'id' => (string) $id,
                'name' => (string) ($plan['name'] ?? $id),
            ];
        }

        return $out;
    }

    public function listPlans(Server $server): array
    {
        $resp = $this->get($server, '/v1/plans');
        if (! $resp->successful()) {
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
        if (! $server || ! $userId) {
            return ['available' => false];
        }

        $out = [
            'available' => true,
            'disk' => null, 'bandwidth' => null, 'counts' => null,
            'cpu' => null, 'ram' => null, 'domains' => [],
        ];

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
                'domains' => (int) ($st['domain_count'] ?? 0),
                'emails' => (int) ($st['email_count'] ?? 0),
                'ftp' => (int) ($st['ftp_count'] ?? 0),
                'databases' => (int) ($st['database_count'] ?? 0),
            ];
        }

        // Live per-account CPU/RAM. The endpoint is newer than some panels, so a
        // 404 (or any failure, or an idle account with no sample) simply leaves
        // cpu/ram null and the dashboard omits those gauges - no hard dependency
        // on the panel version. Server-level metrics are never used here: they
        // describe the whole box and would leak other tenants' load.
        $resResp = $this->get($server, "/v1/accounts/{$userId}/resource-usage");
        if ($resResp->successful() && ($resResp->json('data.available') === true)) {
            $r = $resResp->json('data');
            $out['cpu'] = ['percent' => round((float) ($r['cpu_usage_percent'] ?? 0), 2)];
            $usedMb = (int) ($r['memory_usage_mb'] ?? 0);
            $limitMb = (int) ($r['memory_limit_mb'] ?? 0);
            $out['ram'] = [
                'used_mb' => $usedMb,
                'limit_mb' => $limitMb,
                'percent' => $limitMb > 0 ? round($usedMb / $limitMb * 100, 1) : 0,
            ];
            $out['recorded_at'] = $r['recorded_at'] ?? null;
        }

        // The account's own domains - the "domain list" a customer expects on a
        // hosting overview. Scoped server-side to this account.
        $out['domains'] = array_values($this->accountDomains($service));

        return $out;
    }

    /**
     * Mint a one-time single-sign-on URL so the customer can jump straight into
     * their hosting control panel (POST /v1/accounts/{id}/sso-login).
     */
    public function ssoLogin(Service $service): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }
        $userId = $this->getModuleData($service)['panelica_user_id'] ?? null;
        if (! $userId) {
            return $this->buildResult(false, 'No panel account is linked to this service.');
        }

        $resp = $this->post($server, "/v1/accounts/{$userId}/sso-login", []);
        if (! $resp->successful()) {
            Log::error('PanelicaModule::ssoLogin failed', ['user_id' => $userId, 'body' => $resp->body()]);

            return $this->buildResult(false, 'Could not create a panel login session.');
        }
        $url = $resp->json('data.url') ?? $resp->json('url') ?? $resp->json('data.login_url') ?? null;
        if (! $url) {
            return $this->buildResult(false, 'Panel did not return a login URL.');
        }

        return $this->buildResult(true, 'SSO URL issued.', ['url' => $url]);
    }

    // -------------------------------------------------------------------------
    // Client hosting management (Panelica-only)
    //
    // These power the cPanel-like self-service tabs a customer sees on their
    // service page. They are declared through hostingFeatures() and resolved by
    // method existence, so a feature only appears for a service whose module
    // both lists it and implements it. Other server modules (cPanel, Plesk, and
    // the future Docker/Python/Node modules) ship their own set - none of the
    // methods below leak into them. Every operation is fenced to the account's
    // own resources: the server API key is operator-scoped, so ownership is
    // enforced here, in this module, not assumed from the panel.
    // -------------------------------------------------------------------------

    /**
     * Hosting-management features this service exposes to its owner. A feature
     * key here has a matching client tab and a matching method on this module.
     * Returns [] for a service with no provisioned panel account.
     *
     * Grows one phase at a time (emails first; dns/files/databases/ftp/ssl/
     * subdomains/cron/wordpress to follow). Docker/Python/Node arrive as their
     * own modules with their own feature keys, never here.
     *
     * @return string[]
     */
    public function hostingFeatures(Service $service): array
    {
        if (! $this->linkedAccountId($service)) {
            return [];
        }

        return ['emails', 'files'];
    }

    /** The panel account id linked to this service, or null when unprovisioned. */
    private function linkedAccountId(Service $service): ?string
    {
        $id = $this->getModuleData($service)['panelica_user_id'] ?? null;

        return ($id !== null && $id !== '') ? (string) $id : null;
    }

    /**
     * Best-effort text of an API error, for surfacing back to the customer.
     */
    private function apiMessage(Response $resp, string $fallback): string
    {
        $msg = $resp->json('details') ?? $resp->json('message') ?? $resp->json('error');

        return (is_string($msg) && $msg !== '') ? $msg : $fallback;
    }

    /**
     * The domains this account owns, as [id => name]. The panel scopes this
     * endpoint to the account server-side (user_id = account), so it is the
     * authority on what the customer may touch: every mailbox operation is
     * fenced to a domain id that appears here. A failed call returns [] - no
     * domain, no operation - never another account's list.
     *
     * @return array<string,string> domain id => domain name
     */
    public function accountDomains(Service $service): array
    {
        $server = $this->getServer($service);
        $accountId = $this->linkedAccountId($service);
        if (! $server || ! $accountId) {
            return [];
        }

        $resp = $this->get($server, "/v1/accounts/{$accountId}/domains");
        if (! $resp->successful()) {
            return [];
        }

        $out = [];
        foreach (($resp->json('data') ?? []) as $d) {
            $id = (string) ($d['id'] ?? '');
            $name = (string) ($d['domain_name'] ?? $d['name'] ?? $d['domain'] ?? '');
            if ($id !== '' && $name !== '') {
                $out[$id] = $name;
            }
        }

        return $out;
    }

    /**
     * Email accounts on this service, fenced to the account's own domains. The
     * panel list endpoint answers with whatever the server API key may see (an
     * operator key sees every account on the box), so the account's domain set,
     * not the raw list, decides what the customer is shown. A mailbox on a
     * domain the account does not own is dropped.
     *
     * @return list<array{id:string,email:string,domain_id:string,domain:string,quota_mb:int,used_mb:int,status:string}>
     */
    public function listEmails(Service $service): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return [];
        }
        $domains = $this->accountDomains($service);
        if ($domains === []) {
            return [];
        }

        $resp = $this->get($server, '/v1/email-accounts');
        if (! $resp->successful()) {
            return [];
        }

        $out = [];
        foreach (($resp->json('data') ?? []) as $e) {
            $domainId = (string) ($e['domain_id'] ?? '');
            if (! isset($domains[$domainId])) {
                continue; // not one of this account's domains
            }
            $out[] = [
                'id' => (string) ($e['id'] ?? ''),
                'email' => (string) ($e['email'] ?? ''),
                'domain_id' => $domainId,
                'domain' => $domains[$domainId],
                'quota_mb' => (int) ($e['quota_mb'] ?? 0),
                'used_mb' => (int) ($e['used_quota_mb'] ?? 0),
                'status' => (string) ($e['status'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Create a mailbox under one of this account's domains. The domain id is
     * fenced against accountDomains() before anything is sent, so a forged id
     * for another account's domain never reaches the panel. The panel enforces
     * the plan's mailbox limit itself (403); that is surfaced as a clear
     * message rather than a generic failure.
     */
    public function createEmail(Service $service, string $domainId, string $localPart, string $password, int $quotaMb = 0): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }

        $domains = $this->accountDomains($service);
        if (! isset($domains[$domainId])) {
            return $this->buildResult(false, 'That domain does not belong to this service.');
        }

        $localPart = strtolower(trim($localPart));
        if ($localPart === '' || ! preg_match('/^[a-z0-9._%+\-]+$/', $localPart)) {
            return $this->buildResult(false, 'Enter a valid mailbox name.');
        }

        $payload = [
            'domain_id' => $domainId,
            'username' => $localPart,
            'password' => $password,
        ];
        if ($quotaMb > 0) {
            $payload['quota_mb'] = $quotaMb;
        }

        $resp = $this->post($server, '/v1/email-accounts', $payload);
        if ($resp->status() === 403) {
            return $this->buildResult(false, 'Your plan\'s mailbox limit has been reached.');
        }
        if (! $resp->successful()) {
            Log::error('PanelicaModule::createEmail failed', ['status' => $resp->status(), 'body' => $resp->body()]);

            return $this->buildResult(false, $this->apiMessage($resp, 'Could not create the mailbox.'));
        }

        return $this->buildResult(true, 'Mailbox created.', ['email' => $localPart.'@'.$domains[$domainId]]);
    }

    /**
     * Delete a mailbox. Fenced: the id must belong to one of this account's own
     * mailboxes (listEmails is already domain-scoped), so a forged id for
     * another account's mailbox is refused before any DELETE is sent.
     */
    public function deleteEmail(Service $service, string $emailId): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }
        if (! $this->ownsEmail($service, $emailId)) {
            return $this->buildResult(false, 'That mailbox does not belong to this service.');
        }

        $resp = $this->delete($server, "/v1/email-accounts/{$emailId}");
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not delete the mailbox.'));
        }

        return $this->buildResult(true, 'Mailbox deleted.');
    }

    /**
     * Change a mailbox password. Same ownership fence as deletion.
     */
    public function changeEmailPassword(Service $service, string $emailId, string $password): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }
        if (! $this->ownsEmail($service, $emailId)) {
            return $this->buildResult(false, 'That mailbox does not belong to this service.');
        }

        $resp = $this->post($server, "/v1/email-accounts/{$emailId}/change-password", ['password' => $password]);
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not update the mailbox password.'));
        }

        return $this->buildResult(true, 'Mailbox password updated.');
    }

    /** Whether emailId is one of this account's own mailboxes (domain-fenced). */
    private function ownsEmail(Service $service, string $emailId): bool
    {
        if ($emailId === '') {
            return false;
        }
        foreach ($this->listEmails($service) as $e) {
            if ($e['id'] === $emailId) {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Client file manager (Panelica-only)
    //
    // Scoped two ways for every operation: user_id is fixed to THIS service's
    // own account (the customer never supplies it), and the panel fences every
    // path to that account's home directory server-side - a path crafted to
    // escape home is refused with 403. So a customer can only ever see and touch
    // files under their own /home/<user>. Binary upload is deferred until the
    // panel exposes a multipart endpoint; this covers browse/read/edit/create/
    // rename/delete/download - the cPanel-essential core.
    // -------------------------------------------------------------------------

    /**
     * GET with a signed query string. The external API signs METHOD+PATH+?QUERY
     * +TS+BODY, and the query must be byte-identical to what goes on the wire.
     * PHP_QUERY_RFC3986 encoding matches what Guzzle sends (verified live), so
     * the signature holds for paths that contain slashes and spaces.
     */
    private function getWithQuery(Server $server, string $basePath, array $query): Response
    {
        $qs = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $fullPath = $basePath.($qs !== '' ? '?'.$qs : '');
        $headers = $this->buildHeaders($server, 'GET', $fullPath, '');

        return Http::withHeaders($headers)->withoutVerifying()->get($this->baseUrl($server).$fullPath);
    }

    private function put(Server $server, string $path, array $payload): Response
    {
        $body = json_encode($payload);
        $headers = $this->buildHeaders($server, 'PUT', $path, $body);

        return Http::withHeaders($headers)->withoutVerifying()->withBody($body, 'application/json')->put($this->baseUrl($server).$path);
    }

    /** DELETE carrying a JSON body. The panel excludes the DELETE body from the signature. */
    private function deleteWithBody(Server $server, string $path, array $payload): Response
    {
        $body = json_encode($payload);
        $headers = $this->buildHeaders($server, 'DELETE', $path, '');

        return Http::withHeaders($headers)->withoutVerifying()->withBody($body, 'application/json')->delete($this->baseUrl($server).$path);
    }

    /** The account's home directory - the root of everything the file tab shows. */
    private function filesHome(Service $service): string
    {
        return '/home/'.($service->username ?: 'user');
    }

    /**
     * List a directory under the account's home.
     *
     * @return array{ok:bool,path:string,home:string,entries:array,message:string}
     */
    public function listFiles(Service $service, ?string $path = null): array
    {
        $server = $this->getServer($service);
        $accountId = $this->linkedAccountId($service);
        $home = $this->filesHome($service);
        if (! $server || ! $accountId) {
            return ['ok' => false, 'path' => $home, 'home' => $home, 'entries' => [], 'message' => 'No panel account is linked to this service.'];
        }

        $query = ['user_id' => $accountId];
        if ($path !== null && $path !== '') {
            $query['path'] = $path;
        }
        $resp = $this->getWithQuery($server, '/v1/files', $query);
        if (! $resp->successful()) {
            return ['ok' => false, 'path' => $home, 'home' => $home, 'entries' => [], 'message' => $this->apiMessage($resp, 'Could not open that folder.')];
        }

        $data = $resp->json('data') ?? [];

        return [
            'ok' => true,
            'path' => (string) ($data['path'] ?? $home),
            'home' => $home,
            'entries' => is_array($data['files'] ?? null) ? $data['files'] : [],
            'message' => '',
        ];
    }

    /** Read a text file's content for the editor. */
    public function readFile(Service $service, string $path): array
    {
        [$server, $accountId] = $this->fileContext($service);
        if (! $server) {
            return $this->buildResult(false, 'No panel account is linked to this service.');
        }

        $resp = $this->getWithQuery($server, '/v1/files/content', ['user_id' => $accountId, 'path' => $path]);
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not read that file.'));
        }
        $data = $resp->json('data');
        $content = is_array($data) ? ($data['content'] ?? '') : ($resp->json('content') ?? '');

        return $this->buildResult(true, 'ok', ['content' => (string) $content, 'path' => $path]);
    }

    public function writeFile(Service $service, string $path, string $content): array
    {
        [$server, $accountId] = $this->fileContext($service);
        if (! $server) {
            return $this->buildResult(false, 'No panel account is linked to this service.');
        }

        $resp = $this->put($server, '/v1/files/content', ['user_id' => $accountId, 'path' => $path, 'content' => $content]);
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not save the file.'));
        }

        return $this->buildResult(true, 'File saved.');
    }

    /** Create a folder ($type='folder') or an empty text file ($type='file') in $path. */
    public function createEntry(Service $service, string $path, string $name, string $type, string $content = ''): array
    {
        [$server, $accountId] = $this->fileContext($service);
        if (! $server) {
            return $this->buildResult(false, 'No panel account is linked to this service.');
        }
        $name = trim($name);
        if ($name === '' || str_contains($name, '/') || str_contains($name, "\0") || $name === '.' || $name === '..') {
            return $this->buildResult(false, 'Enter a valid name.');
        }
        $type = $type === 'folder' ? 'folder' : 'file';

        $resp = $this->post($server, '/v1/files', array_filter([
            'user_id' => $accountId,
            'path' => $path,
            'name' => $name,
            'type' => $type,
            'content' => $content !== '' ? $content : null,
        ], fn ($v) => $v !== null));
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not create that item.'));
        }

        return $this->buildResult(true, $type === 'folder' ? 'Folder created.' : 'File created.');
    }

    public function renameEntry(Service $service, string $path, string $newName): array
    {
        [$server, $accountId] = $this->fileContext($service);
        if (! $server) {
            return $this->buildResult(false, 'No panel account is linked to this service.');
        }
        $newName = trim($newName);
        if ($newName === '' || str_contains($newName, '/') || str_contains($newName, "\0") || $newName === '.' || $newName === '..') {
            return $this->buildResult(false, 'Enter a valid name.');
        }

        $resp = $this->patch($server, '/v1/files/rename', ['user_id' => $accountId, 'path' => $path, 'new_name' => $newName]);
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not rename that item.'));
        }

        return $this->buildResult(true, 'Renamed.');
    }

    /** Move one or more paths to trash (or permanently). Paths are fenced to home by the panel. */
    public function deleteEntries(Service $service, array $paths, bool $permanent = false): array
    {
        [$server, $accountId] = $this->fileContext($service);
        if (! $server) {
            return $this->buildResult(false, 'No panel account is linked to this service.');
        }
        $paths = array_values(array_filter(array_map('strval', $paths), fn ($p) => $p !== ''));
        if ($paths === []) {
            return $this->buildResult(false, 'Nothing to delete.');
        }

        $resp = $this->deleteWithBody($server, '/v1/files', ['user_id' => $accountId, 'paths' => $paths, 'permanent' => $permanent]);
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not delete.'));
        }

        return $this->buildResult(true, 'Deleted.');
    }

    /** Raw download response for the controller to stream. Null when unavailable. */
    public function downloadFile(Service $service, string $path): ?Response
    {
        [$server, $accountId] = $this->fileContext($service);
        if (! $server) {
            return null;
        }

        $resp = $this->getWithQuery($server, '/v1/files/download', ['user_id' => $accountId, 'path' => $path]);

        return $resp->successful() ? $resp : null;
    }

    /** [server, accountId] or [null, null] when the service has no linked account. */
    private function fileContext(Service $service): array
    {
        $server = $this->getServer($service);
        $accountId = $this->linkedAccountId($service);

        return ($server && $accountId) ? [$server, $accountId] : [null, null];
    }

    public function testConnection(Server $server): bool
    {
        try {
            $resp = $this->get($server, '/v1/server/status');

            if (! $resp->successful()) {
                Log::warning('PanelicaModule::testConnection HTTP error', ['status' => $resp->status(), 'body' => $resp->body()]);

                return false;
            }

            $body = $resp->json();
            $status = $body['data']['status'] ?? $body['status'] ?? '';

            return strtolower($status) === 'online';
        } catch (\Throwable $e) {
            Log::error('PanelicaModule::testConnection exception', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
