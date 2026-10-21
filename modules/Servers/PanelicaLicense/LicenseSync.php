<?php

namespace Modules\Servers\PanelicaLicense;

use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;

/**
 * One pass over the services this module sells:
 *
 *  - services still waiting for their server: find the server installed with
 *    the customer's link and buy its license (checkInstall());
 *  - licenses already bought: read back from Central once an hour, a few at
 *    a time, so the service pages show what Central says (status, expiry,
 *    the server, its last check-in).
 *
 * Run by pnlcs:panelica-licenses every five minutes and by "Check installs
 * now" on the Panelica Licenses page. The page calls this class, not the
 * command: commands are registered for the console only, and a web request
 * asking Artisan for one gets "command does not exist".
 *
 * One query and nothing else on a site that is not connected to a partner
 * account or sells no license; nothing at all while the module is switched
 * off under Setup -> Modules.
 */
final class LicenseSync
{
    public function __construct(private PanelicaLicenseModule $module) {}

    /**
     * @param  callable(string):void|null  $line  told what happened to each service
     * @return array{checked: int, refreshed: int}
     */
    public function run(?int $serviceId = null, ?callable $line = null): array
    {
        $done = ['checked' => 0, 'refreshed' => 0];
        if (! PanelicaLicenseModule::enabled() || ! PartnerAccount::connected()) {
            return $done;
        }

        $waiting = $this->licenseServices()
            ->where('status', 'active')
            ->whereNotNull('module_data->install_ref')
            ->whereNull('module_data->license_id')
            ->when($serviceId, fn ($q, $id) => $q->whereKey($id))
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->reject(fn (Service $service) => ! empty($service->module_data['ended']));

        foreach ($waiting as $service) {
            $result = $this->module->checkInstall($service);
            $done['checked']++;
            $line && $line("Service #{$service->id}: ".($result['message'] ?? ''));
        }

        $stale = now()->subHour()->toIso8601String();
        $bought = $this->licenseServices()
            ->whereIn('status', ['active', 'suspended'])
            ->whereNotNull('module_data->license_id')
            ->where(fn ($q) => $q->whereNull('module_data->checked_at')->orWhere('module_data->checked_at', '<', $stale))
            ->when($serviceId, fn ($q, $id) => $q->whereKey($id))
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->reject(fn (Service $service) => ! empty($service->module_data['ended']));

        foreach ($bought as $service) {
            $result = $this->module->refreshLicense($service);
            $done['refreshed']++;
            $line && $line("Service #{$service->id}: ".($result['message'] ?? ''));
        }

        return $done;
    }

    private function licenseServices(): Builder
    {
        return Service::with('product', 'order.invoice')
            ->whereHas('product', fn ($q) => $q->where('server_type', PanelicaLicenseModule::NAME));
    }
}
