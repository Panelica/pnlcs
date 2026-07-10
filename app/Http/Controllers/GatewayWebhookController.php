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
        $captureId = $request->input("capture_id");
        $orderId   = $request->input("order_id");

        if (!$captureId) {
            return response()->json(["success" => false, "message" => "Missing capture_id."]);
        }

        $this->recordTransaction($invoice, "paypal", $captureId, (float) $invoice->total);

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
        $module = $this->registry->getGatewayModule("stripe");
        if (!$module) {
            return response()->json(["success" => false, "message" => "Stripe module not available."]);
        }

        $result = $module->capture($invoice, (float) $invoice->total, ["currency" => "usd"]);
        return response()->json($result);
    }

    /**
     * Stripe: confirm payment after Stripe.js success (POST /gateway/stripe/confirm/{invoice}).
     */
    public function stripeConfirm(Request $request, Invoice $invoice)
    {
        $paymentIntentId = $request->input("payment_intent_id");
        if (!$paymentIntentId) {
            return response()->json(["success" => false, "message" => "Missing payment_intent_id."]);
        }

        $this->recordTransaction($invoice, "stripe", $paymentIntentId, (float) $invoice->total);

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
        $module = $this->registry->getGatewayModule("authorize");
        if (!$module) {
            return response()->json(["success" => false, "message" => "Authorize.net module not available."]);
        }

        $opaqueData = $request->input("opaque_data");
        if (!$opaqueData) {
            return response()->json(["success" => false, "message" => "Missing payment data."]);
        }

        $result = $module->capture($invoice, (float) $invoice->total, ["opaque_data" => $opaqueData]);

        if ($result["success"] ?? false) {
            $this->recordTransaction($invoice, "authorize", $result["transaction_id"], (float) $invoice->total);
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
        $module = app(\App\Services\Module\ModuleRegistry::class)->getGatewayModule("mollie");
        if (!$module) return response()->json(["success" => false, "message" => "Not configured"]);
        $result = $module->capture($invoice, (float)$invoice->total, [
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

    public function razorpayCapture(Request $request, \App\Models\Invoice $invoice)
    {
        $module = app(\App\Services\Module\ModuleRegistry::class)->getGatewayModule("razorpay");
        if (!$module) return response()->json(["success" => false, "message" => "Not configured"]);
        if ($request->input("confirm")) {
            // Confirm payment
            $paymentId = $request->input("razorpay_payment_id");
            if ($paymentId) {
                $this->recordTransaction($invoice, "razorpay", $paymentId, (float)$invoice->total);
                return response()->json(["success" => true, "redirect_url" => url("/client/invoices/{$invoice->id}?payment=success")]);
            }
            return response()->json(["success" => false, "message" => "Missing payment ID"]);
        }
        // Create order
        $result = $module->capture($invoice, (float)$invoice->total);
        return response()->json($result);
    }
}