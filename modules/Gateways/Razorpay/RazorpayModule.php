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

        $currency = strtoupper($params['currency'] ?? 'INR');
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
        $amount = (int) round((float) $invoice->total * 100);
        $invoiceId = (int) $invoice->id;
        $invoiceNum = $invoice->invoice_num ?? $invoice->id;
        $displayAmount = number_format((float) $invoice->total, 2);
        $captureUrl = url("/gateway/razorpay/capture/{$invoiceId}");
        $companyName = htmlspecialchars(\App\Models\Setting::get('CompanyName', 'PNLCS'), ENT_QUOTES, 'UTF-8');

        if (!$keyId) {
            return '<div class="alert alert-danger">Razorpay is not configured.</div>';
        }

        return <<<HTML
<div class="my-3">
    <button id="rzp-pay-btn" class="btn btn-primary w-100" type="button">Pay ₹{$displayAmount} with Razorpay</button>
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
                currency: data.currency || "INR",
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
