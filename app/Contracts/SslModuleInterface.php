<?php

namespace App\Contracts;

use App\Models\SslOrder;

interface SslModuleInterface
{
    public function purchaseCertificate(SslOrder $order, array $config): array;
    public function getCertificateStatus(SslOrder $order): array;
    public function renewCertificate(SslOrder $order): array;
    public function revokeCertificate(SslOrder $order, string $reason = ''): array;
    public function reissueCertificate(SslOrder $order, string $newCsr): array;
    public function resendValidationEmail(SslOrder $order): array;
    public function changeValidationMethod(SslOrder $order, string $method): array;
    public function getApproverEmails(string $domain): array;
    public function getWebServerTypes(): array;
    public function getCertificateTypes(): array;
    public function decodeCsr(string $csr): array;
    public function generateCsr(array $params): array;
    public function testConnection(): bool;
    public function getConfigFields(): array;
    public function getModuleName(): string;
}
