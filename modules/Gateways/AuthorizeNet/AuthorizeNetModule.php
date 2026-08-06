<?php

namespace Modules\Gateways\AuthorizeNet;

use App\Contracts\GatewayModuleInterface;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuthorizeNetModule implements GatewayModuleInterface
{
    public function getModuleName(): string
    {
        return "Authorize.net";
    }

    public function isTokenised(): bool
    {
        return true;
    }

    public function getConfigFields(): array
    {
        return [
            ["name" => "api_login_id",    "label" => "API Login ID",      "type" => "text", "required" => true],
            ["name" => "transaction_key", "label" => "Transaction Key",   "type" => "password", "required" => true],
            ["name" => "client_key",      "label" => "Client Key (Accept.js)", "type" => "text"],
            ["name" => "sandbox",         "label" => "Test Mode",         "type" => "select", "options" => ["0" => "Live", "1" => "Sandbox"]],
        ];
    }

    private function getSetting(string $key): ?string
    {
        $row = GatewaySettings::where("gateway", "authorize")->where("setting", $key)->first();
        return $row?->value;
    }

    private function getApiUrl(): string
    {
        return ($this->getSetting("sandbox") === "1")
            ? "https://apitest.authorize.net/xml/v1/request.api"
            : "https://api.authorize.net/xml/v1/request.api";
    }

    private function buildAuth(): array
    {
        return [
            "name"           => $this->getSetting("api_login_id"),
            "transactionKey" => $this->getSetting("transaction_key"),
        ];
    }

    public function capture(Invoice $invoice, float $amount, array $params = []): array
    {
        $loginId = $this->getSetting("api_login_id");
        $txnKey  = $this->getSetting("transaction_key");

        if (!$loginId || !$txnKey) {
            return ["success" => false, "message" => "Authorize.net credentials not configured."];
        }

        $opaqueData = $params["opaque_data"] ?? null;

        if (!$opaqueData) {
            // No payment data yet — frontend must submit via Accept.js first
            return [
                "success"        => false,
                "message"        => "Payment data required. Please complete the payment form.",
                "requires_form"  => true,
            ];
        }

        $payload = [
            "createTransactionRequest" => [
                "merchantAuthentication" => $this->buildAuth(),
                "refId"                  => "INV-" . $invoice->id,
                "transactionRequest"     => [
                    "transactionType" => "authCaptureTransaction",
                    "amount"          => number_format($amount, 2, ".", ""),
                    "payment"         => [
                        "opaqueData" => [
                            "dataDescriptor" => $opaqueData["dataDescriptor"],
                            "dataValue"      => $opaqueData["dataValue"],
                        ],
                    ],
                    "order" => [
                        "invoiceNumber" => $invoice->invoice_num ?? (string) $invoice->id,
                        "description"   => "Invoice #" . ($invoice->invoice_num ?? $invoice->id),
                    ],
                ],
            ],
        ];

        $response = Http::acceptJson()->post($this->getApiUrl(), $payload);

        if (!$response->successful()) {
            Log::error("Authorize.net: HTTP error", [
                "invoice" => $invoice->id,
                "status"  => $response->status(),
            ]);
            return ["success" => false, "message" => "Authorize.net request failed."];
        }

        $data            = $response->json();
        $txnResponse     = $data["transactionResponse"] ?? [];
        $responseCode    = $txnResponse["responseCode"] ?? null;
        $transactionId   = $txnResponse["transId"] ?? null;

        if ($responseCode !== "1") {
            $errors  = $txnResponse["errors"] ?? [];
            $message = $errors[0]["errorText"] ?? ($txnResponse["messages"][0]["description"] ?? "Transaction declined.");
            Log::warning("Authorize.net: transaction not approved", [
                "invoice"       => $invoice->id,
                "responseCode"  => $responseCode,
                "message"       => $message,
            ]);
            return ["success" => false, "message" => $message];
        }

        Log::info("Authorize.net: transaction approved", [
            "invoice"        => $invoice->id,
            "transaction_id" => $transactionId,
        ]);

        return [
            "success"        => true,
            "transaction_id" => $transactionId,
            "auth_code"      => $txnResponse["authCode"] ?? null,
        ];
    }

    public function refund(string $transactionId, float $amount): array
    {
        $loginId = $this->getSetting("api_login_id");
        $txnKey  = $this->getSetting("transaction_key");

        if (!$loginId || !$txnKey) {
            return ["success" => false, "message" => "Authorize.net credentials not configured."];
        }

        $payload = [
            "createTransactionRequest" => [
                "merchantAuthentication" => $this->buildAuth(),
                "transactionRequest"     => [
                    "transactionType"     => "refundTransaction",
                    "amount"              => number_format($amount, 2, ".", ""),
                    "refTransId"          => $transactionId,
                ],
            ],
        ];

        $response = Http::acceptJson()->post($this->getApiUrl(), $payload);

        if (!$response->successful()) {
            return ["success" => false, "message" => "Authorize.net refund request failed."];
        }

        $data         = $response->json();
        $txnResponse  = $data["transactionResponse"] ?? [];
        $responseCode = $txnResponse["responseCode"] ?? null;

        if ($responseCode !== "1") {
            $errors  = $txnResponse["errors"] ?? [];
            $message = $errors[0]["errorText"] ?? "Refund declined.";
            Log::warning("Authorize.net: refund not approved", [
                "transaction" => $transactionId,
                "message"     => $message,
            ]);
            return ["success" => false, "message" => $message];
        }

        return [
            "success"        => true,
            "refund_id"      => $txnResponse["transId"] ?? null,
            "transaction_id" => $transactionId,
        ];
    }

    public function getPaymentForm(Invoice $invoice): string
    {
        $loginId   = $this->getSetting("api_login_id") ?? "";
        $clientKey = $this->getSetting("client_key") ?? "";
        $sandbox   = $this->getSetting("sandbox") === "1";
        $amount    = money_fmt($invoice->amountDue());
        $invoiceId = (int) $invoice->id;

        if (!$loginId || !$clientKey) {
            return "<div class=\"alert alert-danger\">Authorize.net is not configured. Please contact support.</div>";
        }

        $safeLoginId   = htmlspecialchars($loginId, ENT_QUOTES, "UTF-8");
        $safeClientKey = htmlspecialchars($clientKey, ENT_QUOTES, "UTF-8");
        $acceptJsSrc   = $sandbox
            ? "https://jstest.authorize.net/v1/Accept.js"
            : "https://js.authorize.net/v1/Accept.js";
        $captureUrl = url("/gateway/authorize/capture/{$invoiceId}");

        return <<<HTML
<form id="authnet-payment-form" class="my-3" onsubmit="return false;">
    <div class="mb-3">
        <label class="form-label">Card Number</label>
        <input type="text" id="authnet-card-number" class="form-control" placeholder="1234 5678 9012 3456" autocomplete="cc-number" />
    </div>
    <div class="row mb-3">
        <div class="col">
            <label class="form-label">Expiry (MM/YY)</label>
            <input type="text" id="authnet-card-expiry" class="form-control" placeholder="MM/YY" autocomplete="cc-exp" />
        </div>
        <div class="col">
            <label class="form-label">CVV</label>
            <input type="text" id="authnet-card-cvv" class="form-control" placeholder="123" autocomplete="cc-csc" />
        </div>
    </div>
    <div id="authnet-errors" class="text-danger mb-2 small"></div>
    <button type="button" id="authnet-submit-btn" class="btn btn-primary w-100">Pay {$amount}</button>
    <div id="authnet-message" class="mt-2"></div>
</form>
<script src="{$acceptJsSrc}" charset="utf-8"></script>
<script>
(function() {
    document.getElementById("authnet-submit-btn").addEventListener("click", function() {
        var btn     = this;
        var cardNum = document.getElementById("authnet-card-number").value.replace(/\s/g, "");
        var expiry  = document.getElementById("authnet-card-expiry").value.split("/");
        var cvv     = document.getElementById("authnet-card-cvv").value;

        if (!cardNum || expiry.length < 2 || !cvv) {
            document.getElementById("authnet-errors").textContent = "Please fill in all card fields.";
            return;
        }

        btn.disabled    = true;
        btn.textContent = "Processing...";
        document.getElementById("authnet-errors").textContent = "";

        var authData   = { clientKey: "{$safeClientKey}", apiLoginID: "{$safeLoginId}" };
        var cardData   = { cardNumber: cardNum, month: expiry[0].trim(), year: expiry[1].trim(), cardCode: cvv };
        var secureData = { authData: authData, cardData: cardData };

        Accept.dispatchData(secureData, function(response) {
            if (response.messages.resultCode === "Error") {
                var errMsg = response.messages.message.map(function(m) { return m.text; }).join(" ");
                document.getElementById("authnet-errors").textContent = errMsg;
                btn.disabled    = false;
                btn.textContent = "Pay {$amount}";
                return;
            }

            fetch("{$captureUrl}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector("meta[name=csrf-token]") ? document.querySelector("meta[name=csrf-token]").content : ""
                },
                body: JSON.stringify({
                    opaque_data: {
                        dataDescriptor: response.opaqueData.dataDescriptor,
                        dataValue:      response.opaqueData.dataValue
                    }
                })
            })
            .then(function(r) { return r.json(); })
            .then(function(result) {
                if (result.success) {
                    window.location.href = result.redirect_url || "/client/invoices/{$invoiceId}?payment=success";
                } else {
                    document.getElementById("authnet-message").innerHTML = "<div class=\"alert alert-danger\">" + (result.message || "Payment failed") + "</div>";
                    btn.disabled    = false;
                    btn.textContent = "Pay {$amount}";
                }
            })
            .catch(function() {
                document.getElementById("authnet-errors").textContent = "Network error. Please try again.";
                btn.disabled    = false;
                btn.textContent = "Pay {$amount}";
            });
        });
    });
})();
</script>
HTML;
    }

    public function processWebhook(array $data): array
    {
        // Authorize.net sends webhook with HMAC-SHA512 signature
        $payload       = $data["_raw_payload"] ?? "";
        $sigHeader     = $data["_signature_header"] ?? "";
        $txnKey        = $this->getSetting("transaction_key") ?? "";

        // r130-signature: prove who sent this before acting on it.
        //
        // The check used to run only when a signature header happened to be
        // present, so leaving the header off skipped it entirely and anyone
        // able to post here could name an invoice and have it marked paid for
        // nothing. Every other gateway refuses an unsigned call.
        if (!$payload || !$txnKey || !$sigHeader) {
            Log::warning("Authorize.net: webhook refused - no signature to check against");
            return ["success" => false, "message" => "Unsigned webhook."];
        }

        // A prefix, not a set of characters: ltrim("5a1f...", "sha512=") also
        // eats a leading 5, a, 1 or 2 from the signature itself, so genuine
        // webhooks were rejected depending on how their hash began.
        $computed = strtolower(hash_hmac("sha512", $payload, $txnKey));
        $received = strtolower(preg_replace('/^sha512=/i', "", trim($sigHeader)));

        if (!hash_equals($computed, $received)) {
            Log::warning("Authorize.net: webhook signature verification failed");
            return ["success" => false, "message" => "Invalid webhook signature."];
        }

        $eventType = $data["eventType"] ?? "";
        if ($eventType !== "net.authorize.payment.authcapture.created") {
            return ["success" => true, "message" => "Event ignored: " . $eventType];
        }

        $payload    = $data["payload"] ?? [];
        $txnId      = $payload["id"] ?? null;
        $invoiceRef = $payload["merchantReferenceId"] ?? null;
        $invoiceId  = $invoiceRef ? str_replace("INV-", "", $invoiceRef) : null;

        Log::info("Authorize.net webhook: authcapture.created", [
            "transaction_id" => $txnId,
            "invoice_id"     => $invoiceId,
        ]);

        // What was actually captured. Without this the caller falls back to the
        // invoice total, so a part capture was recorded as payment in full.
        $captured = isset($payload["authAmount"]) ? (float) $payload["authAmount"] : null;

        return [
            "success"        => true,
            "transaction_id" => $txnId,
            "invoice_id"     => $invoiceId,
            "amount"         => $captured,
            "gateway"        => "authorize",
        ];
    }
}
