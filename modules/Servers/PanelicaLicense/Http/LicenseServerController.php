<?php

namespace Modules\Servers\PanelicaLicense\Http;

use App\Models\Service;
use Modules\Servers\PanelicaLicense\PanelicaLicenseModule;

/**
 * Staff: free the machine a key is bound to, or end a key for good.
 *
 * A Panelica key remembers the server it was activated on, and a key bound to
 * a machine that no longer exists cannot be used again until it is freed -
 * which used to mean asking Panelica support. Ending a key for good is the
 * other thing a termination deliberately does not do (that suspends, so the
 * period the customer paid for is not lost and a mistake can be undone).
 */
class LicenseServerController
{
    public function unbind(Service $service)
    {
        return $this->run($service, 'unbindServer');
    }

    public function cancel(Service $service)
    {
        return $this->run($service, 'cancelLicense');
    }

    private function run(Service $service, string $action)
    {
        abort_unless(strtolower((string) $service->product?->server_type) === PanelicaLicenseModule::NAME, 404);

        $result = app(PanelicaLicenseModule::class)->{$action}($service);

        return back()->with(($result['success'] ?? false) ? 'success' : 'error', (string) ($result['message'] ?? ''));
    }
}
