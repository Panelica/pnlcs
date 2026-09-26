<?php

namespace Modules\Servers\Proxmox;

/**
 * What the Test button finds out about a Proxmox server, and how to fix it.
 *
 * Reaching /version only proves the address and the key are right. A token
 * with "Privilege Separation" ticked and no permissions of its own passes that
 * and then cannot create a single guest - that is exactly how a token made in
 * the Proxmox web UI starts out. So this reads the token's actual rights where
 * PNLCS will use them and says which are missing, with the commands that
 * grant them.
 */
final class ProxmoxSetup
{
    /** Rights on the guests (the pool, or /vms without one). */
    public const GUEST_PRIVILEGES = [
        'VM.Allocate', 'VM.Clone', 'VM.Audit', 'VM.PowerMgmt', 'VM.Console',
        'VM.Config.CPU', 'VM.Config.Memory', 'VM.Config.Disk', 'VM.Config.Network',
        'VM.Config.Options', 'VM.Config.Cloudinit', 'VM.Config.CDROM', 'VM.Config.HWType',
        'VM.Snapshot', 'VM.Snapshot.Rollback', 'VM.Backup',
        'VM.GuestAgent.Audit', 'VM.GuestAgent.Unrestricted',
    ];

    /** Without these nothing can be sold; the rest only switch features off. */
    public const ESSENTIAL = [
        'VM.Allocate', 'VM.Audit', 'VM.PowerMgmt', 'VM.Config.CPU', 'VM.Config.Memory',
        'VM.Config.Disk', 'VM.Config.Network', 'VM.Config.Options',
    ];

    /** What each non-essential right is for, for the warning. */
    public const PURPOSE = [
        'VM.Clone' => 'clone KVM templates',
        'VM.Console' => 'open the console',
        'VM.Config.Cloudinit' => 'set passwords and addresses with cloud-init',
        'VM.Config.CDROM' => 'boot an installer ISO',
        'VM.Config.HWType' => 'set the hardware type of an ISO-installed VM',
        'VM.Snapshot' => 'take snapshots',
        'VM.Snapshot.Rollback' => 'roll back snapshots',
        'VM.Backup' => 'make backups',
        'VM.GuestAgent.Audit' => 'read the guest\'s IP addresses',
        'VM.GuestAgent.Unrestricted' => 'change a password without a reboot',
    ];

    /** Rights that are more than billing needs; a leaked key with them owns the host. */
    public const TOO_MUCH = ['Sys.Modify', 'Permissions.Modify', 'User.Modify', 'Sys.PowerMgmt', 'Sys.Console'];

    /**
     * Cloud-init vendor data every KVM guest gets.
     *
     * Debian, Ubuntu and RHEL cloud images switch SSH password logins off and
     * refuse root even with the right password; measured on a Debian 12 image
     * cloned by this module: "Permission denied (publickey)" with the password
     * Proxmox had set. They also lack the QEMU guest agent, so Proxmox cannot
     * report the guest's address or change its password while it runs. The
     * drop-in is named 01- so it is read before the image's own settings.
     */
    public const VENDOR_SNIPPET = <<<'YAML'
#cloud-config
# PNLCS vendor data: lets customers sign in with the password PNLCS gives them,
# and installs the QEMU guest agent so PNLCS can read addresses and reset passwords.
ssh_pwauth: true
write_files:
  - path: /etc/ssh/sshd_config.d/01-pnlcs.conf
    permissions: "0644"
    content: |
      PasswordAuthentication yes
      PermitRootLogin yes
packages:
  - qemu-guest-agent
runcmd:
  - [sh, -c, "systemctl enable --now qemu-guest-agent || true"]
  - [sh, -c, "systemctl restart ssh 2>/dev/null || systemctl restart sshd"]
YAML;

    /**
     * Official cloud images a KVM template can be made from, checked to exist
     * on 2026-09-26. The Ubuntu 24.04 one was built into a template with
     * exactly the commands templateRecipes() prints, cloned by this module and
     * signed into with the password PNLCS set.
     */
    public const CLOUD_IMAGES = [
        'debian-12' => ['Debian 12', 'https://cloud.debian.org/images/cloud/bookworm/latest/debian-12-genericcloud-amd64.qcow2'],
        'debian-13' => ['Debian 13', 'https://cloud.debian.org/images/cloud/trixie/latest/debian-13-genericcloud-amd64.qcow2'],
        'ubuntu-24.04' => ['Ubuntu 24.04', 'https://cloud-images.ubuntu.com/noble/current/noble-server-cloudimg-amd64.img'],
        'ubuntu-22.04' => ['Ubuntu 22.04', 'https://cloud-images.ubuntu.com/jammy/current/jammy-server-cloudimg-amd64.img'],
        'almalinux-9' => ['AlmaLinux 9', 'https://repo.almalinux.org/almalinux/9/cloud/x86_64/images/AlmaLinux-9-GenericCloud-latest.x86_64.qcow2'],
        'rocky-9' => ['Rocky Linux 9', 'https://dl.rockylinux.org/pub/rocky/9/images/x86_64/Rocky-9-GenericCloud.latest.x86_64.qcow2'],
    ];

    public function __construct(private readonly ProxmoxClient $api) {}

    /**
     * Commands that turn each official cloud image into a template PNLCS can
     * sell: downloaded once, imported as the boot disk, given a cloud-init
     * drive and put into the pool. The id is the next free one, so running a
     * block twice cannot overwrite a machine.
     */
    public function templateRecipes(string $pool = '', string $storage = 'local-lvm', string $bridge = 'vmbr0'): string
    {
        $lines = ['# '.__('proxmox.commands.recipes')];
        foreach (self::CLOUD_IMAGES as $slug => [$name, $url]) {
            $file = '/var/lib/vz/template/iso/'.basename($url);
            $lines[] = '';
            $lines[] = "# {$name}";
            $lines[] = "ID=\$(pvesh get /cluster/nextid); IMG={$file}";
            $lines[] = "wget -q -O \$IMG {$url}";
            $lines[] = "qm create \$ID --name {$slug}-cloud --ostype l26 --memory 1024 --cores 1 --agent enabled=1 --serial0 socket --vga serial0 --net0 virtio,bridge={$bridge} --scsihw virtio-scsi-single".($pool !== '' ? " --pool {$pool}" : '');
            $lines[] = "qm set \$ID --scsi0 {$storage}:0,import-from=\$IMG,iothread=1,discard=on --ide2 {$storage}:cloudinit --boot order=scsi0";
            $lines[] = 'qm template $ID && echo "'.$name.' is template $ID"';
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{ok: bool, version: ?string, identity: string, checks: array<int, array{level: string, text: string}>, commands: ?string}
     */
    public function check(): array
    {
        $server = $this->api->server();
        $checks = [];
        $add = function (string $level, string $key, array $replace = []) use (&$checks) {
            $checks[] = ['level' => $level, 'text' => __("proxmox.check.{$key}", $replace)];
        };
        $identity = $this->api->identity();

        // A secret with nothing to say whose it is: the form's commonest mistake.
        $secret = trim((string) $server->access_hash);
        if (! $this->api->usesToken() && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $secret)) {
            $add('fail', 'token_id_missing', ['user' => (string) $server->username]);
            $add('info', 'token_shape');

            return $this->answer(false, null, $identity, $checks, null);
        }

        $version = $this->api->get('version');
        if (! $version->ok) {
            $add('fail', $version->status === 401 ? 'auth_refused' : 'unreachable', ['error' => $version->error]);
            if ($version->status === 401 && $this->api->usesToken()) {
                $add('info', 'token_shape');
            }

            return $this->answer(false, null, $identity, $checks, null);
        }
        $release = (string) ($version->data['version'] ?? '?');
        $add('ok', 'version', ['version' => $release, 'identity' => $identity]);
        if (version_compare($release, '7.0', '<')) {
            $add('warn', 'old_release', ['version' => $release]);
        }

        $pool = (string) $server->setting('pool', '');
        $guestPath = $pool !== '' ? "/pool/{$pool}" : '/vms';
        $perms = $this->permissions();

        if ($perms === null) {
            $add('warn', 'permissions_unreadable');
        } elseif ($perms === []) {
            $add('fail', 'no_permissions', ['identity' => $identity]);

            return $this->answer(false, $release, $identity, $checks, $this->commands(ProxmoxModule::configuredNode($server), $pool));
        }

        $failed = false;
        $rightsProblem = $pool === '';

        // Nodes
        $nodes = collect($this->api->get('nodes')->list());
        $wanted = ProxmoxModule::configuredNode($server);
        if ($nodes->isEmpty()) {
            $add('fail', 'no_nodes');
            $failed = true;
        } elseif ($wanted !== '' && ! $nodes->contains('node', $wanted)) {
            $add('fail', 'node_missing', ['node' => $wanted, 'nodes' => $nodes->pluck('node')->implode(', ')]);
            $failed = true;
        } else {
            $online = $nodes->where('status', 'online')->pluck('node');
            $add($online->isEmpty() ? 'fail' : 'ok', 'nodes', ['nodes' => $online->implode(', ') ?: '-', 'count' => $nodes->count()]);
            $failed = $failed || $online->isEmpty();
        }

        // Pool
        if ($pool !== '') {
            $pools = $this->api->get('pools');
            if ($pools->ok && ! collect($pools->list())->contains('poolid', $pool)) {
                $add('fail', 'pool_missing', ['pool' => $pool]);
                $failed = true;
            } else {
                $add('ok', 'pool', ['pool' => $pool]);
            }
        } else {
            $add('warn', 'no_pool');
        }

        // Rights on the guests
        if ($perms !== null) {
            $have = $this->rightsAt($perms, $guestPath);
            $missingEssential = array_values(array_diff(self::ESSENTIAL, $have));
            $missingOther = array_values(array_diff(array_diff(self::GUEST_PRIVILEGES, self::ESSENTIAL), $have));

            if ($missingEssential !== []) {
                $add('fail', 'missing_essential', ['path' => $guestPath, 'privs' => implode(', ', $missingEssential)]);
                $failed = $rightsProblem = true;
            } else {
                $add('ok', 'guest_rights', ['path' => $guestPath]);
            }
            foreach ($missingOther as $priv) {
                $add('warn', 'missing_optional', ['priv' => $priv, 'purpose' => self::PURPOSE[$priv] ?? $priv]);
                $rightsProblem = true;
            }

            $excess = array_values(array_intersect(self::TOO_MUCH, $this->rightsAt($perms, '/')));
            if ($excess !== []) {
                $add('warn', 'too_much', ['privs' => implode(', ', $excess)]);
                $rightsProblem = true;
            }
        }

        // Storage and templates
        $node = $wanted !== '' ? $wanted : (string) ($nodes->firstWhere('status', 'online')['node'] ?? '');
        if ($node !== '') {
            $storages = collect($this->api->get("nodes/{$node}/storage", ['enabled' => 1])->list());
            $disks = $storages->filter(fn ($s) => str_contains((string) ($s['content'] ?? ''), 'images') || str_contains((string) ($s['content'] ?? ''), 'rootdir'));
            if ($disks->isEmpty()) {
                $add('fail', 'no_storage', ['node' => $node]);
                $failed = true;
            } else {
                $usable = $perms === null ? $disks : $disks->filter(
                    fn ($s) => in_array('Datastore.AllocateSpace', $this->rightsAt($perms, '/storage/'.$s['storage']), true)
                );
                if ($usable->isEmpty()) {
                    $add('fail', 'storage_rights', ['storages' => $disks->pluck('storage')->implode(', ')]);
                    $failed = $rightsProblem = true;
                } else {
                    $add('ok', 'storage', ['storages' => $usable->pluck('storage')->implode(', ')]);
                }
            }
        }

        // Since Proxmox 8 a guest can only be put on a bridge with SDN.Use on it.
        if ($perms !== null && version_compare($release, '8.0', '>=')) {
            $sdn = collect($perms)->contains(fn ($privs, $path) => array_key_exists('SDN.Use', $privs)
                && ($path === '/' || $path === '/sdn' || str_starts_with((string) $path, '/sdn/zones')));
            if (! $sdn) {
                $add('fail', 'sdn_use', ['priv' => 'SDN.Use']);
                $failed = $rightsProblem = true;
            }
        }

        $templates = collect($this->api->get('cluster/resources', ['type' => 'vm'])->list())->where('template', 1);
        $add($templates->isEmpty() ? 'warn' : 'ok', $templates->isEmpty() ? 'no_templates' : 'templates', ['count' => $templates->count()]);

        // The vendor snippet that makes cloud images usable with a password.
        $vendor = (string) $server->setting('ci_vendor', '');
        $snippetCommands = null;
        if ($vendor === '') {
            $add('warn', 'no_vendor');
            $snippetCommands = $this->snippetCommands($storages ?? collect());
        } elseif ($node !== '') {
            [$vendorStorage] = explode(':', $vendor, 2);
            $listed = $this->api->get("nodes/{$node}/storage/{$vendorStorage}/content", ['content' => 'snippets']);
            if ($listed->ok && ! collect($listed->list())->contains('volid', $vendor)) {
                $add('fail', 'vendor_missing', ['volume' => $vendor, 'node' => $node]);
                $failed = true;
                $snippetCommands = $this->snippetCommands($storages ?? collect());
            } elseif (! $listed->ok) {
                $add('info', 'vendor_unverified', ['volume' => $vendor, 'storage' => $vendorStorage]);
            } else {
                $add('ok', 'vendor', ['volume' => $vendor]);
            }
        }

        // Backups
        $backupStorage = (string) $server->setting('backup_storage', '');
        if ($backupStorage !== '' && isset($storages)) {
            $store = $storages->firstWhere('storage', $backupStorage);
            if (! $store || ! in_array('backup', explode(',', (string) ($store['content'] ?? '')), true)) {
                $add('fail', 'backup_storage_missing', ['storage' => $backupStorage, 'node' => $node]);
                $failed = true;
            } elseif ($perms !== null && ! in_array('Datastore.AllocateSpace', $this->rightsAt($perms, '/storage/'.$backupStorage), true)) {
                $add('fail', 'backup_storage_rights', ['storage' => $backupStorage]);
                $failed = $rightsProblem = true;
            } else {
                $add('ok', 'backup_storage', ['storage' => $backupStorage]);
            }
        } elseif ($backupStorage === '') {
            $add('info', 'no_backup_storage');
        }

        // VM id range and addresses
        $min = (int) $server->setting('vmid_min', 0);
        if ($min > 0) {
            $add('ok', 'vmid_range', ['min' => $min, 'max' => (int) $server->setting('vmid_max', $min + 9999)]);
        } else {
            $add('info', 'no_vmid_range');
        }
        if (trim((string) $server->setting('ipv4_pool', '')) !== '') {
            try {
                Ipv4Pool::ranges($server->setting('ipv4_pool'));
                $add('ok', 'ipv4_pool', ['free' => Ipv4Pool::freeCount($server)]);
            } catch (\InvalidArgumentException $e) {
                $add('fail', 'ipv4_pool_invalid', ['line' => $e->getMessage()]);
                $failed = true;
            }
        }

        // The whole setup when rights are the problem; only the pool line when
        // all that is missing is a template to sell.
        $commands = null;
        if ($rightsProblem) {
            $commands = $this->commands($node, $pool);
        } elseif ($templates->isEmpty() && $pool !== '') {
            $commands = '# '.__('proxmox.commands.templates')."\npveum pool modify {$pool} --vms <template-id>";
        }
        if ($snippetCommands !== null) {
            $commands = trim(($commands ?? '')."\n".$snippetCommands);
        }

        $answer = $this->answer(! $failed, $release, $identity, $checks, $commands);
        $answer['recipes'] = $this->templateRecipes($pool, (string) ((isset($disks) ? ($disks->first()['storage'] ?? null) : null) ?? 'local-lvm'));

        return $answer;
    }

    /**
     * The caller's own rights: path => [privilege => propagates]; null when
     * they cannot be read.
     *
     * @return array<string, array<string, bool>>|null
     */
    private function permissions(): ?array
    {
        $answer = $this->api->get('access/permissions');
        if (! $answer->ok) {
            return null;
        }

        return collect(is_array($answer->data) ? $answer->data : [])
            ->map(fn ($privs) => collect(is_array($privs) ? $privs : [])->map(fn ($propagate) => (bool) $propagate)->all())
            ->filter(fn ($privs) => $privs !== [])
            ->all();
    }

    /**
     * Privileges that apply at a path.
     *
     * A right granted at "/pool" or "/" reaches "/pool/pnlcs" too, unless it
     * was granted with propagation switched off.
     */
    private function rightsAt(array $perms, string $path): array
    {
        $have = [];
        foreach ($perms as $aclPath => $privs) {
            $aclPath = rtrim((string) $aclPath, '/') ?: '/';
            if ($aclPath === $path) {
                $have = [...$have, ...array_keys($privs)];
            } elseif ($aclPath === '/' || str_starts_with($path, $aclPath.'/')) {
                $have = [...$have, ...array_keys(array_filter($privs))];
            }
        }

        return array_values(array_unique($have));
    }

    /** The pveum commands that set up a user with exactly what PNLCS needs. */
    public function commands(?string $node = null, ?string $pool = null): string
    {
        $pool = $pool ?: 'pnlcs';
        $storage = 'local-lvm';
        $privs = implode(',', [...self::GUEST_PRIVILEGES, 'Datastore.AllocateSpace', 'Datastore.Audit', 'Pool.Audit', 'SDN.Use', 'SDN.Audit']);

        return implode("\n", [
            '# '.__('proxmox.commands.role'),
            "pveum role add PNLCS --privs \"{$privs}\"",
            '# '.__('proxmox.commands.pool'),
            "pveum pool add {$pool} --comment \"Guests sold through PNLCS\"",
            '# '.__('proxmox.commands.user'),
            'pveum user add pnlcs@pve --comment "PNLCS billing"',
            'pveum user token add pnlcs@pve billing --privsep 0 --comment "PNLCS"',
            '# '.__('proxmox.commands.acl'),
            "pveum acl modify /pool/{$pool} --users pnlcs@pve --roles PNLCS",
            "pveum acl modify /storage/{$storage} --users pnlcs@pve --roles PVEDatastoreUser",
            'pveum acl modify /storage/local --users pnlcs@pve --roles PVEDatastoreUser',
            'pveum acl modify /sdn/zones/localnetwork --users pnlcs@pve --roles PVESDNUser',
            'pveum acl modify /nodes --users pnlcs@pve --roles PVEAuditor',
            '# '.__('proxmox.commands.templates'),
            "pveum pool modify {$pool} --vms <template-id>",
        ]);
    }

    /**
     * Commands that put the vendor snippet on a node, keeping the content
     * types the "local" storage already has.
     */
    private function snippetCommands(\Illuminate\Support\Collection $storages): string
    {
        $local = $storages->firstWhere('storage', 'local');
        $content = array_values(array_filter(explode(',', (string) ($local['content'] ?? 'iso,vztmpl,backup'))));
        $lines = ['# '.__('proxmox.commands.snippet')];
        if (! in_array('snippets', $content, true)) {
            $lines[] = 'pvesm set local --content '.implode(',', [...$content, 'snippets']);
        }
        $lines[] = 'mkdir -p /var/lib/vz/snippets';
        $lines[] = "cat > /var/lib/vz/snippets/pnlcs-vendor.yaml <<'EOF'";
        $lines[] = self::VENDOR_SNIPPET;
        $lines[] = 'EOF';
        $lines[] = '# '.__('proxmox.commands.snippet_setting', ['volume' => 'local:snippets/pnlcs-vendor.yaml']);

        return implode("\n", $lines);
    }

    private function answer(bool $ok, ?string $version, string $identity, array $checks, ?string $commands): array
    {
        return compact('ok', 'version', 'identity', 'checks', 'commands');
    }
}
