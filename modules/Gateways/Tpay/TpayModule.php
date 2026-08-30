<?php

namespace Modules\Gateways\Tpay;

use App\Contracts\GatewayModuleInterface;
use App\Models\GatewayLog;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TpayModule implements GatewayModuleInterface
{
    public function getModuleName(): string
    {
        return 'Tpay';
    }

    public function isTokenised(): bool
    {
        return false;
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'client_id',         'label' => 'Open API Client ID',       'type' => 'text', 'required' => true],
            ['name' => 'client_secret',     'label' => 'Open API Client Secret',   'type' => 'password', 'required' => true],
            ['name' => 'confirmation_code', 'label' => 'Security Code (kod bezpieczeństwa)', 'type' => 'password'],
            ['name' => 'sandbox',           'label' => 'Sandbox Mode',             'type' => 'select', 'options' => ['0' => 'Live', '1' => 'Sandbox']],
        ];
    }

    private function getSetting(string $key): ?string
    {
        return GatewaySettings::where('gateway', 'tpay')->where('setting', $key)->first()?->value;
    }

    private function isSandbox(): bool
    {
        return $this->getSetting('sandbox') === '1';
    }

    private function getApiUrl(): string
    {
        return $this->isSandbox()
            ? 'https://openapi.sandbox.tpay.com'
            : 'https://api.tpay.com';
    }

    private function getJwsPrefix(): string
    {
        return $this->isSandbox()
            ? 'https://secure.sandbox.tpay.com'
            : 'https://secure.tpay.com';
    }

    /**
     * Record one gateway activity in the panel's system log (admin → Logs →
     * Gateway). Logging must never break a payment, so failures are swallowed.
     */
    private function logGateway(string $data, string $result = 'success'): void
    {
        try {
            GatewayLog::create([
                'gateway' => 'tpay',
                'date' => now(),
                'data' => $data,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Tpay: failed to write gateway log: ' . $e->getMessage());
        }
    }

    private function getAccessToken(): ?string
    {
        $clientId = $this->getSetting('client_id');
        $clientSecret = $this->getSetting('client_secret');

        if (!$clientId || !$clientSecret) {
            return null;
        }

        $response = Http::asForm()->post($this->getApiUrl() . '/oauth/auth', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => 'write',
        ]);

        if (!$response->successful()) {
            Log::error('Tpay: failed to obtain access token', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        return $response->json('access_token');
    }

    public function capture(Invoice $invoice, float $amount, array $params = []): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            $this->logGateway("Create transaction for invoice #{$invoice->id} failed: token request failed", 'error');
            return ['success' => false, 'message' => 'Tpay credentials not configured or token request failed.'];
        }

        $currency = strtoupper($params['currency'] ?? shop_currency_code());
        $invoiceNum = $invoice->invoice_num ?? $invoice->id;
        $redirectUrl = $params['redirect_url'] ?? url("/client/invoices/{$invoice->id}?payment=success");
        $cancelUrl = $params['cancel_url'] ?? url("/client/invoices/{$invoice->id}?payment=cancelled");
        $webhookUrl = $params['webhook_url'] ?? route('gateway.tpay.webhook');

        $payerName = trim((string) $invoice->buyer('first_name') . ' ' . (string) $invoice->buyer('last_name'));
        if ($payerName === '') {
            $payerName = trim((string) $invoice->buyer('company_name'));
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post($this->getApiUrl() . '/transactions', [
                'amount' => round($amount, 2),
                'currency' => $currency,
                'description' => "Invoice #{$invoiceNum}",
                'hiddenDescription' => 'INV-' . $invoice->id . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 8)),
                'payer' => [
                    'email' => $invoice->buyer('email') ?: null,
                    'name' => $payerName !== '' ? $payerName : ('Customer #' . $invoice->id),
                ],
                'callbacks' => [
                    'notification' => [
                        'url' => $webhookUrl,
                    ],
                    'payerUrls' => [
                        'success' => $redirectUrl,
                        'error' => $cancelUrl,
                    ],
                ],
            ]);

        if (!$response->successful()) {
            $error = $response->json('error.description', $response->json('message', 'Unknown Tpay error'));
            Log::error('Tpay: create transaction failed', [
                'invoice' => $invoice->id,
                'status' => $response->status(),
                'error' => $error,
            ]);
            $this->logGateway("Create transaction for invoice #{$invoice->id} failed: {$error}", 'error');
            return ['success' => false, 'message' => "Tpay error: {$error}"];
        }

        $data = $response->json();

        $this->logGateway("Transaction created for invoice #{$invoice->id}: " . ($data['transactionId'] ?? 'unknown'));

        return [
            'success' => true,
            'transaction_id' => $data['transactionId'] ?? null,
            'checkout_url' => $data['transactionPaymentUrl'] ?? null,
            'redirect' => true,
        ];
    }

    public function refund(string $transactionId, float $amount): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            $this->logGateway("Refund {$transactionId} failed: token request failed", 'error');
            return ['success' => false, 'message' => 'Tpay credentials not configured.'];
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post($this->getApiUrl() . '/transactions/' . rawurlencode($transactionId) . '/refunds', [
                'amount' => round($amount, 2),
            ]);

        if (!$response->successful()) {
            $error = $response->json('error.description', $response->json('message', 'Unknown Tpay error'));
            Log::error('Tpay: refund failed', [
                'transaction' => $transactionId,
                'status' => $response->status(),
                'error' => $error,
            ]);
            $this->logGateway("Refund {$transactionId} failed: {$error}", 'error');
            return ['success' => false, 'message' => "Tpay refund error: {$error}"];
        }

        $this->logGateway("Refund {$transactionId} for " . round($amount, 2) . ' processed');

        return [
            'success' => true,
            'transaction_id' => $transactionId,
        ];
    }

    public function getPaymentForm(Invoice $invoice): string
    {
        $display = money_fmt($invoice->amountDue());
        $invoiceId = (int) $invoice->id;
        $captureUrl = url("/gateway/tpay/capture/{$invoiceId}");

        return <<<HTML
<div class="my-3">
    <p class="mb-2">Pay securely with Tpay (BLIK, quick transfers, cards, and more).</p>
    <form method="POST" action="{$captureUrl}">
        <input type="hidden" name="_token" value="" id="tpay-csrf">
        <button type="submit" class="btn btn-primary w-100" id="tpay-pay-btn">
            Pay {$display} with Tpay
        </button>
    </form>
</div>
<script>
document.getElementById('tpay-csrf').value = document.querySelector('meta[name=csrf-token]')?.content ?? '';
</script>
HTML;
    }

    public function processWebhook(array $data): array
    {
        $rawPayload = $data['_raw_payload'] ?? '';
        $sigHeader = $data['_signature_header'] ?? '';

        // Tpay signs every notification; an unsigned POST must never be acted on.
        if (!$rawPayload || !$sigHeader) {
            Log::warning('Tpay: webhook refused - no signature to check against');
            $this->logGateway('Webhook rejected: unsigned request', 'error');
            return ['success' => false, 'message' => 'Unsigned webhook.'];
        }

        if (!$this->verifyJws($rawPayload, $sigHeader)) {
            Log::warning('Tpay: webhook JWS signature verification failed');
            $this->logGateway('Webhook rejected: invalid JWS signature', 'error');
            return ['success' => false, 'message' => 'Invalid webhook signature.'];
        }

        parse_str($rawPayload, $notification);

        $id = $notification['id'] ?? '';
        $trId = $notification['tr_id'] ?? '';
        $trCrc = $notification['tr_crc'] ?? '';
        $trAmount = $notification['tr_amount'] ?? '';
        $trPaid = $notification['tr_paid'] ?? '';
        $trStatus = $notification['tr_status'] ?? '';
        $md5sum = $notification['md5sum'] ?? '';

        Log::info('Tpay webhook: notification received', [
            'tr_id' => $trId,
            'tr_status' => $trStatus,
            'tr_crc' => $trCrc,
            'tr_amount' => $trAmount,
            'tr_paid' => $trPaid,
        ]);

        $code = $this->getSetting('confirmation_code') ?? '';
        $expectedMd5 = md5($id . $trId . number_format((float) $trAmount, 2, '.', '') . $trCrc . $code);

        if (!hash_equals($expectedMd5, (string) $md5sum)) {
            Log::warning('Tpay: webhook md5 checksum verification failed', ['tr_id' => $trId]);
            $this->logGateway("Webhook rejected: md5 checksum failed (tr_id={$trId})", 'error');
            return ['success' => false, 'message' => 'Invalid checksum.'];
        }

        $status = strtoupper(trim((string) $trStatus));
        if (!in_array($status, ['TRUE', 'PAID'], true)) {
            Log::info('Tpay webhook: transaction status ignored', [
                'tr_id' => $trId,
                'tr_status' => $trStatus,
            ]);
            $this->logGateway("Notification received (tr_id={$trId}, status={$trStatus})", 'ignored');
            return ['success' => true, 'message' => "Status ignored: {$trStatus}"];
        }

        $invoiceId = $this->parseInvoiceId((string) $trCrc);
        $amount = (float) ($trPaid !== '' ? $trPaid : $trAmount);

        Log::info('Tpay webhook: payment confirmed', [
            'tr_id' => $trId,
            'invoice_id' => $invoiceId,
            'amount' => $amount,
        ]);

        $this->logGateway("Payment confirmed (tr_id={$trId}, invoice={$invoiceId}, amount={$amount})", 'success');

        return [
            'success' => true,
            'transaction_id' => $trId,
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'gateway' => 'tpay',
        ];
    }

    /**
     * The invoice id from a transaction CRC ("INV-123-a1b2c3d4" -> "123").
     * The random suffix keeps hiddenDescription unique per attempt, which Tpay
     * enforces; the invoice number is what identifies the document.
     */
    private function parseInvoiceId(string $crc): ?string
    {
        if (preg_match('/INV-(\d+)/', $crc, $matches)) {
            return $matches[1];
        }

        return $crc !== '' ? $crc : null;
    }

    /**
     * Verify the RFC 7515 JWS signature Tpay signs notifications with.
     *
     * The signature header carries a compact JWS whose header names an x5u
     * certificate URL. That certificate is validated against Tpay's root CA
     * before its public key is used to check the signed payload.
     */
    private function verifyJws(string $rawPayload, string $jws): bool
    {
        $parts = explode('.', $jws);
        if (count($parts) < 3) {
            Log::warning('Tpay: webhook JWS malformed', ['parts' => count($parts)]);
            return false;
        }

        $headerB64 = $parts[0];
        $signatureB64 = $parts[2];

        $headerJson = base64_decode(strtr($headerB64, '-_', '+/'));
        $header = json_decode($headerJson, true);
        $x5u = $header['x5u'] ?? null;

        if (!$x5u) {
            Log::warning('Tpay: webhook JWS header missing x5u', ['header' => $headerJson]);
            return false;
        }

        $prefix = $this->getJwsPrefix();
        if (substr($x5u, 0, strlen($prefix)) !== $prefix) {
            Log::warning('Tpay: webhook certificate URL outside expected origin', ['x5u' => $x5u]);
            return false;
        }

        $certificate = $this->fetchUrl($x5u, 7200);
        $rootCa = $this->fetchUrl($prefix . '/x509/tpay-jws-root.pem', 86400);

        if ($certificate === null || $rootCa === null) {
            Log::warning('Tpay: webhook certificate fetch failed', [
                'x5u' => $x5u,
                'cert_len' => $certificate === null ? null : strlen($certificate),
                'root_ca_len' => $rootCa === null ? null : strlen($rootCa),
            ]);
            return false;
        }

        $x509Result = openssl_x509_verify($certificate, $rootCa);
        if ($x509Result !== 1) {
            Log::warning('Tpay: webhook certificate not signed by Tpay CA', [
                'x5u' => $x5u,
                'result' => $x509Result,
            ]);
            return false;
        }

        $publicKey = openssl_pkey_get_public($certificate);
        if (!$publicKey) {
            Log::warning('Tpay: webhook public key extraction failed', ['x5u' => $x5u]);
            return false;
        }

        $payload = str_replace('=', '', strtr(base64_encode($rawPayload), '+/', '-_'));
        $decodedSignature = base64_decode(strtr($signatureB64, '-_', '+/'));

        $result = openssl_verify(
            $headerB64 . '.' . $payload,
            $decodedSignature,
            $publicKey,
            OPENSSL_ALGO_SHA256
        );

        Log::info('Tpay: webhook JWS verification result', ['result' => $result]);

        return $result === 1;
    }

    private function fetchUrl(string $url, int $ttlSeconds): ?string
    {
        $key = 'tpay_cert_' . md5($url);

        $cached = Cache::get($key);
        if (is_string($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout(10)->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        $body = $response->body();
        if ($body !== '') {
            Cache::put($key, $body, $ttlSeconds);
        }

        return $body;
    }
}
