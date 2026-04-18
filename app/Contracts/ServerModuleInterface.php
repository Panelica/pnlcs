<?php
namespace App\Contracts;

use App\Models\Service;
use App\Models\Server;

interface ServerModuleInterface
{
    public function create(Service $service): array;
    public function suspend(Service $service, string $reason = ""): array;
    public function unsuspend(Service $service): array;
    public function terminate(Service $service): array;
    public function changePassword(Service $service, string $newPassword): array;
    public function changePackage(Service $service, array $newPackage): array;
    public function usageUpdate(Server $server): array;
    public function testConnection(Server $server): bool;
    public function getConfigFields(): array;
    public function getModuleName(): string;
}
