<?php

namespace Modules\Gateways\Razorpay;

use App\Contracts\GatewayModuleInterface;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RazorpayModule implements GatewayModuleInterface
{
    protected string $apiUrl = 'https://api.razorpay.com/v1';

    public function getModuleName(): string
    {
        return 'Razorpay';
    }

    public function isTokenised(): bool
    {
        return false;
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'key_id', 'label' => 'Razorpay Key ID', 'type' => 'text', 'required' => true],
            ['name' => 'key_secret', 'label' => 'Razorpay Key Secret', 'type' => 'password', 'required' => true],
            ['name' => 'webhook_secret', 'label' => 'Webhook Secret', 'type' => 'password'],
            ['name' => 'test_mode', 'label' => 'Test Mode', 'type' => 'yesno', 'default' => '1'],
        ];
    }

    private function getSetting(string $key): ?string
    {
        return GatewaySettings::where('gateway', 'razorpay')->where('setting', $key)->first()?->value;
    }

    public function capture(Invoice $invoice, float $amount, array $params = []): array
    {
        $keyId = $this->getSetting('key_id');
        $keySecret = $this->getSetting('key_secret');

        if (!$keyId || !$keySecret) {
            return ['success' => false, 'message' => 'Razorpay credentials not configured.'];
        }

        $currency = strtoupper($params['currency'] ?? shop_currency_code());
        $amountPaise = (int) round($amount * 100);
        $invoiceNum = $invoice->invoice_num ?? $invoice->id;

        $response = Http::withBasicAuth($keyId, $keySecret)
            ->post("{$this->apiUrl}/orders", [
                'amount' => $amountPaise,
                'currency' => $currency,
                'receipt' => "invoice_{$invoiceNum}",
                'notes' => [
                    'invoice_id' => $invoice->id,
                    'invoice_num' => $invoiceNum,
                ],
            ]);

        if (!$response->successful()) {
            $error = $response->json('error.description', 'Unknown Razorpay error');
            Log::error('Razorpay: create order failed', ['invoice' => $invoice->id, 'error' => $error]);
            return ['success' => false, 'message' => "Razorpay error: {$error}"];
        }

        $order = $response->json();

        return [
            'success' => true,
            'order_id' => $order['id'] ?? null,
            'key_id' => $keyId,
            'amount' => $amountPaise,
            'currency' => $currency,
        ];
    }

    public function refund(string $transactionId, float $amount): array
    {
        $keyId = $this->getSetting('key_id');
        $keySecret = $this->getSetting('key_secret');

        if (!$keyId || !$keySecret) {
            return ['success' => false, 'message' => 'Razorpay credentials not configured.'];
        }

        $amountPaise = (int) round($amount * 100);

        $response = Http::withBasicAuth($keyId, $keySecret)
            ->post("{$this->apiUrl}/payments/{$transactionId}/refund", [
                'amount' => $amountPaise,
            ]);

        if (!$response->successful()) {
            $error = $response->json('error.description', 'Unknown Razorpay error');
            return ['success' => false, 'message' => "Razorpay refund error: {$error}"];
        }

        $data = $response->json();
        return [
            'success' => true,
            'refund_id' => $data['id'] ?? null,
            'transaction_id' => $transactionId,
        ];
    }

    public function getPaymentForm(Invoice $invoice): string
    {
        $keyId = htmlspecialchars($this->getSetting('key_id') ?? '', ENT_QUOTES, 'UTF-8');
        $amount = (int) round($invoice->amountDue() * 100);
        $invoiceId = (int) $invoice->id;
        $invoiceNum = $invoice->invoice_num ?? $invoice->id;
        $displayAmount = money_fmt($invoice->amountDue());
        $currency = shop_currency_code();
        $captureUrl = url("/gateway/razorpay/capture/{$invoiceId}");
        $companyName = htmlspecialchars(\App\Models\Setting::get('CompanyName', 'PNLCS'), ENT_QUOTES, 'UTF-8');

        if (!$keyId) {
            return '<div class="alert alert-danger">Razorpay is not configured.</div>';
        }

        return <<<HTML
<div class="my-3">
    <button id="rzp-pay-btn" class="btn btn-primary w-100" type="button">Pay {$displayAmount} with Razorpay</button>
    <div id="rzp-message" class="mt-2"></div>
</div>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function() {
    var csrfToken = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    document.getElementById('rzp-pay-btn').addEventListener('click', function() {
        fetch("{$captureUrl}", {
            method: "POST",
            headers: {"Content-Type":"application/json","X-CSRF-TOKEN":csrfToken}
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('rzp-message').innerHTML = '<div class="alert alert-danger">'+(data.message||'Failed')+'</div>';
                return;
            }
            var options = {
                key: "{$keyId}",
                amount: data.amount,
                currency: data.currency || "{$currency}",
                name: "{$companyName}",
                description: "Invoice #{$invoiceNum}",
                order_id: data.order_id,
                handler: function(response) {
                    fetch("{$captureUrl}", {
                        method: "POST",
                        headers: {"Content-Type":"application/json","X-CSRF-TOKEN":csrfToken},
                        body: JSON.stringify({
                            razorpay_payment_id: response.razorpay_payment_id,
                            razorpay_order_id: response.razorpay_order_id,
                            razorpay_signature: response.razorpay_signature,
                            confirm: true
                        })
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) window.location.href = res.redirect_url || "/client/invoices/{$invoiceId}?payment=success";
                        else document.getElementById('rzp-message').innerHTML = '<div class="alert alert-danger">'+(res.message||'Failed')+'</div>';
                    });
                },
                theme: { color: "#405189" }
            };
            var rzp = new Razorpay(options);
            rzp.open();
        });
    });
})();
</script>
HTML;
    }

    /**
     * Verify a client-side Razorpay checkout result before crediting an invoice.
     * Confirms the signature (proves the order/payment pair came from Razorpay
     * for our account), that the order belongs to THIS invoice, and uses the
     * amount Razorpay recorded — never a client-supplied value.
     */
    public function verifyPayment(string $orderId, string $paymentId, string $signature, int $expectedInvoiceId): array
    {
        $keyId = $this->getSetting('key_id');
        $keySecret = $this->getSetting('key_secret');
        if (!$keyId || !$keySecret) {
            return ['success' => false, 'message' => 'Razorpay credentials not configured.'];
        }
        if (!$orderId || !$paymentId || !$signature) {
            return ['success' => false, 'message' => 'Missing Razorpay payment fields.'];
        }

        $expected = hash_hmac('sha256', "{$orderId}|{$paymentId}", $keySecret);
        if (!hash_equals($expected, $signature)) {
            Log::warning('Razorpay: checkout signature verification failed', ['order' => $orderId, 'payment' => $paymentId]);
            return ['success' => false, 'message' => 'Invalid payment signature.'];
        }

        // Confirm the order belongs to this invoice and read the authoritative amount.
        $orderResp = Http::withBasicAuth($keyId, $keySecret)->get("{$this->apiUrl}/orders/{$orderId}");
        if (!$orderResp->successful()) {
            return ['success' => false, 'message' => 'Razorpay: order lookup failed.'];
        }
        $order = $orderResp->json();
        $orderInvoiceId = (int) ($order['notes']['invoice_id'] ?? 0);
        if ($orderInvoiceId !== $expectedInvoiceId) {
            Log::warning('Razorpay: order invoice mismatch', ['expected' => $expectedInvoiceId, 'actual' => $orderInvoiceId]);
            return ['success' => false, 'message' => 'Payment does not match this invoice.'];
        }
        if (($order['status'] ?? null) !== 'paid') {
            return ['success' => false, 'message' => 'Payment not completed.'];
        }

        return [
            'success'        => true,
            'transaction_id' => $paymentId,
            'amount'         => (int) ($order['amount_paid'] ?? $order['amount'] ?? 0) / 100,
        ];
    }

    public function processWebhook(array $data): array
    {
        $webhookSecret = $this->getSetting('webhook_secret');

        // Verify signature if webhook secret is set
        if ($webhookSecret) {
            $rawPayload = $data['_raw_payload'] ?? '';
            $sigHeader = $data['_signature_header'] ?? '';

            $expected = hash_hmac('sha256', $rawPayload, $webhookSecret);
            if (!hash_equals($expected, $sigHeader)) {
                Log::warning('Razorpay: webhook signature verification failed');
                return ['success' => false, 'message' => 'Invalid webhook signature.'];
            }
        }

        $event = $data['event'] ?? '';
        if ($event !== 'payment.captured') {
            return ['success' => true, 'message' => "Event ignored: {$event}"];
        }

        $payment = $data['payload']['payment']['entity'] ?? [];
        $paymentId = $payment['id'] ?? null;
        $invoiceId = $payment['notes']['invoice_id'] ?? null;
        $amountPaise = $payment['amount'] ?? 0;

        Log::info('Razorpay webhook: payment.captured', [
            'payment_id' => $paymentId,
            'invoice_id' => $invoiceId,
            'amount' => $amountPaise / 100,
        ]);

        return [
            'success' => true,
            'transaction_id' => $paymentId,
            'invoice_id' => $invoiceId,
            'amount' => $amountPaise / 100,
            'gateway' => 'razorpay',
        ];
    }
}
