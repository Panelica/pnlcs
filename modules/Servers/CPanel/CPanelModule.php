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
        return "https://{$server->hostname}:{$port}/json-api/";
    }

    private function authHeader(Server $server): string
    {
        $user  = $server->username ?: 'root';
        $token = $server->access_hash ?? '';
        return "whm {$user}:{$token}";
    }

    private function call(Server $server, string $function, array $params = []): array
    {
        $params['api.version'] = 1;
        $url = $this->baseUrl($server) . $function;

        $resp = Http::withHeaders(['Authorization' => $this->authHeader($server)])
            ->withoutVerifying()
            ->get($url, $params);

        if (!$resp->successful()) {
            Log::error("CPanelModule::{$function} HTTP error", ['status' => $resp->status(), 'body' => $resp->body()]);
            return ['success' => false, 'message' => "WHM API HTTP error {$resp->status()}", 'raw' => []];
        }

        $json   = $resp->json() ?? [];
        $result = (int)($json['metadata']['result'] ?? $json['result'] ?? 0);

        return [
            'success' => $result === 1,
            'message' => $json['metadata']['reason'] ?? $json['reason'] ?? ($result === 1 ? 'OK' : 'WHM API call failed'),
            'raw'     => $json['data'] ?? $json,
        ];
    }

    // -------------------------------------------------------------------------
    // Interface implementation
    // -------------------------------------------------------------------------

    public function create(Service $service): array
    {
        $server = $this->getServer($service);
        if (!$server) {
            return $this->buildResult(false, 'No cPanel/WHM server configured.');
        }

        $client  = $service->client;
        $domain  = $service->domain;
        $product = $service->product;

        if (!$client || !$domain) {
            return $this->buildResult(false, 'Service is missing client or domain.');
        }

        // WHM username: alphanumeric only, max 8 chars
        $username = preg_replace('/[^a-z0-9]/', '', strtolower(explode('.', $domain)[0]));
        $username = substr($username ?: 'user', 0, 8);

        // Ensure username is unique by appending numeric suffix if needed
        $baseUsername = $username;
        $suffix = 1;
        // We'll attempt creation; WHM will reject if username exists

        $password = $service->password ?: bin2hex(random_bytes(8));
        $package  = $this->getRemotePackage($service) ?? 'default';

        $result = $this->call($server, 'createacct', [
            'username'     => $username,
            'domain'       => $domain,
            'password'     => $password,
            'plan'         => $package,
            'contactemail' => $client->email,
        ]);

        if (!$result['success']) {
            return $this->buildResult(false, "cPanel account creation failed: {$result['message']}");
        }

        $this->setModuleData($service, ['cpanel_username' => $username]);
        $service->update(['username' => $username, 'status' => 'Active']);

        $out = $this->buildResult(true, 'cPanel account created successfully.', ['cpanel_username' => $username]);
        $this->logAction($service, 'create', $out);
        return $out;
    }

    public function suspend(Service $service, string $reason = ''): array
    {
        $server = $this->getServer($service);
        if (!$server) {
            return $this->buildResult(false, 'No cPanel/WHM server configured.');
        }

        $data     = $this->getModuleData($service);
        $username = $data['cpanel_username'] ?? $service->username;

        if (!$username) {
            return $this->buildResult(false, 'cPanel username not found in service notes.');
        }

        $result = $this->call($server, 'suspendacct', ['user' => $username, 'reason' => $reason]);

        if (!$result['success']) {
            return $this->buildResult(false, "Suspend failed: {$result['message']}");
        }

        $service->update(['status' => 'Suspended', 'suspension_date' => now(), 'suspension_reason' => $reason]);
        $out = $this->buildResult(true, 'cPanel account suspended.');
        $this->logAction($service, 'suspend', $out);
        return $out;
    }

    public function unsuspend(Service $service): array
    {
        $server = $this->getServer($service);
        if (!$server) {
            return $this->buildResult(false, 'No cPanel/WHM server configured.');
        }

        $data     = $this->getModuleData($service);
        $username = $data['cpanel_username'] ?? $service->username;

        if (!$username) {
            return $this->buildResult(false, 'cPanel username not found in service notes.');
        }

        $result = $this->call($server, 'unsuspendacct', ['user' => $username]);

        if (!$result['success']) {
            return $this->buildResult(false, "Unsuspend failed: {$result['message']}");
        }

        $service->update(['status' => 'Active', 'suspension_date' => null, 'suspension_reason' => null]);
        $out = $this->buildResult(true, 'cPanel account unsuspended.');
        $this->logAction($service, 'unsuspend', $out);
        return $out;
    }

    public function terminate(Service $service): array
    {
        $server = $this->getServer($service);
        if (!$server) {
            return $this->buildResult(false, 'No cPanel/WHM server configured.');
        }

        $data     = $this->getModuleData($service);
        $username = $data['cpanel_username'] ?? $service->username;

        if (!$username) {
            return $this->buildResult(false, 'cPanel username not found in service notes.');
        }

        $result = $this->call($server, 'removeacct', ['user' => $username]);

        if (!$result['success']) {
            return $this->buildResult(false, "Terminate failed: {$result['message']}");
        }

        $service->update(['status' => 'Terminated', 'termination_date' => now()]);
        $out = $this->buildResult(true, 'cPanel account terminated.');
        $this->logAction($service, 'terminate', $out);
        return $out;
    }

    public function changePassword(Service $service, string $newPassword): array
    {
        $server = $this->getServer($service);
        if (!$server) {
            return $this->buildResult(false, 'No cPanel/WHM server configured.');
        }

        $data     = $this->getModuleData($service);
        $username = $data['cpanel_username'] ?? $service->username;

        if (!$username) {
            return $this->buildResult(false, 'cPanel username not found in service notes.');
        }

        $result = $this->call($server, 'passwd', ['user' => $username, 'password' => $newPassword]);

        if (!$result['success']) {
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
        if (!$server) {
            return $this->buildResult(false, 'No cPanel/WHM server configured.');
        }

        $data     = $this->getModuleData($service);
        $username = $data['cpanel_username'] ?? $service->username;

        if (!$username) {
            return $this->buildResult(false, 'cPanel username not found in service notes.');
        }

        $config = is_string($newPackage['config_options'] ?? null)
            ? json_decode($newPackage['config_options'], true)
            : ($newPackage['config_options'] ?? []);
        $packageName = $config['cpanel_package'] ?? $config['package_name'] ?? null;

        if (!$packageName) {
            return $this->buildResult(false, 'New product does not have a cpanel_package configured.');
        }

        $result = $this->call($server, 'changepackage', ['user' => $username, 'pkg' => $packageName]);

        if (!$result['success']) {
            return $this->buildResult(false, "Package change failed: {$result['message']}");
        }

        $out = $this->buildResult(true, 'Package changed successfully.');
        $this->logAction($service, 'changePackage', $out);
        return $out;
    }

    public function usageUpdate(Server $server): array
    {
        $services = \App\Models\Service::where('server_id', $server->id)
            ->where('status', 'Active')
            ->get();

        $updated = 0;
        $errors  = 0;

        foreach ($services as $service) {
            $data     = $this->getModuleData($service);
            $username = $data['cpanel_username'] ?? $service->username;

            if (!$username) {
                $errors++;
                continue;
            }

            $result = $this->call($server, 'accountsummary', ['user' => $username]);

            if (!$result['success']) {
                $errors++;
                continue;
            }

            $raw       = $result['raw'];
            $acct      = $raw['acct'][0] ?? $raw ?? [];
            $diskUsed  = $acct['diskused']  ?? null;
            $diskLimit = $acct['disklimit'] ?? null;
            $bwUsed    = $acct['bandwidth'] ?? null;
            $bwLimit   = $acct['bwlimit']   ?? null;

            $updateData = [];
            if ($diskUsed  !== null) $updateData['disk_usage'] = (int)$diskUsed;
            if ($diskLimit !== null) $updateData['disk_limit'] = (int)$diskLimit;
            if ($bwUsed    !== null) $updateData['bw_usage']   = (int)$bwUsed;
            if ($bwLimit   !== null) $updateData['bw_limit']   = (int)$bwLimit;

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
            $result = $this->call($server, 'version');
            return $result['success'];
        } catch (\Throwable $e) {
            Log::error('CPanelModule::testConnection exception', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
