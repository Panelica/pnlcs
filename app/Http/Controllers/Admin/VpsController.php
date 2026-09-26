<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ServesVps;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\ProvisioningService;
use Modules\Servers\Proxmox\ProxmoxModule;

/**
 * The same virtual server panel the customer has, for staff. Staff may also
 * work on a suspended service's server (to look into it, or to take a backup
 * before it is terminated); a terminated or cancelled one is left alone.
 */
class VpsController extends Controller
{
    use ServesVps;

    protected function vpsModule(Service $service, bool $forChange): ProxmoxModule
    {
        $status = strtolower((string) $service->status);
        abort_if($forChange && ! in_array($status, ['active', 'suspended'], true), 409, __('proxmox.client.not_active'));

        $module = $service->server_id ? app(ProvisioningService::class)->resolveModule($service) : null;
        abort_unless($module instanceof ProxmoxModule, 404);

        return $module;
    }

    protected function vpsActor(): ?string
    {
        return auth('admin')->user()?->full_name ?: 'admin';
    }
}
