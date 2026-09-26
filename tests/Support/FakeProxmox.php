<?php

namespace Tests\Support;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * A Proxmox VE cluster in memory, behaving the way the real API does where it
 * matters to the module: errors come back in the HTTP status line, changes
 * return a task id whose outcome is read separately, a protected guest cannot
 * be deleted, a running one cannot be deleted either, and a container has no
 * password setting.
 */
final class FakeProxmox
{
    public string $version = '9.1.1';

    /** vmid => [node, type, template, status, config] */
    public array $guests = [];

    public array $nodes = [
        'pve' => ['status' => 'online', 'mem' => 4 * 1073741824, 'maxmem' => 32 * 1073741824],
    ];

    /** The caller's rights: path => [privilege => propagate]. */
    public array $permissions = [];

    public array $pools = [];

    public array $storages = [
        ['storage' => 'local', 'type' => 'dir', 'content' => 'iso,vztmpl,backup', 'avail' => 50 * 1073741824],
        ['storage' => 'backup-nfs', 'type' => 'nfs', 'content' => 'backup', 'avail' => 500 * 1073741824],
        ['storage' => 'local-lvm', 'type' => 'lvmthin', 'content' => 'images,rootdir', 'avail' => 300 * 1073741824],
    ];

    public array $volumes = [
        'local' => [
            ['volid' => 'local:vztmpl/debian-12-standard_12.7-1_amd64.tar.zst', 'content' => 'vztmpl', 'size' => 123],
            ['volid' => 'local:iso/debian-12.13.0-amd64-netinst.iso', 'content' => 'iso', 'size' => 456],
        ],
    ];

    /** upid => exitstatus */
    public array $tasks = [];

    /** Requests answered with an error: ["METHOD path-regex" => [status, reason]]; removed after one use when 'once'. */
    public array $failures = [];

    public bool $agentRunning = false;

    /** While true, every task reports "running" - the way a long clone or restore does. */
    public bool $holdTasks = false;

    /** vmid => [snapname => [snaptime, description]] */
    public array $snapshots = [];

    /** Every request as "METHOD path", with its parameters. */
    public array $log = [];

    private int $upids = 0;

    /** The config each backup holds, as taken. */
    public array $backupConfigs = [];

    public array $restored = [];

    public function __construct()
    {
        $this->permissions = ['/' => array_fill_keys([
            'VM.Allocate', 'VM.Clone', 'VM.Audit', 'VM.PowerMgmt', 'VM.Console', 'VM.Config.CPU', 'VM.Config.Memory',
            'VM.Config.Disk', 'VM.Config.Network', 'VM.Config.Options', 'VM.Config.Cloudinit', 'VM.Config.CDROM',
            'VM.Config.HWType', 'VM.Snapshot', 'VM.Snapshot.Rollback', 'VM.Backup', 'VM.GuestAgent.Audit',
            'VM.GuestAgent.Unrestricted', 'Datastore.AllocateSpace', 'Datastore.Audit', 'SDN.Use', 'Sys.Audit', 'Pool.Audit',
        ], 1)];
    }

    public static function install(): self
    {
        $fake = new self;
        Sleep::fake();
        Http::fake(fn (Request $request) => $fake->handle($request));

        return $fake;
    }

    public function template(int $vmid = 9000, string $name = 'debian12-cloud', string $node = 'pve'): self
    {
        $this->guests[$vmid] = [
            'node' => $node, 'type' => 'qemu', 'template' => 1, 'status' => 'stopped',
            'config' => [
                'name' => $name, 'template' => 1, 'cores' => 1, 'memory' => 2048,
                'scsi0' => "local-lvm:base-{$vmid}-disk-0,size=2G", 'ide2' => "local-lvm:vm-{$vmid}-cloudinit,media=cdrom",
                'boot' => 'order=scsi0', 'net0' => 'virtio=BC:24:11:00:00:01,bridge=vmbr0', 'scsihw' => 'virtio-scsi-single',
            ],
        ];

        return $this;
    }

    /** A guest PNLCS did not make: no tags, no marker. */
    public function foreignGuest(int $vmid, string $type = 'qemu', string $status = 'running'): self
    {
        $this->guests[$vmid] = [
            'node' => 'pve', 'type' => $type, 'template' => 0, 'status' => $status,
            'config' => ['name' => "operator-{$vmid}", 'scsi0' => "local-lvm:vm-{$vmid}-disk-0,size=32G", 'net0' => 'virtio=BC:24:11:AA:BB:CC,bridge=vmbr0'],
        ];

        return $this;
    }

    public function fail(string $pattern, int $status, string $reason, bool $once = true): self
    {
        $this->failures[$pattern] = [$status, $reason, $once];

        return $this;
    }

    public function sent(string $method, string $pathRegex): array
    {
        return array_values(array_filter($this->log, fn ($l) => $l['method'] === $method && preg_match('#^'.$pathRegex.'$#', $l['path'])));
    }

    public function handle(Request $request)
    {
        $url = parse_url($request->url());
        $path = rawurldecode(substr($url['path'] ?? '', strlen('/api2/json/')));
        $method = strtoupper($request->method());
        $params = $method === 'GET' || $method === 'DELETE' ? $this->query($url['query'] ?? '') : $request->data();
        $this->log[] = ['method' => $method, 'path' => $path, 'params' => $params, 'auth' => $request->header('Authorization')[0] ?? null];

        foreach ($this->failures as $pattern => [$status, $reason, $once]) {
            [$m, $p] = explode(' ', $pattern, 2);
            if ($m === $method && preg_match('#^'.$p.'$#', $path)) {
                if ($once) {
                    unset($this->failures[$pattern]);
                }

                return $this->error($status, $reason);
            }
        }

        return $this->route($method, $path, $params);
    }

    private function route(string $method, string $path, array $p)
    {
        $seg = explode('/', $path);

        if ($method === 'GET' && $path === 'version') {
            return $this->ok(['version' => $this->version, 'release' => substr($this->version, 0, 3)]);
        }
        if ($method === 'GET' && $path === 'access/permissions') {
            return $this->ok($this->permissions ?: new \stdClass);
        }
        if ($method === 'GET' && $path === 'nodes') {
            return $this->ok(collect($this->nodes)->map(fn ($n, $name) => ['node' => $name] + $n)->values()->all());
        }
        if ($method === 'GET' && $path === 'pools') {
            return $this->ok(array_map(fn ($p) => ['poolid' => $p], $this->pools));
        }
        if ($method === 'GET' && $path === 'cluster/resources') {
            return $this->ok($this->resources());
        }
        if ($method === 'GET' && $path === 'cluster/nextid') {
            if (! empty($p['vmid'])) {
                return isset($this->guests[(int) $p['vmid']])
                    ? $this->error(400, "VM {$p['vmid']} already exists")
                    : $this->ok((string) $p['vmid']);
            }
            $next = 100;
            while (isset($this->guests[$next])) {
                $next++;
            }

            return $this->ok((string) $next);
        }

        // nodes/{node}/...
        if ($seg[0] !== 'nodes' || ! isset($seg[1])) {
            return $this->error(501, "Method '{$method} /{$path}' not implemented");
        }
        $node = $seg[1];
        $rest = array_slice($seg, 2);

        if ($method === 'GET' && $rest === ['storage']) {
            return $this->ok($this->storages);
        }
        if ($method === 'GET' && ($rest[0] ?? '') === 'storage' && ($rest[2] ?? '') === 'content') {
            return $this->ok(array_values(array_filter($this->volumes[$rest[1]] ?? [], fn ($v) => (! isset($p['content']) || $v['content'] === $p['content'])
                && (! isset($p['vmid']) || (int) ($v['vmid'] ?? 0) === (int) $p['vmid']))));
        }
        if ($method === 'DELETE' && ($rest[0] ?? '') === 'storage' && ($rest[2] ?? '') === 'content') {
            $volid = implode('/', array_slice($rest, 3));
            $before = count($this->volumes[$rest[1]] ?? []);
            $this->volumes[$rest[1]] = array_values(array_filter($this->volumes[$rest[1]] ?? [], fn ($v) => $v['volid'] !== $volid));

            return count($this->volumes[$rest[1]]) < $before ? $this->task() : $this->error(500, "volume '{$volid}' does not exist");
        }
        if ($method === 'POST' && $rest === ['vzdump']) {
            $vmid = (int) $p['vmid'];
            $type = $this->guests[$vmid]['type'] ?? 'qemu';
            $n = count($this->volumes[$p['storage']] ?? []);
            $this->volumes[$p['storage']][] = [
                'volid' => "{$p['storage']}:backup/vzdump-{$type}-{$vmid}-2026_09_26-10_00_".sprintf('%02d', $n).'.vma.zst',
                'content' => 'backup', 'vmid' => $vmid, 'size' => 1073741824, 'ctime' => 1790000000 + $n,
                'notes' => (string) ($p['notes-template'] ?? ''),
            ];

            return $this->task();
        }
        if ($method === 'GET' && $rest === ['tasks']) {
            return $this->ok([]);
        }
        if ($method === 'GET' && $rest === ['network']) {
            return $this->ok([['iface' => 'vmbr0', 'type' => 'bridge', 'cidr' => '192.0.2.10/24'], ['iface' => 'eno1', 'type' => 'eth']]);
        }
        if (($rest[0] ?? '') === 'tasks') {
            $upid = $rest[1] ?? '';
            if (($rest[2] ?? '') === 'log') {
                return $this->ok([['n' => 1, 't' => $this->tasks[$upid] ?? 'TASK OK']]);
            }

            if ($this->holdTasks) {
                return $this->ok(['status' => 'running']);
            }

            return $this->ok(['status' => 'stopped', 'exitstatus' => $this->tasks[$upid] ?? 'OK']);
        }

        $type = $rest[0] ?? '';
        if (! in_array($type, ['qemu', 'lxc'], true)) {
            return $this->error(501, 'not implemented');
        }

        // Create
        if ($method === 'POST' && count($rest) === 1) {
            return $this->create($node, $type, $p);
        }

        $vmid = (int) ($rest[1] ?? 0);
        $action = implode('/', array_slice($rest, 2));
        $guest = $this->guests[$vmid] ?? null;

        if ($action === 'clone' && $method === 'POST') {
            return $this->cloneGuest($vmid, $p);
        }

        if (! $guest || $guest['type'] !== $type || $guest['node'] !== $node) {
            return $this->error(500, "Configuration file 'nodes/{$node}/".($type === 'lxc' ? 'lxc' : 'qemu-server')."/{$vmid}.conf' does not exist");
        }

        if ($method === 'DELETE' && $action === '') {
            if (! empty($guest['config']['protection'])) {
                return $this->error(500, "can't remove VM {$vmid} - protection mode enabled");
            }
            if ($guest['status'] === 'running') {
                return $this->error(500, "VM {$vmid} is running - destroy failed");
            }
            unset($this->guests[$vmid]);

            return $this->task();
        }

        if ($action === 'snapshot' && $method === 'GET') {
            $list = [];
            foreach ($this->snapshots[$vmid] ?? [] as $name => $snap) {
                $list[] = ['name' => $name, 'snaptime' => $snap[0], 'description' => $snap[1]];
            }
            $list[] = ['name' => 'current', 'description' => 'You are here!'];

            return $this->ok($list);
        }
        if ($action === 'snapshot' && $method === 'POST') {
            $this->snapshots[$vmid][$p['snapname']] = [1790000000 + count($this->snapshots[$vmid] ?? []), (string) ($p['description'] ?? '')];

            return $this->task();
        }
        if (preg_match('#^snapshot/([^/]+)(/rollback)?$#', $action, $m)) {
            if (! isset($this->snapshots[$vmid][$m[1]])) {
                return $this->error(500, "snapshot '{$m[1]}' does not exist");
            }
            if (($m[2] ?? '') === '/rollback' && $method === 'POST') {
                $this->guests[$vmid]['status'] = 'stopped';

                return $this->task();
            }
            if ($method === 'DELETE') {
                unset($this->snapshots[$vmid][$m[1]]);

                return $this->task();
            }
        }

        return match (true) {
            $method === 'GET' && $action === 'config' => $this->ok($guest['config']),
            in_array($method, ['PUT', 'POST'], true) && $action === 'config' => $this->setConfig($vmid, $p),
            $method === 'PUT' && $action === 'resize' => $this->resize($vmid, $p),
            $method === 'GET' && $action === 'status/current' => $this->ok($this->current($vmid)),
            $method === 'POST' && str_starts_with($action, 'status/') => $this->power($vmid, substr($action, 7)),
            $method === 'POST' && $action === 'agent/set-user-password' => $this->agentRunning ? $this->ok(['result' => []]) : $this->error(500, 'QEMU guest agent is not running'),
            $method === 'GET' && $action === 'agent/network-get-interfaces' => $this->agentRunning
                ? $this->ok(['result' => [['name' => 'lo', 'ip-addresses' => [['ip-address' => '127.0.0.1']]], ['name' => 'eth0', 'ip-addresses' => [['ip-address' => '192.0.2.50'], ['ip-address' => 'fe80::1']]]]])
                : $this->error(500, 'QEMU guest agent is not running'),
            $method === 'GET' && $action === 'agent/get-fsinfo' => $this->agentRunning
                ? $this->ok(['result' => [
                    ['mountpoint' => '/boot/efi', 'type' => 'vfat', 'total-bytes' => 132_000_000, 'used-bytes' => 6_000_000],
                    ['mountpoint' => '/', 'type' => 'ext4', 'total-bytes' => 20 * 1073741824, 'used-bytes' => (int) (3.5 * 1073741824)],
                ]])
                : $this->error(500, 'QEMU guest agent is not running'),
            $method === 'GET' && $action === 'interfaces' => $this->ok([['name' => 'lo', 'inet' => '127.0.0.1/8'], ['name' => 'eth0', 'inet' => '192.0.2.60/24']]),
            $method === 'GET' && $action === 'rrddata' => $this->ok($this->rrd($p['timeframe'] ?? 'hour')),
            default => $this->error(501, "Method '{$method} /{$path}' not implemented"),
        };
    }

    private function create(string $node, string $type, array $p)
    {
        $vmid = (int) $p['vmid'];
        $archive = $type === 'lxc' ? (! empty($p['restore']) ? ($p['ostemplate'] ?? null) : null) : ($p['archive'] ?? null);
        if ($archive !== null) {
            $existing = $this->guests[$vmid] ?? null;
            if ($existing && empty($p['force'])) {
                return $this->error(500, "VM {$vmid} already exists");
            }
            if (! empty($existing['config']['protection'])) {
                // Accepted, and the task fails - the way Proxmox reports it.
                $upid = $this->task()->wait()->getBody()->getContents();
                $upid = json_decode($upid, true)['data'];
                $this->tasks[$upid] = "unable to restore VM {$vmid} - - protection mode enabled";

                return $this->ok($upid);
            }
            // The backup brings the settings it was taken with.
            $this->guests[$vmid] = ['node' => $node, 'type' => $type, 'template' => 0, 'status' => 'stopped',
                'config' => $this->backupConfigs[$archive] ?? ($existing['config'] ?? []), 'pool' => $existing['pool'] ?? null];
            $this->snapshots[$vmid] = [];
            $this->restored[] = $archive;

            return $this->task();
        }
        if (isset($this->guests[$vmid])) {
            return $this->error(500, "VM {$vmid} already exists on node '{$node}'");
        }
        if ($type === 'lxc' && isset($p['password']) && isset($p['vmid']) && ! isset($p['ostemplate'])) {
            return $this->error(400, 'ostemplate: property is missing and it is not optional');
        }

        $config = collect($p)->except(['vmid', 'pool', 'start', 'password', 'ostemplate'])->all();
        if ($type === 'lxc') {
            $config['rootfs'] = preg_replace('/^([^:]+):(\d+)$/', "$1:vm-{$vmid}-disk-0,size=$2G", (string) ($p['rootfs'] ?? ''));
            if (! str_contains((string) ($config['net0'] ?? ''), 'hwaddr=')) {
                $config['net0'] = str_replace('name=eth0,', 'name=eth0,hwaddr=BC:24:11:00:10:'.sprintf('%02X', $vmid % 256).',', (string) ($config['net0'] ?? ''));
            }
        }
        $this->guests[$vmid] = ['node' => $node, 'type' => $type, 'template' => 0, 'status' => 'stopped', 'config' => $config, 'pool' => $p['pool'] ?? null];

        return $this->task();
    }

    private function cloneGuest(int $source, array $p)
    {
        $tpl = $this->guests[$source] ?? null;
        if (! $tpl) {
            return $this->error(500, "Configuration file 'nodes/pve/qemu-server/{$source}.conf' does not exist");
        }
        $newid = (int) $p['newid'];
        if (isset($this->guests[$newid])) {
            return $this->error(500, "unable to create VM {$newid}: config file already exists");
        }

        $config = $tpl['config'];
        unset($config['template']);
        $config['name'] = $p['name'] ?? "Copy-of-VM-{$source}";
        $config['scsi0'] = str_replace("base-{$source}", "vm-{$newid}", $config['scsi0']);
        $config['net0'] = 'virtio=BC:24:11:00:20:'.sprintf('%02X', $newid % 256).',bridge=vmbr0';
        if (isset($p['description'])) {
            $config['description'] = $p['description'];
        }
        $this->guests[$newid] = ['node' => $p['target'] ?? $tpl['node'], 'type' => 'qemu', 'template' => 0, 'status' => 'stopped', 'config' => $config, 'pool' => $p['pool'] ?? null];

        return $this->task();
    }

    private function setConfig(int $vmid, array $p)
    {
        $guest = &$this->guests[$vmid];
        if ($guest['type'] === 'lxc' && array_key_exists('password', $p)) {
            return $this->error(400, 'password: property is not defined in schema and the schema does not allow additional properties');
        }
        if (! empty($guest['config']['protection']) && ! array_key_exists('protection', $p) && collect($p)->keys()->contains(fn ($k) => str_starts_with($k, 'scsi') || $k === 'rootfs')) {
            return $this->error(500, 'protection mode enabled');
        }
        foreach (array_filter(explode(',', (string) ($p['delete'] ?? ''))) as $key) {
            unset($guest['config'][$key]);
        }
        foreach (collect($p)->except(['delete', 'digest'])->all() as $key => $value) {
            $guest['config'][$key] = $value;
        }

        return $this->ok(null);
    }

    private function resize(int $vmid, array $p)
    {
        $disk = (string) $p['disk'];
        $line = (string) ($this->guests[$vmid]['config'][$disk] ?? '');
        if ($line === '') {
            return $this->error(500, "disk '{$disk}' does not exist");
        }
        $this->guests[$vmid]['config'][$disk] = preg_replace('/size=[^,]+/', 'size='.$p['size'], $line);

        return $this->task();
    }

    private function power(int $vmid, string $action)
    {
        $this->guests[$vmid]['status'] = match ($action) {
            'start', 'reboot', 'reset', 'resume' => 'running',
            'stop', 'shutdown' => 'stopped',
            'suspend' => 'paused',
            default => $this->guests[$vmid]['status'],
        };

        return $this->task();
    }

    private function current(int $vmid): array
    {
        $g = $this->guests[$vmid];
        $running = $g['status'] === 'running';

        return [
            'vmid' => $vmid, 'name' => $g['config']['name'] ?? $g['config']['hostname'] ?? '', 'status' => $g['status'],
            'uptime' => $running ? 3723 : 0, 'cpu' => $running ? 0.125 : 0, 'cpus' => (int) ($g['config']['cores'] ?? 1),
            'mem' => $running ? 512 * 1048576 : 0, 'maxmem' => (int) ($g['config']['memory'] ?? 512) * 1048576,
            'disk' => 0, 'maxdisk' => 20 * 1073741824, 'netin' => 1000, 'netout' => 2000,
            'agent' => isset($g['config']['agent']) ? 1 : 0,
        ];
    }

    private function resources(): array
    {
        return collect($this->guests)->map(fn ($g, $vmid) => [
            'id' => "{$g['type']}/{$vmid}", 'vmid' => $vmid, 'node' => $g['node'], 'type' => $g['type'],
            'template' => $g['template'], 'status' => $g['status'], 'name' => $g['config']['name'] ?? $g['config']['hostname'] ?? '',
            'tags' => $g['config']['tags'] ?? '', 'maxdisk' => 20 * 1073741824, 'disk' => 0,
            'netin' => 5 * 1048576, 'netout' => 5 * 1048576, 'pool' => $g['pool'] ?? null,
        ])->values()->all();
    }

    private function rrd(string $timeframe): array
    {
        $step = $timeframe === 'month' ? 43200 : 60;
        $now = now()->getTimestamp();
        $points = [];
        for ($i = 10; $i >= 0; $i--) {
            $points[] = ['time' => $now - $i * $step, 'cpu' => 0.05, 'mem' => 400 * 1048576, 'maxmem' => 1024 * 1048576,
                'netin' => 1024, 'netout' => 1024, 'diskread' => 10, 'diskwrite' => 20];
        }

        return $points;
    }

    public function backup(int $vmid, string $storage = 'backup-nfs', array $config = [], bool $protected = false): string
    {
        $type = $this->guests[$vmid]['type'] ?? 'qemu';
        $volid = "{$storage}:backup/vzdump-{$type}-{$vmid}-2026_09_20-03_00_0".count($this->volumes[$storage] ?? []).'.vma.zst';
        $this->volumes[$storage][] = ['volid' => $volid, 'content' => 'backup', 'vmid' => $vmid, 'size' => 2147483648,
            'ctime' => 1789000000 + count($this->volumes[$storage] ?? []), 'notes' => 'PNLCS', 'protected' => $protected ? 1 : 0];
        $this->backupConfigs[$volid] = $config ?: ($this->guests[$vmid]['config'] ?? []);

        return $volid;
    }

    private function task()
    {
        $upid = sprintf('UPID:pve:%08X:0000:0000:task%d:root@pam:', ++$this->upids, $this->upids);
        $this->tasks[$upid] = 'OK';

        return $this->ok($upid);
    }

    /** Make the next task end with this exit status. */
    public function nextTaskFails(string $exitstatus): void
    {
        $this->tasks['__next'] = $exitstatus;
    }

    private function ok(mixed $data)
    {
        if (is_string($data) && str_starts_with($data, 'UPID:') && isset($this->tasks['__next'])) {
            $this->tasks[$data] = $this->tasks['__next'];
            unset($this->tasks['__next']);
        }

        return Create::promiseFor(new Response(200, ['Content-Type' => 'application/json'], json_encode(['data' => $data])));
    }

    private function error(int $status, string $reason)
    {
        return Create::promiseFor(new Response($status, ['Content-Type' => 'application/json'], json_encode(['data' => null]), '1.1', $reason));
    }

    private function query(string $query): array
    {
        parse_str($query, $out);

        return $out;
    }
}
