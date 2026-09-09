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

    /**
     * Bank transfer settings.
     *
     * One account holder: every account belongs to the same company, so
     * asking per bank is both needless and an invitation for the name to be
     * typed differently under each. Up to three banks, each with its own
     * switch and the details a transfer needs wherever the bank is: an IBAN
     * for SEPA-style transfers, an account number with a sort or routing code
     * elsewhere, SWIFT/BIC for either. A bank is shown when it has a name and
     * at least one of the two account identifiers. The first bank keeps the
     * keys the single-account module used, so nothing typed before is lost.
     */
    public function getConfigFields(): array
    {
        $fields = [
            ["name" => "account_name", "label" => "Account holder (the same for every bank)", "type" => "text"],
        ];

        foreach (self::SLOTS as $n => $keys) {
            $label = "Bank ".($n + 1);
            $fields[] = ["name" => $keys["active"], "label" => "$label - show to customers?", "type" => "yesno", "default" => "1"];
            $fields[] = ["name" => $keys["name"], "label" => "$label - bank name", "type" => "text"];
            $fields[] = ["name" => $keys["iban"], "label" => "$label - IBAN", "type" => "text"];
            $fields[] = ["name" => $keys["account_number"], "label" => "$label - account number (if no IBAN)", "type" => "text"];
            $fields[] = ["name" => $keys["sort_code"], "label" => "$label - sort code / routing (optional)", "type" => "text"];
            $fields[] = ["name" => $keys["swift"], "label" => "$label - SWIFT/BIC (optional)", "type" => "text"];
        }

        $fields[] = ["name" => "notes", "label" => "Additional note (optional)", "type" => "textarea"];

        return $fields;
    }

    /** Setting keys per bank slot; slot 0 carries the legacy single-account keys. */
    private const SLOTS = [
        ["active" => "bank_active",  "name" => "bank_name",  "iban" => "iban",       "account_number" => "account_number",       "sort_code" => "sort_code",       "swift" => "swift"],
        ["active" => "bank2_active", "name" => "bank2_name", "iban" => "bank2_iban", "account_number" => "bank2_account_number", "sort_code" => "bank2_sort_code", "swift" => "bank2_swift"],
        ["active" => "bank3_active", "name" => "bank3_name", "iban" => "bank3_iban", "account_number" => "bank3_account_number", "sort_code" => "bank3_sort_code", "swift" => "bank3_swift"],
    ];

    private function getSetting(string $key): ?string
    {
        $row = GatewaySettings::where("gateway", "banktransfer")->where("setting", $key)->first();

        return $row?->value;
    }

    /**
     * The banks to show the customer, in order.
     *
     * Two conditions: the slot has a name and an account identifier (a half
     * filled row would print "Bank 2: -"), and its switch is on. The switch
     * lets an operator hide a bank for a while without deleting and retyping
     * its details. A switch never written counts as on: the switches came
     * later, and an account that was set up must not vanish by itself.
     *
     * @return array<int, array<string, string>>
     */
    private function banks(): array
    {
        $banks = [];

        foreach (self::SLOTS as $keys) {
            if (trim((string) $this->getSetting($keys["active"])) === "0") {
                continue;
            }

            $bank = [];
            foreach ($keys as $field => $settingKey) {
                $bank[$field] = trim((string) $this->getSetting($settingKey));
            }

            if ($bank["name"] !== "" && ($bank["iban"] !== "" || $bank["account_number"] !== "")) {
                $banks[] = $bank;
            }
        }

        return $banks;
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
        $e = fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, "UTF-8");

        $accountHolder = trim((string) $this->getSetting("account_name"));
        $notes    = trim((string) $this->getSetting("notes"));
        $banks       = $this->banks();
        $invoiceNum  = $e((string) ($invoice->invoice_num ?? $invoice->id));
        // money_fmt printed the shop currency; the customer making the
        // transfer pays in the billing currency. That leads, the shop figure
        // follows in brackets.
        $amount      = dual_money_fmt($invoice->amountDue(), $invoice);

        $detailsTitle = __('messages.banktransfer.details_title');
        $bankLabel    = __('messages.banktransfer.bank_name');
        $holderLabel  = __('messages.banktransfer.account_name');
        $ibanLabel    = __('messages.banktransfer.iban');
        $refLabel     = __('messages.banktransfer.reference');
        $amountLabel  = __('messages.banktransfer.amount');
        $noteLabel    = __('messages.banktransfer.note');
        $refHint      = __('messages.banktransfer.use_invoice_reference');
        // The reference is the short code, not the invoice number: the long
        // number is hard to get right in a bank's description box.
        $reference     = $e(payment_ref($invoice));
        $invoiceLabel = __('client.invoices.invoice_prefix', ['id' => $invoiceNum]);
        $pendingHint  = __('messages.banktransfer.transfer_pending');

        // Each bank in its own table: stacked in one table, a customer could
        // pair one bank's name with another's IBAN.
        $bankBlocks = "";

        foreach ($banks as $bank) {
            $rows  = '<tr><th scope="row">'.$bankLabel.'</th><td>'.$e($bank["name"]).'</td></tr>';

            if ($accountHolder !== "") {
                $rows .= '<tr><th scope="row">'.$holderLabel.'</th><td>'.$e($accountHolder).'</td></tr>';
            }

            foreach ([
                "account_number" => __('messages.banktransfer.account_number'),
                "sort_code"      => __('messages.banktransfer.sort_code'),
                "iban"           => $ibanLabel,
                "swift"          => __('messages.banktransfer.swift'),
            ] as $field => $label) {
                if ($bank[$field] !== "") {
                    $rows .= '<tr><th scope="row">'.$label.'</th><td><code>'.$e($bank[$field]).'</code></td></tr>';
                }
            }

            $bankBlocks .= '<div class="card my-3"><div class="card-body p-0">'
                . '<table class="table table-bordered mb-0"><tbody>'.$rows.'</tbody></table>'
                . '</div></div>';
        }

        if ($bankBlocks === "") {
            return '<div class="alert alert-warning">'.__('messages.banktransfer.no_accounts').'</div>';
        }

        // Reference and amount are the same whichever bank is used; once, at
        // the bottom, rather than repeated under every account.
        // Under the amount, the rate it was converted at: the customer pays
        // in one currency for a figure that came from another, and cannot
        // check the number without the source and date of the rate.
        $rateRow = billing_rate_note($invoice)
            ? '<tr><th scope="row">'.__('pdf.rate_label').'</th><td>'.$e(billing_rate_note($invoice)).'</td></tr>'
            : '';

        $summary = '<div class="card my-3"><div class="card-body p-0"><table class="table table-bordered mb-0"><tbody>'
            . '<tr><th scope="row">'.$refLabel.'</th><td><strong>'.$reference.'</strong></td></tr>'
            . '<tr><th scope="row">'.$amountLabel.'</th><td><strong>'.$amount.'</strong></td></tr>'
            . $rateRow
            . '</tbody></table></div></div>';

        $notesHtml = $notes !== ""
            ? '<div class="alert alert-info mt-3"><strong>'.__('messages.banktransfer.instructions').'</strong><br>'.nl2br($e($notes)).'</div>'
            : "";

        return '<div class="card my-3"><div class="card-header bg-light">'
            . '<h6 class="mb-0"><i class="ri-bank-line me-1"></i> '.$detailsTitle.'</h6>'
            . '</div></div>'
            . $bankBlocks
            . $summary
            . $notesHtml
            . '<div class="alert alert-warning mt-3"><i class="ri-information-line me-1"></i> '
            . '<strong>'.$noteLabel.'</strong> '.$refHint.' <strong>'.$reference.'</strong>. '
            . $pendingHint
            . '</div>';
    }

    public function processWebhook(array $data): array
    {
        return ["success" => false, "message" => "Bank transfer does not support webhooks."];
    }
}
