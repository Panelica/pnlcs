<?php

namespace Modules\Servers\HestiaCP;

use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Servers\AbstractServerModule;

class HestiaCPModule extends AbstractServerModule
{
    public function getModuleName(): string
    {
        return 'hestiacp';
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'username', 'label' => 'HestiaCP Admin Username', 'type' => 'text'],
            ['name' => 'password', 'label' => 'HestiaCP Admin Password', 'type' => 'password'],
        ];
    }

    private function baseUrl(Server $server): string
    {
        $port = $server->port ?: 8083;
        return "https://{$server->hostname}:{$port}/api";
    }

    private function call(Server $server, string $command, array $params = []): array
    {
        $url = $this->baseUrl($server);
        $username = $server->username ?: 'admin';
        $password = $server->access_hash ?? '';

        $postData = array_merge([
            'user' => $username,
            'password' => $password,
            'returncode' => 'no',
            'cmd' => $command,
        ], $params);

        try {
            $response = Http::asForm()
                ->withoutVerifying()
                ->timeout(30)
                ->post($url, $postData);

            if (!$response->successful()) {
                return ['success' => false, 'message' => "HestiaCP API HTTP {$response->status()}", 'raw' => []];
            }

            $body = $response->body();
            $json = json_decode($body, true);

            // HestiaCP returns 0 for success in returncode mode
            if (is_array($json)) {
                return ['success' => true, 'message' => 'OK', 'raw' => $json];
            }

            // Non-JSON response = command output
            $exitCode = (int) trim($body);
            return [
                'success' => $exitCode === 0,
                'message' => $exitCode === 0 ? 'OK' : "HestiaCP command failed (code: {$exitCode})",
                'raw' => ['exit_code' => $exitCode, 'output' => $body],
            ];
        } catch (\Throwable $e) {
            Log::error("HestiaCPModule API error: {$e->getMessage()}");
            return ['success' => false, 'message' => $e->getMessage(), 'raw' => []];
        }
    }

    public function create(Service $service): array
    {
        $server = $this->getServer($service);
        if (!$server) {
            return $this->buildResult(false, 'No HestiaCP server configured.');
        }

        $client = $service->client;
        $domain = $service->domain;
        if (!$client || !$domain) {
            return $this->buildResult(false, 'Service is missing client or domain.');
        }

        // Create username: lowercase, no special chars, max 16
        $username = preg_replace('/[^a-z0-9]/', '', strtolower(explode('.', $domain)[0]));
        $username = substr($username ?: 'user', 0, 16);
        $password = $service->password ?: bin2hex(random_bytes(8));
        $package = $this->getRemotePackage($service) ?? 'default';

        // v-add-user
        $result = $this->call($server, 'v-add-user', [
            'arg1' => $username,
            'arg2' => $password,
            'arg3' => $client->email,
            'arg4' => $package,
        ]);

        if (!$result['success']) {
            return $this->buildResult(false, "Account creation failed: {$result['message']}");
        }

        // v-add-domain
        $domResult = $this->call($server, 'v-add-domain', [
            'arg1' => $username,
            'arg2' => $domain,
        ]);

        if (!$domResult['success']) {
            Log::warning("HestiaCP: user created but domain add failed: {$domResult['message']}");
        }

        $this->setModuleData($service, ['hestia_username' => $username]);
        $service->update(['username' => $username, 'status' => 'Active']);

        $out = $this->buildResult(true, 'HestiaCP account created.', ['hestia_username' => $username]);
        $this->logAction($service, 'create', $out);
        return $out;
    }

    public function suspend(Service $service, string $reason = ''): array
    {
        $server = $this->getServer($service);
        if (!$server) {
            return $this->buildResult(false, 'No HestiaCP server configured.');
        }

        $data = $this->getModuleData($service);
        $username = $data['hestia_username'] ?? $service->username;
        if (!$username) {
            return $this->buildResult(false, 'HestiaCP username not found.');
        }

        $result = $this->call($server, 'v-suspend-user', ['arg1' => $username]);
        if (!$result['success']) {
            return $this->buildResult(false, "Suspend failed: {$result['message']}");
        }

        $service->update(['status' => 'Suspended', 'suspension_date' => now(), 'suspension_reason' => $reason]);
        $out = $this->buildResult(true, 'HestiaCP account suspended.');
        $this->logAction($service, 'suspend', $out);
        return $out;
    }

    public function unsuspend(Service $service): array
    {
        $server = $this->getServer($service);
        if (!$server) {
            return $this->buildResult(false, 'No HestiaCP server configured.');
        }

        $data = $this->getModuleData($service);
        $username = $data['hestia_username'] ?? $service->username;
        if (!$username) {
            return $this->buildResult(false, 'HestiaCP username not found.');
        }

        $result = $this->call($server, 'v-unsuspend-user', ['arg1' => $username]);
        if (!$result['success']) {
            return $this->buildResult(false, "Unsuspend failed: {$result['message']}");
        }

        $service->update(['status' => 'Active', 'suspension_date' => null, 'suspension_reason' => null]);
        $out = $this->buildResult(true, 'HestiaCP account unsuspended.');
        $this->logAction($service, 'unsuspend', $out);
        return $out;
    }

    public function terminate(Service $service): array
    {
        $server = $this->getServer($service);
        if (!$server) {
            return $this->buildResult(false, 'No HestiaCP server configured.');
        }

        $data = $this->getModuleData($service);
        $username = $data['hestia_username'] ?? $service->username;
        if (!$username) {
            return $this->buildResult(false, 'HestiaCP username not found.');
        }

        $result = $this->call($server, 'v-delete-user', ['arg1' => $username]);
        if (!$result['success']) {
            return $this->buildResult(false, "Terminate failed: {$result['message']}");
        }

        $service->update(['status' => 'Terminated', 'termination_date' => now()]);
        $out = $this->buildResult(true, 'HestiaCP account terminated.');
        $this->logAction($service, 'terminate', $out);
        return $out;
    }

    public function changePassword(Service $service, string $newPassword): array
    {
        $server = $this->getServer($service);
        if (!$server) {
            return $this->buildResult(false, 'No HestiaCP server configured.');
        }

        $data = $this->getModuleData($service);
        $username = $data['hestia_username'] ?? $service->username;
        if (!$username) {
            return $this->buildResult(false, 'HestiaCP username not found.');
        }

        $result = $this->call($server, 'v-change-user-password', [
            'arg1' => $username,
            'arg2' => $newPassword,
        ]);

        if (!$result['success']) {
            return $this->buildResult(false, "Password change failed: {$result['message']}");
        }

        $service->update(['password' => $newPassword]);
        $out = $this->buildResult(true, 'Password changed.');
        $this->logAction($service, 'changePassword', $out);
        return $out;
    }

    public function changePackage(Service $service, array $newPackage): array
    {
        $server = $this->getServer($service);
        if (!$server) {
            return $this->buildResult(false, 'No HestiaCP server configured.');
        }

        $data = $this->getModuleData($service);
        $username = $data['hestia_username'] ?? $service->username;
        if (!$username) {
            return $this->buildResult(false, 'HestiaCP username not found.');
        }

        $config = is_string($newPackage['config_options'] ?? null)
            ? json_decode($newPackage['config_options'], true)
            : ($newPackage['config_options'] ?? []);
        $packageName = $config['hestia_package'] ?? $config['package_name'] ?? null;

        if (!$packageName) {
            return $this->buildResult(false, 'No hestia_package configured in product.');
        }

        $result = $this->call($server, 'v-change-user-package', [
            'arg1' => $username,
            'arg2' => $packageName,
        ]);

        if (!$result['success']) {
            return $this->buildResult(false, "Package change failed: {$result['message']}");
        }

        $out = $this->buildResult(true, 'Package changed.');
        $this->logAction($service, 'changePackage', $out);
        return $out;
    }

    public function usageUpdate(Server $server): array
    {
        $result = $this->call($server, 'v-list-users', ['arg1' => 'json']);

        if (!$result['success'] || !is_array($result['raw'])) {
            return ['updated' => 0, 'errors' => 1];
        }

        $updated = 0;
        foreach ($result['raw'] as $username => $userData) {
            $service = \App\Models\Service::where('server_id', $server->id)
                ->where('username', $username)
                ->where('status', 'Active')
                ->first();

            if (!$service) {
                continue;
            }

            $updateData = [];
            if (isset($userData['DISK_USED'])) {
                $updateData['disk_usage'] = (int) $userData['DISK_USED'];
            }
            if (isset($userData['DISK_QUOTA'])) {
                $updateData['disk_limit'] = (int) $userData['DISK_QUOTA'];
            }
            if (isset($userData['BANDWIDTH'])) {
                $updateData['bw_usage'] = (int) $userData['BANDWIDTH'];
            }

            if (!empty($updateData)) {
                $service->update($updateData);
                $updated++;
            }
        }

        return ['updated' => $updated, 'errors' => 0];
    }

    public function testConnection(Server $server): bool
    {
        try {
            $result = $this->call($server, 'v-list-sys-info', ['arg1' => 'json']);
            return $result['success'];
        } catch (\Throwable $e) {
            Log::error("HestiaCPModule::testConnection: {$e->getMessage()}");
            return false;
        }
    }
}
