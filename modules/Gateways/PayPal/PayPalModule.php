<?php

namespace Modules\Gateways\PayPal;

use App\Contracts\GatewayModuleInterface;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalModule implements GatewayModuleInterface
{
    public function getModuleName(): string
    {
        return "PayPal";
    }

    public function isTokenised(): bool
    {
        return false;
    }

    public function getConfigFields(): array
    {
        return [
            ["name" => "email",         "label" => "PayPal Email",   "type" => "text"],
            ["name" => "client_id",     "label" => "Client ID",      "type" => "text", "required" => true],
            ["name" => "client_secret", "label" => "Client Secret",  "type" => "password", "required" => true],
            ["name" => "sandbox",       "label" => "Sandbox Mode",   "type" => "select", "options" => ["0" => "Live", "1" => "Sandbox"]],
        ];
    }

    private function getSetting(string $key): ?string
    {
        $row = GatewaySettings::where("gateway", "paypal")->where("setting", $key)->first();
        return $row?->value;
    }

    private function getBaseUrl(): string
    {
        return ($this->getSetting("sandbox") === "1")
            ? "https://api-m.sandbox.paypal.com"
            : "https://api-m.paypal.com";
    }

    private function getAccessToken(): ?string
    {
        $clientId     = $this->getSetting("client_id");
        $clientSecret = $this->getSetting("client_secret");

        if (!$clientId || !$clientSecret) {
            return null;
        }

        $response = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->post($this->getBaseUrl() . "/v1/oauth2/token", [
                "grant_type" => "client_credentials",
            ]);

        if (!$response->successful()) {
            Log::error("PayPal: failed to obtain access token", [
                "status" => $response->status(),
                "body"   => $response->body(),
            ]);
            return null;
        }

        return $response->json("access_token");
    }

    public function capture(Invoice $invoice, float $amount, array $params = []): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ["success" => false, "message" => "PayPal credentials not configured or token request failed."];
        }

        $currency = strtoupper($params["currency"] ?? shop_currency_code());

        $response = Http::withToken($accessToken)
            ->post($this->getBaseUrl() . "/v2/checkout/orders", [
                "intent" => "CAPTURE",
                "purchase_units" => [[
                    "reference_id" => "INV-" . $invoice->id,
                    "description"  => "Invoice #" . ($invoice->invoice_num ?? $invoice->id),
                    "amount"       => [
                        "currency_code" => $currency,
                        "value"         => number_format($amount, 2, ".", ""),
                    ],
                ]],
                "application_context" => [
                    "return_url"  => url("/client/invoices/" . $invoice->id . "?payment=success"),
                    "cancel_url"  => url("/client/invoices/" . $invoice->id . "?payment=cancelled"),
                    "brand_name"  => config("app.name", "PNLCS"),
                    "user_action" => "PAY_NOW",
                ],
            ]);

        if (!$response->successful()) {
            Log::error("PayPal: create order failed", [
                "invoice" => $invoice->id,
                "status"  => $response->status(),
                "body"    => $response->body(),
            ]);
            return [
                "success" => false,
                "message" => "PayPal order creation failed: " . $response->json("message", "Unknown error"),
            ];
        }

        $order       = $response->json();
        $orderId     = $order["id"] ?? null;
        $approveLink = null;

        foreach ($order["links"] ?? [] as $link) {
            if ($link["rel"] === "approve") {
                $approveLink = $link["href"];
                break;
            }
        }

        return [
            "success"      => true,
            "order_id"     => $orderId,
            "approve_url"  => $approveLink,
            "redirect"     => true,
            "redirect_url" => $approveLink,
        ];
    }

    public function refund(string $transactionId, float $amount): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ["success" => false, "message" => "PayPal credentials not configured."];
        }

        $response = Http::withToken($accessToken)
            ->post($this->getBaseUrl() . "/v2/payments/captures/{$transactionId}/refund", [
                "amount" => [
                    "value"         => number_format($amount, 2, ".", ""),
                    "currency_code" => shop_currency_code(),
                ],
            ]);

        if (!$response->successful()) {
            Log::error("PayPal: refund failed", [
                "transaction" => $transactionId,
                "status"      => $response->status(),
                "body"        => $response->body(),
            ]);
            return [
                "success" => false,
                "message" => "PayPal refund failed: " . $response->json("message", "Unknown error"),
            ];
        }

        $data = $response->json();
        return [
            "success"        => true,
            "refund_id"      => $data["id"] ?? null,
            "status"         => $data["status"] ?? "COMPLETED",
            "transaction_id" => $transactionId,
        ];
    }

    public function getPaymentForm(Invoice $invoice): string
    {
        $clientId  = $this->getSetting("client_id") ?? "";
        $amount    = number_format((float) $invoice->total, 2, ".", "");
        $invoiceId = (int) $invoice->id;
        $currency  = shop_currency_code();
        $display   = money_fmt($invoice->total);

        if (!$clientId) {
            return "<div class=\"alert alert-danger\">PayPal is not configured. Please contact support.</div>";
        }

        $safeClientId = htmlspecialchars($clientId, ENT_QUOTES, "UTF-8");
        $captureUrl   = url("/gateway/paypal/capture/{$invoiceId}");
        $successUrl   = url("/client/invoices/{$invoiceId}?payment=success");

        return <<<HTML
<div id="paypal-button-container" class="my-3"></div>
<div id="paypal-message" class="mt-2"></div>
<script src="https://www.paypal.com/sdk/js?client-id={$safeClientId}&currency={$currency}"></script>
<script>
paypal.Buttons({
    createOrder: function(data, actions) {
        return actions.order.create({
            purchase_units: [{ reference_id: "INV-{$invoiceId}", amount: { value: "{$amount}", currency_code: "{$currency}" } }]
        });
    },
    onApprove: function(data, actions) {
        return actions.order.capture().then(function(details) {
            var capture = details.purchase_units[0].payments.captures[0];
            fetch("{$captureUrl}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector("meta[name=csrf-token]") ? document.querySelector("meta[name=csrf-token]").content : ""
                },
                body: JSON.stringify({ order_id: data.orderID, capture_id: capture.id })
            })
            .then(function(r) { return r.json(); })
            .then(function(result) {
                if (result.success) {
                    window.location.href = result.redirect_url || "{$successUrl}";
                } else {
                    document.getElementById("paypal-message").innerHTML = "<div class=\"alert alert-danger\">" + (result.message || "Payment failed") + "</div>";
                }
            });
        });
    },
    onError: function(err) {
        document.getElementById("paypal-message").innerHTML = "<div class=\"alert alert-danger\">PayPal error: " + err + "</div>";
    }
}).render("#paypal-button-container");
</script>
HTML;
    }

    /**
     * Verify a capture directly against the PayPal API.
     *
     * The webhook payload and the browser-supplied capture_id are both
     * untrusted — anyone can POST a fake PAYMENT.CAPTURE.COMPLETED. We
     * re-fetch the capture from PayPal and only trust its status/amount.
     *
     * @return array{success: bool, message?: string, amount?: float, currency?: string, invoice_ref?: ?string}
     */
    public function verifyCapture(string $captureId): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ["success" => false, "message" => "PayPal credentials not configured."];
        }

        $response = Http::withToken($accessToken)
            ->get($this->getBaseUrl() . "/v2/payments/captures/" . urlencode($captureId));

        if (!$response->successful()) {
            Log::warning("PayPal: capture verification request failed", [
                "capture_id" => $captureId,
                "status"     => $response->status(),
            ]);
            return ["success" => false, "message" => "Could not verify capture with PayPal."];
        }

        $status = $response->json("status");
        if ($status !== "COMPLETED") {
            return ["success" => false, "message" => "Capture status is '{$status}', not COMPLETED."];
        }

        return [
            "success"     => true,
            "amount"      => (float) $response->json("amount.value", 0),
            "currency"    => $response->json("amount.currency_code"),
            "invoice_ref" => $response->json("invoice_id") ?? $response->json("custom_id"),
        ];
    }

    public function processWebhook(array $data): array
    {
        $eventType = $data["event_type"] ?? "";

        if ($eventType !== "PAYMENT.CAPTURE.COMPLETED") {
            return ["success" => true, "message" => "Event ignored: " . $eventType];
        }

        $resource   = $data["resource"] ?? [];
        $captureId  = $resource["id"] ?? null;
        $invoiceRef = $resource["purchase_units"][0]["reference_id"] ?? null;

        if (!$captureId || !$invoiceRef) {
            return ["success" => false, "message" => "Missing capture ID or invoice reference."];
        }

        // Never trust the webhook body — confirm the capture with PayPal.
        $verified = $this->verifyCapture($captureId);
        if (!($verified["success"] ?? false)) {
            Log::warning("PayPal webhook rejected — capture not verified", [
                "capture_id" => $captureId,
                "reason"     => $verified["message"] ?? "unknown",
            ]);
            return ["success" => false, "message" => $verified["message"] ?? "Capture verification failed."];
        }

        $invoiceId = str_replace("INV-", "", (string) $invoiceRef);

        Log::info("PayPal webhook: PAYMENT.CAPTURE.COMPLETED verified", [
            "capture_id" => $captureId,
            "invoice_id" => $invoiceId,
            "amount"     => $verified["amount"],
        ]);

        return [
            "success"        => true,
            "transaction_id" => $captureId,
            "invoice_id"     => $invoiceId,
            "amount"         => $verified["amount"],
            "gateway"        => "paypal",
        ];
    }
}
