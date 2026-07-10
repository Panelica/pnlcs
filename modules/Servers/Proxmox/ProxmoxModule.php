<?php
namespace Modules\Servers\Proxmox;

use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Servers\AbstractServerModule;

class ProxmoxModule extends AbstractServerModule
{
    public function getModuleName(): string { return 'proxmox'; }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'username', 'label' => 'API User (e.g. root@pam)', 'type' => 'text'],
            ['name' => 'password', 'label' => 'Password', 'type' => 'password'],
            ['name' => 'access_hash', 'label' => 'API Token (PVEAPIToken=user@realm!tokenid=UUID)', 'type' => 'password'],
        ];
    }

    // =========================================================================
    // HTTP / Auth
    // =========================================================================

    private function baseUrl(Server $server): string
    {
        return "https://{$server->hostname}:" . ($server->port ?: 8006) . "/api2/json";
    }

    private function http(Server $server): \Illuminate\Http\Client\PendingRequest
    {
        // PVE accepts JSON bodies only on 7.2+; form-encoding works everywhere.
        $client = Http::asForm()->withoutVerifying()->timeout(60);
        $token = $server->access_hash ?? '';
        if (str_starts_with($token, 'PVEAPIToken=')) {
            return $client->withHeaders(['Authorization' => $token]);
        }
        $ticket = $this->getTicket($server);
        if ($ticket) {
            return $client->withHeaders(['CSRFPreventionToken' => $ticket['csrf']])
                ->withCookies(
                    ['PVEAuthCookie' => $ticket['ticket']],
                    parse_url($this->baseUrl($server), PHP_URL_HOST)
                );
        }
        Log::warning('Proxmox: No valid auth method for server #' . $server->id);
        return $client;
    }

    private function getTicket(Server $server): ?array
    {
        $key = "proxmox_ticket_{$server->id}";
        if ($cached = cache($key)) return $cached;

        $resp = Http::asForm()->withoutVerifying()->post($this->baseUrl($server) . '/access/ticket', [
            'username' => $server->username ?: 'root@pam',
            'password' => $server->password ?? '',
        ]);

        if (!$resp->successful()) {
            Log::error('Proxmox login failed', [
                'server' => $server->id,
                'status' => $resp->status(),
                'body'   => substr($resp->body(), 0, 200),
            ]);
            return null;
        }

        $d = $resp->json('data');
        $ticket = ['ticket' => $d['ticket'] ?? '', 'csrf' => $d['CSRFPreventionToken'] ?? ''];
        cache([$key => $ticket], now()->addMinutes(90));
        return $ticket;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function getNode(Server $server, Service $service): string
    {
        $cfg = $this->getCfg($service);
        if (!empty($cfg['node'])) return $cfg['node'];
        if (!empty($server->nameserver1)) return $server->nameserver1;

        $resp = $this->http($server)->get($this->baseUrl($server) . '/nodes');
        if ($resp->successful()) {
            $n = collect($resp->json('data') ?? [])
                ->where('status', 'online')
                ->sortBy('cpu')
                ->first();
            if ($n) return $n['node'];
        }
        return 'pve';
    }

    private function getCfg(Service $service): array
    {
        $p = $service->product;
        if (!$p) return [];
        $c = is_string($p->config_options) ? json_decode($p->config_options, true) : ($p->config_options ?? []);
        return is_array($c) ? $c : [];
    }

    private function nextVmid(Server $server): ?int
    {
        $r = $this->http($server)->get($this->baseUrl($server) . '/cluster/nextid');
        return $r->successful() ? (int) $r->json('data') : null;
    }

    private function waitTask(Server $server, string $node, string $upid, int $timeout = 120): bool
    {
        $start = time();
        while (time() - $start < $timeout) {
            $r = $this->http($server)->get(
                "{$this->baseUrl($server)}/nodes/{$node}/tasks/" . urlencode($upid) . "/status"
            );
            if ($r->successful() && $r->json('data.status') === 'stopped') {
                return $r->json('data.exitstatus') === 'OK';
            }
            sleep(3);
        }
        Log::warning("Proxmox task timed out after {$timeout}s", ['upid' => $upid, 'node' => $node]);
        return false;
    }

    // =========================================================================
    // Create
    // =========================================================================

    public function create(Service $service): array
    {
        $server = $this->getServer($service);
        if (!$server) return $this->buildResult(false, 'No Proxmox server configured.');
        if (!$service->client) return $this->buildResult(false, 'No client associated.');

        $cfg      = $this->getCfg($service);
        $type     = $cfg['type'] ?? 'qemu';
        $node     = $this->getNode($server, $service);
        $vmid     = $this->nextVmid($server);
        if (!$vmid) return $this->buildResult(false, 'Failed to get next VMID.');

        $hostname = $service->domain ?: "vps-{$vmid}";
        $password = $service->password ?: bin2hex(random_bytes(8));
        $cores    = max(1, (int) ($cfg['cores'] ?? 1));
        $memory   = max(256, (int) ($cfg['memory'] ?? 1024));
        $disk     = max(5, (int) ($cfg['disk'] ?? 20));
        $storage  = $cfg['storage'] ?? 'local-lvm';
        $bridge   = $cfg['bridge'] ?? 'vmbr0';

        $base = $this->baseUrl($server);
        $http = $this->http($server);

        if ($type === 'lxc') {
            $result = $this->createLxc($server, $http, $base, $node, $vmid, $hostname, $password, $cores, $memory, $disk, $storage, $bridge, $cfg);
        } else {
            $result = $this->createQemu($server, $http, $base, $node, $vmid, $hostname, $password, $cores, $memory, $disk, $storage, $bridge, $cfg);
        }

        if (!$result['success']) {
            $this->logAction($service, 'create', $result);
            return $result;
        }

        $this->setModuleData($service, [
            'proxmox_vmid' => $vmid,
            'proxmox_node' => $node,
            'proxmox_type' => $type,
        ]);

        $service->update([
            'username' => 'root',
            'password' => $password,
            'status'   => 'active',
        ]);

        $out = $this->buildResult(true, ucfirst($type) . " #{$vmid} created on {$node}.", compact('vmid', 'node', 'type', 'hostname', 'cores', 'memory', 'disk'));
        $this->logAction($service, 'create', $out);
        return $out;
    }

    private function createQemu(Server $server, $http, string $base, string $node, int $vmid, string $hostname, string $password, int $cores, int $memory, int $disk, string $storage, string $bridge, array $cfg): array
    {
        $tplId = $cfg['template_id'] ?? null;

        if ($tplId) {
            // Clone from template
            $resp = $http->post("{$base}/nodes/{$node}/qemu/{$tplId}/clone", [
                'newid'   => $vmid,
                'name'    => $hostname,
                'target'  => $node,
                'full'    => 1,
                'storage' => $storage,
            ]);

            if (!$resp->successful()) {
                return $this->buildResult(false, 'Clone request failed: ' . $resp->body());
            }

            // FIX BUG 1+6: Wait for clone task with correct server reference
            $upid = $resp->json('data');
            if ($upid) {
                $taskOk = $this->waitTask($server, $node, $upid, 300);
                if (!$taskOk) {
                    return $this->buildResult(false, 'Clone task failed or timed out.');
                }
            }

            // Configure resources after successful clone
            $cfgResp = $http->put("{$base}/nodes/{$node}/qemu/{$vmid}/config", [
                'cores'      => $cores,
                'memory'     => $memory,
                'name'       => $hostname,
                'ciuser'     => 'root',
                'cipassword' => $password,
                'net0'       => "virtio,bridge={$bridge}",
            ]);
            if (!$cfgResp->successful()) {
                return $this->buildResult(false, 'VM cloned but config (password/resources) failed: ' . $cfgResp->body());
            }

            // Resize disk (absolute size, only grows)
            $rsResp = $http->put("{$base}/nodes/{$node}/qemu/{$vmid}/resize", [
                'disk' => 'scsi0',
                'size' => "{$disk}G",
            ]);
            if (!$rsResp->successful()) {
                Log::warning('Proxmox disk resize failed (VM still usable)', ['vmid' => $vmid, 'body' => $rsResp->body()]);
            }
        } else {
            // Create VM from scratch (no template)
            $resp = $http->post("{$base}/nodes/{$node}/qemu", [
                'vmid'    => $vmid,
                'name'    => $hostname,
                'ostype'  => $cfg['os_type'] ?? 'l26',
                'cores'   => $cores,
                'sockets' => 1,
                'memory'  => $memory,
                'scsihw'  => 'virtio-scsi-single',
                'scsi0'   => "{$storage}:{$disk},iothread=1",
                'net0'    => "virtio,bridge={$bridge}",
                'boot'    => 'order=scsi0',
                'agent'   => 'enabled=1',
            ]);

            if (!$resp->successful()) {
                return $this->buildResult(false, 'VM create failed: ' . $resp->body());
            }

            if ($upid = $resp->json('data')) {
                $this->waitTask($server, $node, $upid, 120);
            }
        }

        // Start the VM
        $http->post("{$base}/nodes/{$node}/qemu/{$vmid}/status/start");

        return $this->buildResult(true, 'QEMU VM created.', ['vmid' => $vmid]);
    }

    private function createLxc(Server $server, $http, string $base, string $node, int $vmid, string $hostname, string $password, int $cores, int $memory, int $disk, string $storage, string $bridge, array $cfg): array
    {
        $tpl = $cfg['os_template'] ?? 'local:vztmpl/ubuntu-22.04-standard_22.04-1_amd64.tar.zst';

        $resp = $http->post("{$base}/nodes/{$node}/lxc", [
            'vmid'         => $vmid,
            'hostname'     => $hostname,
            'ostemplate'   => $tpl,
            'password'     => $password,
            'rootfs'       => "{$storage}:{$disk}",
            'cores'        => $cores,
            'memory'       => $memory,
            'swap'         => min($memory, 512),
            'net0'         => "name=eth0,bridge={$bridge},ip=dhcp",
            'unprivileged' => 1,
            'start'        => 1,
        ]);

        if (!$resp->successful()) {
            return $this->buildResult(false, 'LXC create failed: ' . $resp->body());
        }

        // FIX BUG 3: Wait for LXC task completion
        $upid = $resp->json('data');
        if ($upid) {
            $taskOk = $this->waitTask($server, $node, $upid, 120);
            if (!$taskOk) {
                return $this->buildResult(false, 'LXC create task failed or timed out.');
            }
        }

        return $this->buildResult(true, 'LXC container created.', ['vmid' => $vmid]);
    }

    // =========================================================================
    // Power Actions
    // =========================================================================

    private function vmAction(Service $service, string $action): array
    {
        $server = $this->getServer($service);
        $d = $this->getModuleData($service);
        if (!$server || !($d['proxmox_vmid'] ?? null) || !($d['proxmox_node'] ?? null)) {
            return $this->buildResult(false, 'Missing Proxmox VM data.');
        }

        $ep = ($d['proxmox_type'] ?? 'qemu') === 'lxc' ? 'lxc' : 'qemu';
        $resp = $this->http($server)->post(
            "{$this->baseUrl($server)}/nodes/{$d['proxmox_node']}/{$ep}/{$d['proxmox_vmid']}/status/{$action}"
        );

        return $this->buildResult(
            $resp->successful(),
            $resp->successful() ? "VM #{$d['proxmox_vmid']} — {$action} executed." : "Action failed: " . $resp->body()
        );
    }

    public function suspend(Service $service, string $reason = ''): array
    {
        $r = $this->vmAction($service, 'shutdown');
        if ($r['success']) {
            $service->update(['status' => 'suspended', 'suspension_date' => now(), 'suspension_reason' => $reason]);
        }
        $this->logAction($service, 'suspend', $r);
        return $r;
    }

    public function unsuspend(Service $service): array
    {
        $r = $this->vmAction($service, 'start');
        if ($r['success']) {
            $service->update(['status' => 'active', 'suspension_date' => null, 'suspension_reason' => null]);
        }
        $this->logAction($service, 'unsuspend', $r);
        return $r;
    }

    public function terminate(Service $service): array
    {
        $server = $this->getServer($service);
        $d = $this->getModuleData($service);
        if (!$server || !($d['proxmox_vmid'] ?? null) || !($d['proxmox_node'] ?? null)) {
            return $this->buildResult(false, 'Missing VM data.');
        }

        $ep   = ($d['proxmox_type'] ?? 'qemu') === 'lxc' ? 'lxc' : 'qemu';
        $base = $this->baseUrl($server);
        $http = $this->http($server);
        $vmid = $d['proxmox_vmid'];
        $node = $d['proxmox_node'];

        // FIX BUG 7: Stop VM and wait properly instead of sleep(5)
        $stopResp = $http->post("{$base}/nodes/{$node}/{$ep}/{$vmid}/status/stop");
        if ($stopResp->successful() && ($upid = $stopResp->json('data'))) {
            $this->waitTask($server, $node, $upid, 30);
        } else {
            sleep(3); // Fallback if no UPID returned
        }

        // Destroy VM with disk cleanup
        $resp = $http->delete("{$base}/nodes/{$node}/{$ep}/{$vmid}?purge=1&destroy-unreferenced-disks=1");

        if ($resp->successful()) {
            $service->update(['status' => 'terminated', 'termination_date' => now()]);
        }

        $out = $this->buildResult($resp->successful(), $resp->successful() ? "VM #{$vmid} destroyed." : "Destroy failed: " . $resp->body());
        $this->logAction($service, 'terminate', $out);
        return $out;
    }

    // =========================================================================
    // Password & Package
    // =========================================================================

    public function changePassword(Service $service, string $newPassword): array
    {
        $server = $this->getServer($service);
        $d = $this->getModuleData($service);
        if (!$server || !($d['proxmox_vmid'] ?? null)) {
            return $this->buildResult(false, 'Missing VM data.');
        }

        $ep    = ($d['proxmox_type'] ?? 'qemu') === 'lxc' ? 'lxc' : 'qemu';
        $field = $ep === 'lxc' ? 'password' : 'cipassword';

        // FIX BUG 9: Check response
        $resp = $this->http($server)->put(
            "{$this->baseUrl($server)}/nodes/{$d['proxmox_node']}/{$ep}/{$d['proxmox_vmid']}/config",
            [$field => $newPassword]
        );

        if (!$resp->successful()) {
            return $this->buildResult(false, 'Password change failed: ' . $resp->body());
        }

        $service->update(['password' => $newPassword]);
        $out = $this->buildResult(true, "Password changed for VM #{$d['proxmox_vmid']}.");
        $this->logAction($service, 'changePassword', $out);
        return $out;
    }

    public function changePackage(Service $service, array $pkg): array
    {
        $server = $this->getServer($service);
        $d = $this->getModuleData($service);
        if (!$server || !($d['proxmox_vmid'] ?? null)) {
            return $this->buildResult(false, 'Missing VM data.');
        }

        $c = is_string($pkg['config_options'] ?? null)
            ? json_decode($pkg['config_options'], true)
            : ($pkg['config_options'] ?? []);

        $ep   = ($d['proxmox_type'] ?? 'qemu') === 'lxc' ? 'lxc' : 'qemu';
        $base = $this->baseUrl($server);
        $vmid = $d['proxmox_vmid'];
        $node = $d['proxmox_node'];

        $params = [];
        if (($c['cores'] ?? 0) > 0) $params['cores'] = (int) $c['cores'];
        if (($c['memory'] ?? 0) > 0) $params['memory'] = (int) $c['memory'];

        if (!empty($params)) {
            $resp = $this->http($server)->put("{$base}/nodes/{$node}/{$ep}/{$vmid}/config", $params);
            if (!$resp->successful()) {
                return $this->buildResult(false, 'Resource update failed: ' . $resp->body());
            }
        }

        // Disk resize (can only grow)
        if (($c['disk'] ?? 0) > 0) {
            $diskName = $ep === 'lxc' ? 'rootfs' : 'scsi0';
            $this->http($server)->put("{$base}/nodes/{$node}/{$ep}/{$vmid}/resize", [
                'disk' => $diskName,
                'size' => $c['disk'] . 'G',
            ]);
        }

        $out = $this->buildResult(true, "VM #{$vmid} resources updated.", $params);
        $this->logAction($service, 'changePackage', $out);
        return $out;
    }

    // =========================================================================
    // Monitoring
    // =========================================================================

    public function usageUpdate(Server $server): array
    {
        $resp = $this->http($server)->get($this->baseUrl($server) . '/cluster/resources', ['type' => 'vm']);
        if (!$resp->successful()) return ['updated' => 0, 'errors' => 1];

        $vms = collect($resp->json('data') ?? []);
        $updated = 0;

        foreach (Service::where('server_id', $server->id)->where('status', 'active')->get() as $svc) {
            $vmid = $this->getModuleData($svc)['proxmox_vmid'] ?? null;
            if (!$vmid) continue;

            $vm = $vms->firstWhere('vmid', (int) $vmid);
            if (!$vm) continue;

            // Canonical unit for services.disk_*/bw_* is MB (overage billing
            // and the client area both assume MB).
            $u = [];
            if (isset($vm['disk']))    $u['disk_usage'] = (int) round($vm['disk'] / 1048576);
            if (isset($vm['maxdisk'])) $u['disk_limit'] = (int) round($vm['maxdisk'] / 1048576);
            if (isset($vm['netin']))   $u['bw_usage'] = (int) round(($vm['netin'] + ($vm['netout'] ?? 0)) / 1048576);

            if (!empty($u)) { $svc->update($u); $updated++; }
        }

        return ['updated' => $updated, 'errors' => 0];
    }

    public function testConnection(Server $server): bool
    {
        try {
            $r = $this->http($server)->get($this->baseUrl($server) . '/version');
            if ($r->successful()) {
                Log::info('Proxmox connection OK: v' . $r->json('data.version'), ['server' => $server->id]);
                return true;
            }
            Log::warning('Proxmox connection failed', ['server' => $server->id, 'status' => $r->status()]);
            return false;
        } catch (\Throwable $e) {
            Log::error('Proxmox connection error', ['server' => $server->id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    // =========================================================================
    // Extra: Power + Console + Status
    // =========================================================================

    public function start(Service $s): array { return $this->vmAction($s, 'start'); }
    public function stop(Service $s): array { return $this->vmAction($s, 'stop'); }
    public function reboot(Service $s): array { return $this->vmAction($s, 'reboot'); }
    public function shutdown(Service $s): array { return $this->vmAction($s, 'shutdown'); }

    public function getConsoleUrl(Service $service): ?string
    {
        $server = $this->getServer($service);
        $d = $this->getModuleData($service);
        if (!$server || !($d['proxmox_vmid'] ?? null)) return null;

        $ep = ($d['proxmox_type'] ?? 'qemu') === 'lxc' ? 'lxc' : 'qemu';
        $resp = $this->http($server)->post(
            "{$this->baseUrl($server)}/nodes/{$d['proxmox_node']}/{$ep}/{$d['proxmox_vmid']}/vncproxy",
            ['websocket' => 1]
        );

        if (!$resp->successful()) return null;

        $port   = $resp->json('data.port');
        $ticket = $resp->json('data.ticket');
        $pvePort = $server->port ?: 8006;

        // FIX BUG 10: Use 'kvm' for QEMU, 'lxc' for LXC
        $consoleType = $ep === 'lxc' ? 'lxc' : 'kvm';

        return "https://{$server->hostname}:{$pvePort}/?console={$consoleType}&novnc=1" .
            "&vmid={$d['proxmox_vmid']}&vmname=" . urlencode($service->domain ?? '') .
            "&node={$d['proxmox_node']}&resize=off&port={$port}&vncticket=" . urlencode($ticket);
    }

    public function getVmStatus(Service $service): ?array
    {
        $server = $this->getServer($service);
        $d = $this->getModuleData($service);
        if (!$server || !($d['proxmox_vmid'] ?? null)) return null;

        $ep = ($d['proxmox_type'] ?? 'qemu') === 'lxc' ? 'lxc' : 'qemu';
        $resp = $this->http($server)->get(
            "{$this->baseUrl($server)}/nodes/{$d['proxmox_node']}/{$ep}/{$d['proxmox_vmid']}/status/current"
        );

        if (!$resp->successful()) return null;

        $v = $resp->json('data');
        return [
            'status'  => $v['status'] ?? 'unknown',
            'uptime'  => $v['uptime'] ?? 0,
            'cpu'     => round(($v['cpu'] ?? 0) * 100, 1),
            'memory'  => [
                'used' => (int) round(($v['mem'] ?? 0) / 1048576),
                'max'  => (int) round(($v['maxmem'] ?? 0) / 1048576),
            ],
            'disk'    => [
                'used' => (int) round(($v['disk'] ?? 0) / 1073741824),
                'max'  => (int) round(($v['maxdisk'] ?? 0) / 1073741824),
            ],
            'network' => [
                'in'  => (int) round(($v['netin'] ?? 0) / 1048576),
                'out' => (int) round(($v['netout'] ?? 0) / 1048576),
            ],
        ];
    }
}
