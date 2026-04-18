<?php

namespace Modules\Gateways\BankTransfer;

use App\Contracts\GatewayModuleInterface;
use App\Models\GatewaySettings;
use App\Models\Invoice;

class BankTransferModule implements GatewayModuleInterface
{
    public function getModuleName(): string
    {
        return "Bank Transfer";
    }

    public function isTokenised(): bool
    {
        return false;
    }

    public function getConfigFields(): array
    {
        return [
            ["name" => "bank_name",       "label" => "Bank Name",          "type" => "text"],
            ["name" => "account_name",    "label" => "Account Name",       "type" => "text"],
            ["name" => "account_number",  "label" => "Account Number",     "type" => "text"],
            ["name" => "sort_code",       "label" => "Sort Code / Routing", "type" => "text"],
            ["name" => "iban",            "label" => "IBAN (optional)",    "type" => "text"],
            ["name" => "swift",           "label" => "SWIFT/BIC (optional)", "type" => "text"],
            ["name" => "notes",           "label" => "Additional Instructions", "type" => "textarea"],
        ];
    }

    private function getSetting(string $key): ?string
    {
        $row = GatewaySettings::where("gateway", "banktransfer")->where("setting", $key)->first();
        return $row?->value;
    }

    public function capture(Invoice $invoice, float $amount, array $params = []): array
    {
        return [
            "success" => false,
            "message" => "Bank transfer is an offline payment method. Please transfer manually and wait for confirmation.",
        ];
    }

    public function refund(string $transactionId, float $amount): array
    {
        return [
            "success" => false,
            "message" => "Bank transfer refunds must be processed manually. Please contact your bank.",
        ];
    }

    public function getPaymentForm(Invoice $invoice): string
    {
        $bankName      = $this->getSetting("bank_name") ?? "";
        $accountName   = $this->getSetting("account_name") ?? "";
        $accountNumber = $this->getSetting("account_number") ?? "";
        $sortCode      = $this->getSetting("sort_code") ?? "";
        $iban          = $this->getSetting("iban") ?? "";
        $swift         = $this->getSetting("swift") ?? "";
        $notes         = $this->getSetting("notes") ?? "";
        $invoiceNum    = htmlspecialchars($invoice->invoice_num ?? $invoice->id, ENT_QUOTES, "UTF-8");
        $amount        = number_format((float) $invoice->total, 2, ".", "");

        $rows = "";

        if ($bankName) {
            $rows .= "<tr><th scope=\"row\">Bank Name</th><td>" . htmlspecialchars($bankName, ENT_QUOTES, "UTF-8") . "</td></tr>";
        }
        if ($accountName) {
            $rows .= "<tr><th scope=\"row\">Account Name</th><td>" . htmlspecialchars($accountName, ENT_QUOTES, "UTF-8") . "</td></tr>";
        }
        if ($accountNumber) {
            $rows .= "<tr><th scope=\"row\">Account Number</th><td><code>" . htmlspecialchars($accountNumber, ENT_QUOTES, "UTF-8") . "</code></td></tr>";
        }
        if ($sortCode) {
            $rows .= "<tr><th scope=\"row\">Sort Code / Routing</th><td><code>" . htmlspecialchars($sortCode, ENT_QUOTES, "UTF-8") . "</code></td></tr>";
        }
        if ($iban) {
            $rows .= "<tr><th scope=\"row\">IBAN</th><td><code>" . htmlspecialchars($iban, ENT_QUOTES, "UTF-8") . "</code></td></tr>";
        }
        if ($swift) {
            $rows .= "<tr><th scope=\"row\">SWIFT/BIC</th><td><code>" . htmlspecialchars($swift, ENT_QUOTES, "UTF-8") . "</code></td></tr>";
        }
        $rows .= "<tr><th scope=\"row\">Reference</th><td><strong>Invoice #{$invoiceNum}</strong></td></tr>";
        $rows .= "<tr><th scope=\"row\">Amount</th><td><strong>{$amount}</strong></td></tr>";

        $notesHtml = $notes
            ? "<div class=\"alert alert-info mt-3\"><strong>Instructions:</strong><br>" . nl2br(htmlspecialchars($notes, ENT_QUOTES, "UTF-8")) . "</div>"
            : "";

        return <<<HTML
<div class="card my-3">
    <div class="card-header bg-light">
        <h6 class="mb-0"><i class="ri-bank-line me-1"></i> Bank Transfer Details</h6>
    </div>
    <div class="card-body p-0">
        <table class="table table-bordered mb-0">
            <tbody>
                {$rows}
            </tbody>
        </table>
    </div>
</div>
{$notesHtml}
<div class="alert alert-warning mt-3">
    <i class="ri-information-line me-1"></i>
    <strong>Note:</strong> Please use <strong>Invoice #{$invoiceNum}</strong> as your payment reference.
    Your invoice will be marked as paid once the transfer is confirmed by an administrator.
</div>
HTML;
    }

    public function processWebhook(array $data): array
    {
        return ["success" => false, "message" => "Bank transfer does not support webhooks."];
    }
}
