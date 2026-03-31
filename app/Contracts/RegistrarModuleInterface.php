<?php
namespace App\Contracts;

use App\Models\Domain;

interface RegistrarModuleInterface
{
    public function register(Domain $domain, int $years, array $params = []): array;
    public function transfer(Domain $domain, string $eppCode): array;
    public function renew(Domain $domain, int $years): array;
    public function getNameservers(Domain $domain): array;
    public function saveNameservers(Domain $domain, array $nameservers): bool;
    public function getEPPCode(Domain $domain): string;
    public function getLockStatus(Domain $domain): bool;
    public function toggleLock(Domain $domain, bool $lock): bool;
    public function checkAvailability(string $domain): array;
    public function getConfigFields(): array;
    public function getModuleName(): string;
}
