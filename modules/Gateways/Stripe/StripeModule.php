<?php

namespace Modules\Gateways\Stripe;

use App\Contracts\GatewayModuleInterface;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripeModule implements GatewayModuleInterface
{
    public function getModuleName(): string
    {
        return "Stripe";
    }

    public function isTokenised(): bool
    {
        return true;
    }

    public function getConfigFields(): array
    {
        return [
            ["name" => "publishable_key", "label" => "Publishable Key",         "type" => "text", "required" => true],
            ["name" => "secret_key",      "label" => "Secret Key",              "type" => "password", "required" => true],
            ["name" => "webhook_secret",  "label" => "Webhook Signing Secret",  "type" => "password"],
        ];
    }

    /** How stale a signed webhook may be, matching Stripe's own libraries. */
    private const WEBHOOK_TOLERANCE_SECONDS = 300;
    private function getSetting(string $key): ?string
    {
        $row = GatewaySettings::where("gateway", "stripe")->where("setting", $key)->first();
        return $row?->value;
    }

    public function capture(Invoice $invoice, float $amount, array $params = []): array
    {
        $secretKey = $this->getSetting("secret_key");
        if (!$secretKey) {
            return ["success" => false, "message" => "Stripe secret key not configured."];
        }

        $currency = strtolower($params["currency"] ?? shop_currency_code());
        $amountCents = (int) round($amount * 100);

        $response = Http::asForm()
            ->withToken($secretKey)
            ->post("https://api.stripe.com/v1/payment_intents", [
                "amount"                      => $amountCents,
                "currency"                    => $currency,
                "payment_method_types[]"      => "card",
                "description"                 => "Invoice #" . ($invoice->invoice_num ?? $invoice->id),
                "metadata[invoice_id]"        => $invoice->id,
                "metadata[invoice_num]"       => $invoice->invoice_num ?? $invoice->id,
            ]);

        if (!$response->successful()) {
            $error = $response->json("error.message", "Unknown error");
            Log::error("Stripe: create payment intent failed", [
                "invoice" => $invoice->id,
                "status"  => $response->status(),
                "error"   => $error,
            ]);
            return ["success" => false, "message" => "Stripe error: " . $error];
        }

        $intent = $response->json();
        return [
            "success"       => true,
            "client_secret" => $intent["client_secret"] ?? null,
            "intent_id"     => $intent["id"] ?? null,
        ];
    }

    public function refund(string $transactionId, float $amount): array
    {
        $secretKey = $this->getSetting("secret_key");
        if (!$secretKey) {
            return ["success" => false, "message" => "Stripe secret key not configured."];
        }

        $amountCents = (int) round($amount * 100);

        $response = Http::asForm()
            ->withToken($secretKey)
            ->post("https://api.stripe.com/v1/refunds", [
                "payment_intent" => $transactionId,
                "amount"         => $amountCents,
            ]);

        if (!$response->successful()) {
            $error = $response->json("error.message", "Unknown error");
            Log::error("Stripe: refund failed", [
                "transaction" => $transactionId,
                "status"      => $response->status(),
                "error"       => $error,
            ]);
            return ["success" => false, "message" => "Stripe refund error: " . $error];
        }

        $data = $response->json();
        return [
            "success"        => true,
            "refund_id"      => $data["id"] ?? null,
            "status"         => $data["status"] ?? "succeeded",
            "transaction_id" => $transactionId,
        ];
    }

    public function getPaymentForm(Invoice $invoice): string
    {
        $publishableKey = $this->getSetting("publishable_key") ?? "";
        $amount         = number_format((float) $invoice->total, 2, ".", "");
        $invoiceId      = (int) $invoice->id;

        if (!$publishableKey) {
            return "<div class=\"alert alert-danger\">Stripe is not configured. Please contact support.</div>";
        }

        $safeKey      = htmlspecialchars($publishableKey, ENT_QUOTES, "UTF-8");
        $intentUrl    = url("/gateway/stripe/intent/{$invoiceId}");
        $confirmUrl   = url("/gateway/stripe/confirm/{$invoiceId}");

        return <<<HTML
<div class="my-3">
    <div id="stripe-card-element" class="form-control p-3" style="min-height:42px;"></div>
    <div id="stripe-card-errors" class="text-danger mt-1 small"></div>
    <button id="stripe-submit-btn" class="btn btn-primary mt-3 w-100" type="button">
        Pay {$amount}
    </button>
    <div id="stripe-message" class="mt-2"></div>
</div>
<script src="https://js.stripe.com/v3/"></script>
<script>
(function() {
    var stripe  = Stripe("{$safeKey}");
    var elements = stripe.elements();
    var card    = elements.create("card", { style: { base: { fontSize: "16px" } } });
    card.mount("#stripe-card-element");

    card.addEventListener("change", function(e) {
        document.getElementById("stripe-card-errors").textContent = e.error ? e.error.message : "";
    });

    document.getElementById("stripe-submit-btn").addEventListener("click", function() {
        var btn = this;
        btn.disabled = true;
        btn.textContent = "Processing...";

        fetch("{$intentUrl}", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document.querySelector("meta[name=csrf-token]") ? document.querySelector("meta[name=csrf-token]").content : ""
            }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                document.getElementById("stripe-card-errors").textContent = data.message || "Setup failed";
                btn.disabled = false;
                btn.textContent = "Pay {$amount}";
                return;
            }
            return stripe.confirmCardPayment(data.client_secret, {
                payment_method: { card: card }
            });
        })
        .then(function(result) {
            if (!result) return;
            if (result.error) {
                document.getElementById("stripe-card-errors").textContent = result.error.message;
                btn.disabled = false;
                btn.textContent = "Pay {$amount}";
            } else if (result.paymentIntent.status === "succeeded") {
                fetch("{$confirmUrl}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document.querySelector("meta[name=csrf-token]") ? document.querySelector("meta[name=csrf-token]").content : ""
                    },
                    body: JSON.stringify({ payment_intent_id: result.paymentIntent.id })
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        window.location.href = res.redirect_url || "/client/invoices/{$invoiceId}?payment=success";
                    } else {
                        document.getElementById("stripe-message").innerHTML = "<div class=\"alert alert-danger\">" + (res.message || "Confirmation failed") + "</div>";
                        btn.disabled = false;
                        btn.textContent = "Pay {$amount}";
                    }
                });
            }
        })
        .catch(function(err) {
            document.getElementById("stripe-card-errors").textContent = "Network error. Please try again.";
            btn.disabled = false;
            btn.textContent = "Pay {$amount}";
        });
    });
})();
</script>
HTML;
    }

    /**
     * Verify a PaymentIntent server-side before crediting an invoice.
     * The intent id arrives from the browser, so confirm with Stripe that it
     * actually succeeded, that it belongs to THIS invoice, and use Stripe's
     * captured amount — never a client-supplied value.
     */
    public function verifyPaymentIntent(string $intentId, int $expectedInvoiceId): array
    {
        $secretKey = $this->getSetting("secret_key");
        if (!$secretKey) {
            return ["success" => false, "message" => "Stripe secret key not configured."];
        }

        $response = Http::withToken($secretKey)
            ->get("https://api.stripe.com/v1/payment_intents/{$intentId}");

        if (!$response->successful()) {
            return ["success" => false, "message" => "Stripe: payment intent lookup failed."];
        }

        $intent = $response->json();

        if (($intent["status"] ?? null) !== "succeeded") {
            return ["success" => false, "message" => "Payment not completed."];
        }

        $intentInvoiceId = (int) ($intent["metadata"]["invoice_id"] ?? 0);
        if ($intentInvoiceId !== $expectedInvoiceId) {
            Log::warning("Stripe: payment intent invoice mismatch", [
                "intent"   => $intentId,
                "expected" => $expectedInvoiceId,
                "actual"   => $intentInvoiceId,
            ]);
            return ["success" => false, "message" => "Payment does not match this invoice."];
        }

        return [
            "success"        => true,
            "transaction_id" => $intent["id"] ?? $intentId,
            "amount"         => (int) ($intent["amount_received"] ?? 0) / 100,
        ];
    }

    public function processWebhook(array $data): array
    {
        $webhookSecret = $this->getSetting("webhook_secret");
        $payload       = $data["_raw_payload"] ?? "";
        $sigHeader     = $data["_signature_header"] ?? "";

        // Verify Stripe webhook signature
        if ($webhookSecret && $sigHeader && $payload) {
            $parts    = [];
            $elements = explode(",", $sigHeader);
            foreach ($elements as $element) {
                $kv = explode("=", $element, 2);
                if (count($kv) === 2) {
                    $parts[$kv[0]] = $kv[1];
                }
            }

            $timestamp    = $parts["t"] ?? null;
            $sigReceived  = $parts["v1"] ?? null;
            $signedPayload = $timestamp . "." . $payload;
            $sigExpected  = hash_hmac("sha256", $signedPayload, $webhookSecret);

            if (!$timestamp || !$sigReceived || !hash_equals($sigExpected, $sigReceived)) {
                Log::warning("Stripe: webhook signature verification failed");
                return ["success" => false, "message" => "Invalid webhook signature."];
            }

            // The timestamp is signed too, so a caller cannot alter it — but a
            // signature stays valid forever unless we refuse stale ones.
            // Stripe's own libraries allow five minutes.
            if (abs(time() - (int) $timestamp) > self::WEBHOOK_TOLERANCE_SECONDS) {
                Log::warning("Stripe: webhook timestamp outside tolerance", ["timestamp" => $timestamp]);
                return ["success" => false, "message" => "Webhook timestamp is too old."];
            }
        }

        $eventType = $data["type"] ?? "";
        if ($eventType !== "payment_intent.succeeded") {
            return ["success" => true, "message" => "Event ignored: " . $eventType];
        }

        $intentObj  = $data["data"]["object"] ?? [];
        $intentId   = $intentObj["id"] ?? null;
        $invoiceId  = $intentObj["metadata"]["invoice_id"] ?? null;
        $amountCents = $intentObj["amount_received"] ?? 0;

        Log::info("Stripe webhook: payment_intent.succeeded", [
            "intent_id"  => $intentId,
            "invoice_id" => $invoiceId,
            "amount"     => $amountCents / 100,
        ]);

        return [
            "success"        => true,
            "transaction_id" => $intentId,
            "invoice_id"     => $invoiceId,
            "amount"         => $amountCents / 100,
            "gateway"        => "stripe",
        ];
    }
}
