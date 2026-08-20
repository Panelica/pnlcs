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
        $amount        = money_fmt($invoice->amountDue());

        $rows = "";

        if ($bankName) {
            $rows .= "<tr><th scope=\"row\">" . __('messages.banktransfer.bank_name') . "</th><td>" . htmlspecialchars($bankName, ENT_QUOTES, "UTF-8") . "</td></tr>";
        }
        if ($accountName) {
            $rows .= "<tr><th scope=\"row\">" . __('messages.banktransfer.account_name') . "</th><td>" . htmlspecialchars($accountName, ENT_QUOTES, "UTF-8") . "</td></tr>";
        }
        if ($accountNumber) {
            $rows .= "<tr><th scope=\"row\">" . __('messages.banktransfer.account_number') . "</th><td><code>" . htmlspecialchars($accountNumber, ENT_QUOTES, "UTF-8") . "</code></td></tr>";
        }
        if ($sortCode) {
            $rows .= "<tr><th scope=\"row\">" . __('messages.banktransfer.sort_code') . "</th><td><code>" . htmlspecialchars($sortCode, ENT_QUOTES, "UTF-8") . "</code></td></tr>";
        }
        if ($iban) {
            $rows .= "<tr><th scope=\"row\">" . __('messages.banktransfer.iban') . "</th><td><code>" . htmlspecialchars($iban, ENT_QUOTES, "UTF-8") . "</code></td></tr>";
        }
        if ($swift) {
            $rows .= "<tr><th scope=\"row\">" . __('messages.banktransfer.swift') . "</th><td><code>" . htmlspecialchars($swift, ENT_QUOTES, "UTF-8") . "</code></td></tr>";
        }
        $rows .= "<tr><th scope=\"row\">" . __('messages.banktransfer.reference') . "</th><td><strong>" . __('client.invoices.invoice_prefix', ['id' => $invoiceNum]) . "</strong></td></tr>";
        $rows .= "<tr><th scope=\"row\">" . __('messages.banktransfer.amount') . "</th><td><strong>{$amount}</strong></td></tr>";

        $notesHtml = $notes
            ? "<div class=\"alert alert-info mt-3\"><strong>" . __('messages.banktransfer.instructions') . "</strong><br>" . nl2br(htmlspecialchars($notes, ENT_QUOTES, "UTF-8")) . "</div>"
            : "";

        $detailsTitle = __('messages.banktransfer.details_title');
        $noteLabel    = __('messages.banktransfer.note');
        $refHint      = __('messages.banktransfer.use_invoice_reference');
        $invoiceLabel = __('client.invoices.invoice_prefix', ['id' => $invoiceNum]);
        $pendingHint  = __('messages.banktransfer.transfer_pending');

        return <<<HTML
<div class="card my-3">
    <div class="card-header bg-light">
        <h6 class="mb-0"><i class="ri-bank-line me-1"></i> {$detailsTitle}</h6>
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
    <strong>{$noteLabel}</strong> {$refHint} <strong>{$invoiceLabel}</strong>.
    {$pendingHint}
</div>
HTML;
    }

    public function processWebhook(array $data): array
    {
        return ["success" => false, "message" => "Bank transfer does not support webhooks."];
    }
}
