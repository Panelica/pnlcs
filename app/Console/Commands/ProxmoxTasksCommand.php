<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Services\ProvisioningService;
use Illuminate\Console\Command;
use Modules\Servers\Proxmox\ProxmoxModule;

/**
 * Carry on the Proxmox work a web request started: a reinstall or a restore
 * is a chain of tasks, and the next one has to be sent when the last is done
 * even if the customer has closed the page.
 */
class ProxmoxTasksCommand extends Command
{
    protected $signature = 'pnlcs:proxmox-tasks';

    protected $description = 'Advance Proxmox reinstalls, restores and rollbacks that are under way';

    public function handle(ProvisioningService $provisioning): int
    {
        $services = Service::whereNotNull('server_id')
            ->whereIn('status', ['active', 'suspended'])
            ->where('module_data', 'like', '%pve_job%')
            ->get();

        foreach ($services as $service) {
            $module = $provisioning->resolveModule($service);
            if (! $module instanceof ProxmoxModule || ! $module->currentJob($service)) {
                continue;
            }
            $state = $module->advanceJob($service);
            $this->line("service #{$service->id}: {$state}");
        }

        // Image downloads started from the admin panel carry on here too.
        foreach (\App\Models\Server::where('type', 'proxmox')->where('settings', 'like', '%image_jobs%')->get() as $server) {
            try {
                \Modules\Servers\Proxmox\ProxmoxImages::for($server)->advance();
            } catch (\Throwable $e) {
                $this->warn("server #{$server->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
