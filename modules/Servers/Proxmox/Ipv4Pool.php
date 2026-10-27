<?php

namespace Modules\Servers\Proxmox;

use App\Models\Server;
use App\Models\Service;

/**
 * The public IPv4 addresses a Proxmox server hands out: one to each guest,
 * and the additional ones a guest's plan sells (the extra_ipv4 option).
 *
 * Written on the server as one range per line:
 *
 *   203.0.113.10-203.0.113.40/24 gw 203.0.113.1
 *   198.51.100.7/29 gw 198.51.100.1
 *
 * An address is taken while a service that is not terminated or cancelled
 * holds it. Nothing is stored apart from the service's own module data, so an
 * address comes back to the pool the moment its service ends.
 */
final class Ipv4Pool
{
    /**
     * @return array<int, array{start: int, end: int, prefix: int, gateway: string}>
     */
    public static function ranges(?string $text): array
    {
        $ranges = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line) ?? '');
            if ($line === '') {
                continue;
            }
            if (! preg_match('#^(\d{1,3}(?:\.\d{1,3}){3})(?:\s*-\s*(\d{1,3}(?:\.\d{1,3}){3}))?/(\d{1,2})\s+(?:gw|gateway)?\s*(\d{1,3}(?:\.\d{1,3}){3})$#i', $line, $m)) {
                throw new \InvalidArgumentException($line);
            }
            $start = ip2long($m[1]);
            $end = $m[2] !== '' ? ip2long($m[2]) : $start;
            $prefix = (int) $m[3];
            if ($start === false || $end === false || ip2long($m[4]) === false || $prefix < 1 || $prefix > 32 || $end < $start) {
                throw new \InvalidArgumentException($line);
            }
            $ranges[] = ['start' => $start, 'end' => $end, 'prefix' => $prefix, 'gateway' => $m[4]];
        }

        return $ranges;
    }

    /** Addresses held by live services on this server. */
    public static function taken(Server $server, ?int $exceptServiceId = null): array
    {
        return array_column(self::holders($server, $exceptServiceId), 'address');
    }

    /**
     * Who holds each address: the service, and whether it is the guest's own
     * address or an additional one.
     *
     * @return array<int, array{address: string, kind: string, service: Service}>
     */
    public static function holders(Server $server, ?int $exceptServiceId = null): array
    {
        $held = [];
        $services = Service::where('server_id', $server->id)
            ->whereNotIn('status', ['terminated', 'cancelled', 'fraud'])
            ->when($exceptServiceId, fn ($q) => $q->where('id', '!=', $exceptServiceId))
            ->orderBy('id')
            ->get(['id', 'client_id', 'product_id', 'domain', 'status', 'module_data']);
        foreach ($services as $service) {
            $data = is_array($service->module_data) ? $service->module_data : [];
            if (! empty($data['pve_ipv4'])) {
                $held[] = ['address' => (string) $data['pve_ipv4'], 'kind' => 'main', 'service' => $service];
            }
            foreach (self::extras($data) as $extra) {
                $held[] = ['address' => $extra['address'], 'kind' => 'extra', 'service' => $service];
            }
        }

        return $held;
    }

    /**
     * The additional addresses a service holds, in the order its network
     * cards carry them (net1, net2, ...).
     *
     * @return array<int, array{address: string, prefix: int, gateway: string}>
     */
    public static function extras(array $moduleData): array
    {
        $out = [];
        foreach ((array) ($moduleData['pve_ipv4_extra'] ?? []) as $extra) {
            if (is_array($extra) && ! empty($extra['address'])) {
                $out[] = ['address' => (string) $extra['address'], 'prefix' => (int) ($extra['prefix'] ?? 32), 'gateway' => (string) ($extra['gateway'] ?? '')];
            }
        }

        return $out;
    }

    /**
     * The first free address, as ['address' => ..., 'prefix' => ..., 'gateway' => ...].
     * The gateway itself and addresses Proxmox already uses are skipped.
     */
    public static function allocate(Server $server, Service $service, array $alsoTaken = []): ?array
    {
        $taken = array_flip([...self::taken($server, $service->id), ...$alsoTaken]);

        foreach (self::ranges($server->setting('ipv4_pool')) as $range) {
            for ($ip = $range['start']; $ip <= $range['end']; $ip++) {
                $address = long2ip($ip);
                if ($address === $range['gateway'] || isset($taken[$address])) {
                    continue;
                }

                return ['address' => $address, 'prefix' => $range['prefix'], 'gateway' => $range['gateway']];
            }
        }

        return null;
    }

    /** How many addresses are free, for the server list. */
    public static function freeCount(Server $server): int
    {
        try {
            $ranges = self::ranges($server->setting('ipv4_pool'));
        } catch (\InvalidArgumentException) {
            return 0;
        }
        $taken = array_flip(self::taken($server));
        $free = 0;
        foreach ($ranges as $range) {
            for ($ip = $range['start']; $ip <= $range['end']; $ip++) {
                $address = long2ip($ip);
                if ($address !== $range['gateway'] && ! isset($taken[$address])) {
                    $free++;
                }
            }
        }

        return $free;
    }
}
