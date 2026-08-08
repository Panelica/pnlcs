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
        $username = $this->accountUsername($service);
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

        $config = is_string($newPackage['config_options'] ?? null)
            ? json_decode($newPackage['config_options'], true)
            : ($newPackage['config_options'] ?? []);
        $package = $config['directadmin_package']
            ?? $config['package_name']
            ?? $newPackage['package_name']
            ?? $newPackage['name']
            ?? null;

        if (! $server || ! $username || ! $package) {
            return $this->buildResult(false, 'Missing server, username, or package name.');
        }

        // CMD_API_MODIFY_USER does nothing without an action. Without it the
        // call came back clean and the account stayed on its old package.
        $resp = $this->http($server)->asForm()->post("{$this->baseUrl($server)}/CMD_API_MODIFY_USER", [
            'action' => 'package',
            'user' => $username,
            'package' => $package,
        ]);

        $data = $this->parseDA($resp->body());
        $ok = $resp->successful() && ($data['error'] ?? '0') === '0';

        return $this->buildResult($ok, $ok ? 'Package changed.' : ($data['text'] ?? $resp->body()));
    }

    /**
     * Disk and bandwidth for the accounts this panel put on this server.
     *
     * It used to walk the server's whole user list and hand back a list of
     * rows, writing nothing: the callers and both sibling modules expect
     * ['updated' => n, 'errors' => n] and the figures stored on the service.
     * The listing was read with explode() on what DirectAdmin answers as
     * list[]=..., which is an array, so in PHP 8 it raised a TypeError and the
     * usage of a DirectAdmin account was never recorded at all.
     */
    public function usageUpdate(Server $server): array
    {
        $updated = 0;
        $errors = 0;

        $services = Service::where('server_id', $server->id)
            ->where('status', 'active')
            ->get();

        foreach ($services as $service) {
            $username = $this->getUsername($service);

            if (! $username) {
                $errors++;

                continue;
            }

            try {
                $usage = $this->parseDA($this->http($server)->get(
                    "{$this->baseUrl($server)}/CMD_API_SHOW_USER_USAGE",
                    ['user' => $username]
                )->body());

                if (($usage['error'] ?? '0') !== '0' || ! isset($usage['quota'], $usage['bandwidth'])) {
                    $errors++;

                    continue;
                }

                // DirectAdmin answers in megabytes, which is what the service
                // stores.
                $updateData = [
                    'disk_usage' => (int) $usage['quota'],
                    'bw_usage' => (int) $usage['bandwidth'],
                ];

                $config = $this->parseDA($this->http($server)->get(
                    "{$this->baseUrl($server)}/CMD_API_SHOW_USER_CONFIG",
                    ['user' => $username]
                )->body());

                // "unlimited" is not a number; leaving the limit alone is the
                // truth, writing 0 would read as a limit of nothing.
                foreach (['quota' => 'disk_limit', 'bandwidth' => 'bw_limit'] as $key => $column) {
                    $limit = $config[$key] ?? null;

                    if ($limit !== null && is_numeric($limit)) {
                        $updateData[$column] = (int) $limit;
                    }
                }

                $service->update($updateData);
                $updated++;
            } catch (\Throwable $e) {
                $errors++;
                Log::error('DirectAdmin usageUpdate failed', ['service' => $service->id, 'error' => $e->getMessage()]);
            }
        }

        return ['updated' => $updated, 'errors' => $errors];
    }

    /**
     * The packages this DirectAdmin server offers, for the product form.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function listPackages(Server $server): array
    {
        try {
            $resp = $this->http($server)->get("{$this->baseUrl($server)}/CMD_API_PACKAGES_USER");
        } catch (\Throwable $e) {
            Log::warning('DirectAdmin listPackages failed', ['server' => $server->id, 'error' => $e->getMessage()]);

            return [];
        }

        if (! $resp->successful()) {
            return [];
        }

        $data = $this->parseDA($resp->body());

        if (($data['error'] ?? '0') !== '0') {
            return [];
        }

        $names = $data['list'] ?? [];
        $names = is_array($names) ? $names : explode(',', (string) $names);

        $packages = [];

        foreach ($names as $name) {
            $name = trim((string) $name);

            if ($name !== '') {
                $packages[$name] = ['id' => $name, 'name' => $name];
            }
        }

        ksort($packages);

        return array_values($packages);
    }

    /**
     * A username DirectAdmin will accept.
     *
     * Lower case letters and digits, starting with a letter, kept short. The
     * module used to send whatever the service carried, or "u" and the row id -
     * a customer-chosen name with a dot or a capital in it was refused by
     * DirectAdmin and the order stopped there.
     */
    private function accountUsername(Service $service): string
    {
        $existing = preg_replace('/[^a-z0-9]/', '', strtolower((string) $service->username));

        if ($existing !== '' && ! ctype_digit($existing[0])) {
            return substr($existing, 0, 10);
        }

        $base = preg_replace('/[^a-z0-9]/', '', strtolower(explode('.', (string) $service->domain)[0]));
        $base = ltrim($base, '0123456789');

        if (strlen($base) < 4) {
            $base = ($base ?: 'user').$service->id;
        }

        return substr($base, 0, 10);
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
