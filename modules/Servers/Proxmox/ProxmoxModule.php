<?php

namespace Modules\Servers\Proxmox;

use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Servers\AbstractServerModule;

/**
 * Proxmox VE: KVM virtual machines and LXC containers.
 *
 * Every guest this module creates carries the tags "pnlcs" and
 * "pnlcs-s<service id>" and a line naming the service in its notes. Anything
 * that stops, changes or removes a guest first checks that mark, so a wrong or
 * stale VM id in the billing records can never touch a guest the operator runs
 * for other reasons.
 */
class ProxmoxModule extends AbstractServerModule
{
    use Concerns\ManagesBackups;
    use Concerns\ManagesSnapshots;
    use Concerns\RunsJobs;

    public const TAG = 'pnlcs';

    /** Power actions a customer may run from the client area. */
    public const CLIENT_POWER_ACTIONS = ['start', 'shutdown', 'reboot', 'stop', 'reset'];

    public function getModuleName(): string
    {
        return 'proxmox';
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'username', 'label' => 'API token ID (user@realm!token)', 'type' => 'text'],
            ['name' => 'access_hash', 'label' => 'API token secret', 'type' => 'password'],
            ['name' => 'password', 'label' => 'Password (only without a token)', 'type' => 'password'],
        ];
    }

    public function client(Server $server): ProxmoxClient
    {
        return new ProxmoxClient($server, $this->serverHost($server));
    }

    // =========================================================================
    // Guests and the ownership mark
    // =========================================================================

    public static function serviceTag(Service $service): string
    {
        return self::TAG.'-s'.$service->id;
    }

    private static function marker(Service $service): string
    {
        return 'pnlcs:service='.$service->id;
    }

    private function description(Service $service): string
    {
        return "Managed by PNLCS for service #{$service->id}".($service->domain ? " ({$service->domain})" : '').".\n"
            .'Do not remove the next line; PNLCS will not touch this guest without it.'."\n"
            .self::marker($service);
    }

    /** Tags as Proxmox stores them: separated by ";" (older releases also used "," or " "). */
    public static function tagsOf(array $config): array
    {
        return array_values(array_filter(preg_split('/[;,\s]+/', strtolower((string) ($config['tags'] ?? ''))) ?: []));
    }

    public function owns(Service $service, array $config): bool
    {
        return in_array(self::serviceTag($service), self::tagsOf($config), true)
            || str_contains((string) ($config['description'] ?? ''), self::marker($service));
    }

    /**
     * The guest behind a service, checked to be the one PNLCS made for it.
     *
     * @return array{vmid: int, node: string, type: string, config: array, path: string}|ProxmoxResult
     */
    public function guest(Service $service, ?ProxmoxClient $api = null): array|ProxmoxResult
    {
        $server = $service->server ?? ($service->server_id ? Server::find($service->server_id) : null);
        $d = $this->getModuleData($service);
        $vmid = (int) ($d['proxmox_vmid'] ?? 0);

        if (! $server || $vmid <= 0) {
            return ProxmoxResult::failed(__('proxmox.error.no_guest'));
        }

        $api ??= $this->client($server);
        $type = ($d['proxmox_type'] ?? 'qemu') === 'lxc' ? 'lxc' : 'qemu';
        $node = (string) ($d['proxmox_node'] ?? '');

        $config = $node !== '' ? $api->get("nodes/{$node}/{$type}/{$vmid}/config") : ProxmoxResult::failed('', 404);

        // Moved to another node (migration, HA) since it was created.
        if (! $config->ok && ($config->isMissing() || $node === '' || $config->status === 500)) {
            $found = collect($api->get('cluster/resources', ['type' => 'vm'])->list())->firstWhere('vmid', $vmid);
            if ($found) {
                $node = (string) $found['node'];
                $type = ($found['type'] ?? $type) === 'lxc' ? 'lxc' : 'qemu';
                $config = $api->get("nodes/{$node}/{$type}/{$vmid}/config");
                if ($config->ok) {
                    $this->setModuleData($service, ['proxmox_node' => $node, 'proxmox_type' => $type]);
                }
            }
        }

        if (! $config->ok) {
            return $config->isMissing()
                ? new ProxmoxResult(false, 404, null, __('proxmox.error.guest_gone', ['vmid' => $vmid]))
                : $config;
        }

        $cfg = is_array($config->data) ? $config->data : [];
        if (! $this->owns($service, $cfg)) {
            return new ProxmoxResult(false, 409, null, __('proxmox.error.not_ours', [
                'vmid' => $vmid, 'tag' => self::serviceTag($service),
            ]));
        }

        return ['vmid' => $vmid, 'node' => $node, 'type' => $type, 'config' => $cfg, 'path' => "nodes/{$node}/{$type}/{$vmid}"];
    }

    // =========================================================================
    // Create
    // =========================================================================

    public function create(Service $service): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, __('proxmox.error.no_server'));
        }

        $api = $this->client($server);
        $plan = ProxmoxPlan::forService($service);

        try {
            $result = $this->provision($service, $server, $api, $plan);
        } catch (\Throwable $e) {
            Log::error('Proxmox create failed', ['service' => $service->id, 'error' => $e->getMessage()]);
            $result = $this->buildResult(false, $e->getMessage());
        }

        $this->logAction($service, 'create', $result);

        return $result;
    }

    private function provision(Service $service, Server $server, ProxmoxClient $api, ProxmoxPlan $plan): array
    {
        if (! $plan->isLxc() && $plan->template === '' && $plan->iso === '') {
            return $this->buildResult(false, __('proxmox.error.no_template'));
        }
        if ($plan->isLxc() && $plan->ostemplate === '') {
            return $this->buildResult(false, __('proxmox.error.no_ostemplate'));
        }

        // A failed create is retried by the module queue. Pick up the guest a
        // previous attempt already made instead of making a second one.
        $existing = $this->findExisting($service, $api);

        if ($existing) {
            ['vmid' => $vmid, 'node' => $node] = $existing;
        } else {
            $ip = $this->reserveAddress($service, $server, $plan);
            if ($ip instanceof ProxmoxResult) {
                return $this->buildResult(false, $ip->error);
            }

            $made = $this->makeGuest($service, $server, $api, $plan, $ip);
            if ($made instanceof ProxmoxResult) {
                return $this->buildResult(false, $made->error);
            }
            ['vmid' => $vmid, 'node' => $node] = $made;
        }

        $password = $this->servicePassword($service);
        $ip = $this->getModuleData($service)['pve_ipv4'] ?? null;

        $configured = $plan->isLxc()
            ? $this->configureLxc($service, $api, $plan, $node, $vmid)
            : $this->configureQemu($service, $api, $plan, $node, $vmid, $password);
        if (! $configured->ok) {
            return $this->buildResult(false, __('proxmox.error.configure_failed', ['vmid' => $vmid, 'error' => $configured->error]));
        }

        $type = $plan->isLxc() ? 'lxc' : 'qemu';
        $status = $api->get("nodes/{$node}/{$type}/{$vmid}/status/current");
        if (($status->data['status'] ?? '') !== 'running') {
            $started = $api->waitForTask($node, $api->post("nodes/{$node}/{$type}/{$vmid}/status/start")->data ?? null, 120);
            if (! $started->ok) {
                return $this->buildResult(false, __('proxmox.error.start_failed', ['vmid' => $vmid, 'error' => $started->error]));
            }
        }

        $this->setModuleData($service, ['pve_state' => 'ready', 'pve_image' => $plan->image()]);
        $service->forceFill([
            'username' => $plan->isLxc() ? 'root' : ($plan->ciuser ?: 'root'),
            'password' => $password,
        ])->save();

        return $this->buildResult(true, __('proxmox.created', ['vmid' => $vmid, 'node' => $node]), [
            'vmid' => $vmid, 'node' => $node, 'type' => $type, 'ip' => $ip,
        ]);
    }

    /** @return array{vmid: int, node: string}|null */
    private function findExisting(Service $service, ProxmoxClient $api): ?array
    {
        $d = $this->getModuleData($service);
        if (! empty($d['proxmox_vmid'])) {
            $guest = $this->guest($service, $api);
            if (is_array($guest)) {
                return ['vmid' => $guest['vmid'], 'node' => $guest['node']];
            }
        }

        $tagged = collect($api->get('cluster/resources', ['type' => 'vm'])->list())
            ->first(fn ($r) => in_array(self::serviceTag($service), self::tagsOf($r), true));

        if ($tagged) {
            $this->setModuleData($service, [
                'proxmox_vmid' => (int) $tagged['vmid'],
                'proxmox_node' => (string) $tagged['node'],
                'proxmox_type' => ($tagged['type'] ?? 'qemu') === 'lxc' ? 'lxc' : 'qemu',
            ]);

            return ['vmid' => (int) $tagged['vmid'], 'node' => (string) $tagged['node']];
        }

        return null;
    }

    private function servicePassword(Service $service): string
    {
        $password = (string) $service->password;
        if ($password === '') {
            $password = Str::password(16, symbols: false);
            $service->forceFill(['password' => $password])->save();
        }

        return $password;
    }

    /** @return array{address: string, prefix: int, gateway: string}|ProxmoxResult|null */
    private function reserveAddress(Service $service, Server $server, ProxmoxPlan $plan): array|ProxmoxResult|null
    {
        if ($plan->ipv4 !== 'pool') {
            return null;
        }

        return Cache::lock("pve-ipv4-{$server->id}", 30)->block(20, function () use ($service, $server) {
            try {
                $ip = Ipv4Pool::allocate($server, $service);
            } catch (\InvalidArgumentException $e) {
                return ProxmoxResult::failed(__('proxmox.error.pool_invalid', ['line' => $e->getMessage()]));
            }
            if (! $ip) {
                return ProxmoxResult::failed(__('proxmox.error.pool_empty', ['server' => $server->name]));
            }
            // Held from this moment, so a second order cannot be given the same address.
            $this->setModuleData($service, ['pve_ipv4' => $ip['address'], 'pve_ipv4_prefix' => $ip['prefix'], 'pve_ipv4_gateway' => $ip['gateway']]);

            return $ip;
        });
    }

    /**
     * Choose a node and VM id and ask Proxmox for the guest.
     *
     * @return array{vmid: int, node: string}|ProxmoxResult
     */
    private function makeGuest(Service $service, Server $server, ProxmoxClient $api, ProxmoxPlan $plan, ?array $ip): array|ProxmoxResult
    {
        $node = $this->pickNode($server, $api, $plan);
        if ($node instanceof ProxmoxResult) {
            return $node;
        }

        return Cache::lock("pve-create-{$server->id}", 120)->block(90, function () use ($service, $server, $api, $plan, $ip, $node) {
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $vmid = $this->pickVmid($server, $api);
                if ($vmid instanceof ProxmoxResult) {
                    return $vmid;
                }

                $sent = $plan->isLxc()
                    ? $api->post("nodes/{$node}/lxc", $this->lxcCreateBody($service, $server, $plan, $vmid, $ip))
                    : $this->sendQemuCreate($service, $server, $api, $plan, $node, $vmid);

                // Someone took the id between the check and the create.
                if (! $sent->ok && str_contains($sent->error, 'already exists')) {
                    continue;
                }
                if (! $sent->ok) {
                    return ProxmoxResult::failed(__('proxmox.error.create_refused', ['error' => $sent->error]), $sent->status);
                }

                // Recorded before waiting, so a retry after a crash finds it.
                $this->setModuleData($service, [
                    'proxmox_vmid' => $vmid,
                    'proxmox_node' => $node,
                    'proxmox_type' => $plan->isLxc() ? 'lxc' : 'qemu',
                    'pve_state' => 'creating',
                ]);

                $done = $api->waitForTask($node, $sent->data, 900);

                return $done->ok ? ['vmid' => $vmid, 'node' => $node]
                    : ProxmoxResult::failed(__('proxmox.error.create_failed', ['vmid' => $vmid, 'error' => $done->error]));
            }

            return ProxmoxResult::failed(__('proxmox.error.no_free_vmid'));
        });
    }

    private function sendQemuCreate(Service $service, Server $server, ProxmoxClient $api, ProxmoxPlan $plan, string $node, int $vmid): ProxmoxResult
    {
        $pool = (string) $server->setting('pool', '');

        if ($plan->template !== '') {
            $body = [
                'newid' => $vmid,
                'name' => $this->guestName($service, $vmid),
                'full' => 1,
                'storage' => $plan->storage,
                'description' => $this->description($service),
            ];
            if ($pool !== '') {
                $body['pool'] = $pool;
            }
            $templateNode = $this->templateNode($api, (int) $plan->template) ?? $node;
            if ($templateNode !== $node) {
                $body['target'] = $node;
            }

            return $api->post("nodes/{$templateNode}/qemu/{$plan->template}/clone", $body);
        }

        // No template: an empty disk and the installer ISO in the drive.
        $body = [
            'vmid' => $vmid,
            'name' => $this->guestName($service, $vmid),
            'ostype' => $plan->ostype,
            'cores' => $plan->cores,
            'sockets' => $plan->sockets,
            'memory' => $plan->memory,
            'scsihw' => 'virtio-scsi-single',
            'scsi0' => "{$plan->storage}:{$plan->disk},iothread=1,discard=on",
            'ide2' => "{$plan->iso},media=cdrom",
            'boot' => 'order=scsi0;ide2',
            'net0' => $this->qemuNet($plan, null),
            'agent' => 'enabled=1',
            'tags' => self::TAG.';'.self::serviceTag($service),
            'description' => $this->description($service),
            'onboot' => 1,
        ];
        if ($pool !== '') {
            $body['pool'] = $pool;
        }

        return $api->post("nodes/{$node}/qemu", $body);
    }

    private function lxcCreateBody(Service $service, Server $server, ProxmoxPlan $plan, int $vmid, ?array $ip): array
    {
        $body = [
            'vmid' => $vmid,
            'hostname' => $this->hostname($service, $vmid),
            'ostemplate' => $plan->ostemplate,
            'password' => $this->servicePassword($service),
            'rootfs' => "{$plan->storage}:{$plan->disk}",
            'cores' => $plan->cores,
            'memory' => $plan->memory,
            'swap' => $plan->swap,
            'net0' => $this->lxcNet($plan, $ip),
            'unprivileged' => 1,
            'onboot' => 1,
            'tags' => self::TAG.';'.self::serviceTag($service),
            'description' => $this->description($service),
        ];
        if ($plan->cpulimit > 0) {
            $body['cpulimit'] = $plan->cpulimit;
        }
        if ($plan->nesting) {
            $body['features'] = 'nesting=1';
        }
        if ($plan->nameserver !== '') {
            $body['nameserver'] = $plan->nameserver;
        }
        if (($pool = (string) $server->setting('pool', '')) !== '') {
            $body['pool'] = $pool;
        }

        return $body;
    }

    /** Settings applied after a KVM clone, and again on every retry. */
    private function configureQemu(Service $service, ProxmoxClient $api, ProxmoxPlan $plan, string $node, int $vmid, string $password): ProxmoxResult
    {
        $path = "nodes/{$node}/qemu/{$vmid}";
        $current = $api->get("{$path}/config");
        if (! $current->ok) {
            return $current;
        }
        $cfg = is_array($current->data) ? $current->data : [];
        $ip = $this->ipFromModuleData($service);

        $body = [
            'name' => $this->guestName($service, $vmid),
            'cores' => $plan->cores,
            'sockets' => $plan->sockets,
            'memory' => $plan->memory,
            'tags' => $this->mergeTags($cfg, $service),
            'description' => $this->description($service),
            'onboot' => 1,
            'agent' => 'enabled=1',
            'net0' => $this->qemuNet($plan, $this->macOf((string) ($cfg['net0'] ?? ''))),
        ];
        if ($plan->cpulimit > 0) {
            $body['cpulimit'] = $plan->cpulimit;
        }

        if ($plan->template !== '') {
            $body += [
                'ciuser' => $plan->ciuser ?: 'root',
                'cipassword' => $password,
                'ciupgrade' => $plan->ciupgrade ? 1 : 0,
                'ipconfig0' => $ip ? "ip={$ip['address']}/{$ip['prefix']},gw={$ip['gateway']}" : 'ip=dhcp',
            ];
            if ($plan->nameserver !== '') {
                $body['nameserver'] = $plan->nameserver;
            }
            // Cloud images ship with password logins switched off and without
            // the guest agent; the vendor snippet (see ProxmoxSetup) fixes both.
            $vendor = (string) $service->server?->setting('ci_vendor', '');
            if ($vendor !== '') {
                $body['cicustom'] = "vendor={$vendor}";
            }
            if (! $this->hasCloudInitDrive($cfg)) {
                $body['ide2'] = "{$plan->storage}:cloudinit";
            }
            $keys = $this->sshKeys($service);
            if ($keys !== '') {
                $body['sshkeys'] = rawurlencode($keys);
            }
        }

        $set = $api->put("{$path}/config", $body);
        if (! $set->ok) {
            return $set;
        }

        $disk = $this->bootDisk($cfg);
        if ($disk && $plan->template !== '') {
            $grown = $this->growDisk($api, $path, $disk, (string) ($cfg[$disk] ?? ''), $plan->disk);
            if (! $grown->ok) {
                return $grown;
            }
        }

        if ($plan->protection) {
            $api->put("{$path}/config", ['protection' => 1]);
        }

        return new ProxmoxResult(true, 200);
    }

    private function configureLxc(Service $service, ProxmoxClient $api, ProxmoxPlan $plan, string $node, int $vmid): ProxmoxResult
    {
        $path = "nodes/{$node}/lxc/{$vmid}";
        $current = $api->get("{$path}/config");
        if (! $current->ok) {
            return $current;
        }
        $cfg = is_array($current->data) ? $current->data : [];

        $set = $api->put("{$path}/config", [
            'tags' => $this->mergeTags($cfg, $service),
            'description' => $this->description($service),
            'onboot' => 1,
        ]);
        if (! $set->ok) {
            return $set;
        }

        if ($plan->protection) {
            $api->put("{$path}/config", ['protection' => 1]);
        }

        return new ProxmoxResult(true, 200);
    }

    private function mergeTags(array $cfg, Service $service): string
    {
        // A clone inherits its template's tags; the image library's mark names a template, not a server.
        $own = array_diff(self::tagsOf($cfg), [ProxmoxImages::TAG]);

        return implode(';', array_unique([...$own, self::TAG, self::serviceTag($service)]));
    }

    private function guestName(Service $service, int $vmid): string
    {
        return $this->hostname($service, $vmid);
    }

    /** A DNS-safe name: the service's domain, or vps-<id>. */
    private function hostname(Service $service, int $vmid): string
    {
        $name = strtolower(trim((string) $service->domain));
        $name = preg_replace('/[^a-z0-9.-]/', '-', $name) ?? '';
        $name = trim(preg_replace('/-+/', '-', $name) ?? '', '.-');

        return $name !== '' && strlen($name) <= 63 ? $name : "vps-{$vmid}";
    }

    private function qemuNet(ProxmoxPlan $plan, ?string $mac): string
    {
        $net = 'virtio'.($mac ? "={$mac}" : '').",bridge={$plan->bridge}";
        if ($plan->vlan > 0) {
            $net .= ",tag={$plan->vlan}";
        }
        if ($plan->rate > 0) {
            $net .= ",rate={$plan->rate}";
        }
        if ($plan->firewall) {
            $net .= ',firewall=1';
        }

        return $net;
    }

    private function lxcNet(ProxmoxPlan $plan, ?array $ip, ?string $mac = null): string
    {
        $net = "name=eth0,bridge={$plan->bridge}".($mac ? ",hwaddr={$mac}" : '');
        $net .= $ip ? ",ip={$ip['address']}/{$ip['prefix']},gw={$ip['gateway']}" : ',ip=dhcp';
        if ($plan->ipv6 === 'auto') {
            $net .= ',ip6=auto';
        }
        if ($plan->vlan > 0) {
            $net .= ",tag={$plan->vlan}";
        }
        if ($plan->rate > 0) {
            $net .= ",rate={$plan->rate}";
        }
        if ($plan->firewall) {
            $net .= ',firewall=1';
        }

        return $net;
    }

    private function macOf(string $net): ?string
    {
        return preg_match('/(?:^|,)(?:[a-z0-9]+=|hwaddr=)([0-9A-Fa-f]{2}(?::[0-9A-Fa-f]{2}){5})/', $net, $m) ? strtoupper($m[1]) : null;
    }

    private function ipFromModuleData(Service $service): ?array
    {
        $d = $this->getModuleData($service);

        return empty($d['pve_ipv4']) ? null : [
            'address' => $d['pve_ipv4'],
            'prefix' => (int) ($d['pve_ipv4_prefix'] ?? 24),
            'gateway' => (string) ($d['pve_ipv4_gateway'] ?? ''),
        ];
    }

    private function hasCloudInitDrive(array $cfg): bool
    {
        foreach ($cfg as $key => $value) {
            if (preg_match('/^(ide|sata|scsi)\d+$/', (string) $key) && str_contains((string) $value, 'cloudinit')) {
                return true;
            }
        }

        return false;
    }

    /** The disk the guest boots from: the first entry of the boot order that is a disk. */
    private function bootDisk(array $cfg): ?string
    {
        if (preg_match('/order=([^,]+)/', (string) ($cfg['boot'] ?? ''), $m)) {
            foreach (explode(';', $m[1]) as $dev) {
                if (isset($cfg[$dev]) && ! str_contains((string) $cfg[$dev], 'media=cdrom') && ! str_contains((string) $cfg[$dev], 'cloudinit')) {
                    return $dev;
                }
            }
        }
        foreach (['scsi0', 'virtio0', 'sata0', 'ide0'] as $dev) {
            if (isset($cfg[$dev]) && ! str_contains((string) $cfg[$dev], 'media=cdrom')) {
                return $dev;
            }
        }

        return null;
    }

    /** Size in GiB from a disk line ("local-lvm:vm-101-disk-0,size=32G"). */
    public static function diskSizeGb(string $line): float
    {
        if (! preg_match('/size=(\d+(?:\.\d+)?)([KMGT]?)/i', $line, $m)) {
            return 0;
        }

        return (float) $m[1] * match (strtoupper($m[2])) {
            'T' => 1024, 'M' => 1 / 1024, 'K' => 1 / 1048576, default => 1,
        };
    }

    /** Grow a disk to the plan's size. Disks never shrink; a smaller plan leaves it as it is. */
    private function growDisk(ProxmoxClient $api, string $path, string $disk, string $line, int $sizeGb): ProxmoxResult
    {
        if (self::diskSizeGb($line) >= $sizeGb) {
            return new ProxmoxResult(true, 200);
        }

        $resized = $api->put("{$path}/resize", ['disk' => $disk, 'size' => "{$sizeGb}G"]);

        return $resized->ok ? $api->waitForTask(explode('/', $path)[1], $resized->data, 300) : $resized;
    }

    private function sshKeys(Service $service): string
    {
        $keys = $this->getModuleData($service)['pve_sshkeys'] ?? '';

        return is_string($keys) ? trim($keys) : '';
    }

    /**
     * The node set on the server record.
     *
     * The first version of this module read it from nameserver1, which the
     * form labelled "Nameservers"; an operator who typed ns1.example.com there
     * sent every order to a node of that name. A Proxmox node name is a bare
     * host name, never dotted, so only such a value is still taken from there.
     */
    public static function configuredNode(Server $server): string
    {
        $node = trim((string) $server->setting('node', ''));
        if ($node !== '') {
            return $node;
        }
        $legacy = trim((string) $server->nameserver1);

        return $legacy !== '' && preg_match('/^[A-Za-z0-9][A-Za-z0-9-]*$/', $legacy) ? $legacy : '';
    }

    private function pickNode(Server $server, ProxmoxClient $api, ProxmoxPlan $plan): string|ProxmoxResult
    {
        $wanted = $plan->node ?: self::configuredNode($server);
        if ($wanted !== '') {
            return $wanted;
        }

        $nodes = $api->get('nodes');
        if (! $nodes->ok) {
            return ProxmoxResult::failed(__('proxmox.error.nodes_unreadable', ['error' => $nodes->error]));
        }

        // The online node with the most memory left.
        $best = collect($nodes->list())
            ->where('status', 'online')
            ->sortBy(fn ($n) => ($n['maxmem'] ?? 0) > 0 ? ($n['mem'] ?? 0) / $n['maxmem'] : 1)
            ->first();

        return $best ? (string) $best['node'] : ProxmoxResult::failed(__('proxmox.error.no_node_online'));
    }

    private function pickVmid(Server $server, ProxmoxClient $api): int|ProxmoxResult
    {
        $min = (int) $server->setting('vmid_min', 0);
        $max = (int) $server->setting('vmid_max', 0);

        if ($min <= 0) {
            $next = $api->get('cluster/nextid');

            return $next->ok ? (int) $next->data : ProxmoxResult::failed(__('proxmox.error.nextid', ['error' => $next->error]));
        }

        $max = $max >= $min ? $max : $min + 9999;
        $seen = collect($api->get('cluster/resources', ['type' => 'vm'])->list())->pluck('vmid')->map(fn ($v) => (int) $v)->flip();

        for ($vmid = $min, $asked = 0; $vmid <= $max && $asked < 200; $vmid++) {
            if (isset($seen[$vmid])) {
                continue;
            }
            // The token may not see every guest; ask Proxmox whether the id is free.
            $asked++;
            if ($api->get('cluster/nextid', ['vmid' => $vmid])->ok) {
                return $vmid;
            }
        }

        return ProxmoxResult::failed(__('proxmox.error.range_full', ['min' => $min, 'max' => $max]));
    }

    private function templateNode(ProxmoxClient $api, int $templateId): ?string
    {
        $found = collect($api->get('cluster/resources', ['type' => 'vm'])->list())->firstWhere('vmid', $templateId);

        return $found['node'] ?? null;
    }

    // =========================================================================
    // Power, suspension, removal
    // =========================================================================

    /** Run a power action and wait for it; the answer Proxmox gives first says only that it began. */
    public function power(Service $service, string $action): array
    {
        if (! in_array($action, ['start', 'stop', 'shutdown', 'reboot', 'reset', 'suspend', 'resume'], true)) {
            return $this->buildResult(false, __('proxmox.error.unknown_action', ['action' => $action]));
        }

        if ($busy = $this->busy($service)) {
            return $busy;
        }
        $api = $service->server ? $this->client($service->server) : null;
        $guest = $api ? $this->guest($service, $api) : ProxmoxResult::failed(__('proxmox.error.no_guest'));
        if ($guest instanceof ProxmoxResult) {
            return $this->buildResult(false, $guest->error);
        }

        $body = match ($action) {
            // A guest that ignores the ACPI button is switched off after a minute.
            'shutdown' => ['forceStop' => 1, 'timeout' => 60],
            default => [],
        };
        if ($guest['type'] === 'lxc' && in_array($action, ['reset'], true)) {
            $action = 'reboot';
        }

        $sent = $api->post("{$guest['path']}/status/{$action}", $body);
        if (! $sent->ok) {
            return $this->buildResult(false, $sent->error);
        }
        $done = $api->waitForTask($guest['node'], $sent->data, $this->waitSeconds($action === 'shutdown' ? 120 : 60));

        // Still going after the web request's share of time: it carries on in
        // Proxmox, and the status the page polls shows when it is done.
        if (! $done->ok && $done->status === 504) {
            return $this->buildResult(true, __('proxmox.power_pending'));
        }

        return $this->buildResult($done->ok, $done->ok ? __('proxmox.power_done.'.$action) : $done->error);
    }

    public function start(Service $service): array
    {
        return $this->power($service, 'start');
    }

    public function stop(Service $service): array
    {
        return $this->power($service, 'stop');
    }

    public function reboot(Service $service): array
    {
        return $this->power($service, 'reboot');
    }

    public function shutdown(Service $service): array
    {
        return $this->power($service, 'shutdown');
    }

    /**
     * Switch the guest off and keep it off.
     *
     * Only switching it off left "start at boot" on, so the next reboot of the
     * host started every suspended customer again.
     */
    public function suspend(Service $service, string $reason = ''): array
    {
        $server = $this->getServer($service);
        $api = $server ? $this->client($server) : null;
        $guest = $api ? $this->guest($service, $api) : ProxmoxResult::failed(__('proxmox.error.no_server'));
        if ($guest instanceof ProxmoxResult) {
            $result = $this->buildResult(false, $guest->error);
            $this->logAction($service, 'suspend', $result);

            return $result;
        }

        $api->put("{$guest['path']}/config", ['onboot' => 0]);
        $state = $api->get("{$guest['path']}/status/current");
        if (($state->data['status'] ?? '') !== 'stopped') {
            $sent = $api->post("{$guest['path']}/status/shutdown", ['forceStop' => 1, 'timeout' => 60]);
            $done = $sent->ok ? $api->waitForTask($guest['node'], $sent->data, 120) : $sent;
            if (! $done->ok) {
                $result = $this->buildResult(false, __('proxmox.error.stop_failed', ['error' => $done->error]));
                $this->logAction($service, 'suspend', $result);

                return $result;
            }
        }

        $result = $this->buildResult(true, __('proxmox.suspended', ['vmid' => $guest['vmid']]));
        $this->logAction($service, 'suspend', $result);

        return $result;
    }

    public function unsuspend(Service $service): array
    {
        $server = $this->getServer($service);
        $api = $server ? $this->client($server) : null;
        $guest = $api ? $this->guest($service, $api) : ProxmoxResult::failed(__('proxmox.error.no_server'));
        if ($guest instanceof ProxmoxResult) {
            $result = $this->buildResult(false, $guest->error);
            $this->logAction($service, 'unsuspend', $result);

            return $result;
        }

        $api->put("{$guest['path']}/config", ['onboot' => 1]);
        $state = $api->get("{$guest['path']}/status/current");
        if (($state->data['status'] ?? '') !== 'running') {
            $sent = $api->post("{$guest['path']}/status/start");
            $done = $sent->ok ? $api->waitForTask($guest['node'], $sent->data, 120) : $sent;
            if (! $done->ok) {
                $result = $this->buildResult(false, __('proxmox.error.start_failed', ['vmid' => $guest['vmid'], 'error' => $done->error]));
                $this->logAction($service, 'unsuspend', $result);

                return $result;
            }
        }

        $result = $this->buildResult(true, __('proxmox.unsuspended', ['vmid' => $guest['vmid']]));
        $this->logAction($service, 'unsuspend', $result);

        return $result;
    }

    /**
     * Remove the guest and its disks.
     *
     * Refused unless the guest carries this service's mark: the VM id alone is
     * not proof, and a wrong one here would delete somebody else's machine.
     */
    public function terminate(Service $service): array
    {
        $server = $this->getServer($service);
        $api = $server ? $this->client($server) : null;
        if (! $api) {
            return $this->buildResult(false, __('proxmox.error.no_server'));
        }

        $d = $this->getModuleData($service);
        if (empty($d['proxmox_vmid'])) {
            // Never created: there is nothing to remove.
            $result = $this->buildResult(true, __('proxmox.nothing_to_remove'));
            $this->logAction($service, 'terminate', $result);

            return $result;
        }

        $guest = $this->guest($service, $api);
        if ($guest instanceof ProxmoxResult) {
            // Already gone is the outcome terminate wants.
            $result = $guest->status === 404
                ? $this->buildResult(true, __('proxmox.already_removed', ['vmid' => $d['proxmox_vmid']]))
                : $this->buildResult(false, $guest->error);
            $this->logAction($service, 'terminate', $result);

            return $result;
        }

        $removed = $this->destroyGuest($api, $guest);
        $result = $removed->ok
            ? $this->buildResult(true, __('proxmox.removed', ['vmid' => $guest['vmid']]))
            : $this->buildResult(false, __('proxmox.error.remove_failed', ['vmid' => $guest['vmid'], 'error' => $removed->error]));

        if ($removed->ok) {
            $this->setModuleData($service, ['pve_state' => 'removed', 'pve_ipv4' => null, 'pve_job' => null]);
        }
        $this->logAction($service, 'terminate', $result);

        return $result;
    }

    private function destroyGuest(ProxmoxClient $api, array $guest): ProxmoxResult
    {
        $state = $api->get("{$guest['path']}/status/current");
        if (($state->data['status'] ?? 'stopped') !== 'stopped') {
            $stop = $api->post("{$guest['path']}/status/stop");
            $done = $stop->ok ? $api->waitForTask($guest['node'], $stop->data, 120) : $stop;
            if (! $done->ok) {
                return $done;
            }
        }

        if (! empty($guest['config']['protection'])) {
            $unprotect = $api->put("{$guest['path']}/config", ['protection' => 0]);
            if (! $unprotect->ok) {
                return $unprotect;
            }
        }

        $delete = $api->delete($guest['path'], ['purge' => 1, 'destroy-unreferenced-disks' => 1]);

        return $delete->ok ? $api->waitForTask($guest['node'], $delete->data, 300) : $delete;
    }

    // =========================================================================
    // Password, plan changes, reinstall
    // =========================================================================

    /**
     * A new root password.
     *
     * A running KVM guest with the QEMU agent gets it at once. Without the
     * agent it is handed to cloud-init and takes effect at the next boot.
     * Proxmox has no way to set a container's password from outside, so for
     * containers the answer says so instead of pretending.
     */
    public function changePassword(Service $service, string $newPassword): array
    {
        $api = $service->server ? $this->client($service->server) : null;
        $guest = $api ? $this->guest($service, $api) : ProxmoxResult::failed(__('proxmox.error.no_server'));
        if ($guest instanceof ProxmoxResult) {
            return $this->buildResult(false, $guest->error);
        }

        if ($guest['type'] === 'lxc') {
            return $this->buildResult(false, __('proxmox.error.lxc_password'));
        }

        $user = (string) ($guest['config']['ciuser'] ?? '') ?: 'root';
        $agent = $api->post("{$guest['path']}/agent/set-user-password", ['username' => $user, 'password' => $newPassword]);

        if ($agent->ok) {
            $service->forceFill(['password' => $newPassword])->save();
            $result = $this->buildResult(true, __('proxmox.password_set_now'));
        } else {
            $cloudInit = $this->hasCloudInitDrive($guest['config'])
                ? $api->put("{$guest['path']}/config", ['cipassword' => $newPassword])
                : ProxmoxResult::failed(__('proxmox.error.password_no_way'));
            if ($cloudInit->ok) {
                $service->forceFill(['password' => $newPassword])->save();
            }
            $result = $cloudInit->ok
                ? $this->buildResult(true, __('proxmox.password_set_on_boot'), ['needs_reboot' => true])
                : $this->buildResult(false, $cloudInit->error);
        }

        $this->logAction($service, 'changePassword', $result);

        return $result;
    }

    public function changePackage(Service $service, array $newPackage): array
    {
        $api = $service->server ? $this->client($service->server) : null;
        $guest = $api ? $this->guest($service, $api) : ProxmoxResult::failed(__('proxmox.error.no_server'));
        if ($guest instanceof ProxmoxResult) {
            return $this->buildResult(false, $guest->error);
        }

        $cfg = $newPackage['config_options'] ?? [];
        $cfg = is_string($cfg) ? (json_decode($cfg, true) ?: []) : (is_array($cfg) ? $cfg : []);
        $plan = ProxmoxPlan::forService($service, $cfg);

        $body = ['cores' => $plan->cores, 'memory' => $plan->memory];
        if ($guest['type'] === 'lxc') {
            $body['swap'] = $plan->swap;
        } else {
            $body['sockets'] = $plan->sockets;
        }
        $body[$plan->cpulimit > 0 ? 'cpulimit' : 'delete'] = $plan->cpulimit > 0 ? $plan->cpulimit : 'cpulimit';

        $set = $api->put("{$guest['path']}/config", $body);
        if (! $set->ok) {
            return $this->buildResult(false, __('proxmox.error.resources_failed', ['error' => $set->error]));
        }

        $disk = $guest['type'] === 'lxc' ? 'rootfs' : $this->bootDisk($guest['config']);
        if ($disk) {
            $grown = $this->growDisk($api, $guest['path'], $disk, (string) ($guest['config'][$disk] ?? ''), $plan->disk);
            if (! $grown->ok) {
                return $this->buildResult(false, __('proxmox.error.resize_failed', ['error' => $grown->error]), $body);
            }
        }

        $result = $this->buildResult(true, __('proxmox.package_changed', ['vmid' => $guest['vmid']]), $body);
        $this->logAction($service, 'changePackage', $result);

        return $result;
    }

    // =========================================================================
    // Reading: status, addresses, graphs, usage
    // =========================================================================

    /**
     * Everything the service page shows about the guest, in one answer.
     */
    public function vmStatus(Service $service): array
    {
        if ($this->currentJob($service) && $this->advanceJob($service) === 'running') {
            $job = $this->currentJob($service);

            return ['available' => false, 'busy' => true, 'job' => $job['kind'], 'step' => $job['step'],
                'error' => null, 'message' => __('proxmox.job_running.'.$job['kind'])];
        }
        $d = $this->getModuleData($service->refresh());
        $lastError = str_ends_with((string) ($d['pve_state'] ?? ''), '_failed') ? (string) ($d['pve_error'] ?? '') : '';

        $api = $service->server ? $this->client($service->server) : null;
        $guest = $api ? $this->guest($service, $api) : ProxmoxResult::failed(__('proxmox.error.no_server'));
        if ($guest instanceof ProxmoxResult) {
            return ['available' => false, 'error' => $lastError !== '' ? $lastError : $guest->error];
        }

        $current = $api->get("{$guest['path']}/status/current");
        if (! $current->ok) {
            return ['available' => false, 'error' => $current->error];
        }
        $v = is_array($current->data) ? $current->data : [];
        $cfg = $guest['config'];
        $running = ($v['status'] ?? '') === 'running';

        return [
            'available' => true,
            'vmid' => $guest['vmid'],
            'node' => $guest['node'],
            'type' => $guest['type'],
            'name' => (string) ($v['name'] ?? $cfg['name'] ?? $cfg['hostname'] ?? ''),
            'status' => (string) ($v['status'] ?? 'unknown'),
            'lock' => (string) ($v['lock'] ?? ''),
            'uptime' => (int) ($v['uptime'] ?? 0),
            'cpus' => (int) ($v['cpus'] ?? $cfg['cores'] ?? 1),
            'cpu' => round(((float) ($v['cpu'] ?? 0)) * 100, 1),
            'memory' => ['used' => (int) round(($v['mem'] ?? 0) / 1048576), 'max' => (int) round(($v['maxmem'] ?? 0) / 1048576)],
            'disk' => $this->diskUsage($api, $guest, $v, $running),
            'net' => ['in' => (int) ($v['netin'] ?? 0), 'out' => (int) ($v['netout'] ?? 0)],
            'agent' => $guest['type'] === 'qemu' && ! empty($v['agent']),
            'addresses' => $running ? $this->addresses($api, $guest, $service) : $this->configuredAddresses($service, $guest),
            'image' => $this->imageLabel($service),
            'last_error' => $lastError,
        ];
    }

    /**
     * Disk size and how much of it is in use.
     *
     * Proxmox knows the fill level of a container, but not of a virtual
     * machine: there it reports 0. The guest agent can say how full the root
     * filesystem is, so it is asked when it runs; otherwise "used" stays null
     * and the page shows only the size rather than a made-up 0.
     */
    private function diskUsage(ProxmoxClient $api, array $guest, array $v, bool $running): array
    {
        $gb = fn ($bytes) => round(((float) $bytes) / 1073741824, 1);
        $max = $gb($v['maxdisk'] ?? 0);

        if ($guest['type'] === 'lxc') {
            return ['used' => $gb($v['disk'] ?? 0), 'max' => $max, 'fs_size' => $max];
        }
        if (! $running || empty($v['agent'])) {
            return ['used' => null, 'max' => $max, 'fs_size' => null];
        }

        $fs = $api->get("{$guest['path']}/agent/get-fsinfo");
        $root = collect(is_array($fs->data['result'] ?? null) ? $fs->data['result'] : [])
            ->first(fn ($f) => in_array($f['mountpoint'] ?? '', ['/', 'C:\\'], true) && isset($f['total-bytes'], $f['used-bytes']));

        return $root
            ? ['used' => $gb($root['used-bytes']), 'max' => $max, 'fs_size' => $gb($root['total-bytes'])]
            : ['used' => null, 'max' => $max, 'fs_size' => null];
    }

    /** The name the product gave the installed image, or one made from its id. */
    private function imageLabel(Service $service): string
    {
        $image = (string) ($this->getModuleData($service)['pve_image'] ?? '');
        if ($image === '') {
            return '';
        }
        $named = collect(ProxmoxPlan::forService($service)->reinstallChoices())->firstWhere('id', $image);

        return (string) ($named['name'] ?? ProxmoxPlan::imageName($image));
    }

    /** Addresses the guest reports, or the ones PNLCS gave it when it cannot say. */
    private function addresses(ProxmoxClient $api, array $guest, Service $service): array
    {
        $found = [];
        $answer = $guest['type'] === 'lxc'
            ? $api->get("{$guest['path']}/interfaces")
            : $api->get("{$guest['path']}/agent/network-get-interfaces");
        $interfaces = $guest['type'] === 'lxc' ? $answer->list() : ($answer->data['result'] ?? []);

        foreach (is_array($interfaces) ? $interfaces : [] as $if) {
            $name = (string) ($if['name'] ?? '');
            if ($name === 'lo') {
                continue;
            }
            foreach (['inet', 'inet6'] as $family) {
                if (! empty($if[$family])) {
                    $found[] = explode('/', (string) $if[$family])[0];
                }
            }
            foreach ($if['ip-addresses'] ?? [] as $addr) {
                $ip = (string) ($addr['ip-address'] ?? '');
                if ($ip !== '' && ! str_starts_with($ip, '127.') && $ip !== '::1' && ! str_starts_with(strtolower($ip), 'fe80')) {
                    $found[] = $ip;
                }
            }
        }

        $found = array_values(array_unique(array_filter($found, fn ($ip) => ! str_starts_with(strtolower($ip), 'fe80'))));

        return $found !== [] ? $found : $this->configuredAddresses($service, $guest);
    }

    private function configuredAddresses(Service $service, array $guest): array
    {
        $ip = $this->getModuleData($service)['pve_ipv4'] ?? null;
        if ($ip) {
            return [$ip];
        }
        $line = (string) ($guest['config']['ipconfig0'] ?? $guest['config']['net0'] ?? '');

        return preg_match('/ip=(\d{1,3}(?:\.\d{1,3}){3})/', $line, $m) ? [$m[1]] : [];
    }

    /**
     * Usage over time for the graphs: CPU, memory, network and disk I/O.
     *
     * @return array{available: bool, points?: array}
     */
    public function graphs(Service $service, string $timeframe = 'hour'): array
    {
        $timeframe = in_array($timeframe, ['hour', 'day', 'week', 'month', 'year'], true) ? $timeframe : 'hour';
        $api = $service->server ? $this->client($service->server) : null;
        $guest = $api ? $this->guest($service, $api) : ProxmoxResult::failed(__('proxmox.error.no_server'));
        if ($guest instanceof ProxmoxResult) {
            return ['available' => false, 'error' => $guest->error];
        }

        $rrd = $api->get("{$guest['path']}/rrddata", ['timeframe' => $timeframe, 'cf' => 'AVERAGE']);
        if (! $rrd->ok) {
            return ['available' => false, 'error' => $rrd->error];
        }

        $points = collect($rrd->list())
            ->filter(fn ($p) => isset($p['time']))
            ->map(fn ($p) => [
                't' => (int) $p['time'],
                'cpu' => isset($p['cpu']) ? round($p['cpu'] * 100, 2) : null,
                'mem' => isset($p['mem']) ? (int) round($p['mem'] / 1048576) : null,
                'maxmem' => isset($p['maxmem']) ? (int) round($p['maxmem'] / 1048576) : null,
                'netin' => isset($p['netin']) ? (int) round($p['netin']) : null,
                'netout' => isset($p['netout']) ? (int) round($p['netout']) : null,
                'diskread' => isset($p['diskread']) ? (int) round($p['diskread']) : null,
                'diskwrite' => isset($p['diskwrite']) ? (int) round($p['diskwrite']) : null,
            ])
            ->values()->all();

        return ['available' => true, 'timeframe' => $timeframe, 'points' => $points];
    }

    /**
     * Traffic this calendar month in MB, from Proxmox's own averages.
     *
     * The netin/netout counters Proxmox shows restart at every guest reboot,
     * so they cannot bill a month; the stored averages can.
     */
    public function monthTrafficMb(ProxmoxClient $api, string $node, string $type, int $vmid): ?int
    {
        $rrd = $api->get("nodes/{$node}/{$type}/{$vmid}/rrddata", ['timeframe' => 'month', 'cf' => 'AVERAGE']);
        if (! $rrd->ok) {
            return null;
        }

        $since = now()->startOfMonth()->getTimestamp();
        $points = collect($rrd->list())->filter(fn ($p) => isset($p['time']))->sortBy('time')->values();
        $bytes = 0.0;
        for ($i = 1; $i < $points->count(); $i++) {
            $p = $points[$i];
            if ($p['time'] <= $since) {
                continue;
            }
            $step = $p['time'] - $points[$i - 1]['time'];
            $bytes += ((float) ($p['netin'] ?? 0) + (float) ($p['netout'] ?? 0)) * $step;
        }

        return (int) round($bytes / 1048576);
    }

    public function usageUpdate(Server $server): array
    {
        $api = $this->client($server);
        $resources = $api->get('cluster/resources', ['type' => 'vm']);
        if (! $resources->ok) {
            return ['updated' => 0, 'errors' => 1];
        }

        $vms = collect($resources->list());
        $updated = 0;
        $errors = 0;

        foreach (Service::where('server_id', $server->id)->whereIn('status', ['active', 'suspended'])->get() as $svc) {
            $vmid = $this->getModuleData($svc)['proxmox_vmid'] ?? null;
            $vm = $vmid ? $vms->firstWhere('vmid', (int) $vmid) : null;

            // A guest deleted by hand on the hypervisor is counted, which is
            // how the operator finds out.
            if (! $vm) {
                $errors++;

                continue;
            }

            $u = [];
            if (isset($vm['maxdisk'])) {
                $u['disk_limit'] = (int) round($vm['maxdisk'] / 1048576);
            }
            if (! empty($vm['disk'])) {
                $u['disk_usage'] = (int) round($vm['disk'] / 1048576);
            }
            $traffic = isset($vm['node'], $vm['type'])
                ? $this->monthTrafficMb($api, (string) $vm['node'], $vm['type'] === 'lxc' ? 'lxc' : 'qemu', (int) $vmid)
                : null;
            if ($traffic !== null) {
                $u['bw_usage'] = $traffic;
            } elseif (isset($vm['netin'])) {
                $u['bw_usage'] = (int) round(($vm['netin'] + ($vm['netout'] ?? 0)) / 1048576);
            }
            $limit = ProxmoxPlan::forService($svc)->bandwidth;
            if ($limit > 0) {
                $u['bw_limit'] = $limit * 1024;
            }

            if ($u !== []) {
                $svc->update($u);
                $updated++;
            }
        }

        return ['updated' => $updated, 'errors' => $errors];
    }

    /**
     * Put an existing guest under this service: one sold before PNLCS, or one
     * the first version of this module made without the mark.
     *
     * Refused when the guest already belongs to another service, by its tags
     * or by another service's records, so linking cannot hand one customer's
     * machine to a second customer.
     */
    public function claim(Service $service, int $vmid): array
    {
        $server = $this->getServer($service);
        if (! $server || $vmid < 100) {
            return $this->buildResult(false, __('proxmox.error.no_server'));
        }
        $api = $this->client($server);

        $found = collect($api->get('cluster/resources', ['type' => 'vm'])->list())->firstWhere('vmid', $vmid);
        if (! $found) {
            return $this->buildResult(false, __('proxmox.error.guest_gone', ['vmid' => $vmid]));
        }
        if (! empty($found['template'])) {
            return $this->buildResult(false, __('proxmox.error.claim_template', ['vmid' => $vmid]));
        }

        $type = ($found['type'] ?? 'qemu') === 'lxc' ? 'lxc' : 'qemu';
        $node = (string) $found['node'];
        $path = "nodes/{$node}/{$type}/{$vmid}";
        $config = $api->get("{$path}/config");
        if (! $config->ok) {
            return $this->buildResult(false, $config->error);
        }
        $cfg = is_array($config->data) ? $config->data : [];

        foreach (self::tagsOf($cfg) as $tag) {
            if (preg_match('/^'.self::TAG.'-s(\d+)$/', $tag, $m) && (int) $m[1] !== $service->id) {
                return $this->buildResult(false, __('proxmox.error.claim_taken', ['vmid' => $vmid, 'service' => $m[1]]));
            }
        }
        $other = Service::where('server_id', $server->id)->where('id', '!=', $service->id)
            ->whereNotIn('status', ['terminated', 'cancelled', 'fraud'])->get(['id', 'module_data'])
            ->first(fn ($s) => (int) (($s->module_data ?? [])['proxmox_vmid'] ?? 0) === $vmid);
        if ($other) {
            return $this->buildResult(false, __('proxmox.error.claim_taken', ['vmid' => $vmid, 'service' => $other->id]));
        }

        $description = trim((string) ($cfg['description'] ?? ''));
        if (! str_contains($description, self::marker($service))) {
            $description = trim($description."\n".self::marker($service));
        }
        $set = $api->put("{$path}/config", ['tags' => $this->mergeTags($cfg, $service), 'description' => $description]);
        if (! $set->ok) {
            return $this->buildResult(false, $set->error);
        }

        $this->setModuleData($service, [
            'proxmox_vmid' => $vmid, 'proxmox_node' => $node, 'proxmox_type' => $type, 'pve_state' => 'ready',
        ]);
        $result = $this->buildResult(true, __('proxmox.claimed', ['vmid' => $vmid]));
        $this->logAction($service, 'claim', $result);

        return $result;
    }

    // =========================================================================
    // Connection
    // =========================================================================

    /**
     * The guests this server's token can see, for the "link an existing
     * server" picker when an operator adds a service by hand. Templates are
     * left out; a guest already tagged for a service says which one, and
     * claim() refuses it.
     *
     * @return array<int, array{id: string, username: string, email: string, status: string}>
     */
    public function listAccounts(Server $server): array
    {
        $guests = $this->client($server)->get('cluster/resources', ['type' => 'vm']);
        if (! $guests->ok) {
            return [];
        }

        return collect($guests->list())
            ->filter(fn ($g) => empty($g['template']) && isset($g['vmid']))
            ->sortBy('vmid')
            ->map(function ($g) {
                $owner = collect(self::tagsOf($g))->map(fn ($t) => preg_match('/^'.self::TAG.'-s(\d+)$/', $t, $m) ? (int) $m[1] : null)->filter()->first();

                return [
                    'id' => (string) $g['vmid'],
                    'username' => '#'.$g['vmid'].' '.($g['name'] ?? ''),
                    'email' => ($g['node'] ?? '').(($g['type'] ?? '') === 'lxc' ? ' · LXC' : ''),
                    'status' => $owner ? __('proxmox.admin.linked_to', ['service' => $owner]) : (string) ($g['status'] ?? ''),
                ];
            })
            ->values()->all();
    }

    public function testConnection(Server $server): bool
    {
        return (new ProxmoxSetup($this->client($server)))->check()['ok'];
    }

    /** The full check behind the admin's Test button: version, rights, node, pool, templates. */
    public function diagnose(Server $server): array
    {
        return (new ProxmoxSetup($this->client($server)))->check();
    }

    /** Lists for the product form: nodes, storages, bridges, templates, pools. */
    public function catalog(Server $server, ?string $node = null): array
    {
        return (new ProxmoxCatalog($this->client($server)))->all($node ?: self::configuredNode($server));
    }

    /** Features the client area shows for this service. */
    public function vpsFeatures(Service $service): array
    {
        if (empty($this->getModuleData($service)['proxmox_vmid'])) {
            return [];
        }
        $plan = ProxmoxPlan::forService($service);
        $features = ['status', 'power', 'graphs'];
        if (count($plan->reinstallChoices()) > 0) {
            $features[] = 'reinstall';
        }
        if ($plan->snapshots > 0) {
            $features[] = 'snapshots';
        }
        if ($plan->backups > 0 && $this->backupStorage($service) !== '') {
            $features[] = 'backups';
        }
        if (($this->getModuleData($service)['proxmox_type'] ?? 'qemu') === 'qemu') {
            $features[] = 'password';
        }

        return $features;
    }
}
