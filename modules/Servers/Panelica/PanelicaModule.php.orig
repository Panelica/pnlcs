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
    /** Installing an app pulls images; a multi-container template takes minutes. */
    private const DEPLOY_TIMEOUT = 300;

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
     * The accounts that already exist on the panel, so an operator migrating a
     * customer in can link a billing service to the real account instead of
     * creating a new one. Each entry carries the panel's user id (what suspend/
     * terminate address) plus a human label. A failure returns an empty list.
     *
     * @return array<int, array{id: string, username: string, email: string, status: string}>
     */
    public function listAccounts(Server $server): array
    {
        try {
            $resp = $this->get($server, '/v1/accounts');
            if (! $resp->successful()) {
                return [];
            }

            $out = [];
            foreach (($resp->json('data') ?? []) as $a) {
                $id = (string) ($a['id'] ?? '');
                if ($id === '' || strtoupper((string) ($a['role'] ?? 'USER')) !== 'USER') {
                    continue;
                }
                $out[] = [
                    'id' => $id,
                    'username' => (string) ($a['username'] ?? ''),
                    'email' => (string) ($a['email'] ?? ''),
                    'status' => (string) ($a['status'] ?? ''),
                ];
            }

            usort($out, fn ($x, $y) => strcasecmp($x['username'], $y['username']));

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
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

    /**
     * @param  int  $timeout  seconds; the default suits ordinary calls, but
     *                        installing an app pulls images and can take minutes
     */
    private function post(Server $server, string $path, array $payload, int $timeout = 30): Response
    {
        $body = json_encode($payload);
        $headers = $this->buildHeaders($server, 'POST', $path, $body);

        return Http::withHeaders($headers)->withoutVerifying()->timeout($timeout)->withBody($body, 'application/json')->post($this->baseUrl($server).$path);
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
            // The panel requires a slug of its own; sending only a name fails
            // validation, which meant managed products never provisioned at all
            // ("Managed plan could not be prepared on the panel").
            $resp = $this->post($server, '/v1/plans', array_merge($spec['basic'], [
                'name' => $name,
                'slug' => $name,
            ]));
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

    /**
     * The product's module config, however it happens to be stored.
     *
     * @return array<string, mixed>
     */
    private function productConfigFor(Service $service): array
    {
        $raw = $service->product?->config_options;
        $config = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($config) ? $config : [];
    }


    /**
     * A per-service address for a customer who already has an account here.
     *
     * The panel keeps one account per email. A customer on their second plan is
     * a normal thing, so the address is tagged rather than the order refused;
     * mail to user+pnlcs7@example.com arrives in user@example.com's inbox.
     */
    private function taggedEmail(string $email, int $serviceId): string
    {
        $at = strrpos($email, '@');
        if ($at === false || $serviceId <= 0) {
            return $email;
        }
        $local = substr($email, 0, $at);
        // Do not stack tags if one is already there.
        if (($plus = strpos($local, '+')) !== false) {
            $local = substr($local, 0, $plus);
        }

        return $local.'+pnlcs'.$serviceId.substr($email, $at);
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

        // Resolve plan ID from product config
        $config = $this->productConfigFor($service);

        // Container Plan: sold as a pool of container resources, not a website.
        // Only these products may be provisioned without a domain - for every
        // other product a missing domain is an order that would silently open
        // an account nobody can use, which is worth refusing.
        $isContainerPlan = ! empty($config['panelica_container_plan']);

        if (! $client || (! $domain && ! $isContainerPlan)) {
            return $this->buildResult(false, 'Service is missing client or domain.');
        }

        // Pre-flight: a product that will install an app while its own plan
        // grants zero containers cannot succeed - the panel refuses the app
        // after the account exists, the account is rolled back, and the reason
        // surfaces as a log line three screens away. Live, this ran three
        // times and left orphaned home directories behind. Refuse here, in
        // words the operator can act on, before anything is created.
        $willInstallApp = ! empty($config['panelica_app_template'])
            || trim((string) ($this->getModuleData($service)['panelica_app_template'] ?? '')) !== '';
        if (($isContainerPlan || $willInstallApp)
            && ! empty($config['res_managed'])
            && (int) ($config['res_max_containers'] ?? 0) === 0) {
            return $this->buildResult(false, __('admin.products.container_plan_needs_containers'));
        }

        // Pre-flight: refuse before creating anything if the domain already
        // exists on the server. Otherwise the account gets created, the domain
        // POST fails, and the account is rolled back - the operator only learns
        // "domain already exists" after the fact. Checking first means no
        // orphaned account and the reason is known up front.
        if ($domain && $this->domainExistsOnServer($server, $domain)) {
            return $this->buildResult(false, "The domain \"{$domain}\" already exists on this server. Use a different domain, or remove it from the panel first.");
        }

        // Derive username from domain (alphanumeric, max 16 chars); a container
        // plan has no domain to derive it from, so the client's name is used.
        $usernameSeed = $domain
            ? explode('.', $domain)[0]
            : ($client->first_name.$client->last_name ?: (string) $client->email);
        $username = preg_replace('/[^a-z0-9]/', '', strtolower($usernameSeed));
        $username = substr($username ?: 'user', 0, 16).rand(100, 999);

        $password = $service->password ?: bin2hex(random_bytes(8));

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

        // A customer buying a second plan hits the panel's unique-email rule and
        // the order dies with "emailAlreadyExists" - which reads as a bug in our
        // billing, not as a rule. Their first account keeps the plain address;
        // later ones get a tagged one that still delivers to the same inbox.
        if (! $accountResp->successful() && str_contains($accountResp->body(), 'emailAlreadyExists')) {
            $accountPayload['email'] = $this->taggedEmail((string) $client->email, (int) $service->id);
            Log::info('PanelicaModule::create retrying with a tagged address', ['service' => $service->id, 'email' => $accountPayload['email']]);
            $accountResp = $this->post($server, '/v1/accounts', $accountPayload);
        }

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

        // Step 2: Create domain (a container plan has none)
        $domainId = null;
        if ($domain) {
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
        }

        $moduleData = [
            'panelica_user_id' => $userId,
            'panelica_domain_id' => $domainId,
        ];

        // App Hosting: the product sells one app, so the order has to deliver
        // it. Handing over an account without it would be reporting success on
        // a service the customer cannot use, so a failed install rolls the
        // account back the same way a failed domain does.
        // What the customer chose while ordering wins over the product's own
        // fixed app: a "pick your app" product has no fixed one, and a customer
        // who chose must get what they paid for.
        $chosen = trim((string) ($this->getModuleData($service)['panelica_app_template'] ?? ''));
        $appSlug = $chosen !== '' ? $chosen : trim((string) ($config['panelica_app_template'] ?? ''));
        if ($appSlug !== '') {
            $app = $this->installProductApp($service, $server, $userId, $domainId, $appSlug, $username, $password);
            if (! $app['success']) {
                $this->delete($server, "/v1/accounts/{$userId}");
                Log::error('PanelicaModule::create app install failed (account rolled back)', ['user_id' => $userId, 'slug' => $appSlug, 'reason' => $app['message']]);

                // The panel's refusal code is for machines. When it says the
                // plan forbids Docker, say what to change instead.
                $reason = str_contains((string) $app['message'], 'disabledInPlan')
                    ? __('admin.products.container_plan_needs_containers')
                    : $app['message'];

                return $this->buildResult(false, "App installation failed (account rolled back): {$reason}");
            }
            $moduleData['panelica_app_container_id'] = $app['container_id'];
            $moduleData['panelica_app_template'] = $appSlug;
        }

        // Persist module data
        $this->setModuleData($service, $moduleData);

        // r134-credentials: keep the password the account was given.
        //
        // This wrote back only the username, so the password made up a few
        // lines above existed nowhere afterwards - not on the service, not in
        // the welcome email. The customer had an account they could not sign
        // in to and nobody could tell them the password. cPanel, Plesk,
        // DirectAdmin and HestiaCP all store it, and so does the provisioning
        // service when the password is changed later.
        $service->update(['username' => $username, 'password' => $password, 'status' => 'active']);

        $created = array_filter([$domain ? 'domain' : null, $appSlug !== '' ? $appSlug : null]);
        $result = $this->buildResult(true, $created === []
            ? 'Account created successfully.'
            : 'Account, '.implode(' and ', $created).' created successfully.', $moduleData);
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

        // A container plan has no website, so every domain-based tool would open
        // on an empty list. Show the one thing the product actually sells.
        $config = $this->productConfigFor($service);
        if (! empty($config['panelica_container_plan'])) {
            return ['containers'];
        }

        return ['emails', 'files', 'databases', 'ftp', 'subdomains', 'cron', 'dns', 'backups', 'containers', 'laravel', 'nodejs', 'python'];
    }

    /**
     * The account's own runtime applications (Laravel / Node.js / Python).
     *
     * The panel hosts these natively, separate from Docker containers, and
     * PNLCS never showed them - a customer who bought a Laravel/Node/Python
     * account saw only their Docker apps under "My Services". The panel's
     * list endpoint answers with everything the API key may see and the PNLCS
     * key is ROOT-scoped, so the app's owner_user_id (which the module records
     * as the account's panelica_user_id) is what decides what the customer is
     * shown - exactly how containers() filters on panelica.user_id.
     *
     * Read-only on purpose: creating and deploying apps stays in the control
     * panel, where the git/upload/deploy flow and its warnings live.
     *
     * @return list<array{id:string,name:string,domain:string,status:string,url:string,version:string,framework:string}>
     */
    private function runtimeApps(Service $service, string $type): array
    {
        $server = $this->getServer($service);
        $accountId = $this->linkedAccountId($service);
        if (! $server || ! $accountId) {
            return [];
        }

        $resp = $this->get($server, "/v1/{$type}/apps");
        if (! $resp->successful()) {
            return [];
        }

        $out = [];
        foreach (($resp->json('data') ?? []) as $a) {
            if (! is_array($a)) {
                continue;
            }
            // Not this account's app. The PNLCS key sees every app on the
            // server; the owner is the only thing that makes one the
            // customer's own.
            if ((string) ($a['owner_user_id'] ?? '') !== (string) $accountId) {
                continue;
            }
            $out[] = [
                'id' => (string) ($a['id'] ?? ''),
                'name' => (string) ($a['name'] ?? ''),
                'domain' => (string) ($a['domain'] ?? ''),
                'status' => strtolower((string) ($a['status'] ?? '')),
                'url' => (string) ($a['app_url'] ?? $a['url'] ?? ''),
                // One "version" column whatever the runtime calls it.
                'version' => (string) ($a['php_version'] ?? $a['node_version'] ?? $a['python_version'] ?? ''),
                'framework' => (string) ($a['framework'] ?? ''),
            ];
        }

        return $out;
    }

    /** @return list<array<string,string>> */
    public function laravelApps(Service $service): array
    {
        return $this->runtimeApps($service, 'laravel');
    }

    /** @return list<array<string,string>> */
    public function nodejsApps(Service $service): array
    {
        return $this->runtimeApps($service, 'nodejs');
    }

    /** @return list<array<string,string>> */
    public function pythonApps(Service $service): array
    {
        return $this->runtimeApps($service, 'python');
    }

    // -------------------------------------------------------------------------
    // Backups (Panelica-only). The panel takes per-domain archives; this exposes
    // the account's own restore points and lets it take a fresh one when the plan
    // allows. Restore is deliberately NOT offered here — rolling a site back from
    // a billing panel silently discards everything written since, so it stays in
    // the panel where the warnings and the file-level picker live.
    // -------------------------------------------------------------------------

    /**
     * Restore points covering the account's own domains.
     *
     * The panel's list endpoint answers with everything the API key may see, and
     * the PNLCS key is ROOT-scoped — so the account's domain set, not the raw
     * list, decides what the customer is shown.
     *
     * @return list<array{id:string,filename:string,name:string,size_mb:float,domains:list<string>,created_at:string,status:string,type:string,encrypted:bool}>
     */
    public function backups(Service $service): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return [];
        }
        $mine = array_map('strtolower', array_values($this->accountDomains($service)));
        if ($mine === []) {
            return [];
        }
        $resp = $this->get($server, '/v1/backups');
        if (! $resp->successful()) {
            return [];
        }
        $out = [];
        foreach (($resp->json('data') ?? []) as $b) {
            $domains = array_map('strtolower', (array) ($b['domain_names'] ?? []));
            // Only archives made of this account's domains. An archive that also
            // covers someone else's domain is not this customer's to see.
            if ($domains === [] || array_diff($domains, $mine) !== []) {
                continue;
            }
            $out[] = [
                'id' => (string) ($b['backup_id'] ?? ''),
                'filename' => (string) ($b['filename'] ?? ''),
                'name' => (string) ($b['backup_name'] ?? $b['schedule_name'] ?? ''),
                'size_mb' => (float) ($b['size_mb'] ?? 0),
                'domains' => $domains,
                'created_at' => (string) ($b['created_at'] ?? ''),
                'status' => (string) ($b['status'] ?? ''),
                'type' => (string) ($b['backup_type'] ?? 'full'),
                'encrypted' => (bool) ($b['encrypted'] ?? false),
            ];
        }
        usort($out, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return $out;
    }

    /**
     * @return array{enabled:bool,count:int,can_create:bool}
     */
    public function backupPolicy(Service $service): array
    {
        $count = count($this->backups($service));
        $enabled = $this->planField($service, 'backup_enabled');
        // Unknown plan → let the panel be the authority (it enforces too).
        $isEnabled = $enabled === null ? true : (bool) $enabled;

        return ['enabled' => $isEnabled, 'count' => $count, 'can_create' => $isEnabled];
    }

    /**
     * Take a fresh restore point. Without an explicit domain list the panel
     * would back up everything the (ROOT) key can see, so the account's own
     * domains are always passed explicitly.
     */
    public function createBackup(Service $service, ?string $domainId = null, string $name = ''): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }
        if (! $this->backupPolicy($service)['enabled']) {
            return $this->buildResult(false, 'Backups are not included in your current plan.');
        }
        $domains = $this->accountDomains($service);
        if ($domains === []) {
            return $this->buildResult(false, 'There are no domains to back up yet.');
        }
        if ($domainId !== null && $domainId !== '') {
            if (! isset($domains[$domainId])) {
                return $this->buildResult(false, 'That domain does not belong to this service.');
            }
            $ids = [$domainId];
        } else {
            $ids = array_keys($domains);
        }

        $payload = ['domain_ids' => array_values($ids)];
        if (trim($name) !== '') {
            $payload['backup_name'] = trim($name);
        }
        $resp = $this->post($server, '/v1/backups', $payload);
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not start the backup.'));
        }

        return $this->buildResult(true, 'Backup created.');
    }

    public function deleteBackup(Service $service, string $filename): array
    {
        if ($filename === '' || ! $this->ownsBackup($service, $filename)) {
            return $this->buildResult(false, 'That backup does not belong to this service.');
        }
        $resp = $this->delete($this->getServer($service), '/v1/backups/'.rawurlencode($filename));
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not delete the backup.'));
        }

        return $this->buildResult(true, 'Backup deleted.');
    }

    // -------------------------------------------------------------------------
    // Containers (Panelica-only). Docker apps the customer runs on their hosting.
    // Each container is placed in the account's own cgroup slice (CPU/RAM come out
    // of the plan), its volumes live under the account's home (disk quota), and the
    // panel strips privileged/capabilities on the external path — so a container is
    // just another thing the account owns, not a way out of it.
    // -------------------------------------------------------------------------

    /**
     * Containers belonging to this account.
     *
     * The panel list is scoped by the API key, and the PNLCS key is ROOT-scoped —
     * it can see every container on the host. So the account's own id, taken from
     * the container's ownership label, is what decides the list, never the raw
     * response.
     *
     * @return list<array{id:string,name:string,image:string,state:string,status:string,cpu_percent:float,mem_usage:int,mem_limit:int,ports:list<string>,created:string,template:string}>
     */
    /** How many containers to inspect for a single page render. */
    private const LIVE_ACCESS_MAX = 12;

    public function containers(Service $service): array
    {
        $server = $this->getServer($service);
        $accountId = $this->linkedAccountId($service);
        if (! $server || ! $accountId) {
            return [];
        }
        $resp = $this->get($server, '/v1/docker/containers');
        if (! $resp->successful()) {
            return [];
        }

        $out = [];
        foreach (($resp->json('data.containers') ?? $resp->json('data') ?? []) as $c) {
            $labels = (array) ($c['labels'] ?? []);
            if ((string) ($labels['panelica.user_id'] ?? '') !== (string) $accountId) {
                continue; // not this account's container
            }
            // The panel names these host_port/container_port. Reading only Docker's
            // own PublicPort/PrivatePort spelling meant this list came back empty
            // every time and the customer saw no port at all.
            $ports = [];
            foreach ((array) ($c['ports'] ?? []) as $p) {
                $pub = $p['host_port'] ?? $p['public_port'] ?? $p['PublicPort'] ?? null;
                $priv = $p['container_port'] ?? $p['private_port'] ?? $p['PrivatePort'] ?? null;
                if ((string) $pub === '' || (string) $priv === '') {
                    continue;   // not published outside the container
                }
                $label = $pub.' → '.$priv;
                if (! in_array($label, $ports, true)) {
                    $ports[] = $label;   // tcp and udp of one mapping read the same
                }
            }
            $out[] = [
                'id' => (string) ($c['id'] ?? ''),
                'name' => ltrim((string) ($c['name'] ?? ''), '/'),
                'image' => (string) ($c['image'] ?? ''),
                'state' => strtolower((string) ($c['state'] ?? '')),
                'status' => (string) ($c['status'] ?? ''),
                'cpu_percent' => (float) ($c['cpu_percent'] ?? 0),
                'mem_usage' => (int) ($c['mem_usage'] ?? 0),
                'mem_limit' => (int) ($c['mem_limit'] ?? 0),
                'ports' => $ports,
                'created' => (string) ($c['created'] ?? ''),
                'template' => (string) ($labels['panelica.template'] ?? ''),
                // A template can deploy several containers as one app - the
                // panel marks them with a shared stack label and gives the
                // helpers (mysql, redis) a role. The app itself has no role.
                // Without these two fields the customer was shown three raw
                // containers for one WordPress and could delete its database
                // on its own.
                'stack' => (string) ($labels['panelica.stack'] ?? ''),
                'role' => (string) ($labels['panelica.template.role'] ?? ''),
            ];
        }
        usort($out, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $out;
    }

    /**
     * The other members of a container's stack, main app first.
     *
     * @param  list<array<string,mixed>>  $all  output of containers()
     * @return list<array<string,mixed>>
     */
    private function stackMembers(array $all, string $stack): array
    {
        if ($stack === '') {
            return [];
        }
        $members = array_values(array_filter($all, fn ($c) => ($c['stack'] ?? '') === $stack));
        usort($members, fn ($a, $b) => strcmp((string) ($a['role'] ?? ''), (string) ($b['role'] ?? '')));

        return $members; // role '' (the app) sorts first
    }

    /**
     * @return array{max:int,used:int,can_create:bool,enabled:bool}
     */
    public function containerPolicy(Service $service): array
    {
        $used = count($this->containers($service));
        $max = $this->planLimit($service, 'max_containers');
        // Unknown plan → let the panel be the authority (it enforces the limit and
        // the catalogue on every deploy anyway).
        if ($max === null) {
            return ['max' => -1, 'used' => $used, 'can_create' => true, 'enabled' => true];
        }
        if ($max === 0) {
            return ['max' => 0, 'used' => $used, 'can_create' => false, 'enabled' => false];
        }

        return ['max' => $max, 'used' => $used, 'can_create' => $max < 0 || $used < $max, 'enabled' => true];
    }

    /**
     * The app catalogue this account may install from. The panel filters it by the
     * account's plan, so a curated plan returns only its own apps and a plan with
     * no containers returns nothing.
     *
     * @return list<array{slug:string,name:string,description:string,logo_url:string,categories:list<string>}>
     */
    public function containerTemplates(Service $service): array
    {
        $server = $this->getServer($service);
        $accountId = $this->linkedAccountId($service);
        if (! $server || ! $accountId) {
            return [];
        }
        $resp = $this->get($server, '/v1/docker/templates?owner_user_id='.urlencode($accountId));
        if (! $resp->successful()) {
            return [];
        }
        $out = [];
        foreach (($resp->json('data.templates') ?? []) as $t) {
            $out[] = [
                'slug' => (string) ($t['slug'] ?? ''),
                'name' => (string) ($t['name'] ?? ''),
                'description' => (string) ($t['description'] ?? ''),
                'logo_url' => (string) ($t['logo_url'] ?? ''),
                'categories' => array_values((array) ($t['categories'] ?? [])),
                // What the app needs to run. Carried through so the page can say
                // whether the plan can actually hold it, instead of letting the
                // customer install something that will be starved of memory.
                'min_memory_mb' => (int) ($t['min_memory_mb'] ?? 0),
                'min_cpu_percent' => (int) ($t['min_cpu_percent'] ?? 0),
                'is_popular' => (bool) ($t['is_popular'] ?? false),
                'extra_services' => $this->linkedServiceCount($t),
                'website_url' => (string) ($t['website_url'] ?? ''),
                'documentation_url' => (string) ($t['documentation_url'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * The whole app catalogue on a server, for the admin product form.
     *
     * containerTemplates() is the customer's view and is filtered by their
     * plan; an operator building a product has to be able to pick anything the
     * server offers, including apps no plan exposes yet.
     */

    /**
     * How many extra containers a template starts alongside the main one.
     *
     * It matters to the customer: the memory floor applies to the main
     * container, but the helpers - a database, a cache, a machine-learning
     * worker - run in the same account slice and draw on the same allowance.
     * A card that says "2 GB" while quietly starting four containers is not
     * telling the truth about what the app costs.
     */
    private function linkedServiceCount(array $t): int
    {
        $ls = $t['linked_services'] ?? null;
        if (is_string($ls)) {
            $ls = json_decode($ls, true);
        }

        return is_array($ls) ? count($ls) : 0;
    }

    public function appTemplates(Server $server): array
    {
        $resp = $this->get($server, '/v1/docker/templates');
        if (! $resp->successful()) {
            return [];
        }
        $out = [];
        foreach (($resp->json('data.templates') ?? []) as $t) {
            $slug = (string) ($t['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $out[] = [
                'slug' => $slug,
                'name' => (string) ($t['name'] ?? $slug),
                'description' => (string) ($t['description'] ?? ''),
                'categories' => array_values((array) ($t['categories'] ?? [])),
                'min_memory_mb' => (int) ($t['min_memory_mb'] ?? 0),
                'min_cpu_percent' => (int) ($t['min_cpu_percent'] ?? 0),
                // How the catalogue orders itself before an operator says
                // anything: how often an app has actually been installed, then
                // the panel's own popular flag.
                'is_popular' => (bool) ($t['is_popular'] ?? false),
                'deploy_count' => (int) ($t['deploy_count'] ?? 0),
                'extra_services' => $this->linkedServiceCount($t),
                // The panel's own link. Used only as a starting point when
                // filling in images; the catalogue renders ours.
                'logo_url' => (string) ($t['logo_url'] ?? ''),
            ];
        }
        usort($out, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $out;
    }

    /**
     * App Hosting: install the product's app and serve it on the customer's domain.
     *
     * Returns the container id on success. Deploy puts the container in the
     * account's cgroup slice and under its plan limits; the link step writes the
     * vhost that proxies the domain to it. A container that exists but is not
     * reachable on the domain is not the product the customer bought, so a
     * failed link takes the container back down with it.
     */

    /** A container name derived from the app slug, for when nobody supplied one. */
    private function defaultContainerName(string $slug): string
    {
        $name = strtolower(preg_replace('/[^a-z0-9-]/i', '-', $slug));
        $name = trim(preg_replace('/-+/', '-', $name), '-');

        return substr($name ?: 'app', 0, 40);
    }

    /**
     * Values for the template's REQUIRED env vars, taken from the account.
     *
     * The order form never asks for env vars, so a template that requires a
     * sign-in of its own (the cloud desktop's VNC_USER/VNC_PASSWORD) failed
     * validation and rolled the whole order back. Nothing is invented here:
     * the account's own username and password are reused - the welcome email
     * already carries them and the Apps page shows them, so the customer has
     * one sign-in. Templates without such fields get an empty map and deploy
     * exactly as before.
     *
     * @return array<string, string>
     */
    private function requiredEnvFor(Server $server, string $slug, string $username, string $password): array
    {
        $resp = $this->get($server, '/v1/docker/templates/'.rawurlencode($slug));
        if (! $resp->successful()) {
            return [];
        }
        $tpl = $resp->json('data.template') ?? $resp->json('data') ?? [];
        $vars = $tpl['env_vars'] ?? [];
        if (is_string($vars)) {
            $vars = json_decode($vars, true) ?: [];
        }

        $env = [];
        foreach ((array) $vars as $var) {
            if (! is_array($var) || empty($var['required'])) {
                continue;
            }
            $key = (string) ($var['key'] ?? '');
            if ($key === '' || (string) ($var['default'] ?? '') !== '') {
                continue;
            }
            if ($username !== '' && preg_match('/USER(NAME)?$/i', $key)) {
                $env[$key] = $username;
            } elseif ($password !== '' && (! empty($var['secret']) || preg_match('/(PASS(WORD)?|SECRET)$/i', $key))) {
                $env[$key] = $password;
            }
        }

        return $env;
    }

    private function installProductApp(Service $service, Server $server, string $userId, ?string $domainId, string $slug, string $appUser = '', string $appPass = ''): array
    {
        // The panel requires a name; without one the deploy fails validation and
        // the whole order rolls back. The slug is the obvious default - the
        // panel prefixes it with the account's username, so it stays unique
        // across tenants.
        $generatedEnv = $this->requiredEnvFor($server, $slug, $appUser, $appPass);
        $deployPayload = [
            'owner_user_id' => $userId,
            'container_name' => $this->defaultContainerName($slug),
        ];
        if ($generatedEnv !== []) {
            $deployPayload['env'] = $generatedEnv;
        }
        $deploy = $this->post($server, '/v1/docker/templates/'.rawurlencode($slug).'/deploy', $deployPayload, timeout: self::DEPLOY_TIMEOUT);
        if (! $deploy->successful()) {
            return ['success' => false, 'message' => $this->apiMessage($deploy, 'the app could not be installed'), 'container_id' => null];
        }
        $containerId = (string) ($deploy->json('data.container_id') ?? $deploy->json('container_id') ?? '');
        if ($containerId === '') {
            return ['success' => false, 'message' => 'the panel did not report a container id', 'container_id' => null];
        }
        // The sign-in values were invented here, not by the panel - if they are
        // not written down with the rest, the customer has an app they cannot
        // open and nobody can tell them the password.
        $accessData = $deploy->json('data') ?? [];
        if ($generatedEnv !== []) {
            $accessData['credentials'] = array_merge($generatedEnv, (array) ($accessData['credentials'] ?? []));
        }
        $this->rememberAppAccess($service, $slug, $accessData);

        if (! $domainId) {
            // Nothing to point at it; the app is still installed and usable.
            return ['success' => true, 'message' => 'installed', 'container_id' => $containerId];
        }

        // A freshly created container needs a moment before it reports running,
        // and the panel refuses to link one that is not. Give it that moment
        // rather than failing an install that was actually fine.
        $link = null;
        foreach ([0, 2, 4] as $wait) {
            if ($wait > 0) {
                sleep($wait);
            }
            $link = $this->post($server, '/v1/docker/domains/link', [
                'domain_id' => $domainId,
                'container_id' => $containerId,
                'owner_user_id' => $userId,
            ]);
            if ($link->successful()) {
                return ['success' => true, 'message' => 'installed', 'container_id' => $containerId];
            }
        }

        $this->delete($server, "/v1/docker/containers/{$containerId}?owner_user_id=".urlencode($userId));

        return ['success' => false, 'message' => $this->apiMessage($link, 'the app could not be served on the domain'), 'container_id' => null];
    }

    public function deployContainer(Service $service, string $slug, string $name = ''): array
    {
        $server = $this->getServer($service);
        $accountId = $this->linkedAccountId($service);
        if (! $server || ! $accountId) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }
        $slug = trim($slug);
        if ($slug === '' || ! preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $slug)) {
            return $this->buildResult(false, 'Choose an app to install.');
        }
        $policy = $this->containerPolicy($service);
        if (! $policy['enabled']) {
            return $this->buildResult(false, 'Containers are not included in your current plan.');
        }
        if (! $policy['can_create']) {
            return $this->buildResult(false, 'You have reached your plan\'s container limit.');
        }
        // Only offer what the plan actually allows: the panel refuses anything else,
        // and failing here gives the customer a sentence instead of a 500.
        $allowed = array_column($this->containerTemplates($service), 'slug');
        if ($allowed !== [] && ! in_array($slug, $allowed, true)) {
            return $this->buildResult(false, 'That app is not available on your plan.');
        }

        $name = strtolower(trim($name));
        if ($name !== '' && ! preg_match('/^[a-z0-9][a-z0-9-]{0,40}$/', $name)) {
            return $this->buildResult(false, 'Use letters, numbers and hyphens for the name.');
        }
        // A name is required by the panel, so an empty box means "name it after
        // the app" rather than a validation error the customer cannot act on.
        $payload = [
            'owner_user_id' => $accountId,
            'container_name' => $name !== '' ? $name : $this->defaultContainerName($slug),
        ];

        // Installing pulls images - a multi-container app can take minutes, and
        // the default 30 seconds turned a working install into a 500 page while
        // the panel carried on building it.
        try {
            $resp = $this->post($server, '/v1/docker/templates/'.rawurlencode($slug).'/deploy', $payload, timeout: self::DEPLOY_TIMEOUT);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return $this->buildResult(false, 'The install is taking longer than expected and is still running on the server. Check back in a few minutes.');
        }
        if ($resp->status() === 403) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Your plan does not allow this.'));
        }
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not install the app.'));
        }

        // The panel hands out the address and any generated login exactly once,
        // in this response. Without keeping it the customer is left with a
        // running app and no idea what to open or what password it made.
        $this->rememberAppAccess($service, $slug, $resp->json('data') ?? []);

        return $this->buildResult(true, 'App installed.');
    }

    /**
     * Serve one of the account's own apps on one of its own domains.
     *
     * Both sides are checked here and again by the panel, which refuses to link
     * across accounts when the caller names the account - our key is
     * operator-scoped and would otherwise be allowed to point anybody's domain
     * anywhere.
     */
    public function linkContainerDomain(Service $service, string $containerId, string $domainId): array
    {
        $server = $this->getServer($service);
        $accountId = $this->linkedAccountId($service);
        if (! $server || ! $accountId) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }
        if (! $this->ownsContainer($service, $containerId)) {
            return $this->buildResult(false, 'That app does not belong to this service.');
        }
        if (! $this->ownsDomain($service, $domainId)) {
            return $this->buildResult(false, 'That domain does not belong to this service.');
        }

        $resp = $this->post($server, '/v1/docker/domains/link', [
            'domain_id' => $domainId,
            'container_id' => $containerId,
            'owner_user_id' => $accountId,
        ]);
        if (! $resp->successful()) {
            // The panel refuses to point a domain at an app that is not up,
            // which is the most common reason this fails.
            $msg = $this->apiMessage($resp, 'Could not point the domain at this app.');
            if (str_contains(strtolower($resp->body()), 'notrunning')) {
                $msg = 'Start the app first - a domain cannot be pointed at an app that is not running.';
            }

            return $this->buildResult(false, $msg);
        }

        return $this->buildResult(true, 'Domain is now serving this app.');
    }

    /** Stop serving an app on a domain and give the domain back to normal hosting. */
    public function unlinkContainerDomain(Service $service, string $domainId): array
    {
        $server = $this->getServer($service);
        $accountId = $this->linkedAccountId($service);
        if (! $server || ! $accountId) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }
        if (! $this->ownsDomain($service, $domainId)) {
            return $this->buildResult(false, 'That domain does not belong to this service.');
        }

        $resp = $this->post($server, '/v1/docker/domains/unlink', [
            'domain_id' => $domainId,
            'owner_user_id' => $accountId,
        ]);

        return $resp->successful()
            ? $this->buildResult(true, 'Domain no longer serves this app.')
            : $this->buildResult(false, $this->apiMessage($resp, 'Could not unlink the domain.'));
    }

    /** Which of the account's domains are already serving an app. */
    public function containerDomainLinks(Service $service): array
    {
        $server = $this->getServer($service);
        $accountId = $this->linkedAccountId($service);
        if (! $server || ! $accountId) {
            return [];
        }
        $resp = $this->get($server, '/v1/docker/domains/linked?owner_user_id='.urlencode($accountId));
        if (! $resp->successful()) {
            return [];
        }
        $own = array_keys($this->accountDomains($service));
        $out = [];
        foreach (($resp->json('data') ?? $resp->json('data.mappings') ?? []) as $m) {
            $domainId = (string) ($m['domain_id'] ?? '');
            if ($domainId === '' || ($own !== [] && ! in_array($domainId, $own, true))) {
                continue;
            }
            $out[$domainId] = [
                'container_id' => (string) ($m['container_id'] ?? ''),
                'container_name' => (string) ($m['container_name'] ?? ''),
            ];
        }

        return $out;
    }

    private function ownsDomain(Service $service, string $domainId): bool
    {
        // accountDomains() is an id => name map, not a list of rows.
        return $domainId !== '' && array_key_exists($domainId, $this->accountDomains($service));
    }


    /**
     * Keep what the panel said about reaching a freshly installed app.
     *
     * Best-effort on purpose: a failure to record this must never fail an
     * install that otherwise worked. The customer would rather have the app
     * running with a missing note than no app at all.
     *
     * @param  array<string, mixed>  $data  the panel's deploy response
     */
    private function rememberAppAccess(Service $service, string $slug, array $data): void
    {
        $containerId = (string) ($data['container_id'] ?? '');
        if ($containerId === '') {
            return;
        }

        $payload = array_filter([
            'access_url' => (string) ($data['access_url'] ?? '') ?: null,
            'credentials' => array_filter((array) ($data['credentials'] ?? [])),
            'notes' => (string) ($data['post_install_notes'] ?? '') ?: null,
        ]);
        if ($payload === []) {
            return;
        }

        try {
            \App\Models\DockerAppCredential::updateOrCreate(
                ['service_id' => $service->id, 'container_id' => $containerId],
                [
                    'container_name' => (string) ($data['container_name'] ?? $slug),
                    'slug' => $slug,
                    'payload' => $payload,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('PanelicaModule: could not record app access details', [
                'service' => $service->id, 'slug' => $slug, 'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Access details for the apps on this service, keyed by container id.
     *
     * @return array<string, \App\Models\DockerAppCredential>
     */
    public function containerAccessDetails(Service $service): array
    {
        return \App\Models\DockerAppCredential::forService((int) $service->id);
    }

    /**
     * What the panel can tell us about a running container right now.
     *
     * The deploy-time record only exists for apps installed through here. Anything
     * installed from the panel itself, or before that record was kept, showed the
     * customer nothing at all - while the same details sat in the panel the whole
     * time. So ask: the published port gives the address, and the container's own
     * environment holds the first login the image generated.
     *
     * Fenced to the account: only containers this service already owns are
     * inspected, because the billing key is operator-scoped and would happily
     * describe somebody else's container.
     *
     * @param  array<int, array<string, mixed>>  $containers  the fenced list
     * @return array<string, array{access_url: ?string, credentials: array<string, string>, data_path: ?string}>
     */
    public function liveContainerAccess(Service $service, array $containers): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return [];
        }
        $host = trim((string) $server->hostname) ?: trim((string) $server->ip_address);

        $out = [];
        foreach (array_slice($containers, 0, self::LIVE_ACCESS_MAX) as $c) {
            $id = (string) ($c['id'] ?? '');
            if ($id === '') {
                continue;
            }
            try {
                $resp = $this->get($server, '/v1/docker/containers/'.rawurlencode($id));
            } catch (\Throwable $e) {
                continue;   // one unreachable container must not empty the page
            }
            if (! $resp->successful()) {
                continue;
            }
            $d = (array) ($resp->json('data') ?? []);
            $out[$id] = [
                'access_url' => $this->publishedAddress($host, (array) ($d['ports'] ?? [])),
                'credentials' => $this->credentialsFromEnv((array) ($d['env'] ?? [])),
                'data_path' => $this->dataPath((array) ($d['mounts'] ?? [])),
            ];
        }

        return $out;
    }

    /**
     * The address a browser can open, from the container's published ports.
     *
     * Prefers a web port over whatever else happens to be published, so an app
     * that exposes both 80 and 3306 is offered as a site, not as a database.
     *
     * @param  array<int, array<string, mixed>>  $ports
     */
    private function publishedAddress(string $host, array $ports): ?string
    {
        if ($host === '') {
            return null;
        }
        $best = null;
        $bestRank = PHP_INT_MAX;
        foreach ($ports as $p) {
            $pub = (string) ($p['host_port'] ?? '');
            $priv = (string) ($p['container_port'] ?? '');
            if ($pub === '' || $priv === '' || strtolower((string) ($p['protocol'] ?? 'tcp')) !== 'tcp') {
                continue;
            }
            $rank = match ($priv) {
                '80' => 0, '443' => 1, '8080' => 2, '3000' => 3, default => 10,
            };
            if ($rank < $bestRank) {
                $bestRank = $rank;
                $best = ($priv === '443' ? 'https://' : 'http://').$host.':'.$pub;
            }
        }

        return $best;
    }

    /**
     * The parts of a container's environment worth showing the customer.
     *
     * An image's environment is mostly plumbing - PATH, LANG, the image's own
     * version stamp - with the generated admin password sitting in the middle of
     * it. Keep what reads like a credential or a connection detail, drop the rest,
     * and never show a value long enough to be a certificate or a key file.
     *
     * @param  array<int, string>  $env
     * @return array<string, string>
     */
    private function credentialsFromEnv(array $env): array
    {
        $keep = '/(PASSWORD|PASSWD|SECRET|TOKEN|_KEY$|APIKEY|API_KEY|USER$|USERNAME|_USER$|ADMIN|EMAIL|DATABASE|^DB_|_DB$|_HOST$|LICENSE|LOGIN)/i';
        $drop = '/(^PATH$|VERSION|^LANG|^LC_|DEBIAN_FRONTEND|^GOSU|^TERM$|^HOME$|^HOSTNAME$|_PATH$|^LS_FD$|^PHPINI)/i';

        $out = [];
        foreach ($env as $line) {
            $line = (string) $line;
            $at = strpos($line, '=');
            if ($at === false || $at === 0) {
                continue;
            }
            $key = substr($line, 0, $at);
            $value = substr($line, $at + 1);
            if ($value === '' || strlen($value) > 200) {
                continue;
            }
            if (preg_match($drop, $key) || ! preg_match($keep, $key)) {
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * Where the app keeps its data on the customer's account, if it binds a path.
     *
     * @param  array<int, array<string, mixed>>  $mounts
     */
    private function dataPath(array $mounts): ?string
    {
        foreach ($mounts as $m) {
            $src = (string) ($m['source'] ?? '');
            if ((string) ($m['type'] ?? '') === 'bind' && str_starts_with($src, '/home/')) {
                return $src;
            }
        }

        return null;
    }

    public function containerAction(Service $service, string $id, string $action): array
    {
        if (! in_array($action, ['start', 'stop', 'restart'], true)) {
            return $this->buildResult(false, 'Unsupported action.');
        }
        $all = $this->containers($service);
        $target = collect($all)->firstWhere('id', $id);
        if (! $target) {
            return $this->buildResult(false, 'That container does not belong to this service.');
        }

        // A stacked app is one thing to the customer: starting or stopping it
        // means the whole stack. Helpers come up before the app (it needs its
        // database) and go down after it.
        $members = $this->stackMembers($all, (string) ($target['stack'] ?? ''));
        $ordered = $members !== [] ? $members : [$target];
        if ($action === 'start') {
            $ordered = array_reverse($ordered); // helpers first, app last
        }

        $server = $this->getServer($service);
        foreach ($ordered as $member) {
            $resp = $this->post($server, '/v1/docker/containers/'.rawurlencode((string) $member['id']).'/'.$action, []);
            if (! $resp->successful()) {
                return $this->buildResult(false, $this->apiMessage($resp, 'Could not complete that action.'));
            }
        }

        return $this->buildResult(true, 'Done.');
    }

    public function deleteContainer(Service $service, string $id): array
    {
        $all = $this->containers($service);
        $target = collect($all)->firstWhere('id', $id);
        if (! $target) {
            return $this->buildResult(false, 'That container does not belong to this service.');
        }

        // A helper (mysql, redis) is part of an app, not a thing of its own.
        // Deleting one on its own left the app running against a database that
        // no longer existed - so it is refused, the app is what gets deleted.
        if ((string) ($target['role'] ?? '') !== '') {
            return $this->buildResult(false, __('client.hosting.containers.component_delete_refused'));
        }

        // The app goes first (nothing should be writing to the helpers while
        // they are removed), then its helpers. The panel keeps data shared
        // with anything else alive on its side, so a failed helper removal is
        // reported but does not undo the app removal.
        $members = $this->stackMembers($all, (string) ($target['stack'] ?? ''));
        $ordered = $members !== [] ? $members : [$target];

        $server = $this->getServer($service);
        $removed = [];
        foreach ($ordered as $member) {
            $mid = (string) $member['id'];
            $resp = $this->delete($server, '/v1/docker/containers/'.rawurlencode($mid).'?force=true&remove_volumes=true');
            if (! $resp->successful()) {
                if ($removed === []) {
                    return $this->buildResult(false, $this->apiMessage($resp, 'Could not remove the app.'));
                }

                return $this->buildResult(false, $this->apiMessage($resp, 'The app was removed but one of its components was not: '.$member['name']));
            }
            $removed[] = $mid;
        }

        // The app is gone; its first-login password has no reason to outlive it.
        // Measured after removing a container: the row stayed behind, so every
        // install-and-remove left an encrypted credential for something that no
        // longer exists.
        \App\Models\DockerAppCredential::where('service_id', $service->id)
            ->whereIn('container_id', $removed)->delete();

        return $this->buildResult(true, 'App removed.');
    }

    /**
     * Whether this service's account owns that container.
     *
     * Public because the client controller needs the same answer before it
     * sends a customer to a shell for one particular container.
     */
    public function ownsContainer(Service $service, string $id): bool
    {
        if ($id === '') {
            return false;
        }
        foreach ($this->containers($service) as $c) {
            if ($c['id'] === $id || $c['name'] === $id) {
                return true;
            }
        }

        return false;
    }

    private function ownsBackup(Service $service, string $filename): bool
    {
        foreach ($this->backups($service) as $b) {
            if ($b['filename'] === $filename) {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // DNS zone (Panelica-only). One authoritative BIND zone per domain; the panel
    // rewrites the zone file and bumps the serial on every change. Fenced to the
    // account's own domains. Records the hosting itself depends on (the zone's
    // NS/SOA and the root/www A records pointing at the server) are surfaced as
    // protected: editing them from a billing panel is how a customer silently
    // takes their own site offline.
    // -------------------------------------------------------------------------

    /**
     * DNS records across the account's own domains.
     *
     * @return list<array{id:string,domain:string,domain_id:string,type:string,name:string,content:string,ttl:?int,priority:?int,protected:bool}>
     */
    public function dnsRecords(Service $service, ?string $onlyDomainId = null): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return [];
        }
        $domains = $this->accountDomains($service);
        // A zone editor edits ONE zone. Restricting the fetch to the selected
        // domain also drops the request count from one-per-domain to one.
        if ($onlyDomainId !== null) {
            if (! isset($domains[$onlyDomainId])) {
                return [];
            }
            $domains = [$onlyDomainId => $domains[$onlyDomainId]];
        }
        $out = [];
        foreach ($domains as $domainId => $domainName) {
            $resp = $this->get($server, "/v1/dns/zones/{$domainId}/records");
            if (! $resp->successful()) {
                continue;
            }
            foreach (($resp->json('data') ?? []) as $r) {
                $type = strtoupper((string) ($r['type'] ?? ''));
                $name = (string) ($r['name'] ?? '');
                $out[] = [
                    'id' => (string) ($r['id'] ?? ''),
                    'domain' => $domainName,
                    'domain_id' => $domainId,
                    'type' => $type,
                    'name' => $name,
                    'content' => (string) ($r['content'] ?? ''),
                    'ttl' => isset($r['ttl']) ? (int) $r['ttl'] : null,
                    'priority' => isset($r['priority']) ? (int) $r['priority'] : null,
                    'protected' => $this->isProtectedDnsRecord($type, $name),
                ];
            }
        }

        // Group by record type in the order an operator reads a zone, then by
        // name — a zone listed in API order is unreadable.
        $order = array_flip(['SOA', 'NS', 'A', 'AAAA', 'CNAME', 'MX', 'TXT', 'SRV', 'CAA']);
        usort($out, function (array $a, array $b) use ($order) {
            $ra = $order[$a['type']] ?? 99;
            $rb = $order[$b['type']] ?? 99;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }
            if ($a['domain'] !== $b['domain']) {
                return strcmp($a['domain'], $b['domain']);
            }

            return strcmp($a['name'], $b['name']);
        });

        return $out;
    }

    /**
     * Record types a customer may manage from billing. SOA/NS are deliberately
     * absent — they are the zone's own delegation and the panel owns them.
     *
     * @return list<string>
     */
    public function dnsRecordTypes(): array
    {
        return ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'SRV', 'CAA'];
    }

    public function createDnsRecord(Service $service, string $domainId, string $type, string $name, string $content, ?int $ttl = null, ?int $priority = null): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }
        if (! isset($this->accountDomains($service)[$domainId])) {
            return $this->buildResult(false, 'That domain does not belong to this service.');
        }
        $type = strtoupper(trim($type));
        if (! in_array($type, $this->dnsRecordTypes(), true)) {
            return $this->buildResult(false, 'Unsupported record type.');
        }
        $name = trim($name) === '' ? '@' : trim($name);
        $content = trim($content);
        if ($content === '') {
            return $this->buildResult(false, 'Record value is required.');
        }
        if ($this->isProtectedDnsRecord($type, $name)) {
            return $this->buildResult(false, 'That record is managed by the hosting platform and cannot be changed here.');
        }

        $payload = ['type' => $type, 'name' => $name, 'content' => $content];
        if ($ttl !== null && $ttl > 0) {
            $payload['ttl'] = $ttl;
        }
        if ($type === 'MX' || $type === 'SRV') {
            $payload['priority'] = $priority ?? 10;
        }

        $resp = $this->post($server, "/v1/dns/zones/{$domainId}/records", $payload);
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not create the DNS record.'));
        }

        return $this->buildResult(true, 'DNS record created.');
    }

    /**
     * Edit an existing record's name/value/TTL/priority. The record type is not
     * editable (the panel endpoint does not change it either) — changing a type
     * in place is how a zone silently ends up with an A record where a CNAME was.
     */
    public function updateDnsRecord(Service $service, string $id, string $name, string $content, ?int $ttl = null, ?int $priority = null): array
    {
        $record = $this->findOwnDnsRecord($service, $id);
        if (! $record) {
            return $this->buildResult(false, 'That DNS record does not belong to this service.');
        }
        if ($record['protected']) {
            return $this->buildResult(false, 'That record is managed by the hosting platform and cannot be changed here.');
        }
        $name = trim($name) === '' ? '@' : trim($name);
        $content = trim($content);
        if ($content === '') {
            return $this->buildResult(false, 'Record value is required.');
        }
        // The panel's update endpoint accepts a new name, so renaming an ordinary
        // record INTO a managed one (blog A -> www A) would walk straight around
        // the protection. Judge the destination, not just the source.
        if ($this->isProtectedDnsRecord($record['type'], $name)) {
            return $this->buildResult(false, 'That name is managed by the hosting platform and cannot be used here.');
        }

        $payload = ['name' => $name, 'content' => $content];
        if ($ttl !== null && $ttl > 0) {
            $payload['ttl'] = $ttl;
        }
        if ($record['type'] === 'MX' || $record['type'] === 'SRV') {
            $payload['priority'] = $priority ?? ($record['priority'] ?? 10);
        }

        $resp = $this->patch($this->getServer($service), "/v1/dns/records/{$id}", $payload);
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not update the DNS record.'));
        }

        return $this->buildResult(true, 'DNS record updated.');
    }

    public function deleteDnsRecord(Service $service, string $id): array
    {
        $record = $this->findOwnDnsRecord($service, $id);
        if (! $record) {
            return $this->buildResult(false, 'That DNS record does not belong to this service.');
        }
        if ($record['protected']) {
            return $this->buildResult(false, 'That record is managed by the hosting platform and cannot be deleted here.');
        }
        $resp = $this->delete($this->getServer($service), "/v1/dns/records/{$id}");
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not delete the DNS record.'));
        }

        return $this->buildResult(true, 'DNS record deleted.');
    }

    /**
     * Records the hosting depends on. The zone's own delegation (SOA/NS) and the
     * apex/www A records point at the server that serves the customer's site —
     * changing them from billing is a silent outage, so billing shows them
     * read-only and the panel stays the place to override that deliberately.
     */
    private function isProtectedDnsRecord(string $type, string $name): bool
    {
        $type = strtoupper($type);
        if ($type === 'SOA' || $type === 'NS') {
            return true;
        }
        $name = strtolower(trim($name));

        return $type === 'A' && ($name === '@' || $name === '' || $name === 'www');
    }

    /** @return array<string,mixed>|null */
    private function findOwnDnsRecord(Service $service, string $id): ?array
    {
        if ($id === '') {
            return null;
        }
        foreach ($this->dnsRecords($service) as $r) {
            if ($r['id'] === $id) {
                return $r;
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Subdomains (Panelica-only). Per parent-domain; every subdomain is a real
    // vhost the panel provisions (nginx/apache + document root + PHP-FPM + SSL +
    // DNS). Fenced to the account's own domains; creation is gated by the plan's
    // max_subdomains (enforced by the panel too).
    // -------------------------------------------------------------------------

    /**
     * Subdomains across the account's own domains.
     *
     * @return list<array{id:string,name:string,full_name:string,domain:string,document_root:string,php_version:string,ssl:bool,status:string}>
     */
    public function subdomains(Service $service): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return [];
        }
        $out = [];
        foreach ($this->accountDomains($service) as $domainId => $domainName) {
            $resp = $this->get($server, "/v1/domains/{$domainId}/subdomains");
            if (! $resp->successful()) {
                continue;
            }
            foreach (($resp->json('data') ?? []) as $s) {
                $out[] = [
                    'id' => (string) ($s['id'] ?? ''),
                    'name' => (string) ($s['subdomain_name'] ?? ''),
                    'full_name' => (string) ($s['full_name'] ?? ''),
                    'domain' => $domainName,
                    'domain_id' => $domainId,
                    'document_root' => (string) ($s['document_root'] ?? ''),
                    'php_version' => (string) ($s['php_version'] ?? ''),
                    'ssl' => (bool) ($s['ssl_enabled'] ?? false),
                    'status' => (string) ($s['status'] ?? ''),
                ];
            }
        }

        return $out;
    }

    /**
     * Plan policy for subdomains: the cap, current usage across all the
     * account's domains, and whether another may be created.
     *
     * @return array{max:int,used:int,can_create:bool}
     */
    public function subdomainPolicy(Service $service): array
    {
        $used = count($this->subdomains($service));
        $policy = ['max' => 0, 'used' => $used, 'can_create' => false];
        $planLimit = $this->planLimit($service, 'max_subdomains');
        if ($planLimit === null) {
            // Unknown plan → let the panel be the authority (allow, it enforces).
            return ['max' => -1, 'used' => $used, 'can_create' => true];
        }
        $policy['max'] = $planLimit;
        $policy['can_create'] = $planLimit < 0 || $used < $planLimit;

        return $policy;
    }

    public function createSubdomain(Service $service, string $domainId, string $name, ?string $documentRoot = null, ?string $phpVersion = null, bool $ssl = true): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }
        if (! isset($this->accountDomains($service)[$domainId])) {
            return $this->buildResult(false, 'That domain does not belong to this service.');
        }
        $name = strtolower(trim($name));
        if ($name === '' || ! preg_match('/^[a-z0-9-]+$/', $name)) {
            return $this->buildResult(false, 'Enter a valid subdomain name (letters, numbers, hyphens).');
        }
        if (! $this->subdomainPolicy($service)['can_create']) {
            return $this->buildResult(false, 'You have reached your plan\'s subdomain limit.');
        }

        $payload = ['subdomain_name' => $name, 'ssl_enabled' => $ssl];
        // The form pre-fills "public_html". Forward it as-is; if the user
        // deliberately clears the field ('') the panel serves from the
        // subdomain root. Only omit (null) to let the panel default to
        // public_html — that path is for callers that don't set it at all.
        if ($documentRoot !== null) {
            $payload['document_root'] = trim($documentRoot);
        }
        if ($phpVersion !== null && $phpVersion !== '') {
            $payload['php_version'] = $phpVersion;
        }

        $resp = $this->post($server, "/v1/domains/{$domainId}/subdomains", $payload);
        if ($resp->status() === 403) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Your plan does not allow this.'));
        }
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not create the subdomain.'));
        }

        return $this->buildResult(true, 'Subdomain created.');
    }

    public function deleteSubdomain(Service $service, string $id): array
    {
        $server = $this->getServer($service);
        if (! $server || ! $this->ownsSubdomain($service, $id)) {
            return $this->buildResult(false, 'That subdomain does not belong to this service.');
        }
        $resp = $this->delete($server, "/v1/subdomains/{$id}");
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not delete the subdomain.'));
        }

        return $this->buildResult(true, 'Subdomain deleted.');
    }

    /** @var array<string, array<string, mixed>|null> plan row per account, for this request */
    private array $planRowCache = [];

    /**
     * The account's plan row, fetched once per request.
     *
     * Resolving it costs two calls (account, then plans). The apps page asks
     * for the container limit and the CPU/RAM ceilings together, and doing that
     * naively would make four calls to answer one page.
     *
     * @return array<string, mixed>|null
     */
    private function planRow(Service $service): ?array
    {
        $server = $this->getServer($service);
        $accountId = $this->linkedAccountId($service);
        if (! $server || ! $accountId) {
            return null;
        }
        if (array_key_exists($accountId, $this->planRowCache)) {
            return $this->planRowCache[$accountId];
        }

        $row = null;
        $acc = $this->get($server, "/v1/accounts/{$accountId}");
        $planId = $acc->successful() ? ($acc->json('data.plan_id') ?? null) : null;
        if ($planId) {
            $plans = $this->get($server, '/v1/plans');
            if ($plans->successful()) {
                foreach (($plans->json('data') ?? []) as $p) {
                    if ((string) ($p['id'] ?? '') === (string) $planId) {
                        $row = $p;
                        break;
                    }
                }
            }
        }

        return $this->planRowCache[$accountId] = $row;
    }

    /** A plan integer limit for the account, or null when it cannot be resolved. */
    private function planLimit(Service $service, string $field): ?int
    {
        $row = $this->planRow($service);

        return isset($row[$field]) ? (int) $row[$field] : null;
    }

    /**
     * The CPU/RAM ceilings an app on this account runs under.
     *
     * The catalogue states what each app needs; without these the customer
     * cannot tell whether their plan can actually run it. 0 means "not capped
     * by the plan" and is shown as unlimited rather than as zero.
     *
     * @return array{memory_mb:int, cpu_percent:int}
     */
    public function containerResources(Service $service): array
    {
        return [
            'memory_mb' => (int) ($this->planLimit($service, 'memory_limit_mb') ?? 0),
            'cpu_percent' => (int) ($this->planLimit($service, 'cpu_limit_percent') ?? 0),
        ];
    }

    private function ownsSubdomain(Service $service, string $id): bool
    {
        if ($id === '') {
            return false;
        }
        foreach ($this->subdomains($service) as $s) {
            if ($s['id'] === $id) {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Cron jobs (Panelica-only). Per-domain user cron: each job runs AS the
    // domain owner's unprivileged system user (pn-cron-exec, namespace + cgroup
    // isolated) and is scheduled by the panel's cron-scheduler (Redis + next_run
    // — the panel provisions all of that). Fenced to the account's own domains;
    // creation is gated by the plan's cron_jobs_enabled + max_cron_jobs (the
    // panel enforces both too). The panel's system cron (root maintenance jobs)
    // is deliberately NOT exposed here.
    // -------------------------------------------------------------------------

    /**
     * Cron jobs across the account's own domains. The panel list endpoint
     * answers with whatever the operator key can see (every job on the box), so
     * the account's domain set — not the raw list — decides what is shown.
     *
     * @return list<array{id:string,task_name:string,command:string,schedule:string,minute:string,hour:string,day_of_month:string,month:string,day_of_week:string,enabled:bool,domain_id:string,domain:string,last_run:?string,next_run:?string}>
     */
    public function cronJobs(Service $service): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return [];
        }
        $domains = $this->accountDomains($service);
        $resp = $this->get($server, '/v1/cron-jobs');
        if (! $resp->successful()) {
            return [];
        }
        $out = [];
        foreach (($resp->json('data') ?? []) as $j) {
            $domainId = (string) ($j['domain_id'] ?? '');
            if (! isset($domains[$domainId])) {
                continue; // fence: not one of the account's domains
            }
            $out[] = [
                'id' => (string) ($j['id'] ?? ''),
                'task_name' => (string) ($j['task_name'] ?? ''),
                'command' => (string) ($j['command'] ?? ''),
                'schedule' => trim(($j['minute'] ?? '*').' '.($j['hour'] ?? '*').' '.($j['day_of_month'] ?? '*').' '.($j['month'] ?? '*').' '.($j['day_of_week'] ?? '*')),
                'minute' => (string) ($j['minute'] ?? '*'),
                'hour' => (string) ($j['hour'] ?? '*'),
                'day_of_month' => (string) ($j['day_of_month'] ?? '*'),
                'month' => (string) ($j['month'] ?? '*'),
                'day_of_week' => (string) ($j['day_of_week'] ?? '*'),
                'enabled' => (bool) ($j['enabled'] ?? false),
                'domain_id' => $domainId,
                'domain' => $domains[$domainId],
                'last_run' => $j['last_run'] ?? null,
                'next_run' => $j['next_run'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Cron policy from the plan: whether cron is allowed at all, the cap,
     * current usage, and whether another may be created.
     *
     * @return array{enabled:bool,max:int,used:int,can_create:bool}
     */
    public function cronPolicy(Service $service): array
    {
        $used = count($this->cronJobs($service));
        $enabled = $this->planField($service, 'cron_jobs_enabled');
        $max = $this->planField($service, 'max_cron_jobs');

        // Unknown plan → let the panel be the authority (it enforces both).
        $isEnabled = $enabled === null ? true : (bool) $enabled;
        $maxInt = $max === null ? -1 : (int) $max;
        if (! $isEnabled || $maxInt === 0) {
            return ['enabled' => false, 'max' => $maxInt, 'used' => $used, 'can_create' => false];
        }
        $canCreate = $maxInt < 0 || $used < $maxInt;

        return ['enabled' => true, 'max' => $maxInt, 'used' => $used, 'can_create' => $canCreate];
    }

    public function createCronJob(Service $service, string $domainId, string $taskName, string $command, array $schedule = [], int $timeoutSeconds = 0, bool $emailOnError = false, string $emailRecipient = ''): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }
        if (! isset($this->accountDomains($service)[$domainId])) {
            return $this->buildResult(false, 'That domain does not belong to this service.');
        }
        $taskName = trim($taskName);
        $command = trim($command);
        if ($taskName === '' || $command === '') {
            return $this->buildResult(false, 'Task name and command are required.');
        }
        $policy = $this->cronPolicy($service);
        if (! $policy['enabled']) {
            return $this->buildResult(false, 'Your plan does not include cron jobs.');
        }
        if (! $policy['can_create']) {
            return $this->buildResult(false, 'You have reached your plan\'s cron job limit.');
        }

        $payload = [
            'domain_id' => $domainId,
            'task_name' => $taskName,
            'command' => $command,
            'minute' => $schedule['minute'] ?? '*',
            'hour' => $schedule['hour'] ?? '*',
            'day_of_month' => $schedule['day_of_month'] ?? '*',
            'month' => $schedule['month'] ?? '*',
            'day_of_week' => $schedule['day_of_week'] ?? '*',
            'email_on_error' => $emailOnError,
        ];
        if ($timeoutSeconds > 0) {
            $payload['timeout_seconds'] = $timeoutSeconds;
        }
        if ($emailRecipient !== '') {
            $payload['email_recipient'] = $emailRecipient;
        }

        $resp = $this->post($server, '/v1/cron-jobs', $payload);
        if ($resp->status() === 403) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Your plan does not allow this.'));
        }
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not create the cron job.'));
        }

        return $this->buildResult(true, 'Cron job created.');
    }

    public function toggleCronJob(Service $service, string $id): array
    {
        if (! $this->ownsCronJob($service, $id)) {
            return $this->buildResult(false, 'That cron job does not belong to this service.');
        }
        $resp = $this->post($this->getServer($service), "/v1/cron-jobs/{$id}/toggle", []);
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not toggle the cron job.'));
        }

        return $this->buildResult(true, 'Cron job updated.');
    }

    public function runCronJob(Service $service, string $id): array
    {
        if (! $this->ownsCronJob($service, $id)) {
            return $this->buildResult(false, 'That cron job does not belong to this service.');
        }
        $resp = $this->post($this->getServer($service), "/v1/cron-jobs/{$id}/run", []);
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not run the cron job.'));
        }

        return $this->buildResult(true, 'Cron job executed.', ['output' => (string) ($resp->json('data.output') ?? '')]);
    }

    public function deleteCronJob(Service $service, string $id): array
    {
        if (! $this->ownsCronJob($service, $id)) {
            return $this->buildResult(false, 'That cron job does not belong to this service.');
        }
        $resp = $this->delete($this->getServer($service), "/v1/cron-jobs/{$id}");
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not delete the cron job.'));
        }

        return $this->buildResult(true, 'Cron job deleted.');
    }

    private function ownsCronJob(Service $service, string $id): bool
    {
        if ($id === '') {
            return false;
        }
        foreach ($this->cronJobs($service) as $j) {
            if ($j['id'] === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Raw value of a field on the account's plan (bool/int/string as stored),
     * or null when the plan or field is unknown. planLimit() casts to int;
     * this preserves the native type so boolean flags read correctly.
     *
     * @return mixed
     */
    private function planField(Service $service, string $field)
    {
        $server = $this->getServer($service);
        $accountId = $this->linkedAccountId($service);
        if (! $server || ! $accountId) {
            return null;
        }
        $acc = $this->get($server, "/v1/accounts/{$accountId}");
        $planId = $acc->successful() ? ($acc->json('data.plan_id') ?? null) : null;
        if (! $planId) {
            return null;
        }
        $plans = $this->get($server, '/v1/plans');
        if (! $plans->successful()) {
            return null;
        }
        foreach (($plans->json('data') ?? []) as $p) {
            if ((string) ($p['id'] ?? '') === (string) $planId) {
                return $p[$field] ?? null;
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // FTP accounts (Panelica-only). Per-account (user_id), NOT per-domain.
    //
    // The customer sees only their own FTP accounts, can change a password and
    // delete, and may create ONLY when the plan allows it (ftp_access_enabled)
    // and the plan's max_ftp_accounts is not yet reached. The panel enforces all
    // of this server-side (FTPService); PNLCS mirrors the policy so the UI shows
    // the create form only when creation is actually permitted.
    // -------------------------------------------------------------------------

    /**
     * The account's own FTP accounts.
     *
     * @return list<array{id:string,username:string,home:string,quota_mb:int,used_mb:int,status:string}>
     */
    public function ftpAccounts(Service $service): array
    {
        $server = $this->getServer($service);
        $accountId = $this->linkedAccountId($service);
        if (! $server || ! $accountId) {
            return [];
        }
        $resp = $this->get($server, '/v1/ftp-accounts');
        if (! $resp->successful()) {
            return [];
        }
        $out = [];
        foreach (($resp->json('data') ?? []) as $f) {
            if ((string) ($f['user_id'] ?? '') !== $accountId) {
                continue; // fence: only this account's FTP users
            }
            $out[] = [
                'id' => (string) ($f['id'] ?? ''),
                'username' => (string) ($f['ftp_username'] ?? ''),
                'home' => (string) ($f['home_directory'] ?? ''),
                'quota_mb' => (int) ($f['quota_mb'] ?? 0),
                'used_mb' => (int) ($f['used_mb'] ?? 0),
                'status' => (string) ($f['status'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * The account's FTP policy from its Panelica plan: whether FTP creation is
     * allowed, the account cap, current usage, and whether another may be made.
     *
     * @return array{enabled:bool,max:int,used:int,can_create:bool}
     */
    public function ftpPolicy(Service $service): array
    {
        $used = count($this->ftpAccounts($service));
        $policy = ['enabled' => false, 'max' => 0, 'used' => $used, 'can_create' => false];

        $server = $this->getServer($service);
        $accountId = $this->linkedAccountId($service);
        if (! $server || ! $accountId) {
            return $policy;
        }

        // account -> plan_id -> plan (max_ftp_accounts + ftp_access_enabled)
        $acc = $this->get($server, "/v1/accounts/{$accountId}");
        $planId = $acc->successful() ? ($acc->json('data.plan_id') ?? null) : null;
        if (! $planId) {
            return $policy;
        }
        $plansResp = $this->get($server, '/v1/plans');
        if (! $plansResp->successful()) {
            return $policy;
        }
        foreach (($plansResp->json('data') ?? []) as $p) {
            if ((string) ($p['id'] ?? '') === (string) $planId) {
                $policy['enabled'] = (bool) ($p['ftp_access_enabled'] ?? false);
                $policy['max'] = (int) ($p['max_ftp_accounts'] ?? 0);
                break;
            }
        }
        // max = -1 means unlimited
        $underLimit = $policy['max'] < 0 || $used < $policy['max'];
        $policy['can_create'] = $policy['enabled'] && $underLimit;

        return $policy;
    }

    /**
     * Create an FTP account for the service's account. The panel enforces the
     * plan (limit + ftp_access_enabled), hashes the password for ProFTPD, and
     * fences the home directory to the account's own home; $domain (optional)
     * scopes the home to that domain's folder, otherwise it is the account home.
     */
    public function createFtpAccount(Service $service, string $username, string $password, ?string $domain = null, int $quotaMb = 0): array
    {
        $server = $this->getServer($service);
        $accountId = $this->linkedAccountId($service);
        if (! $server || ! $accountId) {
            return $this->buildResult(false, 'No panel account is linked to this service.');
        }
        if (! $this->ftpPolicy($service)['can_create']) {
            return $this->buildResult(false, 'Your plan does not allow creating more FTP accounts.');
        }
        $username = strtolower(trim($username));
        if ($username === '' || ! preg_match('/^[a-z0-9._-]+$/', $username)) {
            return $this->buildResult(false, 'Enter a valid FTP username.');
        }

        $payload = [
            'user_id' => $accountId,
            'ftp_username' => $username,
            'password' => $password,
        ];
        if ($quotaMb > 0) {
            $payload['quota_mb'] = $quotaMb;
        }
        // A domain (passed as its id) scopes the home to /<domain>; the panel
        // resolves it under the account home and refuses anything outside it.
        if ($domain !== null && $domain !== '') {
            $domains = $this->accountDomains($service);
            if (isset($domains[$domain])) {
                $payload['home_directory'] = '/'.$domains[$domain];
            }
        }

        $resp = $this->post($server, '/v1/ftp-accounts', $payload);
        if ($resp->status() === 403) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Your plan does not allow this.'));
        }
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not create the FTP account.'));
        }

        return $this->buildResult(true, 'FTP account created.');
    }

    public function deleteFtpAccount(Service $service, string $id): array
    {
        $server = $this->getServer($service);
        if (! $server || ! $this->ownsFtp($service, $id)) {
            return $this->buildResult(false, 'That FTP account does not belong to this service.');
        }
        $resp = $this->delete($server, "/v1/ftp-accounts/{$id}");
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not delete the FTP account.'));
        }

        return $this->buildResult(true, 'FTP account deleted.');
    }

    public function changeFtpPassword(Service $service, string $id, string $password): array
    {
        $server = $this->getServer($service);
        if (! $server || ! $this->ownsFtp($service, $id)) {
            return $this->buildResult(false, 'That FTP account does not belong to this service.');
        }
        $resp = $this->post($server, "/v1/ftp-accounts/{$id}/change-password", ['password' => $password]);
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not update the FTP password.'));
        }

        return $this->buildResult(true, 'FTP password updated.');
    }

    /** FTP connection host for the customer (the panel hostname). */
    public function ftpHost(Service $service): ?string
    {
        $server = $this->getServer($service);
        if (! $server) {
            return null;
        }

        return trim((string) $server->hostname) ?: trim((string) $server->ip_address);
    }

    private function ownsFtp(Service $service, string $id): bool
    {
        if ($id === '') {
            return false;
        }
        foreach ($this->ftpAccounts($service) as $f) {
            if ($f['id'] === $id) {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Databases (Panelica-only) — MySQL databases + users, fenced to the account
    //
    // Scoped exactly like emails/files: every operation targets a domain that
    // belongs to THIS service's account (fenced via accountDomains), and user
    // operations are fenced to the account's own database users. The panel
    // enforces the plan's database quota. Creating a database also creates its
    // primary user; extra users can be added per database with a role.
    // -------------------------------------------------------------------------

    /**
     * Databases across the account's domains, grouped by domain. Each entry
     * carries the domain and its database users (primary marked). Fenced: only
     * the account's own domains are queried.
     *
     * @return list<array{domain_id:string,domain:string,users:array}>
     */
    public function listDatabases(Service $service): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return [];
        }
        $out = [];
        foreach ($this->accountDomains($service) as $domainId => $domainName) {
            $resp = $this->getWithQuery($server, '/v1/databases', ['domain_id' => $domainId]);
            $users = $resp->successful() ? ($resp->json('data') ?? []) : [];
            $out[] = [
                'domain_id' => $domainId,
                'domain' => $domainName,
                'users' => array_map(fn ($u) => [
                    'id' => (string) ($u['id'] ?? ''),
                    'username' => (string) ($u['username'] ?? ''),
                    'role' => (string) ($u['role'] ?? ''),
                    'is_primary' => (bool) ($u['is_primary'] ?? false),
                    'database_name' => (string) ($u['database_name'] ?? ''),
                ], is_array($users) ? $users : []),
            ];
        }

        return $out;
    }

    public function createDatabase(Service $service, string $domainId, string $dbName, string $dbUser, string $password): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }
        if (! isset($this->accountDomains($service)[$domainId])) {
            return $this->buildResult(false, 'That domain does not belong to this service.');
        }
        if (! $this->validIdentifier($dbName) || ! $this->validIdentifier($dbUser)) {
            return $this->buildResult(false, 'Database and user names may use letters, numbers and underscores only (max 32).');
        }

        $resp = $this->post($server, "/v1/domains/{$domainId}/databases", [
            'database_name' => $dbName,
            'database_user' => $dbUser,
            'password' => $password,
        ]);
        if ($resp->status() === 404) {
            return $this->buildResult(false, 'Database creation is not available on this server yet.');
        }
        if ($resp->status() === 403) {
            return $this->buildResult(false, 'Your plan\'s database limit has been reached.');
        }
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not create the database.'));
        }

        return $this->buildResult(true, 'Database created.');
    }

    public function deleteDatabase(Service $service, string $domainId, string $databaseName): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }
        if (! isset($this->accountDomains($service)[$domainId])) {
            return $this->buildResult(false, 'That domain does not belong to this service.');
        }
        // Fence the name to a database that actually appears under this account.
        if (! $this->ownsDatabase($service, $domainId, $databaseName)) {
            return $this->buildResult(false, 'That database does not belong to this service.');
        }

        $qs = http_build_query(['database_name' => $databaseName], '', '&', PHP_QUERY_RFC3986);
        $path = "/v1/domains/{$domainId}/databases?{$qs}";
        $headers = $this->buildHeaders($server, 'DELETE', $path, '');
        $resp = Http::withHeaders($headers)->withoutVerifying()->delete($this->baseUrl($server).$path);
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not delete the database.'));
        }

        return $this->buildResult(true, 'Database deleted.');
    }

    public function createDatabaseUser(Service $service, string $domainId, string $username, string $password, string $role): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No Panelica server configured.');
        }
        if (! isset($this->accountDomains($service)[$domainId])) {
            return $this->buildResult(false, 'That domain does not belong to this service.');
        }
        if (! $this->validIdentifier($username)) {
            return $this->buildResult(false, 'User name may use letters, numbers and underscores only (max 32).');
        }
        $role = in_array($role, ['read', 'readWrite', 'dbAdmin', 'dbOwner'], true) ? $role : 'readWrite';

        $resp = $this->post($server, '/v1/databases', [
            'username' => $username,
            'password' => $password,
            'domain_id' => $domainId,
            'role' => $role,
        ]);
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not create the database user.'));
        }

        return $this->buildResult(true, 'Database user created.');
    }

    public function deleteDatabaseUser(Service $service, string $userId): array
    {
        $server = $this->getServer($service);
        if (! $server || ! $this->ownsDatabaseUser($service, $userId)) {
            return $this->buildResult(false, 'That database user does not belong to this service.');
        }
        $resp = $this->delete($server, "/v1/mysql-users/{$userId}");
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not delete the database user.'));
        }

        return $this->buildResult(true, 'Database user deleted.');
    }

    public function changeDatabaseUserPassword(Service $service, string $userId, string $password): array
    {
        $server = $this->getServer($service);
        if (! $server || ! $this->ownsDatabaseUser($service, $userId)) {
            return $this->buildResult(false, 'That database user does not belong to this service.');
        }
        $resp = $this->post($server, "/v1/mysql-users/{$userId}/change-password", ['password' => $password]);
        if (! $resp->successful()) {
            return $this->buildResult(false, $this->apiMessage($resp, 'Could not update the password.'));
        }

        return $this->buildResult(true, 'Password updated.');
    }

    private function validIdentifier(string $s): bool
    {
        return $s !== '' && strlen($s) <= 32 && preg_match('/^[A-Za-z0-9_]+$/', $s) === 1;
    }

    private function ownsDatabase(Service $service, string $domainId, string $databaseName): bool
    {
        foreach ($this->listDatabases($service) as $group) {
            if ($group['domain_id'] !== $domainId) {
                continue;
            }
            foreach ($group['users'] as $u) {
                if ($u['database_name'] !== '' && $u['database_name'] === $databaseName) {
                    return true;
                }
            }
        }

        return false;
    }

    private function ownsDatabaseUser(Service $service, string $userId): bool
    {
        if ($userId === '') {
            return false;
        }
        foreach ($this->listDatabases($service) as $group) {
            foreach ($group['users'] as $u) {
                if ($u['id'] === $userId) {
                    return true;
                }
            }
        }

        return false;
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

    /**
     * Upload a file into a directory under the account's home. The panel excludes
     * the multipart body from the HMAC signature (TLS protects it), so the
     * signature is computed over an empty body. target_path is fenced to the
     * account's home server-side. Returns a friendly result; a 404 means the
     * panel has not shipped the upload endpoint yet (older server).
     */
    public function uploadFile(Service $service, string $targetPath, string $contents, string $filename): array
    {
        [$server, $accountId] = $this->fileContext($service);
        if (! $server) {
            return $this->buildResult(false, 'No panel account is linked to this service.');
        }

        $path = '/v1/files/upload';
        $timestamp = (string) time();
        // multipart body is excluded from the signature (see server hmac_auth_service)
        $signature = hash_hmac('sha256', 'POST'.$path.$timestamp.'', $this->apiSecret($server));

        $resp = Http::withHeaders([
            'X-API-Key' => $this->apiKey($server),
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
            'Accept' => 'application/json',
        ])->withoutVerifying()
            ->attach('file', $contents, $filename)
            ->post($this->baseUrl($server).$path, [
                'user_id' => $accountId,
                'target_path' => $targetPath,
            ]);

        if ($resp->status() === 404) {
            return $this->buildResult(false, 'File upload is not available on this server yet.');
        }
        if (! $resp->successful()) {
            Log::error('PanelicaModule::uploadFile failed', ['status' => $resp->status(), 'body' => $resp->body()]);

            return $this->buildResult(false, $this->apiMessage($resp, 'Could not upload the file.'));
        }

        return $this->buildResult(true, 'File uploaded.');
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

    /**
     * URL of the panel's Roundcube webmail (served at /email/webmail on the panel
     * HTTPS port). The customer signs in there with their mailbox credentials.
     */
    public function webmailUrl(Service $service): ?string
    {
        $server = $this->getServer($service);
        if (! $server) {
            return null;
        }

        // Use the hostname the customer's browser will open (matches the panel's
        // TLS certificate), not serverHost() which falls back to the raw IP.
        $host = trim((string) $server->hostname) ?: trim((string) $server->ip_address);

        return 'https://'.$host.':'.$server->port.'/email/webmail';
    }

    /**
     * URL of the panel's phpMyAdmin (served at /databases/phpmyadmin on the panel
     * HTTPS port). The customer signs in there with any of their database users.
     */
    public function phpMyAdminUrl(Service $service): ?string
    {
        $server = $this->getServer($service);
        if (! $server) {
            return null;
        }
        $host = trim((string) $server->hostname) ?: trim((string) $server->ip_address);

        return 'https://'.$host.':'.$server->port.'/databases/phpmyadmin/';
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
