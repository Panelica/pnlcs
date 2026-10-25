<?php

namespace Modules\Servers\Proxmox;

use App\Models\Server;
use App\Models\Service;

/**
 * How much of a Proxmox server has been sold (#303).
 *
 * What a host is using now says little about what it can still take: a node
 * with 256 GB of memory may be using 80 GB while its guests have been given
 * 240 GB between them. This reads, per node, the physical CPU threads, memory
 * and guest storage, and what the guests on it have been given (their cores,
 * memory and disks) - all of them, and those PNLCS made (tagged "pnlcs") -
 * so staff can see what is committed before they sell another server, and
 * pnlcs:proxmox-capacity can say when a node's memory or disk passes the
 * operator's line.
 *
 * Only what the token can see is counted: a token limited to a pool sees the
 * guests in its pool, which is what PNLCS sells from.
 */
final class ProxmoxCapacity
{
    public function __construct(private readonly ProxmoxClient $api, private readonly Server $server) {}

    public static function for(Server $server): self
    {
        return new self((new ProxmoxModule)->client($server), $server);
    }

    /** Resources a line is drawn for: cores are shared between guests by design, so CPU is shown and never alerts. */
    public const ALERTED = ['memory', 'disk'];

    /** The share of a node's memory or disk, in per cent, past which staff are warned (server setting; 0 = never). */
    public function threshold(): int
    {
        return max(0, min(1000, (int) $this->server->setting('capacity_alert', 80)));
    }

    /**
     * @return array{ok: bool, error?: string, threshold: int, nodes: list<array<string, mixed>>, services: array<string, int>}
     */
    public function overview(): array
    {
        $nodes = $this->api->get('nodes');
        if (! $nodes->ok) {
            return ['ok' => false, 'error' => $nodes->error, 'threshold' => $this->threshold(), 'nodes' => [], 'services' => $this->services()];
        }

        $guests = collect($this->api->get('cluster/resources', ['type' => 'vm'])->list())
            ->filter(fn ($g) => empty($g['template']));
        $only = (string) $this->server->setting('node', '');

        $out = [];
        foreach ($nodes->list() as $n) {
            $name = (string) ($n['node'] ?? '');
            if ($name === '' || ($only !== '' && $name !== $only)) {
                continue;
            }

            $here = $guests->where('node', $name);
            $ours = $here->filter(fn ($g) => in_array(ProxmoxModule::TAG, ProxmoxModule::tagsOf($g), true));
            $storage = collect($this->api->get("nodes/{$name}/storage")->list())
                ->filter(fn ($s) => (bool) array_intersect(['images', 'rootdir'], explode(',', (string) ($s['content'] ?? ''))) && empty($s['shared']))
                ->map(fn ($s) => ['storage' => (string) $s['storage'], 'total' => (int) ($s['total'] ?? 0), 'used' => (int) ($s['used'] ?? 0), 'avail' => (int) ($s['avail'] ?? 0)])
                ->values()->all();

            $row = [
                'node' => $name,
                'online' => ($n['status'] ?? '') === 'online',
                'cpu' => ['total' => (int) ($n['maxcpu'] ?? 0), 'given' => (int) $here->sum('maxcpu'), 'ours' => (int) $ours->sum('maxcpu'), 'load' => round((float) ($n['cpu'] ?? 0) * 100, 1)],
                'memory' => ['total' => (int) ($n['maxmem'] ?? 0), 'given' => (int) $here->sum('maxmem'), 'ours' => (int) $ours->sum('maxmem'), 'used' => (int) ($n['mem'] ?? 0)],
                'disk' => ['total' => (int) array_sum(array_column($storage, 'total')), 'given' => (int) $here->sum('maxdisk'), 'ours' => (int) $ours->sum('maxdisk'), 'used' => (int) array_sum(array_column($storage, 'used'))],
                'storage' => $storage,
                'guests' => ['running' => $here->where('status', 'running')->count(), 'stopped' => $here->where('status', '!=', 'running')->count(), 'ours' => $ours->count()],
            ];
            $row['share'] = [
                'cpu' => self::percent($row['cpu']['given'], $row['cpu']['total']),
                'memory' => self::percent($row['memory']['given'], $row['memory']['total']),
                'disk' => self::percent($row['disk']['given'], $row['disk']['total']),
            ];
            $out[] = $row;
        }

        return ['ok' => true, 'threshold' => $this->threshold(), 'nodes' => $out, 'services' => $this->services()];
    }

    /**
     * The nodes past the line, with what passed it.
     *
     * @return list<array{node: string, over: array<string, float>}>
     */
    public function overLine(array $overview): array
    {
        $line = $this->threshold();
        if ($line <= 0 || ! ($overview['ok'] ?? false)) {
            return [];
        }

        $out = [];
        foreach ($overview['nodes'] as $node) {
            $over = array_filter(array_intersect_key($node['share'], array_flip(self::ALERTED)), fn ($p) => $p !== null && $p >= $line);
            if ($over) {
                $out[] = ['node' => $node['node'], 'over' => $over];
            }
        }

        return $out;
    }

    /** @return array<string, int> this server's services in PNLCS, by status */
    private function services(): array
    {
        return Service::where('server_id', $this->server->id)
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')
            ->map(fn ($n) => (int) $n)->all();
    }

    private static function percent(int $given, int $total): ?float
    {
        return $total > 0 ? round($given * 100 / $total, 1) : null;
    }
}
