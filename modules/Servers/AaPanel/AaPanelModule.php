<?php

namespace Modules\Servers\AaPanel;

use App\Contracts\RequiresProvisioningLock;
use App\Models\Client;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Cache;
use Modules\Servers\AbstractServerModule;

/** Provision Sub aaPanel accounts, not websites on the administrator's account. */
class AaPanelModule extends AbstractServerModule implements RequiresProvisioningLock
{
    private const LIMITS = ['disk_space_quota', 'monthly_bandwidth_limit', 'max_site_limit', 'max_database', 'php_start_children', 'php_max_children'];

    public function getModuleName(): string
    {
        return 'aapanel';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getConfigFields(): array
    {
        // Uses the existing server form; access_hash is encrypted by Server.
        return [];
    }

    public function testConnection(Server $server): bool
    {
        try {
            $api = new AaPanelClient($server);
            $api->assertReady();
            $api->listing('get_package_list');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function listPackages(Server $server): array
    {
        $api = new AaPanelClient($server);
        $api->assertReady();
        $packages = [];
        foreach ($api->listing('get_package_list') as $package) {
            if (isset($package['package_id'], $package['package_name'])) {
                // PNLCS stores this as package_name. Names also work across a
                // server group whose members assign different numeric IDs.
                $name = (string) $package['package_name'];
                $packages[] = ['id' => $name, 'name' => $name];
            }
        }

        return $packages;
    }

    /**
     * @return array<string, mixed>
     */
    public function create(Service $service): array
    {
        return $this->run($service, 'create', fn () => $this->createAccount($service));
    }

    /** @return array<string, mixed> */
    private function createAccount(Service $service): array
    {
        $this->assertMutable($service);
        $data = $this->getModuleData($service);
        if (! empty($data['aapanel_modify_pending'])) {
            throw new AaPanelException('Resolve the pending aaPanel modification before activation.');
        }
        $server = $this->getServer($service);
        if (! $server || strtolower((string) $server->type) !== 'aapanel') {
            throw new AaPanelException('No aaPanel server configured.');
        }
        $this->assertServerBinding($data, $server);
        $api = new AaPanelClient($server);
        $api->assertReady();
        [$username, $email, $owner] = $this->createIdentity($service, $server);
        $existing = $this->findAccount($api, $username);
        if ($existing !== null) {
            $this->bindActiveAccount($service, $server, $existing);

            return $this->buildResult(true, 'Existing aaPanel account reconciled.', ['aapanel_account_id' => $existing['account_id']]);
        }
        $this->assertNewCreate($data);
        $this->sendCreate($service, $api, $username, $email, $owner);
        $account = $this->findAccount($api, $username);
        if ($account === null) {
            throw new AaPanelException('aaPanel accepted creation but the account is not yet visible. Retry will reconcile it without sending another create.');
        }
        $this->bindActiveAccount($service, $server, $account);

        return $this->buildResult(true, 'aaPanel hosting account created.', ['aapanel_account_id' => $account['account_id']]);
    }

    /** @param array<string, mixed> $data */
    private function assertServerBinding(array $data, Server $server): void
    {
        if (isset($data['aapanel_server_id']) && (int) $data['aapanel_server_id'] !== (int) $server->id) {
            throw new AaPanelException('The aaPanel server binding changed. Restore the original server before retrying.');
        }
    }

    /** @return array{string, string, string} */
    private function createIdentity(Service $service, Server $server): array
    {
        $data = $this->getModuleData($service);
        $username = (string) ($data['aapanel_username'] ?? ($service->username ?: 'pnlcs'.$service->id));
        if (! preg_match('/^[a-z][a-z0-9_]{2,31}$/D', $username)) {
            throw new AaPanelException('Use an aaPanel username of 3–32 lowercase letters, numbers or underscores, starting with a letter.');
        }
        /** @var Client|null $client */
        $client = $service->getRelationValue('client');
        $email = (string) $client?->email;
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new AaPanelException('The hosting service needs a valid client email address.');
        }
        $owner = (string) ($data['aapanel_owner'] ?? 'pnlcs:'.bin2hex(random_bytes(16)));
        $this->setModuleData($service, [
            'aapanel_owner' => $owner, 'aapanel_username' => $username,
            'aapanel_server_id' => $server->id,
        ]);

        return [$username, $email, $owner];
    }

    /** @param array<string, mixed> $data */
    private function assertNewCreate(array $data): void
    {
        if (! empty($data['aapanel_account_id'])) {
            throw new AaPanelException('The recorded aaPanel account does not exist. Refusing to recreate it automatically.');
        }
        if (! empty($data['aapanel_create_pending'])) {
            throw new AaPanelException('A previous aaPanel create has an unknown outcome. Inspect the panel before retrying; no duplicate create was sent.');
        }
    }

    private function sendCreate(Service $service, AaPanelClient $api, string $username, string $email, string $owner): void
    {
        $package = $this->package($api, $this->getRemotePackage($service));
        $mountpoint = $this->storageMountpoint($api);
        $password = $service->password ?: bin2hex(random_bytes(16)).'aA!';
        $this->saveAttributes($service, ['username' => $username, 'password' => $password]);
        $this->setModuleData($service, ['aapanel_create_pending' => true]);
        try {
            $api->request('create_account', array_merge($this->limits($package), [
                'username' => $username, 'password' => $password, 'email' => $email,
                'expire_date' => '0000-00-00', 'package_id' => $package['package_id'],
                'mountpoint' => $mountpoint, 'remark' => $owner, 'automatic_dns' => 0,
            ]));
        } catch (AaPanelException $e) {
            if (! $e->outcomeUnknown) {
                $this->setModuleData($service, ['aapanel_create_pending' => false]);
            }
            throw $e;
        }
    }

    private function storageMountpoint(AaPanelClient $api): string
    {
        $disks = $api->request('get_disk_list')['message'] ?? null;
        $disks = is_array($disks) ? ($disks['list'] ?? $disks) : null;
        if (! is_array($disks) || $disks === [] || ! array_is_list($disks)) {
            throw new AaPanelException('No aaPanel storage disk is available. Configure storage in Account first.');
        }
        $selected = null;
        foreach ($disks as $disk) {
            $mountpoint = $this->validatedMountpoint($disk);
            $selected ??= $mountpoint;
            if (in_array($disk['is_default'] ?? null, [true, 1, '1'], true)) {
                return $mountpoint;
            }
        }

        return $selected;
    }

    private function validatedMountpoint(mixed $disk): string
    {
        $mountpoint = is_array($disk) ? ($disk['mountpoint'] ?? null) : null;
        if (! is_string($mountpoint) || ! str_starts_with($mountpoint, '/') || str_contains($mountpoint, "\0")) {
            throw new AaPanelException('aaPanel did not return a valid storage mountpoint.');
        }

        return $mountpoint;
    }

    /** @param array<string, mixed> $account */
    private function bindActiveAccount(Service $service, Server $server, array $account): void
    {
        $this->assertIdentity($service, $server, $account, false);
        if (! in_array($account['status'] ?? null, [1, '1'], true)) {
            throw new AaPanelException('The owned aaPanel account exists but is not active. Review it before activation.');
        }
        $this->bindAccount($service, $account);
    }

    /**
     * @return array<string, mixed>
     */
    public function suspend(Service $service, string $reason = ''): array
    {
        return $this->setStatus($service, 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function unsuspend(Service $service): array
    {
        return $this->setStatus($service, 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function setStatus(Service $service, int $status): array
    {
        return $this->run($service, $status ? 'unsuspend' : 'suspend', function () use ($service, $status) {
            [$api, $account] = $this->accountContext($service);
            $this->modify($service, $api, $account, ['status' => $status], 'status');

            return $this->buildResult(true, $status ? 'aaPanel account enabled.' : 'aaPanel account suspended.');
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function terminate(Service $service): array
    {
        return $this->run($service, 'terminate', function () use ($service) {
            [$api, $account] = $this->accountContext($service, true);
            if ($account !== null) {
                $data = $this->getModuleData($service);
                if (! empty($data['aapanel_terminated'])) {
                    throw new AaPanelException('A previously terminated aaPanel identity has reappeared. Inspect it before any further deletion.');
                }
                $this->reconcileModification($service, $account);
                if (! empty($data['aapanel_terminate_pending'])) {
                    throw new AaPanelException('A previous aaPanel termination has an unknown outcome. Inspect the exact account before retrying; no delete was repeated.');
                }
                $this->setModuleData($service, ['aapanel_terminate_pending' => true]);
                try {
                    $api->request('remove_account', ['account_id' => $account['account_id'], 'is_del_resources' => 1]);
                } catch (AaPanelException $e) {
                    if (! $e->outcomeUnknown) {
                        $this->setModuleData($service, ['aapanel_terminate_pending' => false]);
                    }
                    throw $e;
                }
            }
            // Retain identity as a tombstone; repeating a terminate is safe,
            // whereas clearing it could let a recycled username be adopted.
            $this->setModuleData($service, ['aapanel_terminated' => true, 'aapanel_terminate_pending' => false]);

            return $this->buildResult(true, 'aaPanel account removed or already absent.');
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function changePassword(Service $service, string $newPassword): array
    {
        return $this->run($service, 'changePassword', function () use ($service, $newPassword) {
            if (strlen($newPassword) < 8 || strlen($newPassword) > 255 || str_contains($newPassword, "\0")) {
                throw new AaPanelException('Use an aaPanel password between 8 and 255 characters.');
            }
            [$api, $account] = $this->accountContext($service);
            $this->modify($service, $api, $account, ['password' => $newPassword], 'password');

            return $this->buildResult(true, 'aaPanel password changed.');
        });
    }

    /**
     * @param  array<string, mixed>  $newPackage
     * @return array<string, mixed>
     */
    public function changePackage(Service $service, array $newPackage): array
    {
        return $this->run($service, 'changePackage', function () use ($service, $newPackage) {
            [$api, $account] = $this->accountContext($service);
            $config = $newPackage['config_options'] ?? [];
            $config = is_string($config) ? json_decode($config, true) : $config;
            $name = is_array($config) ? ($config['package_name'] ?? null) : null;
            $package = $this->package($api, $name);
            $this->modify($service, $api, $account, array_merge($this->limits($package), ['package_id' => $package['package_id']]), 'package');

            return $this->buildResult(true, 'aaPanel resource package changed.');
        });
    }

    /**
     * Reconcile a lost response before another modification. Passwords cannot
     * be verified by reading the API, so an unknown password outcome requires
     * operator intervention. No password or password hash goes in module_data.
     *
     * @param  array<string, mixed>  $account
     * @param  array<string, mixed>  $changes
     */
    private function modify(Service $service, AaPanelClient $api, array $account, array $changes, string $action): void
    {
        $this->reconcileModification($service, $account);
        if ($action !== 'password' && $this->matches($account, $changes)) {
            return;
        }
        $payload = array_merge($this->editable($account), $changes);
        $this->setModuleData($service, ['aapanel_modify_pending' => [
            'action' => $action, 'target' => $action === 'password' ? [] : $changes,
        ]]);
        try {
            $api->request('modify_account', $payload);
        } catch (AaPanelException $e) {
            if (! $e->outcomeUnknown) {
                $this->setModuleData($service, ['aapanel_modify_pending' => null]);
            }
            throw $e;
        }
        if ($action === 'password') {
            $this->saveAttributes($service, ['password' => $changes['password']]);
        }
        $this->setModuleData($service, ['aapanel_modify_pending' => null]);
    }

    /** @param array<string, mixed> $account */
    private function reconcileModification(Service $service, array $account): void
    {
        $pending = $this->getModuleData($service)['aapanel_modify_pending'] ?? null;
        if ($pending === null) {
            return;
        }
        if (! is_array($pending) || ! in_array($pending['action'] ?? null, ['status', 'package'], true)
            || ! is_array($pending['target'] ?? null) || ! $this->matches($account, $pending['target'])) {
            throw new AaPanelException('A previous aaPanel modification has an unknown outcome. Inspect the account before another change; no modification was repeated.');
        }
        $this->setModuleData($service, ['aapanel_modify_pending' => null]);
    }

    /**
     * @param  array<string, mixed>  $account
     * @param  array<string, mixed>  $target
     */
    private function matches(array $account, array $target): bool
    {
        if ($target === []) {
            return false;
        }
        foreach ($target as $key => $value) {
            if (! isset($account[$key]) || ! is_scalar($account[$key]) || ! is_scalar($value)
                || (string) $account[$key] !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{updated: int, errors: int}
     */
    public function usageUpdate(Server $server): array
    {
        $updated = 0;
        $errors = 0;
        try {
            $api = new AaPanelClient($server);
            $api->assertReady();
            $accounts = $api->listing('get_account_list', ['type_id' => -1, 'search_value' => '']);
            $byId = [];
            foreach ($accounts as $account) {
                if (isset($account['account_id'])) {
                    $byId[(string) $account['account_id']] = $account;
                }
            }
            foreach (Service::where('server_id', $server->id)->whereIn('status', ['active', 'suspended'])->get() as $service) {
                try {
                    $id = $this->getModuleData($service)['aapanel_account_id'] ?? null;
                    $account = $byId[(string) $id] ?? null;
                    if ($account === null) {
                        throw new AaPanelException('aaPanel account does not exist.');
                    }
                    $this->assertIdentity($service, $server, $account);
                    $this->saveAttributes($service, $this->usage($account));
                    $updated++;
                } catch (\Throwable) {
                    $errors++;
                }
            }
        } catch (\Throwable) {
            return ['updated' => $updated, 'errors' => $errors + 1];
        }

        return ['updated' => $updated, 'errors' => $errors];
    }

    /**
     * @return array<string, mixed>
     */
    public function liveUsage(Service $service): array
    {
        try {
            [, $account] = $this->accountContext($service);
            if ($account === null) {
                return ['available' => false];
            }
            $usage = $this->usage($account);

            return [
                'available' => true,
                'disk' => ['used_mb' => $usage['disk_usage'], 'quota_mb' => $usage['disk_limit']],
                'bandwidth' => ['used_mb' => $usage['bw_usage'], 'quota_mb' => $usage['bw_limit']],
                'cpu' => null, 'ram' => null, 'domains' => [],
            ];
        } catch (\Throwable) {
            return ['available' => false];
        }
    }

    /**
     * @param  array<string, mixed>  $account
     * @return array<string, int>
     */
    private function usage(array $account): array
    {
        $result = [];
        foreach (['disk_space_used' => 'disk_usage', 'disk_space_quota' => 'disk_limit', 'monthly_bandwidth_used' => 'bw_usage', 'monthly_bandwidth_limit' => 'bw_limit'] as $remote => $local) {
            $bytes = AaPanelValue::integer($account[$remote] ?? null);
            $result[$local] = intdiv($bytes, 1048576) + (int) ($bytes % 1048576 !== 0);
        }

        return $result;
    }

    /**
     * @return array{0: AaPanelClient, 1: array<string, mixed>|null}
     */
    private function accountContext(Service $service, bool $allowMissing = false): array
    {
        if (! $allowMissing) {
            $this->assertMutable($service);
        }
        /** @var Server|null $server */
        $server = $service->getRelationValue('server');
        $data = $this->getModuleData($service);
        if (! $server || strtolower((string) $server->type) !== 'aapanel'
            || empty($data['aapanel_account_id']) || empty($data['aapanel_username'])
            || empty($data['aapanel_owner']) || (int) ($data['aapanel_server_id'] ?? 0) !== (int) $server->id) {
            throw new AaPanelException('No account binding for this aaPanel service. Restore its original server and remote identity.');
        }
        $api = new AaPanelClient($server);
        $api->assertReady();
        $account = $this->findAccount($api, (string) $data['aapanel_username']);
        if ($account === null && $allowMissing) {
            // A renamed account is not a deleted account. Before reconciling
            // an absent username as terminated, check the complete ID list.
            foreach ($api->listing('get_account_list', ['type_id' => -1, 'search_value' => '']) as $candidate) {
                if ((string) ($candidate['account_id'] ?? '') === (string) $data['aapanel_account_id']) {
                    $account = $candidate;
                    break;
                }
            }
        }
        if ($account === null && ! $allowMissing) {
            throw new AaPanelException('The aaPanel account does not exist.');
        }
        if ($account !== null) {
            $this->assertIdentity($service, $server, $account);
        }

        return [$api, $account];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findAccount(AaPanelClient $api, string $username): ?array
    {
        $matches = array_values(array_filter(
            $api->listing('get_account_list', ['type_id' => -1, 'search_value' => $username]),
            fn ($account) => ($account['username'] ?? null) === $username
        ));
        if (count($matches) > 1) {
            throw new AaPanelException('Multiple aaPanel accounts matched this username. Refusing an ambiguous action.');
        }

        return $matches[0] ?? null;
    }

    /** @param array<string, mixed> $account */
    private function assertIdentity(Service $service, Server $server, array $account, bool $requireId = true): void
    {
        $data = $this->getModuleData($service);
        if (empty($account['account_id'])
            || (string) ($data['aapanel_username'] ?? '') !== (string) ($account['username'] ?? '')
            || empty($data['aapanel_owner']) || ! hash_equals((string) $data['aapanel_owner'], (string) ($account['remark'] ?? ''))
            || (int) ($data['aapanel_server_id'] ?? 0) !== (int) $server->id
            || (($requireId || ! empty($data['aapanel_account_id'])) && (string) ($data['aapanel_account_id'] ?? '') !== (string) $account['account_id'])) {
            throw new AaPanelException('aaPanel account identity does not match this service. No account changes were made.');
        }
    }

    /** @param array<string, mixed> $account */
    private function bindAccount(Service $service, array $account): void
    {
        $this->setModuleData($service, ['aapanel_account_id' => $account['account_id'], 'aapanel_create_pending' => false]);
        $this->saveAttributes($service, ['username' => $account['username']]);
    }

    /**
     * @return array<string, mixed>
     */
    private function package(AaPanelClient $api, ?string $name): array
    {
        if ($name === null || $name === '') {
            throw new AaPanelException('Select an existing aaPanel resource package on the PNLCS product.');
        }
        $matches = array_values(array_filter($api->listing('get_package_list'), fn ($package) => (string) ($package['package_name'] ?? '') === $name));
        if (count($matches) !== 1 || ! isset($matches[0]['package_id'])) {
            throw new AaPanelException('The selected aaPanel resource package was not uniquely found on this server.');
        }

        return $matches[0];
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function limits(array $source): array
    {
        $result = [];
        foreach (self::LIMITS as $field) {
            $result[$field] = AaPanelValue::integer($source[$field] ?? null);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $account
     * @return array<string, mixed>
     */
    private function editable(array $account): array
    {
        $result = $this->limits($account);
        foreach (['account_id', 'username', 'email', 'expire_date', 'package_id', 'remark', 'status'] as $field) {
            if (! array_key_exists($field, $account) || ! is_scalar($account[$field])) {
                throw new AaPanelException('aaPanel returned incomplete account information; refusing to overwrite it.');
            }
            $result[$field] = $account[$field];
        }
        // Do not send init_password/login_url or enable automatic DNS during
        // a password/package/status change. Keep an existing domain unchanged.
        if (isset($account['domain']) && is_string($account['domain'])) {
            $result['domain'] = $account['domain'];
        }
        if (array_key_exists('max_email_account', $account)) {
            $result['max_email_account'] = AaPanelValue::integer($account['max_email_account']);
        }
        $result['automatic_dns'] = 0;

        return $result;
    }

    /** aaPanel has no legacy notes-based identity; customer notes are never authority.
     * @return array<string, mixed>
     */
    protected function getModuleData(Service $service): array
    {
        $data = $service->getAttribute('module_data');

        return is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $data */
    protected function setModuleData(Service $service, array $data): void
    {
        $this->saveAttributes($service, ['module_data' => array_merge($this->getModuleData($service), $data)]);
    }

    /** @param array<string, mixed> $attributes */
    private function saveAttributes(Service $service, array $attributes): void
    {
        if (! $service->forceFill($attributes)->save()) {
            throw new AaPanelException('The aaPanel service state could not be saved. No further API writes were sent.');
        }
    }

    private function assertMutable(Service $service): void
    {
        $data = $this->getModuleData($service);
        if (in_array($service->status, ['terminated', 'cancelled'], true)
            || ! empty($data['aapanel_terminated']) || ! empty($data['aapanel_terminate_pending'])) {
            throw new AaPanelException('This aaPanel service is terminated or its termination is pending. Reconcile termination before any other action.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function run(Service $service, string $action, callable $callback): array
    {
        if (! $service->exists || ! $service->id) {
            return $this->buildResult(false, 'Save the hosting service before provisioning aaPanel.');
        }
        try {
            // Shared cache locking serializes cron retries and admin clicks.
            // The bounded API calls cannot outlive this lease under normal
            // response limits. Use a shared cache on multi-node installations.
            $result = Cache::lock('aapanel:service:'.$service->id, 3600)->get(function () use ($service, $callback) {
                $service->refresh();

                return $callback();
            });
            if ($result === false) {
                return $this->buildResult(false, 'Another aaPanel action is already running for this service.');
            }
            $this->logAction($service, $action, $result);

            return $result;
        } catch (AaPanelException $e) {
            return $this->buildResult(false, $e->getMessage());
        } catch (\Throwable) {
            return $this->buildResult(false, 'aaPanel action failed locally. Check application configuration; remote outcomes may require reconciliation.');
        }
    }
}
