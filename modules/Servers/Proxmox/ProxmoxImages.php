<?php

namespace Modules\Servers\Proxmox;

use App\Models\Server;
use Illuminate\Support\Facades\Cache;

/**
 * Ready-made operating systems for a Proxmox server, installed from the
 * admin panel instead of the host's shell.
 *
 * KVM: the official cloud image is downloaded into a storage that holds
 * "import" content, imported as the boot disk of a new machine with a
 * cloud-init drive, and turned into a template in the server's pool - the
 * same steps the Test button prints as shell commands, sent through the API.
 *
 * LXC: the container template is fetched from Proxmox's own appliance
 * catalogue into a storage that holds container templates.
 *
 * A download takes minutes, so an install is a job kept in the server's
 * settings (image_jobs) and moved on by the admin page while it is open and
 * by the scheduler every minute when it is not.
 */
final class ProxmoxImages
{
    /** Tag on every template this class made. */
    public const TAG = 'pnlcs-image';

    /** Rights the library needs beyond selling: fetching files onto the node. */
    public const RIGHTS = ['Datastore.AllocateTemplate', 'Sys.AccessNetwork'];

    public function __construct(private readonly ProxmoxClient $api, private readonly Server $server) {}

    public static function for(Server $server): self
    {
        return new self((new ProxmoxModule)->client($server), $server);
    }

    /** The name a template made from a cloud image carries: "debian-12-cloud". */
    public static function templateName(string $slug): string
    {
        return "{$slug}-cloud";
    }

    /** The friendly name of a template this library (or the printed recipe) made, or null. */
    public static function friendlyName(string $templateName): ?string
    {
        foreach (ProxmoxSetup::CLOUD_IMAGES as $slug => [$name]) {
            if ($templateName === self::templateName($slug)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Everything the library page shows, after moving any running job on.
     *
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $this->advance();

        $node = $this->node();
        if ($node === '') {
            return ['ok' => false, 'error' => __('proxmox.error.no_nodes_visible')];
        }

        $storages = collect($this->api->get("nodes/{$node}/storage", ['enabled' => 1])->list());
        $holding = fn (string $type) => $storages
            ->filter(fn ($s) => in_array($type, explode(',', (string) ($s['content'] ?? '')), true))
            ->pluck('storage')->values()->all();

        $importStores = $holding('import');
        $templateStores = $holding('vztmpl');
        $diskStores = $holding('images');
        $jobs = $this->jobs();

        $templates = collect($this->api->get('cluster/resources', ['type' => 'vm'])->list())
            ->where('template', 1)->keyBy(fn ($t) => (string) ($t['name'] ?? ''));

        $kvm = collect(ProxmoxSetup::CLOUD_IMAGES)->map(function ($image, $slug) use ($templates, $jobs) {
            [$name, $url] = $image;
            $made = $templates->get(self::templateName($slug));
            $job = $jobs["kvm:{$slug}"] ?? null;
            // The cluster's list can lag a moment behind a template just made.
            $vmid = $made ? (int) $made['vmid'] : ((($job['step'] ?? '') === 'done' && ! empty($job['vmid'])) ? (int) $job['vmid'] : null);

            return [
                'id' => $slug,
                'name' => $name,
                'source' => parse_url($url, PHP_URL_HOST),
                'template' => $vmid,
                'job' => $jobs["kvm:{$slug}"] ?? null,
            ];
        })->values()->all();

        $have = collect($templateStores)->flatMap(fn ($store) => collect(
            $this->api->get("nodes/{$node}/storage/{$store}/content", ['content' => 'vztmpl'])->list()
        )->pluck('volid'))->map(fn ($volid) => basename((string) $volid))->all();

        $lxc = collect($this->api->get("nodes/{$node}/aplinfo")->list())
            ->filter(fn ($t) => ($t['section'] ?? '') === 'system' && str_contains((string) ($t['template'] ?? ''), '_amd64'))
            ->sortBy('template')
            ->map(fn ($t) => [
                'id' => (string) $t['template'],
                'name' => ProxmoxPlan::imageName('x:vztmpl/'.$t['template']),
                'description' => (string) ($t['headline'] ?? ''),
                'installed' => in_array($t['template'], $have, true),
                'job' => $jobs['lxc:'.$t['template']] ?? null,
            ])->values()->all();

        return [
            'ok' => true,
            'node' => $node,
            'import_storages' => $importStores,
            'template_storages' => $templateStores,
            'disk_storages' => $diskStores,
            'missing' => $this->missingRights($node, $importStores[0] ?? ($templateStores[0] ?? '')),
            'kvm' => $kvm,
            'lxc' => $lxc,
            'busy' => collect($jobs)->contains(fn ($j) => ($j['step'] ?? '') !== 'done' && ($j['step'] ?? '') !== 'failed'),
        ];
    }

    /**
     * Start installing a cloud image (kind "kvm") or a container template
     * (kind "lxc"). Returns at once; the job carries on by itself.
     */
    public function install(string $kind, string $id, string $diskStorage = ''): ProxmoxResult
    {
        $node = $this->node();
        if ($node === '') {
            return ProxmoxResult::failed(__('proxmox.error.no_nodes_visible'));
        }
        $storages = collect($this->api->get("nodes/{$node}/storage", ['enabled' => 1])->list());
        $holding = fn (string $type) => $storages
            ->filter(fn ($s) => in_array($type, explode(',', (string) ($s['content'] ?? '')), true))
            ->pluck('storage')->values();

        $key = "{$kind}:{$id}";
        $current = $this->jobs()[$key] ?? null;
        if ($current && ! in_array($current['step'] ?? '', ['done', 'failed'], true)) {
            return ProxmoxResult::failed(__('proxmox.images.already_running'), 409);
        }

        if ($kind === 'kvm') {
            if (! isset(ProxmoxSetup::CLOUD_IMAGES[$id])) {
                return ProxmoxResult::failed(__('proxmox.images.unknown'), 422);
            }
            $import = (string) ($holding('import')->first() ?? '');
            if ($import === '') {
                return ProxmoxResult::failed(__('proxmox.images.no_import_storage', ['node' => $node]), 422);
            }
            $disk = $diskStorage !== '' && $holding('images')->contains($diskStorage) ? $diskStorage : (string) ($holding('images')->first() ?? '');
            if ($disk === '') {
                return ProxmoxResult::failed(__('proxmox.check.no_storage', ['node' => $node]), 422);
            }
            $file = "{$id}.qcow2";
            $already = collect($this->api->get("nodes/{$node}/storage/{$import}/content", ['content' => 'import'])->list())
                ->contains('volid', "{$import}:import/{$file}");

            $job = ['kind' => 'kvm', 'id' => $id, 'node' => $node, 'import' => $import, 'disk' => $disk, 'file' => $file, 'started' => now()->getTimestamp()];
            if ($already) {
                $job['step'] = 'downloaded';
            } else {
                $sent = $this->api->post("nodes/{$node}/storage/{$import}/download-url", [
                    'content' => 'import', 'filename' => $file, 'url' => ProxmoxSetup::CLOUD_IMAGES[$id][1],
                ]);
                if (! $sent->ok) {
                    return $sent;
                }
                $job += ['step' => 'downloading', 'upid' => $sent->data];
            }
        } elseif ($kind === 'lxc') {
            $store = (string) ($holding('vztmpl')->first() ?? '');
            if ($store === '') {
                return ProxmoxResult::failed(__('proxmox.images.no_template_storage', ['node' => $node]), 422);
            }
            $known = collect($this->api->get("nodes/{$node}/aplinfo")->list())->contains('template', $id);
            if (! $known) {
                return ProxmoxResult::failed(__('proxmox.images.unknown'), 422);
            }
            $sent = $this->api->post("nodes/{$node}/aplinfo", ['storage' => $store, 'template' => $id]);
            if (! $sent->ok) {
                return $sent;
            }
            $job = ['kind' => 'lxc', 'id' => $id, 'node' => $node, 'storage' => $store, 'step' => 'downloading', 'upid' => $sent->data, 'started' => now()->getTimestamp()];
        } else {
            return ProxmoxResult::failed(__('proxmox.images.unknown'), 422);
        }

        $this->saveJob($key, $job);
        $this->advance();

        return new ProxmoxResult(true, 200, $job);
    }

    /** Move every running job one step on, as far as it can go right now. */
    public function advance(): void
    {
        foreach ($this->jobs() as $key => $job) {
            if (in_array($job['step'] ?? '', ['done', 'failed'], true)) {
                continue;
            }
            Cache::lock("pve-image-{$this->server->id}-{$key}", 120)->get(function () use ($key) {
                // A quick task (making the template) is often done by the
                // time it is asked about, so keep going while steps finish.
                for ($i = 0; $i < 4; $i++) {
                    $job = $this->jobs()[$key] ?? null;
                    if (! $job || ! $this->step($key, $job)) {
                        break;
                    }
                }
            });
        }
    }

    /** One step of a job; true when it moved and may move again at once. */
    private function step(string $key, array $job): bool
    {
        $node = (string) $job['node'];

        // A task still running: note its progress and come back later.
        if (! empty($job['upid'])) {
            $state = $this->api->taskState($node, $job['upid']);
            if (! $state->ok && $state->status === 504) {
                $this->saveJob($key, ['progress' => mb_substr((string) $state->error, 0, 160)] + $job);

                return false;
            }
            if (! $state->ok) {
                $this->saveJob($key, ['step' => 'failed', 'error' => $state->error, 'upid' => null] + $job);

                return false;
            }
            $job['upid'] = null;
            $job['progress'] = null;
            $job['step'] = match ($job['step']) {
                'downloading' => $job['kind'] === 'lxc' ? 'done' : 'downloaded',
                'creating' => 'created',
                'templating' => 'done',
                default => $job['step'],
            };
        }

        if ($job['step'] === 'downloaded') {
            $vmid = $this->templateVmid();
            if ($vmid < 100) {
                $this->saveJob($key, ['step' => 'failed', 'error' => __('proxmox.error.no_free_vmid')] + $job);

                return false;
            }
            $body = [
                'vmid' => $vmid,
                'name' => self::templateName($job['id']),
                'ostype' => 'l26',
                'memory' => 1024,
                'cores' => 1,
                'agent' => 'enabled=1',
                'serial0' => 'socket',
                'vga' => 'serial0',
                'scsihw' => 'virtio-scsi-single',
                'net0' => 'virtio,bridge='.$this->bridge($node),
                'scsi0' => "{$job['disk']}:0,import-from={$job['import']}:import/{$job['file']},iothread=1,discard=on",
                'ide2' => "{$job['disk']}:cloudinit",
                'boot' => 'order=scsi0',
                'tags' => self::TAG,
                'description' => 'Cloud image template made by PNLCS from '.ProxmoxSetup::CLOUD_IMAGES[$job['id']][1],
            ];
            if (($pool = (string) $this->server->setting('pool', '')) !== '') {
                $body['pool'] = $pool;
            }
            $sent = $this->api->post("nodes/{$node}/qemu", $body);
            if (! $sent->ok) {
                $this->saveJob($key, ['step' => 'failed', 'error' => $sent->error] + $job);

                return false;
            }
            $this->saveJob($key, ['step' => 'creating', 'upid' => $sent->data, 'vmid' => $vmid] + $job);

            return true;
        }

        if ($job['step'] === 'created') {
            $sent = $this->api->post("nodes/{$node}/qemu/{$job['vmid']}/template");
            if (! $sent->ok) {
                $this->saveJob($key, ['step' => 'failed', 'error' => $sent->error] + $job);

                return false;
            }
            $this->saveJob($key, ['step' => 'templating', 'upid' => $sent->data] + $job);

            return true;
        }

        $this->saveJob($key, $job);

        return $job['step'] !== 'done';
    }

    /**
     * Rights the library needs that the token lacks, with where they are
     * needed. Empty when it may go ahead, or when the rights cannot be read.
     *
     * @return array<int, string>
     */
    public function missingRights(string $node, string $storage): array
    {
        $perms = (new ProxmoxSetup($this->api))->permissions();
        if ($perms === null) {
            return [];
        }
        $missing = [];
        if ($storage !== '' && ! in_array('Datastore.AllocateTemplate', ProxmoxSetup::rightsAt($perms, "/storage/{$storage}"), true)) {
            $missing[] = "Datastore.AllocateTemplate (/storage/{$storage})";
        }
        $network = array_intersect(['Sys.AccessNetwork'], ProxmoxSetup::rightsAt($perms, "/nodes/{$node}"))
            ?: array_intersect(['Sys.Audit', 'Sys.Modify'], ProxmoxSetup::rightsAt($perms, '/'));
        if ($network === []) {
            $missing[] = "Sys.AccessNetwork (/nodes/{$node})";
        }

        return $missing;
    }

    /** The commands that give the PNLCS user what the library needs. */
    public static function rightsCommands(string $storage = 'local'): string
    {
        return implode("\n", [
            '# '.__('proxmox.commands.images'),
            'pveum role add PNLCSImages --privs "Datastore.AllocateTemplate,Datastore.AllocateSpace,Datastore.Audit,Sys.AccessNetwork"',
            "pveum acl modify /storage/{$storage} --users pnlcs@pve --roles PNLCSImages",
            'pveum acl modify /nodes --users pnlcs@pve --roles PNLCSImages',
        ]);
    }

    /** @return array<string, array<string, mixed>> */
    public function jobs(): array
    {
        $jobs = $this->server->fresh()?->setting('image_jobs', []);

        return is_array($jobs) ? $jobs : [];
    }

    private function saveJob(string $key, array $job): void
    {
        $server = $this->server->fresh();
        $settings = $server->settings ?? [];
        $jobs = is_array($settings['image_jobs'] ?? null) ? $settings['image_jobs'] : [];
        $jobs[$key] = $job;
        $settings['image_jobs'] = $jobs;
        $server->forceFill(['settings' => $settings])->save();
        $this->server->setRawAttributes($server->getAttributes(), true);
    }

    private function node(): string
    {
        $wanted = ProxmoxModule::configuredNode($this->server);
        $nodes = collect($this->api->get('nodes')->list());
        if ($wanted !== '' && $nodes->contains('node', $wanted)) {
            return $wanted;
        }

        return (string) ($nodes->firstWhere('status', 'online')['node'] ?? '');
    }

    /**
     * An id for a new template. With a VM id range on the server, the highest
     * free id in it, so templates stay inside PNLCS's own range and apart
     * from the customers' servers, which fill it from the bottom; otherwise
     * the cluster's next free id.
     */
    private function templateVmid(): int
    {
        $min = (int) $this->server->setting('vmid_min', 0);
        $max = (int) $this->server->setting('vmid_max', 0);
        if ($min >= 100 && $max > $min) {
            for ($id = $max, $tries = 0; $id >= $min && $tries < 50; $id--, $tries++) {
                if ($this->api->get('cluster/nextid', ['vmid' => $id])->ok) {
                    return $id;
                }
            }
        }

        return (int) ($this->api->get('cluster/nextid')->data ?? 0);
    }

    private function bridge(string $node): string
    {
        $bridge = collect($this->api->get("nodes/{$node}/network", ['type' => 'any_bridge'])->list())->pluck('iface')->sort()->first();

        return is_string($bridge) && $bridge !== '' ? $bridge : 'vmbr0';
    }
}
