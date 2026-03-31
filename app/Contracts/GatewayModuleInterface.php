<?php
namespace App\Contracts;

use App\Models\Invoice;

interface GatewayModuleInterface
{
    public function capture(Invoice $invoice, float $amount, array $params = []): array;
    public function refund(string $transactionId, float $amount): array;
    public function getPaymentForm(Invoice $invoice): string;
    public function processWebhook(array $data): array;
    public function getConfigFields(): array;
    public function getModuleName(): string;
    public function isTokenised(): bool;
}
