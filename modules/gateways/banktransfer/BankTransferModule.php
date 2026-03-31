<?php
namespace Modules\Gateways\BankTransfer;

use App\Contracts\GatewayModuleInterface;
use App\Models\Invoice;

class BankTransferModule implements GatewayModuleInterface
{
    public function capture(Invoice $invoice, float $amount, array $params = []): array { return ["success" => false, "message" => "Bank transfer is an offline method"]; }
    public function refund(string $transactionId, float $amount): array { return ["success" => false, "message" => "Manual refund required"]; }
    public function getPaymentForm(Invoice $invoice): string { return "<p>Please transfer the amount to our bank account. Include invoice #{$invoice->id} as reference.</p>"; }
    public function processWebhook(array $data): array { return ["success" => false]; }
    public function getConfigFields(): array { return [["name" => "bank_details", "label" => "Bank Account Details", "type" => "textarea"]]; }
    public function getModuleName(): string { return "Bank Transfer"; }
    public function isTokenised(): bool { return false; }
}
