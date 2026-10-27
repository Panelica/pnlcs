<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Servers\Proxmox\ProxmoxCapacity;

/**
 * Tell staff when a Proxmox node has given out more than its line (#303).
 *
 * For every active Proxmox server, the share of each node's memory and guest
 * storage that its guests have been given is set against the server's
 * capacity line (80% unless set; 0 = never). CPU is not: cores are shared. Past it, the
 * server.capacity_high notification goes out through the notification rules,
 * once a day per node and resource, so the stock of the products sold on it
 * can be lowered, or a server added, before an order cannot be set up.
 */
class ProxmoxCapacityCommand extends Command
{
    protected $signature = 'pnlcs:proxmox-capacity';

    protected $description = 'Warn when a Proxmox node has given out more of its memory or disk than its capacity line';

    public function handle(NotificationService $notifications): int
    {
        foreach (Server::where('active', true)->whereRaw('LOWER(type) = ?', ['proxmox'])->get() as $server) {
            try {
                $capacity = ProxmoxCapacity::for($server);
                foreach ($capacity->overLine($capacity->overview()) as $hit) {
                    foreach ($hit['over'] as $resource => $share) {
                        $key = "pve-capacity-alert:{$server->id}:{$hit['node']}:{$resource}:".now()->toDateString();
                        if (! Cache::add($key, 1, now()->addDay())) {
                            continue;
                        }
                        $notifications->dispatch('server.capacity_high', [
                            'event_type' => 'server.capacity_high',
                            'subject' => "Capacity: {$server->name} / {$hit['node']} {$resource} at {$share}%",
                            'message' => "Guests on node {$hit['node']} of {$server->name} have been given {$share}% of its {$resource} (line: {$capacity->threshold()}%). Lower the stock of the products sold there, or add a server.",
                            'server_id' => $server->id,
                        ]);
                        $this->line("{$server->name} / {$hit['node']}: {$resource} {$share}%");
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('pnlcs:proxmox-capacity: '.$server->name.': '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
