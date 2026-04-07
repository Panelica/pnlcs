<?php

namespace App\Services;

use App\Contracts\ServerModuleInterface;
use App\Models\Product;
use App\Models\Service;
use App\Services\Module\ModuleRegistry;
use Illuminate\Support\Facades\Log;
use App\Events\ServiceActivated;
use App\Events\ServiceSuspended;
use App\Events\ServiceTerminated;

class ProvisioningService
{
    public function __construct(private ModuleRegistry $registry) {}

    public function createAccount(Service $service): array
    {
        $module = $this->getModuleForService($service);

        if (!$module) {
            return ['success' => false, 'message' => __('messages.error.no_server_module_configured')];
        }

        try {
            $result = $module->create($service);

            if ($result['success'] ?? false) {
                $service->status = 'Active';
                $service->registration_date = $service->registration_date ?? now();
                $service->save();
                event(new ServiceActivated($service));
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('ProvisioningService::createAccount failed', [
                'service_id' => $service->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function suspendAccount(Service $service, string $reason = ''): array
    {
        $module = $this->getModuleForService($service);

        if (!$module) {
            return ['success' => false, 'message' => __('messages.error.no_server_module_configured')];
        }

        try {
            $result = $module->suspend($service, $reason);

            if ($result['success'] ?? false) {
                $service->status = 'Suspended';
                $service->suspension_date = now();
                $service->suspension_reason = $reason;
                $service->save();
                event(new ServiceSuspended($service, $reason));
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('ProvisioningService::suspendAccount failed', [
                'service_id' => $service->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function unsuspendAccount(Service $service): array
    {
        $module = $this->getModuleForService($service);

        if (!$module) {
            return ['success' => false, 'message' => __('messages.error.no_server_module_configured')];
        }

        try {
            $result = $module->unsuspend($service);

            if ($result['success'] ?? false) {
                $service->status = 'Active';
                $service->suspension_date = null;
                $service->suspension_reason = null;
                $service->save();
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('ProvisioningService::unsuspendAccount failed', [
                'service_id' => $service->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function terminateAccount(Service $service): array
    {
        $module = $this->getModuleForService($service);

        if (!$module) {
            return ['success' => false, 'message' => __('messages.error.no_server_module_configured')];
        }

        try {
            $result = $module->terminate($service);

            if ($result['success'] ?? false) {
                $service->status = 'Terminated';
                $service->termination_date = now();
                $service->save();
                event(new ServiceTerminated($service));
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('ProvisioningService::terminateAccount failed', [
                'service_id' => $service->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function changePassword(Service $service, string $newPassword): array
    {
        $module = $this->getModuleForService($service);

        if (!$module) {
            return ['success' => false, 'message' => __('messages.error.no_server_module_configured')];
        }

        try {
            return $module->changePassword($service, $newPassword);
        } catch (\Throwable $e) {
            Log::error('ProvisioningService::changePassword failed', [
                'service_id' => $service->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function changePackage(Service $service, Product $newProduct): array
    {
        $module = $this->getModuleForService($service);

        if (!$module) {
            return ['success' => false, 'message' => __('messages.error.no_server_module_configured')];
        }

        try {
            $result = $module->changePackage($service, $newProduct->toArray());

            if ($result['success'] ?? false) {
                $service->product_id = $newProduct->id;
                $service->save();
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('ProvisioningService::changePackage failed', [
                'service_id' => $service->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getModuleForService(Service $service): ?ServerModuleInterface
    {
        $serverType = $service->product?->server_type;

        if (empty($serverType)) {
            return null;
        }

        return $this->registry->getServerModule($serverType);
    }
}
