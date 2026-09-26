<?php

namespace Modules\Servers\Proxmox;

/**
 * What a Proxmox cluster offers, for the product form's drop-downs: nodes,
 * disk storages, network bridges, KVM templates, container templates, ISO
 * images and resource pools - each as the token can see it.
 */
final class ProxmoxCatalog
{
    public function __construct(private readonly ProxmoxClient $api) {}

    public function all(string $node = ''): array
    {
        $nodes = collect($this->api->get('nodes')->list())->sortBy('node')->values();
        if ($nodes->isEmpty()) {
            $answer = $this->api->get('version');

            return ['ok' => false, 'error' => $answer->ok ? __('proxmox.error.no_nodes_visible') : $answer->error];
        }

        $node = $node !== '' && $nodes->contains('node', $node)
            ? $node
            : (string) ($nodes->firstWhere('status', 'online')['node'] ?? $nodes->first()['node']);

        $storages = collect($this->api->get("nodes/{$node}/storage", ['enabled' => 1])->list());
        $content = fn (string $type) => $storages
            ->filter(fn ($s) => in_array($type, explode(',', (string) ($s['content'] ?? '')), true))
            ->pluck('storage')->values();

        $volumes = function (string $type) use ($content, $node) {
            return $content($type)->flatMap(fn ($storage) => collect(
                $this->api->get("nodes/{$node}/storage/{$storage}/content", ['content' => $type])->list()
            ))->map(fn ($v) => ['id' => (string) $v['volid'], 'name' => ProxmoxPlan::imageName((string) $v['volid']), 'size' => (int) ($v['size'] ?? 0)])
                ->sortBy('name')->values()->all();
        };

        $bridges = collect($this->api->get("nodes/{$node}/network")->list())
            ->filter(fn ($n) => in_array($n['type'] ?? '', ['bridge', 'OVSBridge'], true))
            ->map(fn ($n) => ['id' => (string) $n['iface'], 'name' => $n['iface'].(! empty($n['cidr']) ? " ({$n['cidr']})" : '').(! empty($n['bridge_vlan_aware']) ? ' VLAN' : '')])
            ->sortBy('id')->values()->all();

        $templates = collect($this->api->get('cluster/resources', ['type' => 'vm'])->list())
            ->where('template', 1)
            ->map(fn ($t) => [
                'id' => (string) $t['vmid'],
                'name' => "#{$t['vmid']} ".($t['name'] ?? '').' ('.($t['type'] ?? 'qemu').', '.$t['node'].')',
                'type' => (string) ($t['type'] ?? 'qemu'),
                'node' => (string) $t['node'],
            ])
            ->sortBy('id')->values()->all();

        return [
            'ok' => true,
            'node' => $node,
            'nodes' => $nodes->map(fn ($n) => ['id' => (string) $n['node'], 'name' => $n['node'].(($n['status'] ?? '') !== 'online' ? ' (offline)' : '')])->all(),
            'storages' => $storages
                ->filter(fn ($s) => array_intersect(['images', 'rootdir'], explode(',', (string) ($s['content'] ?? ''))) !== [])
                ->map(fn ($s) => [
                    'id' => (string) $s['storage'],
                    'name' => $s['storage'].' ('.($s['type'] ?? '').(isset($s['avail']) ? ', '.round($s['avail'] / 1073741824).' GB free' : '').')',
                    'content' => (string) ($s['content'] ?? ''),
                ])->values()->all(),
            'bridges' => $bridges,
            'templates' => array_values(array_filter($templates, fn ($t) => $t['type'] === 'qemu')),
            'ostemplates' => $volumes('vztmpl'),
            'isos' => $volumes('iso'),
            'pools' => collect($this->api->get('pools')->list())->map(fn ($p) => ['id' => (string) $p['poolid'], 'name' => (string) $p['poolid']])->values()->all(),
        ];
    }
}
