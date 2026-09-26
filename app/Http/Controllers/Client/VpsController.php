<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Concerns\ResolvesClient;
use App\Http\Controllers\Concerns\ServesVps;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\ProvisioningService;
use Modules\Servers\Proxmox\ProxmoxModule;

/**
 * The customer's own controls for a virtual server: status, power, graphs,
 * root password, reinstall, snapshots and backups.
 *
 * Reading works while the service is active or suspended; anything that
 * changes the machine only while it is active, so a customer suspended for
 * non-payment cannot switch the server back on from here.
 */
class VpsController extends Controller
{
    use ResolvesClient;
    use ServesVps;

    protected function vpsModule(Service $service, bool $forChange): ProxmoxModule
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $status = strtolower((string) $service->status);
        abort_unless(in_array($status, $forChange ? ['active'] : ['active', 'suspended'], true), 409, __('proxmox.client.not_active'));

        $module = $service->server_id ? app(ProvisioningService::class)->resolveModule($service) : null;
        abort_unless($module instanceof ProxmoxModule, 404);

        return $module;
    }

    protected function vpsActor(): ?string
    {
        return auth()->user()?->email;
    }
}
