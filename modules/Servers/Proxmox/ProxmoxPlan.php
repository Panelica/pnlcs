<?php

namespace Modules\Servers\Proxmox;

use App\Models\Service;

/**
 * What a Proxmox product sells, read from the product and the options the
 * customer chose.
 *
 * Product settings live under pve_* keys. The keys the first version of the
 * module read (type, cores, memory, disk, template_id, os_template, storage,
 * bridge, node) are still honoured, so products set up by hand keep working.
 *
 * A configurable option overrides the product when its name starts with one of
 * the keys below, the way WHMCS modules do it: an option "memory|RAM" whose
 * choices are "2048|2 GB" and "4096|4 GB" sells the memory the customer picks.
 */
final class ProxmoxPlan
{
    /** Configurable option keys this module understands. */
    public const OPTION_KEYS = ['cores', 'memory', 'disk', 'swap', 'os', 'bandwidth', 'rate', 'snapshots', 'backups'];

    public function __construct(public readonly array $values) {}

    public static function forService(Service $service, ?array $productConfig = null): self
    {
        $cfg = $productConfig ?? self::productConfig($service);
        $pick = fn (string $key, array $legacy, mixed $default) => self::first($cfg, ["pve_{$key}", ...$legacy], $default);

        $type = strtolower((string) $pick('type', ['type'], 'qemu')) === 'lxc' ? 'lxc' : 'qemu';

        $values = [
            'type' => $type,
            'node' => (string) $pick('node', ['node'], ''),
            'template' => (string) $pick('template', ['template_id'], ''),
            'ostemplate' => (string) $pick('ostemplate', ['os_template'], ''),
            'iso' => (string) $pick('iso', [], ''),
            'storage' => (string) $pick('storage', ['storage'], 'local-lvm'),
            'bridge' => (string) $pick('bridge', ['bridge'], 'vmbr0'),
            'vlan' => (int) $pick('vlan', [], 0),
            'rate' => (float) $pick('rate', [], 0),
            'firewall' => (bool) $pick('firewall', [], false),
            'cores' => max(1, (int) $pick('cores', ['cores'], 1)),
            'sockets' => max(1, (int) $pick('sockets', [], 1)),
            'cpulimit' => max(0, (float) $pick('cpulimit', [], 0)),
            'memory' => max(64, (int) $pick('memory', ['memory'], 1024)),
            'swap' => max(0, (int) $pick('swap', [], 512)),
            'disk' => max(1, (int) $pick('disk', ['disk'], 20)),
            'ipv4' => (string) $pick('ipv4', [], 'dhcp') === 'pool' ? 'pool' : 'dhcp',
            'ipv6' => (string) $pick('ipv6', [], 'none'),
            'nesting' => (bool) $pick('nesting', [], false),
            'protection' => (bool) $pick('protection', [], true),
            'ciuser' => (string) $pick('ciuser', [], 'root'),
            'ciupgrade' => (bool) $pick('ciupgrade', [], false),
            'ostype' => (string) $pick('ostype', ['os_type'], 'l26'),
            'bandwidth' => max(0, (int) $pick('bandwidth', [], 0)),
            'snapshots' => max(0, (int) $pick('snapshots', [], 0)),
            'backups' => max(0, (int) $pick('backups', [], 0)),
            'os_choices' => self::osChoices($pick('os_choices', [], [])),
            'nameserver' => (string) $pick('nameserver', [], ''),
        ];

        foreach (self::chosenOptions($service) as $key => $value) {
            $values = self::applyOption($values, $key, $value);
        }

        return new self($values);
    }

    public function __get(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }

    public function isLxc(): bool
    {
        return $this->values['type'] === 'lxc';
    }

    /** The template this plan installs: a KVM template id or an LXC volume id. */
    public function image(): string
    {
        return $this->isLxc() ? $this->values['ostemplate'] : $this->values['template'];
    }

    /**
     * The images a customer may reinstall with, including the plan's own.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function reinstallChoices(): array
    {
        $choices = collect($this->values['os_choices']);
        if ($this->image() !== '' && ! $choices->contains('id', $this->image())) {
            $choices->prepend(['id' => $this->image(), 'name' => self::imageName($this->image())]);
        }

        return $choices->values()->all();
    }

    public static function imageName(string $image): string
    {
        if (str_contains($image, ':')) {
            // local:vztmpl/debian-12-standard_12.7-1_amd64.tar.zst -> debian-12-standard 12.7-1
            $file = basename($image);
            $file = preg_replace('/\.(tar\.(gz|xz|zst)|tgz)$/', '', $file) ?? $file;

            return str_replace('_', ' ', preg_replace('/_amd64$/', '', $file) ?? $file);
        }

        return 'Template #'.$image;
    }

    public static function productConfig(Service $service): array
    {
        $raw = $service->product?->config_options;
        $cfg = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($cfg) ? $cfg : [];
    }

    private static function first(array $cfg, array $keys, mixed $default): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $cfg) && $cfg[$key] !== '' && $cfg[$key] !== null) {
                return $cfg[$key];
            }
        }

        return $default;
    }

    /** @return array<int, array{id: string, name: string}> */
    private static function osChoices(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: array_filter(array_map('trim', explode("\n", $raw)));
        }

        return collect(is_array($raw) ? $raw : [])
            ->map(function ($item) {
                if (is_array($item)) {
                    $id = trim((string) ($item['id'] ?? ''));

                    return $id === '' ? null : ['id' => $id, 'name' => trim((string) ($item['name'] ?? '')) ?: self::imageName($id)];
                }
                [$id, $name] = array_pad(array_map('trim', explode('|', (string) $item, 2)), 2, '');

                return $id === '' ? null : ['id' => $id, 'name' => $name !== '' ? $name : self::imageName($id)];
            })
            ->filter()->unique('id')->values()->all();
    }

    /**
     * The customer's configurable option choices, keyed by the module key the
     * option name starts with.
     *
     * @return array<string, string|int>
     */
    private static function chosenOptions(Service $service): array
    {
        if (! $service->exists) {
            return [];
        }

        $chosen = [];
        foreach ($service->configOptions()->with('option', 'sub')->get() as $row) {
            $key = strtolower(trim(explode('|', (string) $row->option?->option_name, 2)[0]));
            if (! in_array($key, self::OPTION_KEYS, true)) {
                continue;
            }

            if ($row->option?->isQuantity()) {
                $chosen[$key] = (int) $row->qty;
            } elseif ($row->sub) {
                $chosen[$key] = trim(explode('|', (string) $row->sub->option_name, 2)[0]);
            }
        }

        return $chosen;
    }

    private static function applyOption(array $values, string $key, string|int $value): array
    {
        if ($key === 'os') {
            $value = (string) $value;
            if ($value !== '') {
                $values[$values['type'] === 'lxc' ? 'ostemplate' : 'template'] = $value;
            }

            return $values;
        }

        if (is_numeric($value) && (float) $value > 0) {
            $values[$key] = $key === 'rate' ? (float) $value : (int) $value;
        }

        return $values;
    }
}
