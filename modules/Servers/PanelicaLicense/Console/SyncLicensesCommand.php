<?php

namespace Modules\Servers\PanelicaLicense\Console;

use App\Models\Server;
use App\Models\Service;
use Illuminate\Console\Command;
use Modules\Servers\PanelicaLicense\PanelicaLicenseModule;

/**
 * Find the servers customers installed with their link and buy their
 * licenses (PanelicaLicenseModule::checkInstall()).
 *
 * Every five minutes, and only for services still waiting for their server:
 * one indexed lookup when there are none. Licenses already bought are read
 * back hourly by pnlcs:usage-polling (usageUpdate()).
 */
class SyncLicensesCommand extends Command
{
    protected $signature = 'pnlcs:panelica-licenses {--service= : Check one service now}';

    protected $description = 'Find servers installed with a Panelica license link and buy their licenses';

    public function handle(): int
    {
        $servers = Server::where('type', PanelicaLicenseModule::NAME)->pluck('id');
        if ($servers->isEmpty()) {
            return self::SUCCESS;
        }

        $module = app(PanelicaLicenseModule::class);

        $services = Service::with('product', 'server')
            ->whereIn('server_id', $servers)
            ->where('status', 'active')
            ->whereNotNull('module_data->install_ref')
            ->whereNull('module_data->license_id')
            ->when($this->option('service'), fn ($q, $id) => $q->whereKey((int) $id))
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->reject(fn (Service $service) => ! empty($service->module_data['ended']));

        foreach ($services as $service) {
            $result = $module->checkInstall($service);
            $this->line("Service #{$service->id}: ".($result['message'] ?? ''));
        }

        return self::SUCCESS;
    }
}
