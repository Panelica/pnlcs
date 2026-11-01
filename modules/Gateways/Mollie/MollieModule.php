<?php

namespace Modules\Gateways\Mollie;

use App\Contracts\GatewayModuleInterface;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MollieModule implements GatewayModuleInterface
{
    protected string $apiUrl = 'https://api.mollie.com/v2';

    /**
     * The currencies Mollie takes payments in, and the ones written without
     * decimals (https://docs.mollie.com/docs/multicurrency, fetched 2026-10-12).
     * A billing currency not on the list is not offered to Mollie: the invoice
     * is charged in the shop currency instead, as it is with the option off.
     */
    private const CURRENCIES = ['AED', 'AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'ISK', 'JPY',
        'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'RON', 'RUB', 'SEK', 'SGD', 'THB', 'TWD', 'USD', 'ZAR'];

    private const ZERO_DECIMAL = ['ISK', 'JPY'];

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
            ['name' => 'charge_billing_currency', 'label' => "Charge in the invoice's billing currency", 'type' => 'yesno', 'default' => '0',
                'description' => "An invoice billed in another currency (Setup > Currencies) is charged in that currency, at the rate on the invoice, so the customer's statement shows the amount they saw. The payment is still recorded in the shop currency. A currency Mollie does not take is charged in the shop currency."],
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

    private function decimals(string $currency): int
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL, true) ? 0 : 2;
    }

    /** An amount the way Mollie wants it written: a string with the currency's own number of decimals. */
    private function amountValue(float $amount, string $currency): string
    {
        $d = $this->decimals($currency);

        return number_format(round($amount, $d), $d, '.', '');
    }

    public function capture(Invoice $invoice, float $amount, array $params = []): array
    {
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            return ['success' => false, 'message' => 'Mollie API key not configured.'];
        }

        $currency = strtoupper($params['currency'] ?? shop_currency_code());
        $value = round($amount, $this->decimals($currency));
        $metadata = [];
        // Charged in the invoice's billing currency when the operator chose to:
        // the amount at the invoice's own rate, with what it stands for written
        // on the payment so the webhook and a refund can read it back.
        if ($charge = $this->billingCharge($invoice, $amount)) {
            $currency = $charge['currency'];
            $value = $charge['amount'];
            $metadata = ['source_currency' => $charge['source_currency'], 'source_amount' => number_format($amount, 2, '.', ''), 'rate' => (string) $charge['rate']];
        }
        $invoiceNum = $invoice->invoice_num ?? $invoice->id;
        $redirectUrl = $params['redirect_url'] ?? url("/client/invoices/{$invoice->id}?payment=success");
        $webhookUrl = $params['webhook_url'] ?? route('gateway.mollie.webhook');

        $response = Http::withToken($apiKey)
            ->post("{$this->apiUrl}/payments", [
                'amount' => [
                    'currency' => $currency,
                    'value' => $this->amountValue($value, $currency),
                ],
                'description' => "Invoice #{$invoiceNum}",
                'redirectUrl' => $redirectUrl,
                'webhookUrl' => $webhookUrl,
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'invoice_num' => $invoiceNum,
                ] + $metadata,
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

        $currency = strtoupper(shop_currency_code());
        $value = round($amount, $this->decimals($currency));
        // A payment taken in the invoice's billing currency is refunded in it:
        // the amount asked for is in the shop currency, converted at the rate
        // the payment was made with. Everything that was paid gives back exactly
        // what was taken. Looked up only for such invoices, so every other
        // refund sends what it always sent.
        if ($this->billedInAnotherCurrency($transactionId)) {
            $payment = Http::withToken($apiKey)->get("{$this->apiUrl}/payments/{$transactionId}");
            if ($payment->successful() && ($billed = $this->billedAmount((array) $payment->json(), $amount))) {
                [$currency, $value] = $billed;
            }
        }

        $response = Http::withToken($apiKey)
            ->post("{$this->apiUrl}/payments/{$transactionId}/refunds", [
                'amount' => [
                    'currency' => $currency,
                    'value' => $this->amountValue($value, $currency),
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
        // What is still due, in the currency the card will be charged in.
        $display = e($this->billingCharge($invoice, $invoice->amountDue())
            ? billing_money_fmt($invoice->amountDue(), $invoice)
            : invoice_money_fmt($invoice->amountDue(), $invoice));
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

        // Encoded, so the id asked about is the whole id that was sent: a
        // "#…" or "?…" on the end would otherwise be cut off by the HTTP
        // client and still find the payment.
        $response = Http::withToken($apiKey)->get("{$this->apiUrl}/payments/" . rawurlencode((string) $paymentId));

        if (!$response->successful()) {
            return ['success' => false, 'message' => 'Failed to verify payment with Mollie.'];
        }

        $payment = $response->json();
        $status = $payment['status'] ?? '';

        if ($status !== 'paid') {
            return ['success' => true, 'message' => "Payment status: {$status}"];
        }

        $invoiceId = $payment['metadata']['invoice_id'] ?? null;
        $amountValue = $this->recordedAmount($payment);

        Log::info('Mollie webhook: payment paid', [
            'payment_id' => $paymentId,
            'invoice_id' => $invoiceId,
            'amount' => $amountValue,
        ]);

        return [
            'success' => true,
            // Mollie's own id for the payment: one payment is one transaction,
            // however the webhook spelled it.
            'transaction_id' => (string) ($payment['id'] ?? $paymentId),
            'invoice_id' => $invoiceId,
            'amount' => $amountValue,
            'gateway' => 'mollie',
        ];
    }

    /**
     * The charge for an invoice billed in another currency, when the operator
     * chose to charge in it: the billing currency, the amount at the invoice's
     * own rate (frozen the day it was raised), and what that stands for.
     * Null when the setting is off or the invoice has no conversion.
     *
     * @return array{currency: string, amount: float, rate: float, source_currency: string}|null
     */
    private function billingCharge(Invoice $invoice, float $amount): ?array
    {
        $currency = strtoupper((string) $invoice->billing_currency);
        if ($this->getSetting('charge_billing_currency') !== '1' || ! has_billing_conversion($invoice)
            || ! in_array($currency, self::CURRENCIES, true)) {
            return null;
        }
        $rate = (float) $invoice->exchange_rate;

        return [
            'currency' => $currency,
            'amount' => round($amount * $rate, $this->decimals($currency)),
            'rate' => $rate,
            'source_currency' => strtoupper((string) ($invoice->source_currency ?: shop_currency_code())),
        ];
    }

    /**
     * What a paid payment is worth in the invoice's currency. One taken in the
     * billing currency is read back through the rate it was made with: in full
     * it is exactly the amount it stood for, a part is converted.
     */
    private function recordedAmount(array $payment): float
    {
        $value = (float) ($payment['amount']['value'] ?? 0);
        $meta = (array) ($payment['metadata'] ?? []);
        $source = strtoupper((string) ($meta['source_currency'] ?? ''));
        $rate = (float) ($meta['rate'] ?? 0);

        if ($source === '' || $rate <= 0 || $source === strtoupper((string) ($payment['amount']['currency'] ?? ''))) {
            return $value;
        }

        $sourceAmount = (float) ($meta['source_amount'] ?? 0);
        if ($sourceAmount > 0 && $value + 0.005 >= round($sourceAmount * $rate, $this->decimals((string) ($payment['amount']['currency'] ?? '')))) {
            return $sourceAmount;
        }

        return round($value / $rate, 2);
    }

    /**
     * A refund of an amount in the invoice's currency, in the currency the
     * payment was taken in: [currency, value]. The whole amount refunds what
     * was taken; never more than that. Null for a payment in the shop currency.
     *
     * @return array{0: string, 1: float}|null
     */
    private function billedAmount(array $payment, float $amount): ?array
    {
        $meta = (array) ($payment['metadata'] ?? []);
        $source = strtoupper((string) ($meta['source_currency'] ?? ''));
        $rate = (float) ($meta['rate'] ?? 0);
        $currency = strtoupper((string) ($payment['amount']['currency'] ?? ''));
        if ($source === '' || $rate <= 0 || $currency === '' || $source === $currency) {
            return null;
        }

        $taken = (float) ($payment['amount']['value'] ?? 0);
        $sourceAmount = (float) ($meta['source_amount'] ?? 0);
        $value = $sourceAmount > 0 && abs($amount - $sourceAmount) < 0.005 ? $taken : round($amount * $rate, $this->decimals($currency));

        return [$currency, $taken > 0 ? min($value, $taken) : $value];
    }

    /** Whether the invoice a transaction paid was billed in a currency other than its own. */
    private function billedInAnotherCurrency(string $transactionId): bool
    {
        $invoice = Transaction::query()->where('transaction_id', $transactionId)->whereNotNull('invoice_id')->latest('id')->first()?->invoice;

        return $invoice !== null && has_billing_conversion($invoice);
    }
}
