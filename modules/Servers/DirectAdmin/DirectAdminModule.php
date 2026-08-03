<?php

namespace Modules\Servers\DirectAdmin;

use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Servers\AbstractServerModule;

class DirectAdminModule extends AbstractServerModule
{
    public function getModuleName(): string
    {
        return 'directadmin';
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'login_key', 'label' => 'Login Key (optional, used instead of password)', 'type' => 'password'],
        ];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function baseUrl(Server $server): string
    {
        $port = $server->port ?: 2222;

        return "https://{$this->serverHost($server)}:{$port}";
    }

    private function http(Server $server)
    {
        $username = $server->username;
        // access_hash holds the login key when set; fall back to password
        $credential = $server->access_hash ?: $server->password;

        return Http::withoutVerifying()
            ->withBasicAuth($username, $credential)
            ->timeout(30);
    }

    private function parseDA(string $body): array
    {
        // An HTML body means we hit the login page → authentication failed.
        if (stripos($body, '<html') !== false || stripos($body, '<!doctype') !== false) {
            return ['error' => '1', 'text' => 'DirectAdmin authentication failed (login page returned)'];
        }

        // DirectAdmin CMD_API responses are URL-encoded key=value pairs
        $result = [];
        parse_str($body, $result);

        return $result;
    }

    private function getUsername(Service $service): ?string
    {
        return $this->getModuleData($service)['da_username'] ?? $service->username ?? null;
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
        $username = $service->username ?: 'u'.$service->id;
        $email = $client->email ?? '';
        $password = $service->password ?: Str::random(16);
        $domain = $service->domain ?: '';
        $package = $this->getRemotePackage($service) ?? 'Default';

        $resp = $this->http($server)->asForm()->post("{$this->baseUrl($server)}/CMD_API_ACCOUNT_USER", [
            'action' => 'create',
            'username' => $username,
            'email' => $email,
            'passwd' => $password,
            'passwd2' => $password,
            'domain' => $domain,
            'package' => $package,
            'ip' => 'shared',
            'notify' => 'no',
        ]);

        $data = $this->parseDA($resp->body());

        if (! $resp->successful() || ($data['error'] ?? '0') !== '0') {
            $msg = $data['text'] ?? $data['details'] ?? $resp->body();
            Log::error('DirectAdmin create account failed', ['body' => $resp->body()]);

            return $this->buildResult(false, 'Failed to create DirectAdmin account: '.$msg);
        }

        $this->setModuleData($service, ['da_username' => $username]);
        $service->update(['username' => $username, 'password' => $password]);

        $this->logAction($service, 'create', ['success' => true]);

        return $this->buildResult(true, 'DirectAdmin account created successfully.', [
            'da_username' => $username,
        ]);
    }

    public function suspend(Service $service, string $reason = ''): array
    {
        $server = $this->getServer($service);
        $username = $this->getUsername($service);

        if (! $server || ! $username) {
            return $this->buildResult(false, 'Missing server or DirectAdmin username.');
        }

        $resp = $this->http($server)->asForm()->post("{$this->baseUrl($server)}/CMD_API_SELECT_USERS", [
            'location' => 'USER_SHOW',
            'suspend' => 'Suspend',
            'select0' => $username,
        ]);

        $data = $this->parseDA($resp->body());
        $ok = $resp->successful() && ($data['error'] ?? '0') === '0';
        $result = $this->buildResult($ok, $ok ? 'Account suspended.' : ($data['text'] ?? $resp->body()));
        $this->logAction($service, 'suspend', $result);

        return $result;
    }

    public function unsuspend(Service $service): array
    {
        $server = $this->getServer($service);
        $username = $this->getUsername($service);

        if (! $server || ! $username) {
            return $this->buildResult(false, 'Missing server or DirectAdmin username.');
        }

        $resp = $this->http($server)->asForm()->post("{$this->baseUrl($server)}/CMD_API_SELECT_USERS", [
            'location' => 'USER_SHOW',
            'suspend' => 'Unsuspend',
            'select0' => $username,
        ]);

        $data = $this->parseDA($resp->body());
        $ok = $resp->successful() && ($data['error'] ?? '0') === '0';
        $result = $this->buildResult($ok, $ok ? 'Account unsuspended.' : ($data['text'] ?? $resp->body()));
        $this->logAction($service, 'unsuspend', $result);

        return $result;
    }

    public function terminate(Service $service): array
    {
        $server = $this->getServer($service);
        $username = $this->getUsername($service);

        if (! $server || ! $username) {
            return $this->buildResult(false, 'Missing server or DirectAdmin username.');
        }

        $resp = $this->http($server)->asForm()->post("{$this->baseUrl($server)}/CMD_API_SELECT_USERS", [
            'confirmed' => 'Confirm',
            'delete' => 'yes',
            'select0' => $username,
        ]);

        $data = $this->parseDA($resp->body());
        $ok = $resp->successful() && ($data['error'] ?? '0') === '0';
        $result = $this->buildResult($ok, $ok ? 'Account terminated.' : ($data['text'] ?? $resp->body()));
        $this->logAction($service, 'terminate', $result);

        return $result;
    }

    public function changePassword(Service $service, string $newPassword): array
    {
        $server = $this->getServer($service);
        $username = $this->getUsername($service);

        if (! $server || ! $username) {
            return $this->buildResult(false, 'Missing server or DirectAdmin username.');
        }

        $resp = $this->http($server)->asForm()->post("{$this->baseUrl($server)}/CMD_API_USER_PASSWD", [
            'username' => $username,
            'passwd' => $newPassword,
            'passwd2' => $newPassword,
        ]);

        $data = $this->parseDA($resp->body());
        $ok = $resp->successful() && ($data['error'] ?? '0') === '0';

        return $this->buildResult($ok, $ok ? 'Password changed.' : ($data['text'] ?? $resp->body()));
    }

    public function changePackage(Service $service, array $newPackage): array
    {
        $server = $this->getServer($service);
        $username = $this->getUsername($service);
        $package = $newPackage['package_name'] ?? $newPackage['name'] ?? null;

        if (! $server || ! $username || ! $package) {
            return $this->buildResult(false, 'Missing server, username, or package name.');
        }

        $resp = $this->http($server)->asForm()->post("{$this->baseUrl($server)}/CMD_API_MODIFY_USER", [
            'user' => $username,
            'package' => $package,
        ]);

        $data = $this->parseDA($resp->body());
        $ok = $resp->successful() && ($data['error'] ?? '0') === '0';

        return $this->buildResult($ok, $ok ? 'Package changed.' : ($data['text'] ?? $resp->body()));
    }

    public function usageUpdate(Server $server): array
    {
        $results = [];

        try {
            // Get all users first
            $usersResp = $this->http($server)->get("{$this->baseUrl($server)}/CMD_API_SHOW_ALL_USERS");
            if (! $usersResp->successful()) {
                return [];
            }

            $users = $this->parseDA($usersResp->body());
            $list = isset($users['list']) ? explode(',', $users['list']) : [];

            foreach ($list as $user) {
                $user = trim($user);
                if (empty($user)) {
                    continue;
                }

                $usageResp = $this->http($server)->get(
                    "{$this->baseUrl($server)}/CMD_API_SHOW_USER_USAGE",
                    ['user' => $user]
                );

                if ($usageResp->successful()) {
                    $usage = $this->parseDA($usageResp->body());
                    $results[] = [
                        'username' => $user,
                        'disk_usage' => $usage['quota'] ?? 0,
                        'bw_usage' => $usage['bandwidth'] ?? 0,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::error('DirectAdmin usageUpdate failed', ['server' => $server->id, 'error' => $e->getMessage()]);
        }

        return $results;
    }

    public function testConnection(Server $server): bool
    {
        try {
            $resp = $this->http($server)->get("{$this->baseUrl($server)}/CMD_API_SHOW_ALL_USERS");

            return $resp->successful();
        } catch (\Throwable $e) {
            Log::warning('DirectAdmin testConnection failed', ['server' => $server->id, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
