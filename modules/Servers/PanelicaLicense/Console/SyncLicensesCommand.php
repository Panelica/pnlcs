<?php

namespace Modules\Servers\PanelicaLicense\Console;

use App\Models\Service;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Modules\Servers\PanelicaLicense\PanelicaLicenseModule;
use Modules\Servers\PanelicaLicense\PartnerAccount;

/**
 * Every five minutes:
 *
 *  - services still waiting for their server: find the server installed with
 *    the customer's link and buy its license (checkInstall());
 *  - licenses already bought: read back from Central once an hour, a few at
 *    a time, so the service pages show what Central says (status, expiry,
 *    the server, its last check-in).
 *
 * One query and nothing else on a site that is not connected to a partner
 * account or sells no license.
 */
class SyncLicensesCommand extends Command
{
    protected $signature = 'pnlcs:panelica-licenses {--service= : Check one service now}';

    protected $description = 'Find servers installed with a Panelica license link, buy their licenses and read bought ones back';

    public function handle(): int
    {
        if (! PartnerAccount::connected()) {
            return self::SUCCESS;
        }

        $module = app(PanelicaLicenseModule::class);
        $only = $this->option('service');

        $waiting = $this->licenseServices()
            ->where('status', 'active')
            ->whereNotNull('module_data->install_ref')
            ->whereNull('module_data->license_id')
            ->when($only, fn ($q, $id) => $q->whereKey((int) $id))
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->reject(fn (Service $service) => ! empty($service->module_data['ended']));

        foreach ($waiting as $service) {
            $result = $module->checkInstall($service);
            $this->line("Service #{$service->id}: ".($result['message'] ?? ''));
        }

        $stale = now()->subHour()->toIso8601String();
        $bought = $this->licenseServices()
            ->whereIn('status', ['active', 'suspended'])
            ->whereNotNull('module_data->license_id')
            ->where(fn ($q) => $q->whereNull('module_data->checked_at')->orWhere('module_data->checked_at', '<', $stale))
            ->when($only, fn ($q, $id) => $q->whereKey((int) $id))
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->reject(fn (Service $service) => ! empty($service->module_data['ended']));

        foreach ($bought as $service) {
            $result = $module->refreshLicense($service);
            $this->line("Service #{$service->id}: ".($result['message'] ?? ''));
        }

        return self::SUCCESS;
    }

    private function licenseServices(): Builder
    {
        return Service::with('product', 'order.invoice')
            ->whereHas('product', fn ($q) => $q->where('server_type', PanelicaLicenseModule::NAME));
    }
}
