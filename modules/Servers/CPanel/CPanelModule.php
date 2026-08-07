<?php

namespace Modules\Servers\CPanel;

use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Servers\AbstractServerModule;

class CPanelModule extends AbstractServerModule
{
    public function getModuleName(): string
    {
        return 'cpanel';
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'username',   'label' => 'WHM Username (root)',  'type' => 'text'],
            ['name' => 'api_token',  'label' => 'WHM API Token',        'type' => 'password'],
        ];
    }

    // -------------------------------------------------------------------------
    // HTTP helpers
    // -------------------------------------------------------------------------

    private function baseUrl(Server $server): string
    {
        $port = $server->port ?: 2087;

        return "https://{$this->serverHost($server)}:{$port}/json-api/";
    }

    /**
     * WHM takes an API token or the account's own password, and the server form
     * calls the token "Optional". Sending "whm root:" with an empty token
     * failed every call for an operator who had filled in the password.
     */
    private function authHeader(Server $server): string
    {
        $user = (string) ($server->username ?: 'root');
        $token = trim((string) $server->access_hash);

        if ($token !== '') {
            return "whm {$user}:{$token}";
        }

        return 'Basic '.base64_encode($user.':'.(string) $server->password);
    }

    private function call(Server $server, string $function, array $params = []): array
    {
        $params['api.version'] = 1;
        $url = $this->baseUrl($server).$function;

        $resp = Http::withHeaders(['Authorization' => $this->authHeader($server)])
            ->withoutVerifying()
            ->timeout(60)
            ->asForm()
            ->post($url, $params);

        if (! $resp->successful()) {
            Log::error("CPanelModule::{$function} HTTP error", ['status' => $resp->status(), 'body' => $resp->body()]);

            return ['success' => false, 'message' => "WHM API HTTP error {$resp->status()}", 'raw' => []];
        }

        $json = $resp->json() ?? [];
        $result = (int) ($json['metadata']['result'] ?? $json['result'] ?? 0);

        return [
            'success' => $result === 1,
            'message' => $json['metadata']['reason'] ?? $json['reason'] ?? ($result === 1 ? 'OK' : 'WHM API call failed'),
            'raw' => $json['data'] ?? $json,
        ];
    }

    // -------------------------------------------------------------------------
    // Interface implementation
    // -------------------------------------------------------------------------

    public function create(Service $service): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No cPanel/WHM server configured.');
        }

        $client = $service->client;
        $domain = $service->domain;
        $product = $service->product;

        if (! $client || ! $domain) {
            return $this->buildResult(false, 'Service is missing client or domain.');
        }

        // WHM username: lowercase alphanumeric, must start with a letter, max 8 chars
        $username = preg_replace('/[^a-z0-9]/', '', strtolower(explode('.', $domain)[0]));
        $username = ltrim($username, '0123456789') ?: 'u'.$service->id;
        $username = substr($username, 0, 8);

        $password = $service->password ?: bin2hex(random_bytes(8));
        $package = $this->getRemotePackage($service);

        $params = [
            'username' => $username,
            'domain' => $domain,
            'password' => $password,
            'contactemail' => $client->email,
        ];
        if ($package) {
            $params['plan'] = $package; // only send a plan when one is configured
        }

        // Retry with a numeric suffix when the username is already taken
        $result = $this->call($server, 'createacct', $params);
        $attempt = 1;
        while (! $result['success'] && $attempt <= 3 && stripos($result['message'], 'already') !== false) {
            $params['username'] = substr($username, 0, 7 - strlen((string) $attempt)).$attempt;
            $result = $this->call($server, 'createacct', $params);
            $attempt++;
        }
        $username = $params['username'];

        if (! $result['success']) {
            return $this->buildResult(false, "cPanel account creation failed: {$result['message']}");
        }

        $this->setModuleData($service, ['cpanel_username' => $username]);
        $service->update(['username' => $username, 'password' => $password, 'status' => 'active']);

        $out = $this->buildResult(true, 'cPanel account created successfully.', ['cpanel_username' => $username]);
        $this->logAction($service, 'create', $out);

        return $out;
    }

    public function suspend(Service $service, string $reason = ''): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No cPanel/WHM server configured.');
        }

        $data = $this->getModuleData($service);
        $username = $data['cpanel_username'] ?? $service->username;

        if (! $username) {
            return $this->buildResult(false, 'cPanel username not found in service notes.');
        }

        $result = $this->call($server, 'suspendacct', ['user' => $username, 'reason' => $reason]);

        if (! $result['success']) {
            return $this->buildResult(false, "Suspend failed: {$result['message']}");
        }

        $service->update(['status' => 'suspended', 'suspension_date' => now(), 'suspension_reason' => $reason]);
        $out = $this->buildResult(true, 'cPanel account suspended.');
        $this->logAction($service, 'suspend', $out);

        return $out;
    }

    public function unsuspend(Service $service): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No cPanel/WHM server configured.');
        }

        $data = $this->getModuleData($service);
        $username = $data['cpanel_username'] ?? $service->username;

        if (! $username) {
            return $this->buildResult(false, 'cPanel username not found in service notes.');
        }

        $result = $this->call($server, 'unsuspendacct', ['user' => $username]);

        if (! $result['success']) {
            return $this->buildResult(false, "Unsuspend failed: {$result['message']}");
        }

        $service->update(['status' => 'active', 'suspension_date' => null, 'suspension_reason' => null]);
        $out = $this->buildResult(true, 'cPanel account unsuspended.');
        $this->logAction($service, 'unsuspend', $out);

        return $out;
    }

    public function terminate(Service $service): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No cPanel/WHM server configured.');
        }

        $data = $this->getModuleData($service);
        $username = $data['cpanel_username'] ?? $service->username;

        if (! $username) {
            return $this->buildResult(false, 'cPanel username not found in service notes.');
        }

        $result = $this->call($server, 'removeacct', ['user' => $username]);

        if (! $result['success']) {
            return $this->buildResult(false, "Terminate failed: {$result['message']}");
        }

        $service->update(['status' => 'terminated', 'termination_date' => now()]);
        $out = $this->buildResult(true, 'cPanel account terminated.');
        $this->logAction($service, 'terminate', $out);

        return $out;
    }

    public function changePassword(Service $service, string $newPassword): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No cPanel/WHM server configured.');
        }

        $data = $this->getModuleData($service);
        $username = $data['cpanel_username'] ?? $service->username;

        if (! $username) {
            return $this->buildResult(false, 'cPanel username not found in service notes.');
        }

        $result = $this->call($server, 'passwd', ['user' => $username, 'password' => $newPassword]);

        if (! $result['success']) {
            return $this->buildResult(false, "Password change failed: {$result['message']}");
        }

        $service->update(['password' => $newPassword]);
        $out = $this->buildResult(true, 'Password changed successfully.');
        $this->logAction($service, 'changePassword', $out);

        return $out;
    }

    public function changePackage(Service $service, array $newPackage): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No cPanel/WHM server configured.');
        }

        $data = $this->getModuleData($service);
        $username = $data['cpanel_username'] ?? $service->username;

        if (! $username) {
            return $this->buildResult(false, 'cPanel username not found in service notes.');
        }

        $config = is_string($newPackage['config_options'] ?? null)
            ? json_decode($newPackage['config_options'], true)
            : ($newPackage['config_options'] ?? []);
        $packageName = $config['cpanel_package'] ?? $config['package_name'] ?? null;

        if (! $packageName) {
            return $this->buildResult(false, 'New product does not have a cpanel_package configured.');
        }

        $result = $this->call($server, 'changepackage', ['user' => $username, 'pkg' => $packageName]);

        if (! $result['success']) {
            return $this->buildResult(false, "Package change failed: {$result['message']}");
        }

        $out = $this->buildResult(true, 'Package changed successfully.');
        $this->logAction($service, 'changePackage', $out);

        return $out;
    }

    /**
     * Disk and bandwidth for every account this server hosts.
     *
     * Two calls, not one per account: listaccts carries the disk figures and
     * showbw the bandwidth. accountsummary - which this used to ask, once per
     * service - does not return bandwidth at all, so bw_usage and bw_limit
     * were never written and the overage billing that reads them could not
     * fire.
     */
    public function usageUpdate(Server $server): array
    {
        $services = Service::where('server_id', $server->id)
            ->where('status', 'active')
            ->get();

        if ($services->isEmpty()) {
            return ['updated' => 0, 'errors' => 0];
        }

        $disk = $this->indexByUser($this->call($server, 'listaccts'));
        $bandwidth = $this->indexByUser($this->call($server, 'showbw'));

        if ($disk === [] && $bandwidth === []) {
            return ['updated' => 0, 'errors' => $services->count()];
        }

        $updated = 0;
        $errors = 0;

        foreach ($services as $service) {
            $data = $this->getModuleData($service);
            $username = strtolower((string) ($data['cpanel_username'] ?? $service->username ?? ''));

            if ($username === '') {
                $errors++;

                continue;
            }

            $account = $disk[$username] ?? null;
            $bw = $bandwidth[$username] ?? null;

            if (! $account && ! $bw) {
                // The account is gone from the server, or was never made.
                $errors++;

                continue;
            }

            $updateData = [];

            // "1024M", "0M", or "unlimited".
            foreach (['diskused' => 'disk_usage', 'disklimit' => 'disk_limit'] as $key => $column) {
                $value = $account[$key] ?? null;

                if ($value !== null && strcasecmp((string) $value, 'unlimited') !== 0) {
                    $updateData[$column] = (int) $value;
                }
            }

            // showbw answers in bytes, and a limit of zero means unlimited.
            if ($bw) {
                $updateData['bw_usage'] = (int) round(((float) ($bw['totalbytes'] ?? 0)) / 1048576);

                $limit = (float) ($bw['limit'] ?? 0);

                if ($limit > 0) {
                    $updateData['bw_limit'] = (int) round($limit / 1048576);
                }
            }

            if ($updateData !== []) {
                $service->update($updateData);
                $updated++;
            }
        }

        return ['updated' => $updated, 'errors' => $errors];
    }

    /**
     * @param  array{success: bool, raw: mixed}  $result
     * @return array<string, array<string, mixed>>
     */
    private function indexByUser(array $result): array
    {
        if (! ($result['success'] ?? false)) {
            return [];
        }

        $accounts = $result['raw']['acct'] ?? [];
        $indexed = [];

        foreach ($accounts as $account) {
            $user = $account['user'] ?? null;

            if ($user) {
                $indexed[strtolower((string) $user)] = $account;
            }
        }

        return $indexed;
    }

    /**
     * WHM's packages, as the product form offers them.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function listPackages(Server $server): array
    {
        $result = $this->call($server, 'listpkgs');

        if (! ($result['success'] ?? false)) {
            return [];
        }

        $packages = $result['raw']['pkg'] ?? [];
        $out = [];

        foreach ($packages as $pkg) {
            $name = $pkg['name'] ?? null;

            if (! $name) {
                continue;
            }

            $quota = $pkg['QUOTA'] ?? null;
            $mail = $pkg['MAXPOP'] ?? null;
            $detail = array_filter([
                $quota !== null ? 'disk '.$quota : null,
                $mail !== null ? 'email '.$mail : null,
            ]);

            $out[] = [
                'id' => $name,
                'name' => $detail === [] ? $name : $name.' ('.implode(', ', $detail).')',
            ];
        }

        usort($out, fn ($a, $b) => strcmp($a['id'], $b['id']));

        return $out;
    }

    public function testConnection(Server $server): bool
    {
        try {
            $result = $this->call($server, 'version');

            return $result['success'];
        } catch (\Throwable $e) {
            Log::error('CPanelModule::testConnection exception', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
