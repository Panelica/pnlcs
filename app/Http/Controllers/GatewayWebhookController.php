<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Module\ModuleRegistry;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GatewayWebhookController extends Controller
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly PaymentService $payments,
    ) {}

    /**
     * Refuse to start a payment on an invoice that is not the caller's.
     *
     * The capture endpoints are reached from the customer's own browser, so
     * the invoice has to belong to the account that is signed in. Webhooks are
     * a different matter: they arrive from the gateway and are verified by
     * signature instead.
     */
    private function authoriseInvoice(Invoice $invoice): void
    {
        $clientIds = auth()->user()?->clients()->pluck('clients.id');

        abort_if(! $clientIds || ! $clientIds->contains($invoice->client_id), 403);
    }

    /**
     * PayPal webhook endpoint.
     * URL: POST /gateway/paypal/webhook
     */
    public function paypal(Request $request)
    {
        return $this->handle("paypal", $request, $request->header("PayPal-Transmission-Sig", ""));
    }

    /**
     * Stripe webhook endpoint.
     * URL: POST /gateway/stripe/webhook
     */
    public function stripe(Request $request)
    {
        return $this->handle("stripe", $request, $request->header("Stripe-Signature", ""), true);
    }

    /**
     * Authorize.net webhook endpoint.
     * URL: POST /gateway/authorize/webhook
     */
    public function authorize(Request $request)
    {
        return $this->handle("authorize", $request, $request->header("X-ANET-Signature", ""));
    }

    /**
     * PayPal JS-SDK capture endpoint (POST /gateway/paypal/capture/{invoice}).
     * Called by the PayPal Smart Buttons JS after buyer approves.
     */
    public function paypalCapture(Request $request, Invoice $invoice)
    {
        $this->authoriseInvoice($invoice);

        $captureId = $request->input("capture_id");

        if (!$captureId) {
            return response()->json(["success" => false, "message" => "Missing capture_id."]);
        }

        // The capture_id comes from the browser — verify it against PayPal
        // before crediting anything, and trust PayPal's amount, not the client's.
        $module = $this->registry->getGatewayModule("paypal");
        if (!$module || !method_exists($module, "verifyCapture")) {
            return response()->json(["success" => false, "message" => "PayPal module not available."]);
        }

        $verified = $module->verifyCapture($captureId);
        if (!($verified["success"] ?? false)) {
            Log::warning("PayPal capture rejected", ["invoice" => $invoice->id, "reason" => $verified["message"] ?? "unknown"]);
            $this->notifyPaymentFailed($invoice, "paypal", (string) ($verified["message"] ?? "unknown"));
            return response()->json(["success" => false, "message" => $verified["message"] ?? "Payment could not be verified."]);
        }

        $this->recordTransaction($invoice, "paypal", $captureId, (float) ($verified["amount"] ?? $invoice->total));

        return response()->json([
            "success"      => true,
            "redirect_url" => route("client.invoices.show", $invoice),
        ]);
    }

    /**
     * Stripe: create PaymentIntent (POST /gateway/stripe/intent/{invoice}).
     * Returns client_secret for frontend Stripe.js confirmCardPayment().
     */
    public function stripeIntent(Request $request, Invoice $invoice)
    {
        $this->authoriseInvoice($invoice);

        $module = $this->registry->getGatewayModule("stripe");
        if (!$module) {
            return response()->json(["success" => false, "message" => "Stripe module not available."]);
        }

        // No currency override: the module charges in the currency the shop
        // sells in. A hard-coded "usd" here once charged a GBP shop's
        // customers in dollars while the module's own test stayed green.
        $result = $module->capture($invoice, $invoice->amountDue());
        return response()->json($result);
    }

    /**
     * Stripe: confirm payment after Stripe.js success (POST /gateway/stripe/confirm/{invoice}).
     */
    public function stripeConfirm(Request $request, Invoice $invoice)
    {
        $this->authoriseInvoice($invoice);

        $paymentIntentId = $request->input("payment_intent_id");
        if (!$paymentIntentId) {
            return response()->json(["success" => false, "message" => "Missing payment_intent_id."]);
        }

        // The intent id comes from the browser — verify it with Stripe and use
        // Stripe's captured amount, never the client-supplied value.
        $module = $this->registry->getGatewayModule("stripe");
        if (!$module || !method_exists($module, "verifyPaymentIntent")) {
            return response()->json(["success" => false, "message" => "Stripe module not available."]);
        }

        $verified = $module->verifyPaymentIntent($paymentIntentId, (int) $invoice->id);
        if (!($verified["success"] ?? false)) {
            Log::warning("Stripe confirm rejected", ["invoice" => $invoice->id, "reason" => $verified["message"] ?? "unknown"]);
            $this->notifyPaymentFailed($invoice, "stripe", (string) ($verified["message"] ?? "unknown"));
            return response()->json(["success" => false, "message" => $verified["message"] ?? "Payment could not be verified."]);
        }

        $this->recordTransaction($invoice, "stripe", $verified["transaction_id"] ?? $paymentIntentId, (float) ($verified["amount"] ?? $invoice->total));

        return response()->json([
            "success"      => true,
            "redirect_url" => route("client.invoices.show", $invoice),
        ]);
    }

    /**
     * Authorize.net: Accept.js capture (POST /gateway/authorize/capture/{invoice}).
     */
    public function authorizeCapture(Request $request, Invoice $invoice)
    {
        $this->authoriseInvoice($invoice);

        $module = $this->registry->getGatewayModule("authorize");
        if (!$module) {
            return response()->json(["success" => false, "message" => "Authorize.net module not available."]);
        }

        $opaqueData = $request->input("opaque_data");
        if (!$opaqueData) {
            return response()->json(["success" => false, "message" => "Missing payment data."]);
        }

        $due = $invoice->amountDue();

        $result = $module->capture($invoice, $due, ["opaque_data" => $opaqueData]);

        if ($result["success"] ?? false) {
            $this->recordTransaction($invoice, "authorize", $result["transaction_id"], $due);
            $result["redirect_url"] = route("client.invoices.show", $invoice);
        }

        return response()->json($result);
    }

    /**
     * Generic webhook dispatch — passes raw payload + signature header to the module.
     */
    private function handle(string $gateway, Request $request, string $sigHeader, bool $passRaw = false): \Illuminate\Http\Response
    {
        $module = $this->registry->getGatewayModule($gateway);
        if (!$module) {
            Log::warning("Webhook received for unknown gateway: " . $gateway);
            return response("Gateway not found", 404);
        }

        $rawPayload = $request->getContent();

        $data = array_merge(
            $request->all(),
            [
                "_raw_payload"      => $rawPayload,
                "_signature_header" => $sigHeader,
            ]
        );

        try {
            $result = $module->processWebhook($data);
        } catch (\Throwable $e) {
            Log::error("Webhook exception [{$gateway}]: " . $e->getMessage());
            return response("Webhook processing error", 500);
        }

        if (!($result["success"] ?? false)) {
            Log::warning("Webhook [{$gateway}] returned failure", $result);
            // Return 200 to prevent repeated delivery for logic-level failures
            return response("ok", 200);
        }

        // Auto-record transaction if webhook provided invoice_id + transaction_id
        $invoiceId = $result["invoice_id"] ?? null;
        $txnId     = $result["transaction_id"] ?? null;
        $amount    = $result["amount"] ?? null;

        if ($invoiceId && $txnId) {
            $invoice = Invoice::find($invoiceId);
            if ($invoice) {
                $this->recordTransaction($invoice, $gateway, $txnId, $amount ?? (float) $invoice->total);
            }
        }

        return response("ok", 200);
    }

    /**
     * Record a successful gateway payment through the central payment chain.
     * PaymentService handles idempotency, partial payments, overpayment credit,
     * and fires InvoicePaid → auto-accept order → provisioning.
     */
    /**
     * Tell somebody that a payment did not go through.
     *
     * A successful payment raises InvoicePaid and reaches whoever subscribed
     * to it. A refused one only ever reached the log file - the customer sat
     * looking at "payment could not be verified" and nobody at this end knew
     * to call them back. The invoice stays unpaid either way; the difference
     * is whether anyone finds out today or at the end of the month.
     *
     * Never allowed to break the payment return: an exception here would
     * leave the customer on a blank page after their card was charged.
     */
    private function notifyPaymentFailed(?Invoice $invoice, string $gateway, string $reason): void
    {
        try {
            $client = $invoice?->client;
            $who = $client
                ? trim(($client->first_name ?? '').' '.($client->last_name ?? '')).' <'.($client->email ?? '-').'>'
                : '-';

            app(\App\Services\NotificationService::class)->dispatch('payment.failed', [
                'event_type' => 'payment.failed',
                'subject' => 'Payment failed',
                'message' => "Invoice: ".($invoice ? ($invoice->invoice_num ?: $invoice->id) : '-')."\n"
                    ."Customer: ".$who."\n"
                    ."Gateway: ".$gateway."\n"
                    ."Reason: ".$reason,
                'invoice_id' => $invoice?->id,
                'client_id' => $invoice?->client_id,
                'gateway' => $gateway,
                'reason' => $reason,
            ]);
        } catch (\Throwable $e) {
            Log::error("Payment failure notification could not be sent: ".$e->getMessage());
        }
    }

    private function recordTransaction(Invoice $invoice, string $gateway, string $transactionId, float $amount): void
    {
        $result = $this->payments->applyPayment($invoice, $gateway, $transactionId, $amount > 0 ? $amount : null);

        Log::info("Webhook payment processed for invoice #{$invoice->id} via {$gateway}", [
            'transaction_id' => $transactionId,
            'status'         => $result['status'] ?? null,
            'balance'        => $result['balance'] ?? null,
            'duplicate'      => $result['duplicate'] ?? false,
        ]);
    }

    // ========== Mollie ==========

    public function mollie(Request $request)
    {
        $data = $request->all();
        $module = app(\App\Services\Module\ModuleRegistry::class)->getGatewayModule("mollie");
        if (!$module) return response("Mollie not configured", 500);
        $result = $module->processWebhook($data);
        if (($result["success"] ?? false) && !empty($result["invoice_id"])) {
            $invoice = \App\Models\Invoice::find($result["invoice_id"]);
            if ($invoice) {
                $this->recordTransaction($invoice, "mollie", $result["transaction_id"] ?? "", (float)($result["amount"] ?? 0));
            }
        }
        return response("OK", 200);
    }

    public function mollieCapture(Request $request, \App\Models\Invoice $invoice)
    {
        $this->authoriseInvoice($invoice);

        $module = app(\App\Services\Module\ModuleRegistry::class)->getGatewayModule("mollie");
        if (!$module) return response()->json(["success" => false, "message" => "Not configured"]);
        $result = $module->capture($invoice, $invoice->amountDue(), [
            "redirect_url" => url("/client/invoices/{$invoice->id}?payment=success"),
            "webhook_url" => route("gateway.mollie.webhook"),
        ]);
        if (($result["redirect"] ?? false) && !empty($result["checkout_url"])) {
            return redirect($result["checkout_url"]);
        }
        return response()->json($result);
    }

    // ========== Razorpay ==========

    public function razorpay(Request $request)
    {
        $data = $request->all();
        $data["_raw_payload"] = $request->getContent();
        $data["_signature_header"] = $request->header("X-Razorpay-Signature", "");
        $module = app(\App\Services\Module\ModuleRegistry::class)->getGatewayModule("razorpay");
        if (!$module) return response("Not configured", 500);
        $result = $module->processWebhook($data);
        if (($result["success"] ?? false) && !empty($result["invoice_id"])) {
            $invoice = \App\Models\Invoice::find($result["invoice_id"]);
            if ($invoice) {
                $this->recordTransaction($invoice, "razorpay", $result["transaction_id"] ?? "", (float)($result["amount"] ?? 0));
            }
        }
        return response("OK", 200);
    }

    // ========== Tpay ==========

    public function tpay(Request $request)
    {
        $module = $this->registry->getGatewayModule("tpay");
        if (!$module) {
            return response("Gateway not found", 404);
        }

        $data = array_merge($request->all(), [
            "_raw_payload"      => $request->getContent(),
            "_signature_header" => $request->header("X-JWS-Signature", ""),
        ]);

        try {
            $result = $module->processWebhook($data);
        } catch (\Throwable $e) {
            Log::error("Tpay webhook exception: " . $e->getMessage());
            return response("FALSE", 200);
        }

        if (!($result["success"] ?? false)) {
            Log::warning("Tpay webhook returned failure", $result);
            return response("FALSE", 200);
        }

        $invoiceId = $result["invoice_id"] ?? null;
        $txnId     = $result["transaction_id"] ?? null;

        if ($invoiceId && $txnId) {
            $invoice = \App\Models\Invoice::find($invoiceId);
            if ($invoice) {
                $this->recordTransaction($invoice, "tpay", $txnId, (float) ($result["amount"] ?? 0));
            }
        }

        return response("TRUE", 200);
    }

    public function tpayCapture(Request $request, \App\Models\Invoice $invoice)
    {
        $this->authoriseInvoice($invoice);

        $module = app(\App\Services\Module\ModuleRegistry::class)->getGatewayModule("tpay");
        if (!$module) {
            return response()->json(["success" => false, "message" => "Not configured"]);
        }

        $result = $module->capture($invoice, $invoice->amountDue(), [
            "redirect_url" => url("/client/invoices/{$invoice->id}?payment=success"),
            "cancel_url"   => url("/client/invoices/{$invoice->id}?payment=cancelled"),
            "webhook_url"  => route("gateway.tpay.webhook"),
        ]);

        if (($result["redirect"] ?? false) && !empty($result["checkout_url"])) {
            return redirect($result["checkout_url"]);
        }

        return response()->json($result);
    }

    public function razorpayCapture(Request $request, \App\Models\Invoice $invoice)
    {
        $this->authoriseInvoice($invoice);

        $module = app(\App\Services\Module\ModuleRegistry::class)->getGatewayModule("razorpay");
        if (!$module) return response()->json(["success" => false, "message" => "Not configured"]);
        if ($request->input("confirm")) {
            // Confirm payment — verify the Razorpay signature and order server-side
            // before crediting; never trust the browser-supplied result.
            $paymentId = $request->input("razorpay_payment_id");
            $orderId   = $request->input("razorpay_order_id");
            $signature = $request->input("razorpay_signature");

            if (!$paymentId || !$orderId || !$signature) {
                return response()->json(["success" => false, "message" => "Missing payment confirmation fields."]);
            }
            if (!method_exists($module, "verifyPayment")) {
                return response()->json(["success" => false, "message" => "Razorpay module not available."]);
            }

            $verified = $module->verifyPayment($orderId, $paymentId, $signature, (int) $invoice->id);
            if (!($verified["success"] ?? false)) {
                Log::warning("Razorpay confirm rejected", ["invoice" => $invoice->id, "reason" => $verified["message"] ?? "unknown"]);
                $this->notifyPaymentFailed($invoice, "razorpay", (string) ($verified["message"] ?? "unknown"));
                return response()->json(["success" => false, "message" => $verified["message"] ?? "Payment could not be verified."]);
            }

            $this->recordTransaction($invoice, "razorpay", $verified["transaction_id"] ?? $paymentId, (float) ($verified["amount"] ?? $invoice->total));
            return response()->json(["success" => true, "redirect_url" => url("/client/invoices/{$invoice->id}?payment=success")]);
        }
        // Create order
        $result = $module->capture($invoice, $invoice->amountDue());
        return response()->json($result);
    }
    // ========== iyzico ==========

    /**
     * iyzico: open the payment form (POST /gateway/iyzico/init/{invoice}).
     *
     * Full page, not JSON: what iyzico returns is a script, and a script
     * written into the page with innerHTML never runs.
     */
    public function iyzicoInit(Request $request, Invoice $invoice)
    {
        $this->authoriseInvoice($invoice);

        $module = $this->registry->getGatewayModule("iyzico");
        if (! $module) {
            return redirect()->route("client.invoices.show", $invoice)
                ->with("error", __("messages.iyzico.not_configured"));
        }

        $due = $invoice->amountDue();
        if ($due <= 0) {
            return redirect()->route("client.invoices.show", $invoice)
                ->with("error", __("messages.iyzico.nothing_due"));
        }

        $result = $module->capture($invoice, $due);

        if (! ($result["success"] ?? false)) {
            Log::warning("iyzico: form could not be started", [
                "invoice" => $invoice->id,
                "reason" => $result["message"] ?? "unknown",
            ]);

            return redirect()->route("client.invoices.show", $invoice)
                ->with("error", $result["message"] ?? __("messages.iyzico.init_failed"));
        }

        // Some accounts also get a ready-made payment page address back. Using
        // it is both less code and means the form opens on iyzico's own
        // domain rather than inside a page of ours.
        if (! empty($result["payment_page_url"])) {
            return redirect()->away($result["payment_page_url"]);
        }

        return response($this->iyzicoFormPage($invoice, (string) $result["checkout_form_content"]));
    }

    /**
     * The interstitial page that carries iyzico's form.
     */
    private function iyzicoFormPage(Invoice $invoice, string $formContent): string
    {
        $back = htmlspecialchars(route("client.invoices.show", $invoice), ENT_QUOTES, "UTF-8");
        $title = htmlspecialchars(__("messages.iyzico.page_title"), ENT_QUOTES, "UTF-8");
        $cancel = htmlspecialchars(__("messages.iyzico.cancel"), ENT_QUOTES, "UTF-8");
        $locale = htmlspecialchars(app()->getLocale(), ENT_QUOTES, "UTF-8");

        return <<<HTML
<!DOCTYPE html>
<html lang="{$locale}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{$title}</title>
<style>
  body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background:#f5f6f8; margin:0; padding:24px; }
  .wrap { max-width: 720px; margin: 0 auto; }
  h1 { font-size: 18px; margin: 0 0 16px; color:#222; }
  .back { display:inline-block; margin-top:20px; font-size:13px; color:#555; text-decoration:none; }
  .back:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="wrap">
  <h1>{$title}</h1>
  <div id="iyzipay-checkout-form" class="responsive"></div>
  <a class="back" href="{$back}">&larr; {$cancel}</a>
</div>
{$formContent}
</body>
</html>
HTML;
    }

    /**
     * iyzico: the payment return (POST /gateway/iyzico/callback).
     *
     * No session cookie arrives - the POST comes from iyzico's page, so
     * SameSite drops it - which is why there is no auth here. The security
     * comes from the signature instead: the token is handed back to iyzico
     * over a request signed with our own keys, and the amount is read from
     * that answer. Nothing the browser sent is trusted.
     */
    public function iyzicoCallback(Request $request)
    {
        $token = (string) $request->input("token", "");
        $module = $this->registry->getGatewayModule("iyzico");

        if (! $module || ! method_exists($module, "retrieveCheckoutForm")) {
            return $this->iyzicoReturn(null, "notconfigured");
        }

        // The initialisation record: which invoice, how much lira, at what
        // rate. What to credit comes from here, because the callback itself
        // carries no amount.
        $log = \App\Models\GatewayLog::where("gateway", "iyzico")
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '\$.token')) = ?", [$token])
            ->latest("id")
            ->first();

        $started = $log ? (json_decode((string) $log->data, true) ?: []) : [];

        $verified = $module->retrieveCheckoutForm($token);

        if (! ($verified["success"] ?? false)) {
            Log::warning("iyzico: payment could not be verified", [
                "invoice" => $started["invoice_id"] ?? null,
                "reason" => $verified["message"] ?? "unknown",
            ]);

            if ($log) {
                $log->update(["result" => "failed: ".mb_substr((string) ($verified["message"] ?? ""), 0, 200)]);
            }

            $invoice = isset($started["invoice_id"]) ? Invoice::find($started["invoice_id"]) : null;

            $this->notifyPaymentFailed($invoice, "iyzico", (string) ($verified["message"] ?? "unknown"));

            return $this->iyzicoReturn($invoice, "failed");
        }

        $invoiceId = (int) ($started["invoice_id"] ?? $verified["conversation_id"] ?? 0);
        $invoice = Invoice::find($invoiceId);

        if (! $invoice) {
            Log::error("iyzico: payment taken but no invoice found", [
                "payment_id" => $verified["payment_id"] ?? null,
                "invoice_id" => $invoiceId,
            ]);

            $this->notifyPaymentFailed(null, "iyzico", "Payment taken but no invoice found"
                ." (iyzico payment id: ".($verified["payment_id"] ?? "-").", looked for invoice: ".$invoiceId.")");

            return $this->iyzicoReturn(null, "orphan");
        }

        // Does the lira taken match the lira quoted at initialisation? If it
        // does, the shop-currency figure worked out then is what goes on the
        // invoice, so no rounding drift appears. If it does not - an
        // instalment fee, a partial payment - what iyzico actually took is
        // converted back at the same rate. Either way the record is the money.
        $taken = (float) ($verified["paid_price"] ?? 0);
        $rate = (float) ($started["rate"] ?? 0);
        $expected = (float) ($started["try_amount"] ?? 0);

        if ($expected > 0 && abs($taken - $expected) < 0.01) {
            $amount = (float) ($started["due_amount"] ?? 0);
        } elseif ($rate > 0) {
            $amount = round($taken / $rate, 2);
            Log::info("iyzico: amount taken differs from the amount quoted", [
                "invoice" => $invoice->id, "expected" => $expected, "taken" => $taken,
            ]);
        } else {
            $amount = $taken;
        }

        $this->recordTransaction($invoice, "iyzico", (string) $verified["payment_id"], $amount);

        if ($log) {
            $log->update(["result" => "paid: ".$verified["payment_id"]." / ".$taken." TRY"]);
        }

        $this->rememberIyzicoCard($invoice, $verified);

        return $this->iyzicoReturn($invoice, null);
    }

    /**
     * Keep the keys iyzico gives back when the customer stores their card.
     *
     * The card number never reaches us; what is kept is iyzico's handle for
     * it, which is what saves asking for the card again on a renewal.
     */
    private function rememberIyzicoCard(Invoice $invoice, array $verified): void
    {
        $cardUserKey = $verified["card_user_key"] ?? null;
        $cardToken = $verified["card_token"] ?? null;

        if (! $cardUserKey || ! $cardToken) {
            return;
        }

        try {
            \App\Models\PaymentMethod::updateOrCreate(
                [
                    "client_id" => $invoice->client_id,
                    "gateway_name" => "iyzico",
                    "last_four" => $verified["last_four"] ?? null,
                ],
                [
                    "description" => trim("iyzico ".($verified["card_association"] ?? "")),
                    "payment_type" => "card",
                    "remote_token" => json_encode([
                        "cardUserKey" => $cardUserKey,
                        "cardToken" => $cardToken,
                    ]),
                ]
            );
        } catch (\Throwable $e) {
            // A card that cannot be stored does not make the payment any less
            // valid; it only means the customer types their card again next
            // time.
            Log::warning("iyzico: card key could not be stored: ".$e->getMessage());
        }
    }

    /**
     * Send the customer back to their invoice.
     *
     * Two things at once. The redirect targets the top window, because the
     * form may have opened in an iframe and a plain redirect would trap the
     * customer inside it. And the outcome goes in the address bar rather than
     * the session - there is no session on this request (see the route) - so
     * the invoice page prints the message from its own session.
     */
    private function iyzicoReturn(?Invoice $invoice, ?string $status): \Illuminate\Http\Response
    {
        $url = $invoice
            ? route("client.invoices.show", $invoice)."?payment=".($status ?: "success")
            : route("client.invoices.index")."?payment=".($status ?: "success");

        $safe = htmlspecialchars($url, ENT_QUOTES, "UTF-8");
        $continue = htmlspecialchars(__("messages.iyzico.continue"), ENT_QUOTES, "UTF-8");

        return response(<<<HTML
<!DOCTYPE html><html><head><meta charset="utf-8"><title>{$continue}</title></head>
<body><script>
(function(){ var u = "{$safe}"; if (window.top !== window.self) { window.top.location.href = u; } else { window.location.href = u; } })();
</script>
<noscript><a href="{$safe}">{$continue}</a></noscript>
</body></html>
HTML);
    }
}
