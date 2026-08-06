<?php

namespace Modules\Gateways\Mollie;

use App\Contracts\GatewayModuleInterface;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MollieModule implements GatewayModuleInterface
{
    protected string $apiUrl = 'https://api.mollie.com/v2';

    public function getModuleName(): string
    {
        return 'Mollie';
    }

    public function isTokenised(): bool
    {
        return false;
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'api_key', 'label' => 'Mollie API Key', 'type' => 'password', 'required' => true],
            ['name' => 'test_mode', 'label' => 'Test Mode', 'type' => 'yesno', 'default' => '1'],
        ];
    }

    private function getSetting(string $key): ?string
    {
        return GatewaySettings::where('gateway', 'mollie')->where('setting', $key)->first()?->value;
    }

    private function getApiKey(): string
    {
        return $this->getSetting('api_key') ?? '';
    }

    public function capture(Invoice $invoice, float $amount, array $params = []): array
    {
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            return ['success' => false, 'message' => 'Mollie API key not configured.'];
        }

        $currency = strtoupper($params['currency'] ?? shop_currency_code());
        $invoiceNum = $invoice->invoice_num ?? $invoice->id;
        $redirectUrl = $params['redirect_url'] ?? url("/client/invoices/{$invoice->id}?payment=success");
        $webhookUrl = $params['webhook_url'] ?? route('gateway.mollie.webhook');

        $response = Http::withToken($apiKey)
            ->post("{$this->apiUrl}/payments", [
                'amount' => [
                    'currency' => $currency,
                    'value' => number_format($amount, 2, '.', ''),
                ],
                'description' => "Invoice #{$invoiceNum}",
                'redirectUrl' => $redirectUrl,
                'webhookUrl' => $webhookUrl,
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'invoice_num' => $invoiceNum,
                ],
            ]);

        if (!$response->successful()) {
            $error = $response->json('detail', 'Unknown Mollie error');
            Log::error('Mollie: create payment failed', ['invoice' => $invoice->id, 'error' => $error]);
            return ['success' => false, 'message' => "Mollie error: {$error}"];
        }

        $data = $response->json();

        return [
            'success' => true,
            'payment_id' => $data['id'] ?? null,
            'checkout_url' => $data['_links']['checkout']['href'] ?? null,
            'redirect' => true,
        ];
    }

    public function refund(string $transactionId, float $amount): array
    {
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            return ['success' => false, 'message' => 'Mollie API key not configured.'];
        }

        $response = Http::withToken($apiKey)
            ->post("{$this->apiUrl}/payments/{$transactionId}/refunds", [
                'amount' => [
                    'currency' => shop_currency_code(),
                    'value' => number_format($amount, 2, '.', ''),
                ],
            ]);

        if (!$response->successful()) {
            $error = $response->json('detail', 'Unknown Mollie error');
            return ['success' => false, 'message' => "Mollie refund error: {$error}"];
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
        $amount = number_format($invoice->amountDue(), 2, '.', '');
        $display = money_fmt($invoice->total);
        $invoiceId = (int) $invoice->id;
        $captureUrl = url("/gateway/mollie/capture/{$invoiceId}");

        return <<<HTML
<div class="my-3">
    <p class="mb-2">Pay securely with Mollie (iDEAL, Credit Card, Bancontact, and more).</p>
    <form method="POST" action="{$captureUrl}">
        <input type="hidden" name="_token" value="" id="mollie-csrf">
        <button type="submit" class="btn btn-primary w-100" id="mollie-pay-btn">
            Pay {$display} with Mollie
        </button>
    </form>
</div>
<script>
document.getElementById('mollie-csrf').value = document.querySelector('meta[name=csrf-token]')?.content ?? '';
</script>
HTML;
    }

    public function processWebhook(array $data): array
    {
        $paymentId = $data['id'] ?? null;
        if (!$paymentId) {
            return ['success' => false, 'message' => 'Missing payment ID.'];
        }

        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            return ['success' => false, 'message' => 'Mollie API key not configured.'];
        }

        $response = Http::withToken($apiKey)->get("{$this->apiUrl}/payments/{$paymentId}");

        if (!$response->successful()) {
            return ['success' => false, 'message' => 'Failed to verify payment with Mollie.'];
        }

        $payment = $response->json();
        $status = $payment['status'] ?? '';

        if ($status !== 'paid') {
            return ['success' => true, 'message' => "Payment status: {$status}"];
        }

        $invoiceId = $payment['metadata']['invoice_id'] ?? null;
        $amountValue = (float) ($payment['amount']['value'] ?? 0);

        Log::info('Mollie webhook: payment paid', [
            'payment_id' => $paymentId,
            'invoice_id' => $invoiceId,
            'amount' => $amountValue,
        ]);

        return [
            'success' => true,
            'transaction_id' => $paymentId,
            'invoice_id' => $invoiceId,
            'amount' => $amountValue,
            'gateway' => 'mollie',
        ];
    }
}
