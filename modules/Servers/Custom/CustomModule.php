<?php

namespace Modules\Servers\Custom;

use App\Contracts\ServerModuleInterface;
use App\Models\Server;
use App\Models\Service;

class CustomModule implements ServerModuleInterface
{
    public function create(Service $service): array
    {
        return ['success' => true, 'message' => 'Service created manually'];
    }

    public function suspend(Service $service, string $reason = ''): array
    {
        return ['success' => true];
    }

    public function unsuspend(Service $service): array
    {
        return ['success' => true];
    }

    public function terminate(Service $service): array
    {
        return ['success' => true];
    }

    public function changePassword(Service $service, string $newPassword): array
    {
        return ['success' => true];
    }

    public function changePackage(Service $service, array $newPackage): array
    {
        return ['success' => true];
    }

    public function usageUpdate(Server $server): array
    {
        return [];
    }

    public function testConnection(Server $server): bool
    {
        return true;
    }

    public function getConfigFields(): array
    {
        return [];
    }

    public function getModuleName(): string
    {
        return 'Custom/No Module';
    }
}
