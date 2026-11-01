<?php

declare(strict_types=1);

namespace Modules\Gateways\Monobank;

use App\Contracts\GatewayModuleInterface;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

/** PNLCS hosted Monobank Acquiring gateway, USD/UAH invoices paid in UAH. */
final class MonobankModule implements GatewayModuleInterface
{
    private const API = 'https://api.monobank.ua/api/merchant';
    private const TABLE = 'pnlcs_monobank_sessions';
    private const VALIDITY_SECONDS = 900;
    private const RATE_SCALE = 100000000;
    /** Sanity window for USD/UAH. A feed value outside it is treated as broken, never used for pricing. */
    private const RATE_MIN = 5.0;
    private const RATE_MAX = 500.0;
    /** An unanswered refund request younger than this may still be in flight at the bank. */
    private const REFUND_RECHECK_SECONDS = 300;

    public function getModuleName(): string { return 'Monobank'; }
    public function isTokenised(): bool { return false; }

    public function getConfigFields(): array
    {
        // The token itself determines test/production mode. No misleading test-mode switch.
        return [
            ['name' => 'api_token', 'label' => 'Monobank Merchant API token (test or live)', 'type' => 'password', 'required' => true],
        ];
    }

    private function token(): string
    {
        return (string) (GatewaySettings::where('gateway', 'monobank')->where('setting', 'api_token')->first()?->value ?? '');
    }

    public function hasToken(): bool
    {
        return $this->token() !== '';
    }

    private function request()
    {
        return Http::withHeaders(['X-Token' => $this->token(), 'X-Cms' => 'PNLCS', 'X-Cms-Version' => '0.5.2'])
            ->acceptJson()->timeout(15)->connectTimeout(5);
    }

    /** Never store the merchant token. Previously issued SHA-256 bindings remain readable. */
    private function tokenHash(): string
    {
        $key = (string) config('app.key', '');
        if ($key === '') throw new \RuntimeException('APP_KEY must be configured');
        return hash_hmac('sha256', $this->token(), $key);
    }

    private function matchesMerchant($record): bool
    {
        $stored = (string) ($record->merchant_token_hash ?? '');
        return $stored !== '' && (
            hash_equals($stored, $this->tokenHash()) ||
            hash_equals($stored, hash('sha256', $this->token()))
        );
    }

    private function rateSell(): ?string
    {
        $fresh = 'pnlcs:monobank:usd-uah:rate-sell:v4';
        $fallback = 'pnlcs:monobank:usd-uah:last-known:v4';
        try {
            $rate = Cache::remember($fresh, now()->addMinutes(5), function () use ($fallback) {
                $response = Http::acceptJson()->timeout(12)->connectTimeout(5)
                    ->get('https://api.monobank.ua/bank/currency');
                if (!$response->successful() || !is_array($response->json())) {
                    throw new \RuntimeException('Exchange-rate API unavailable');
                }
                foreach ($response->json() as $item) {
                    if (($item['currencyCodeA'] ?? null) == 840 && ($item['currencyCodeB'] ?? null) == 980) {
                        $sell = $item['rateSell'] ?? null;
                        if ($this->validRate($sell)) {
                            $decimal = number_format((float) $sell, 8, '.', '');
                            Cache::put($fallback, $decimal, now()->addMinutes(60));
                            return $decimal;
                        }
                    }
                }
                throw new \RuntimeException('USD/UAH rateSell missing');
            });
            return is_string($rate) && $this->validRate($rate) ? $rate : null;
        } catch (\Throwable $e) {
            Log::warning('Monobank FX rate unavailable', ['error' => $e->getMessage()]);
            $last = Cache::get($fallback);
            return is_string($last) && $this->validRate($last) ? $last : null;
        }
    }

    private function validRate(mixed $rate): bool
    {
        return is_numeric($rate) && is_finite((float) $rate)
            && (float) $rate >= self::RATE_MIN && (float) $rate <= self::RATE_MAX;
    }

    /** Integer-only conversion from original cents to UAH kopiykas. */
    private function convertedMinor(int $invoiceMinor, string $rate): int
    {
        if ($invoiceMinor <= 0 || $invoiceMinor > 100000000) {
            throw new \InvalidArgumentException('Invoice amount outside supported range');
        }
        if (!preg_match('/^([0-9]{1,5})\.([0-9]{8})$/D', $rate, $m)) {
            throw new \InvalidArgumentException('Invalid FX quote');
        }
        $whole = (int) $m[1];
        $fraction = (int) $m[2];
        $result = $invoiceMinor * $whole
            + intdiv($invoiceMinor * $fraction + intdiv(self::RATE_SCALE, 2), self::RATE_SCALE);
        if ($result <= 0) throw new \InvalidArgumentException('Converted amount must be positive');
        return $result;
    }

    /**
     * PNLCS keeps invoice amounts in the SHOP (base) currency; a customer's own currency only changes how
     * the amount is displayed. Charging therefore follows the shop currency and NEVER the client's currency:
     * a client switching their account to UAH must not turn "50 USD" into "50 UAH".
     */
    private function invoiceCurrency(Invoice $invoice): ?string
    {
        $shop = strtoupper(trim((string) shop_currency_code()));
        if (!in_array($shop, ['USD', 'UAH'], true)) {
            return null;
        }
        // If an invoice ever carries its own explicit currency that differs, fail closed.
        $own = $invoice->currency_code ?? null;
        if (is_string($own) && trim($own) !== '' && strtoupper(trim($own)) !== $shop) {
            return null;
        }
        return $shop;
    }

    private function appUrl(string $routeName, $params): string
    {
        $base = rtrim((string) config('app.url'), '/');
        if (!preg_match('~^https://[a-z0-9.-]+(?::[0-9]+)?$~iD', $base)) {
            throw new \RuntimeException('APP_URL must be a trusted HTTPS origin');
        }
        $path = route($routeName, $params, false);
        if (!is_string($path) || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
            throw new \RuntimeException('Unsafe generated route');
        }
        return $base.$path;
    }

    /** Checkout URL validation shared by the gateway and its controller. */
    public static function isAllowedCheckoutUrl(mixed $url): bool
    {
        return is_string($url) && strlen($url) < 2048
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && in_array(parse_url($url, PHP_URL_HOST), ['pay.monobank.ua', 'pay.monk.biz', 'pay.mbnk.biz'], true)
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_PASS) === null
            && parse_url($url, PHP_URL_PORT) === null;
    }

    private function trustedCheckoutUrl($url): bool
    {
        return self::isAllowedCheckoutUrl($url);
    }

    public function capture(Invoice $invoice, float $amount, array $params = []): array
    {
        if ($this->token() === '') return ['success' => false, 'message' => 'Merchant token is not configured'];
        $currency = $this->invoiceCurrency($invoice);
        if ($currency === null) return ['success' => false, 'message' => 'Unsupported or unknown invoice currency'];
        if (!is_finite($amount) || $amount <= 0 || abs($amount - (float) $invoice->amountDue()) > 0.001) {
            return ['success' => false, 'message' => 'Invalid invoice amount'];
        }

        try {
            $minor = (int) round($amount * 100);
            if ($minor <= 0) throw new \InvalidArgumentException('Invalid amount');
            // Reuse only a persistent, unexpired checkout with the same amount and merchant.
            $previous = DB::table(self::TABLE)
                ->where('invoice_id', (int) $invoice->id)
                ->where('status', 'created')
                ->where('invoice_amount_minor', $minor)
                ->where('invoice_currency', $currency)
                ->where('expires_at', '>', now()->addSeconds(30))
                ->orderByDesc('id')->first();
            if ($previous && $this->matchesMerchant($previous)
                && $this->trustedCheckoutUrl($previous->checkout_url ?? null)) {
                return ['success' => true, 'redirect' => true, 'checkout_url' => $previous->checkout_url,
                    'payment_id' => $previous->mono_invoice_id];
            }

            $rate = $currency === 'USD' ? $this->rateSell() : '1.00000000';
            if ($rate === null) return ['success' => false, 'message' => 'Monobank exchange rate unavailable'];
            $paymentMinor = $this->convertedMinor($minor, $rate);
            $response = $this->request()->post(self::API.'/invoice/create', [
                'amount' => $paymentMinor,
                'ccy' => 980,
                'validity' => self::VALIDITY_SECONDS,
                'merchantPaymInfo' => [
                    'reference' => 'pnlcs-invoice-'.$invoice->id,
                    'destination' => 'PNLCS invoice #'.$invoice->id,
                ],
                'redirectUrl' => $this->appUrl('client.invoices.show', $invoice),
                'webHookUrl' => $this->appUrl('gateway.monobank.webhook', []),
            ]);
            $monoId = $response->json('invoiceId');
            $pageUrl = $response->json('pageUrl');
            if (!$response->successful() || !is_string($monoId) || $monoId === '' || strlen($monoId) > 128
                || !$this->trustedCheckoutUrl($pageUrl)) {
                return ['success' => false, 'message' => 'Monobank returned invalid payment link'];
            }
            DB::table(self::TABLE)->insert([
                'mono_invoice_id' => $monoId,
                'invoice_id' => (int) $invoice->id,
                'invoice_currency' => $currency,
                'invoice_amount_minor' => $minor,
                'payment_currency' => 980,
                'payment_amount_minor' => $paymentMinor,
                'exchange_rate' => $rate,
                'merchant_token_hash' => $this->tokenHash(),
                'checkout_url' => $pageUrl,
                'expires_at' => now()->addSeconds(self::VALIDITY_SECONDS),
                'status' => 'created',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return ['success' => true, 'redirect' => true, 'checkout_url' => $pageUrl, 'payment_id' => $monoId];
        } catch (\Throwable $e) {
            Log::error('Monobank checkout failed', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Could not create checkout; please try again or contact support'];
        }
    }

    public function getPaymentForm(Invoice $invoice): string
    {
        // The checkout and webhook routes come from MonobankServiceProvider (registered by PNLCS through the
        // manifest's "provider"). On a PNLCS without provider support they do not exist: never show a dead button.
        if (!Route::has('gateway.monobank.capture') || !Route::has('gateway.monobank.webhook')) {
            Log::error('Monobank routes are not registered: PNLCS release without module service providers, or stale route cache (php artisan optimize)');
            return '<div class="alert alert-warning my-3">Monobank payment is temporarily unavailable.</div>';
        }
        // Relative action: works on any host the customer is browsing and never throws while rendering the page.
        $url = e(route('gateway.monobank.capture', ['invoice' => (int) $invoice->id], false));
        $amount = e(invoice_money_fmt($invoice->amountDue(), $invoice));
        $csrf = e(csrf_token());
        return <<<HTML
<div class="my-3">
  <form method="POST" action="{$url}" target="_blank" rel="noopener">
    <input type="hidden" name="_token" value="{$csrf}" class="monobank-csrf">
    <button class="btn btn-primary w-100" type="submit">Pay {$amount} with Monobank</button>
  </form>
</div>
<script>
(function () {
    document.querySelectorAll('.monobank-csrf').forEach(function (el) {
        const fresh = document.querySelector('meta[name="csrf-token"]')?.content;
        if (fresh) el.value = fresh; // keep the server-rendered token when the page has no meta tag
        const form = el.closest('form');
        if (!form) return;
        const key = 'pnlcs.monobank.pending.' + location.pathname;
        form.addEventListener('submit', function () {
            try { sessionStorage.setItem(key, String(Date.now())); } catch (err) {}
            const button = form.querySelector('button[type=submit]');
            if (button) {
                button.disabled = true;
                button.textContent = 'Opening Monobank...';
            }
        });
        let refreshing = false;
        function refreshAfterReturn() {
            if (refreshing || document.hidden) return;
            let started = 0;
            try { started = Number(sessionStorage.getItem(key) || 0); } catch (err) {}
            if (!started || Date.now() - started < 3000 || Date.now() - started > 86400000) return;
            refreshing = true;
            try { sessionStorage.removeItem(key); } catch (err) {}
            // A UI refresh only: server-side payment credit is webhook-driven.
            setTimeout(function () { location.reload(); }, 1200);
        }
        window.addEventListener('focus', refreshAfterReturn);
        document.addEventListener('visibilitychange', refreshAfterReturn);
        window.addEventListener('pageshow', refreshAfterReturn);
    });
})();
</script>
HTML;
    }

    /** Verify before parsing. Capture and signed webhook share one strict session validator. */
    public function processWebhook(array $data): array
    {
        $raw = $data['_raw_payload'] ?? null;
        $signature = $this->headerValue($data['_headers'] ?? null, 'x-sign');
        if (!is_string($raw) || $signature === null || !$this->verify($raw, $signature)) {
            return ['success' => false, 'response' => 'Invalid signature', 'http_status' => 403];
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload)) return ['success' => false, 'response' => 'Invalid JSON', 'http_status' => 422];
        return $this->parseProviderPayment($payload);
    }

    /**
     * Whole webhook flow for the module-owned route: verify signature, then settle through PNLCS PaymentService.
     * Returns the HTTP body/status for Monobank. Everything beyond a verified, matching payment answers 2xx
     * (so the bank stops retrying) except temporary errors, which answer 5xx so it retries.
     *
     * @return array{body:string,status:int}
     */
    public function handleWebhookRequest(array $data): array
    {
        $result = $this->processWebhook($data);
        if (!($result['success'] ?? false)) {
            return ['body' => (string) ($result['response'] ?? 'OK'), 'status' => (int) ($result['http_status'] ?? 200)];
        }
        $settled = $this->settleVerifiedWebhook($result, function ($invoice, $txnId, $amount) {
            return app(\App\Services\PaymentService::class)->applyPayment($invoice, 'monobank', $txnId, $amount);
        });
        if (!($settled['success'] ?? false)) {
            return ['body' => (string) ($settled['response'] ?? 'Temporary settlement error'), 'status' => (int) ($settled['http_status'] ?? 503)];
        }
        return ['body' => 'OK', 'status' => 200];
    }

    /** True when the user belongs to the client that owns the invoice (same rule as the core's checkout routes). */
    public static function ownsInvoice(?iterable $userClientIds, mixed $invoiceClientId): bool
    {
        if ($userClientIds === null || $invoiceClientId === null) {
            return false;
        }
        foreach ($userClientIds as $id) {
            if ((string) $id === (string) $invoiceClientId) {
                return true;
            }
        }
        return false;
    }

    /** Only called with verified Monobank signature or authenticated /invoice/status response. */
    private function parseProviderPayment(array $payload): array
    {
        $status = $payload['status'] ?? null;
        if (!in_array($status, ['success', 'reversed', 'processing', 'created', 'failure', 'expired', 'hold'], true)) {
            return ['success' => false, 'response' => 'OK'];
        }
        $monoId = $payload['invoiceId'] ?? null;
        if (!is_string($monoId) || $monoId === '' || strlen($monoId) > 128) {
            return ['success' => false, 'response' => 'Invalid invoiceId', 'http_status' => 422];
        }
        if (!in_array($status, ['success', 'reversed'], true)) {
            return ['success' => false, 'response' => 'OK'];
        }
        try {
            $record = DB::table(self::TABLE)->where('mono_invoice_id', $monoId)->first();
            if ($record === null) $record = $this->migrateLegacySession($monoId);
        } catch (\Throwable $e) {
            Log::error('Monobank session lookup failed', ['mono_invoice_id' => $monoId, 'error' => $e->getMessage()]);
            return ['success' => false, 'response' => 'Temporary storage error', 'http_status' => 503];
        }
        if ($record === null) return ['success' => false, 'response' => 'Payment session missing', 'http_status' => 503];

        $ref = $payload['reference'] ?? ($payload['merchantPaymInfo']['reference'] ?? null);
        $expectedRef = 'pnlcs-invoice-'.$record->invoice_id;
        if (!is_string($ref) || !hash_equals($expectedRef, $ref)
            || !is_int($payload['amount'] ?? null)
            || (int) $record->payment_amount_minor !== $payload['amount']
            || (int) ($payload['ccy'] ?? 0) !== 980
            || (int) $record->payment_currency !== 980) {
            return ['success' => false, 'response' => 'Invoice mismatch', 'http_status' => 422];
        }
        // The provider signature and our own random invoiceId already bind this payment to us. A token that
        // changed since the session was created (rotation) must not strand a payment the customer made.
        if (!$this->matchesMerchant($record)) {
            Log::warning('Monobank payment confirmed under a different merchant token than the session was created with', ['mono_invoice_id' => $monoId]);
        }
        if (isset($record->invoice_currency) && !in_array($record->invoice_currency, ['USD', 'UAH'], true)) {
            return ['success' => false, 'response' => 'Invalid stored currency', 'http_status' => 422];
        }
        if (Invoice::find((int) $record->invoice_id) === null) {
            return ['success' => false, 'response' => 'Invoice not found', 'http_status' => 422];
        }
        $modifiedAt = $this->modifiedDate($payload);
        $cancelled = null;
        $partial = false;
        foreach ((array) ($payload['cancelList'] ?? []) as $cancel) {
            if (is_array($cancel) && in_array($cancel['status'] ?? null, ['success', 'processing'], true)) {
                $cancelAmount = $cancel['amount'] ?? null;
                if (is_int($cancelAmount) && $cancelAmount > 0 && $cancelAmount < (int) $record->payment_amount_minor) {
                    $partial = true; // part of the money already left through Monobank directly
                    continue;
                }
                $cancelled = (string) $cancel['status'];
                break;
            }
        }
        if ($partial && $cancelled === null && $status === 'success') {
            // Neither a full credit (over-credits what was really received) nor a full reversal is right:
            // stop automation and let a person decide. The row leaves the reconciliation set.
            try {
                DB::table(self::TABLE)->where('mono_invoice_id', $monoId)->update([
                    'status' => 'partial_refund',
                    'last_provider_modified_at' => $modifiedAt ?? ($record->last_provider_modified_at ?? null),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::error('Monobank partial-refund storage failed', ['mono_invoice_id' => $monoId, 'error' => $e->getMessage()]);
                return ['success' => false, 'response' => 'Temporary storage error', 'http_status' => 503];
            }
            Log::error('Monobank PARTIAL REFUND made outside PNLCS: automatic crediting blocked, reconcile the invoice manually', [
                'invoice_id' => $record->invoice_id, 'mono_invoice_id' => $monoId,
            ]);
            return ['success' => false, 'response' => 'OK'];
        }
        if ($status === 'reversed' || $cancelled !== null) {
            try {
                DB::transaction(function () use ($monoId, $modifiedAt, $status, $cancelled) {
                    $row = DB::table(self::TABLE)->where('mono_invoice_id', $monoId)->lockForUpdate()->first();
                    if (!$row) throw new \RuntimeException('Session missing during reversal');
                    $refunded = $status === 'reversed' || $cancelled === 'success';
                    DB::table(self::TABLE)->where('mono_invoice_id', $monoId)->update([
                        'status' => $refunded ? 'refunded' : 'refund_pending',
                        'refund_status' => $refunded ? 'success' : 'processing',
                        'refunded_at' => $refunded ? now() : ($row->refunded_at ?? null),
                        'refund_requested_at' => $row->refund_requested_at ?? now(),
                        'last_provider_modified_at' => $modifiedAt ?? ($row->last_provider_modified_at ?? null),
                        'updated_at' => now(),
                    ]);
                });
                // We intentionally do not mutate PNLCS payment ledger. Review credit and provisioning.
                Log::warning('Monobank payment reversed; reconcile PNLCS account/benefits', [
                    'invoice_id' => $record->invoice_id, 'mono_invoice_id' => $monoId,
                ]);
            } catch (\Throwable $e) {
                Log::error('Monobank reversal storage failed', ['invoice_id' => $record->invoice_id, 'error' => $e->getMessage()]);
                return ['success' => false, 'response' => 'Temporary storage error', 'http_status' => 503];
            }
            return ['success' => false, 'response' => 'OK'];
        }
        return [
            'success' => true,
            'invoice_id' => (int) $record->invoice_id,
            'mono_invoice_id' => $monoId,
            'transaction_id' => 'monobank:'.$monoId,
            'amount' => ((int) $record->invoice_amount_minor) / 100,
            'modified_at' => $modifiedAt,
            'response' => 'OK',
        ];
    }

    /** Symfony's HeaderBag yields lists of values; other callers yield strings. Accept both, any case. */
    private function headerValue(mixed $headers, string $name): ?string
    {
        if (!is_array($headers)) return null;
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) !== $name) continue;
            if (is_array($value)) $value = $value[0] ?? null;
            return is_string($value) && $value !== '' ? $value : null;
        }
        return null;
    }

    private function modifiedDate(array $payload): ?string
    {
        $value = $payload['modifiedDate'] ?? null;
        if (!is_string($value) || strlen($value) > 40) return null;
        try { return Carbon::parse($value)->utc()->toDateTimeString(); }
        catch (\Throwable $e) { return null; }
    }

    /**
     * Must be invoked by the controller INSTEAD of ordinary recordTransaction().
     * Locks a session until PNLCS PaymentService commits; duplicate callbacks
     * (including ones after refund) can never credit twice through this path.
     */
    public function settleVerifiedWebhook(array $result, callable $applyPayment): array
    {
        if (!($result['success'] ?? false) || empty($result['mono_invoice_id'])) {
            return ['success' => false, 'http_status' => 422];
        }
        try {
            return DB::transaction(function () use ($result, $applyPayment) {
                $monoId = (string) $result['mono_invoice_id'];
                $row = DB::table(self::TABLE)->where('mono_invoice_id', $monoId)->lockForUpdate()->first();
                if (!$row || (int) $row->invoice_id !== (int) $result['invoice_id']) {
                    return ['success' => false, 'http_status' => 422];
                }
                if (!$this->matchesMerchant($row)) {
                    Log::warning('Monobank settlement under a different merchant token than the session was created with', ['mono_invoice_id' => $monoId]);
                }
                if ($row->refund_requested_at !== null || ($row->refunded_at ?? null) !== null
                    || in_array($row->status ?? '', ['refund_pending', 'refunded'], true)) {
                    return ['success' => true, 'http_status' => 200, 'duplicate' => true, 'refunded' => true];
                }
                if (($row->status ?? '') === 'partial_refund') {
                    // Parked for a human: an old "success" notification (without the cancelList) must never credit it.
                    Log::error('Monobank success notification ignored for a partially refunded session', [
                        'mono_invoice_id' => $monoId, 'invoice_id' => $row->invoice_id,
                    ]);
                    return ['success' => true, 'http_status' => 200, 'duplicate' => true, 'blocked' => 'partial_refund'];
                }
                if ($row->paid_at !== null || ($row->status ?? '') === 'paid') {
                    return ['success' => true, 'http_status' => 200, 'duplicate' => true];
                }
                if (($result['modified_at'] ?? null) && ($row->last_provider_modified_at ?? null)
                    && $result['modified_at'] < $row->last_provider_modified_at) {
                    return ['success' => true, 'http_status' => 200, 'stale' => true];
                }
                $invoice = Invoice::find((int) $row->invoice_id);
                if (!$invoice) return ['success' => false, 'http_status' => 503];
                $outcome = $applyPayment($invoice, $result['transaction_id'], (float) $result['amount']);
                if (!is_array($outcome) || !($outcome['success'] ?? false)) {
                    throw new \RuntimeException('PNLCS PaymentService did not confirm payment');
                }
                DB::table(self::TABLE)->where('mono_invoice_id', $monoId)->update([
                    'status' => 'paid', 'paid_at' => now(), 'updated_at' => now(),
                    'last_provider_modified_at' => $result['modified_at'] ?? ($row->last_provider_modified_at ?? null),
                ]);
                return ['success' => true, 'http_status' => 200, 'duplicate' => (bool) ($outcome['duplicate'] ?? false)];
            }, 3);
        } catch (\Throwable $e) {
            Log::error('Monobank settlement failed; retry/reconciliation required', [
                'mono_invoice_id' => $result['mono_invoice_id'], 'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'response' => 'Temporary settlement error', 'http_status' => 503];
        }
    }

    /** Previously issued v0.2.x sessions remain usable while their file cache survives. */
    private function migrateLegacySession(string $monoId): ?object
    {
        $legacyKey = 'monobank:acquiring:payment:'.hash('sha256', $monoId);
        $old = Cache::store('file')->get($legacyKey);
        if (!is_array($old) ||
            !is_int($old['invoice_id'] ?? null) ||
            !is_int($old['invoice_amount_minor'] ?? null) ||
            !is_int($old['payment_amount_minor'] ?? null) ||
            !in_array($old['invoice_currency'] ?? null, ['USD', 'UAH'], true) ||
            (int) ($old['payment_currency'] ?? 0) !== 980 ||
            $old['invoice_amount_minor'] <= 0 || $old['payment_amount_minor'] <= 0 ||
            !is_numeric($old['rate'] ?? null)) {
            return null;
        }
        DB::table(self::TABLE)->insertOrIgnore([
            'mono_invoice_id' => $monoId,
            'invoice_id' => $old['invoice_id'],
            'invoice_currency' => $old['invoice_currency'],
            'invoice_amount_minor' => $old['invoice_amount_minor'],
            'payment_currency' => 980,
            'payment_amount_minor' => $old['payment_amount_minor'],
            'exchange_rate' => (float) $old['rate'],
            'merchant_token_hash' => $this->tokenHash(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return DB::table(self::TABLE)->where('mono_invoice_id', $monoId)->first();
    }

    private function verify(string $raw, string $encodedSignature): bool
    {
        if (strlen($encodedSignature) > 4096 || strlen($raw) > 65536 || $this->token() === '') return false;
        $signature = base64_decode($encodedSignature, true);
        if (!is_string($signature) || $signature === '') return false;
        $keyName = 'monobank:acquiring:public-key:v4:'.$this->tokenHash();
        $key = Cache::get($keyName);
        $cold = !is_string($key) || $key === '';
        if (!$cold && $this->verifyWith($raw, $signature, $key)) return true;

        if ($cold) {
            // Provider was unreachable moments ago: do not let a flood of requests each wait for a fetch.
            if (Cache::get($keyName.':fetch-failed')) return false;
        } elseif (!Cache::add($keyName.':refresh-lock', true, now()->addSeconds(30))) {
            // Warm cache + failed check: at most one rotation refresh per 30 s. The cached key is NEVER dropped,
            // so a forged request cannot disable real webhooks.
            return false;
        }
        // The key comes from Monobank itself over TLS, authenticated with our merchant token, so it is authentic
        // whatever the incoming request looks like: cache it BEFORE judging the signature. (Caching only after
        // a successful verification would let anonymous forged requests keep a cold cache empty forever.)
        $fresh = $this->fetchPublicKey($keyName);
        return $fresh !== null && $this->verifyWith($raw, $signature, $fresh);
    }

    private function fetchPublicKey(string $keyName): ?string
    {
        try {
            $response = $this->request()->get(self::API.'/pubkey');
            $encoded = $response->successful() ? $response->json('key') : null;
            $decoded = is_string($encoded) ? base64_decode($encoded, true) : false;
            if (!is_string($decoded) || openssl_pkey_get_public($decoded) === false) {
                Cache::put($keyName.':fetch-failed', true, now()->addSeconds(10));
                return null;
            }
            Cache::put($keyName, $decoded, now()->addHours(12));
            return $decoded;
        } catch (\Throwable $e) {
            Log::warning('Monobank public-key refresh failed', ['error' => $e->getMessage()]);
            Cache::put($keyName.':fetch-failed', true, now()->addSeconds(10));
            return null;
        }
    }

    private function verifyWith(string $raw, string $signature, string $key): bool
    {
        try {
            return openssl_verify($raw, $signature, $key, OPENSSL_ALGO_SHA256) === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Read-only provider check, safe even when webhooks were dropped. */
    private function providerStatus(string $monoId): ?array
    {
        $response = $this->request()->get(self::API.'/invoice/status', ['invoiceId' => $monoId]);
        return $response->successful() && is_array($response->json()) ? $response->json() : null;
    }

    /** Run by the module's scheduled task (or bin/reconcile.php); never over an unsigned public endpoint. */
    public function reconcilePending(int $limit = 20): array
    {
        $limit = max(1, min($limit, 50));
        // No age cut-off: a session that looks unpaid may be a real payment whose notification was lost days ago.
        // Round-robin instead: least recently examined first, and every examined row is stamped (updated_at), so
        // no pile of unresolved sessions can starve another one. Rows leave the set once resolved
        // (paid, expired, failed, refunded, partial_refund).
        $rows = DB::table(self::TABLE)->where('status', 'created')->whereNull('paid_at')->whereNull('refund_requested_at')
            ->where('created_at', '<', now()->subMinutes(3))
            ->orderBy('updated_at')->limit($limit)->get();
        $stats = ['checked' => 0, 'credited' => 0, 'errors' => 0, 'ignored' => 0, 'closed' => 0];
        foreach ($rows as $row) {
            $otherMerchant = !$this->matchesMerchant($row); // e.g. token rotated, or a test-environment leftover
            try {
                DB::table(self::TABLE)->where('mono_invoice_id', $row->mono_invoice_id)->where('status', 'created')
                    ->update(['updated_at' => now()]); // stamp BEFORE the network call: progress even if it fails
                $provider = $this->providerStatus((string) $row->mono_invoice_id);
                $stats['checked']++;
                if ($provider === null) {
                    $stats[$otherMerchant ? 'ignored' : 'errors']++;
                    continue;
                }
                $providerState = $provider['status'] ?? null;
                if (in_array($providerState, ['failure', 'expired'], true)) {
                    DB::table(self::TABLE)->where('mono_invoice_id', $row->mono_invoice_id)
                        ->where('status', 'created')->whereNull('paid_at')
                        ->update(['status' => $providerState === 'expired' ? 'expired' : 'failed', 'updated_at' => now()]);
                    $stats['closed']++;
                    continue;
                }
                $result = $this->parseProviderPayment($provider);
                if (!($result['success'] ?? false)) {
                    if (($result['http_status'] ?? 200) >= 400) $stats['errors']++;
                    else $stats['ignored']++;
                    continue;
                }
                $applied = $this->settleVerifiedWebhook($result, function ($invoice, $txnId, $amount) {
                    return app(\App\Services\PaymentService::class)->applyPayment($invoice, 'monobank', $txnId, $amount);
                });
                if (!($applied['success'] ?? false)) $stats['errors']++;
                elseif (empty($applied['duplicate']) && empty($applied['stale'])) $stats['credited']++;
                else $stats['ignored']++;
            } catch (\Throwable $e) {
                Log::error('Monobank reconciliation entry failed', ['mono_invoice_id' => $row->mono_invoice_id, 'error' => $e->getMessage()]);
                $stats['errors']++;
            }
        }
        return $stats;
    }

    /** Resolves refund requests that are still open (pending/unknown) by asking the bank; never issues a refund. */
    public function reconcileOpenRefunds(int $limit = 20): array
    {
        $stats = ['checked' => 0, 'confirmed' => 0, 'manual' => 0, 'open' => 0];
        $rows = DB::table(self::TABLE)->whereNotNull('refund_requested_at')->whereNull('refunded_at')
            ->where('refund_status', '!=', 'manual_review')
            ->orderBy('updated_at')->limit(max(1, min($limit, 50)))->get();
        foreach ($rows as $row) {
            $r = $this->reconcileRefund((string) $row->mono_invoice_id);
            $stats['checked']++;
            $state = $r['status'] ?? '';
            if ($state === 'success') $stats['confirmed']++;
            elseif ($state === 'manual_review') $stats['manual']++;
            else $stats['open']++;
        }
        return $stats;
    }

    /** Read-only reconciliation of an initiated refund; never issues a second refund. */
    public function reconcileRefund(string $monoId): array
    {
        try {
            $row = DB::table(self::TABLE)->where('mono_invoice_id', $monoId)->first();
            if (!$row || ($row->refund_requested_at ?? null) === null) {
                return ['success' => false, 'message' => 'No matching pending refund'];
            }
            $d = $this->providerStatus($monoId);
            if ($d === null) return ['success' => false, 'message' => 'Provider status unavailable'];
            $entries = is_array($d['cancelList'] ?? null) ? $d['cancelList'] : [];
            $match = null;
            foreach ($entries as $entry) {
                if (is_array($entry) && hash_equals((string) $row->refund_ext_ref, (string) ($entry['extRef'] ?? ''))) {
                    $match = $entry; break;
                }
            }
            // Our extRef is absent from the bank's cancelList. That is NOT proof that the bank will never execute it
            // (a request that timed out may still be processed late), so the claim is never released automatically:
            // the row is parked for a person, who checks the Monobank cabinet (see docs/OPERATIONS.md).
            if ($match === null) {
                $requestedAt = strtotime((string) $row->refund_requested_at);
                if (($d['status'] ?? null) === 'success' && empty($d['cancelList'])
                    && $requestedAt !== false && time() - $requestedAt >= self::REFUND_RECHECK_SECONDS
                    && ($row->refund_status ?? '') !== 'manual_review') {
                    DB::table(self::TABLE)->where('mono_invoice_id', $monoId)->update([
                        'refund_status' => 'manual_review', 'updated_at' => now(),
                    ]);
                    Log::error('Monobank refund NOT visible at the bank after the wait: check the Monobank cabinet before retrying; the claim stays locked', [
                        'mono_invoice_id' => $monoId, 'invoice_id' => $row->invoice_id,
                    ]);
                }
                return ['success' => false, 'status' => 'manual_review',
                    'message' => 'The refund is not visible at the bank yet; automatic retry is disabled, verify in the Monobank cabinet'];
            }
            $state = (string) ($match['status'] ?? 'unknown');
            if (!in_array($state, ['success', 'processing', 'failure'], true)) $state = 'unknown';
            DB::table(self::TABLE)->where('mono_invoice_id', $monoId)->update([
                'refund_status' => $state,
                'refunded_at' => $state === 'success' ? now() : $row->refunded_at,
                'status' => $state === 'success' ? 'refunded' : ($state === 'processing' ? 'refund_pending' : $row->status),
                'updated_at' => now(),
            ]);
            return ['success' => $state === 'success', 'status' => $state,
                'message' => $state === 'success' ? 'Provider refund confirmed; reconcile PNLCS ledger' : 'No completed refund confirmed'];
        } catch (\Throwable $e) {
            Log::error('Monobank refund reconciliation failed', ['mono_invoice_id' => $monoId, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Refund reconciliation failed'];
        }
    }

    /** Full original UAH refund only, one external request per recorded session. */
    public function refund(string $transactionId, float $amount): array
    {
        $monoId = str_starts_with($transactionId, 'monobank:') ? substr($transactionId, 9) : $transactionId;
        if ($monoId === '' || !is_finite($amount) || $amount <= 0 || $this->token() === '') {
            return ['success' => false, 'message' => 'Invalid refund request'];
        }
        try {
            $row = DB::table(self::TABLE)->where('mono_invoice_id', $monoId)->first();
            if (!$row) return ['success' => false, 'message' => 'Payment session not found'];
            if (!$this->matchesMerchant($row)) {
                Log::warning('Monobank refund under a different merchant token than the session was created with', ['mono_invoice_id' => $monoId]);
            }
            $statusBefore = (string) ($row->status ?? 'paid');
            if ((int) round($amount * 100) !== (int) $row->invoice_amount_minor) {
                return ['success' => false, 'message' => 'Only full refunds are supported'];
            }
            if (($row->refund_requested_at ?? null) !== null) {
                return ['success' => false, 'message' => 'Refund already requested; reconcile status without retry'];
            }
            $provider = $this->providerStatus($monoId);
            if (!is_array($provider) || ($provider['status'] ?? null) !== 'success'
                || ($provider['reference'] ?? null) !== 'pnlcs-invoice-'.$row->invoice_id
                || ($provider['amount'] ?? null) !== (int) $row->payment_amount_minor
                || ($provider['ccy'] ?? null) !== 980 || !empty($provider['cancelList'])) {
                return ['success' => false, 'message' => 'Refund blocked: provider payment not verifiably successful'];
            }
            $extRef = DB::transaction(function () use ($monoId) {
                $locked = DB::table(self::TABLE)->where('mono_invoice_id', $monoId)->lockForUpdate()->first();
                if (!$locked || $locked->refund_requested_at !== null) return null;
                $ref = (string) Str::uuid();
                DB::table(self::TABLE)->where('mono_invoice_id', $monoId)->update([
                    'refund_ext_ref' => $ref, 'refund_requested_at' => now(),
                    'refund_status' => 'requested', 'status' => 'refund_pending', 'updated_at' => now(),
                ]);
                return $ref;
            });
            if (!$extRef) return ['success' => false, 'message' => 'Refund already requested'];
            $response = $this->request()->post(self::API.'/invoice/cancel', ['invoiceId' => $monoId, 'extRef' => $extRef]);
            $http = $response->status();
            if (in_array($http, [400, 401, 403, 404, 422], true)) {
                // Explicit rejection (bad request, no permission, not found...): Monobank did not act, so the
                // claim is released. 429/408/409/5xx are NOT here: they stay claimed until reconcileRefund()
                // has checked the bank's cancelList.
                DB::table(self::TABLE)->where('mono_invoice_id', $monoId)->update([
                    'refund_ext_ref' => null, 'refund_requested_at' => null, 'refund_status' => 'rejected',
                    'status' => $statusBefore === 'refund_pending' ? 'paid' : $statusBefore, 'updated_at' => now(),
                ]);
                Log::warning('Monobank refund rejected by provider', ['mono_invoice_id' => $monoId, 'http_status' => $http, 'errCode' => $response->json('errCode')]);
                return ['success' => false, 'status' => 'rejected', 'message' => 'Monobank rejected the refund; nothing was refunded'];
            }
            $state = (string) ($response->json('status') ?? 'unknown');
            if (!$response->successful()) $state = 'unknown';
            if (!in_array($state, ['success', 'processing', 'failure'], true)) $state = 'unknown';
            DB::table(self::TABLE)->where('mono_invoice_id', $monoId)->update([
                'refund_status' => $state,
                'refunded_at' => $state === 'success' ? now() : null,
                'status' => $state === 'success' ? 'refunded' : 'refund_pending',
                'updated_at' => now(),
            ]);
            if ($state !== 'success') {
                return ['success' => false, 'status' => $state,
                    'message' => 'Refund is not confirmed; check Monobank before further actions'];
            }
            Log::warning('Monobank refund confirmed; reconcile account credit and provisioned services', [
                'mono_invoice_id' => $monoId, 'invoice_id' => $row->invoice_id,
            ]);
            return ['success' => true, 'transaction_id' => $transactionId, 'status' => 'success'];
        } catch (\Throwable $e) {
            Log::error('Monobank refund request ambiguous; reconcile before retry', ['mono_invoice_id' => $monoId, 'error' => $e->getMessage()]);
            return ['success' => false, 'status' => 'unknown', 'message' => 'Refund needs provider reconciliation'];
        }
    }
}
