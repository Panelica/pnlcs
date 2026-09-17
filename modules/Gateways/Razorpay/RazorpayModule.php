<?php

namespace Modules\Gateways\Razorpay;

use App\Contracts\GatewayModuleInterface;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\Transaction;
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

        // The invoice's own currency, not the shop's current one. The two are
        // the same until an operator changes the shop currency, and after that
        // the shop's answer reinterprets an old invoice at the new sign. It
        // also has to agree with refund(), which converts using
        // refundCurrency() and reads this same column: two legs reading two
        // different sources is itself a hundredfold refund error the moment one
        // of them is zero-decimal. Invoices raised before that column existed
        // carry null and fall back to the shop, exactly as this line did for
        // everyone before.
        $currency = strtoupper($params['currency'] ?? ($invoice->source_currency ?: shop_currency_code()));

        // Converted with the very currency this request carries three lines
        // down, so the figure and the unit it is counted in cannot come apart.
        $amountPaise = $this->minorUnits($amount, $currency);
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

        // "Amount should be in the smallest unit of the currency in which the
        // payment was made" (https://razorpay.com/docs/api/refunds/create-instant/),
        // and a refund request carries no currency of its own to read it from.
        $amountPaise = $this->minorUnits($amount, $this->refundCurrency($transactionId));

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

    /**
     * Currencies Razorpay counts in whole units - exponent 0.
     *
     * Razorpay publishes an exponent per currency in the table under
     * https://razorpay.com/docs/payments/payments/international-payments/
     * ("Currency Name / ISO Code / Exponent"). These sixteen are every row of
     * that table carrying exponent 0, which matches the count Razorpay claims
     * in https://razorpay.com/blog/razorpay-supports-zero-exponent-currency/
     * ("Razorpay now supports 16 zero-exponent currencies").
     *
     * DELIBERATELY NOT SHARED WITH StripeModule, which keeps its own list a few
     * files away. The temptation is obvious and the lists genuinely disagree:
     * Razorpay marks MGA exponent 2 while Stripe calls it zero-decimal, and
     * Razorpay marks ISK and UGX exponent 0 while Stripe wants both multiplied
     * by a hundred with the decimals always zero
     * (https://docs.stripe.com/currencies#special-cases). A shared table would
     * have to be wrong for one of the two gateways on three currencies, and the
     * way it would be wrong is a hundredfold charge. Each gateway follows the
     * exponent its own processor publishes; the duplication is the point.
     */
    private const ZERO_DECIMAL_CURRENCIES = [
        'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW',
        'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    /**
     * Currencies Razorpay counts in thousandths - exponent 3.
     *
     * Every exponent-3 row of the same table. Stripe has no equivalent of these
     * at all, which is the second reason the two modules cannot share a helper.
     */
    private const THREE_DECIMAL_CURRENCIES = ['BHD', 'IQD', 'JOD', 'KWD', 'OMR', 'TND'];

    /**
     * An amount in the units Razorpay wants to be told about it in.
     *
     * "Payment amount in the smallest currency sub-unit. For example, if the
     * amount to be charged is [INR 299], then pass 29900 in this field. In the
     * case of three decimal currencies, such as KWD, BHD and OMR, to accept a
     * payment of 295.991, pass the value as 295990. And in the case of zero
     * decimal currencies such as JPY, to accept a payment of 295, pass the
     * value as 295." https://razorpay.com/docs/api/orders/create/
     *
     * Multiplying by a hundred regardless is how a shop selling in yen charges
     * a hundred times the invoice. The shop currency is operator-set with no
     * validation behind it, so this has to hold for whatever is typed in.
     *
     * Both outbound legs come through here - capture() and refund() - and both
     * inbound legs read Razorpay's figures back through reportedMajorUnits().
     * They moved together on purpose. Until they did, all four were wrong in
     * both directions at once, and that is the only reason a yen shop's books
     * agreed with themselves: the order was raised for a hundred times the
     * invoice and the amount recorded against it was a hundredth of what
     * Razorpay took, so the ledger balanced while the customer was out a
     * hundredfold. Correcting the outbound half alone would have turned a loud
     * disaster into a quiet accounting one.
     *
     * An unrecognised code still means multiply by a hundred, so a currency
     * Razorpay does not list behaves exactly as it always has rather than
     * newly refusing money.
     */
    private function minorUnits(float $amount, string $currency): int
    {
        $currency = strtoupper($currency);

        if (in_array($currency, self::ZERO_DECIMAL_CURRENCIES, true)) {
            return (int) round($amount);
        }

        if (in_array($currency, self::THREE_DECIMAL_CURRENCIES, true)) {
            // Thousandths, but not any thousandth: "you should pass the last
            // decimal number as 0 for three decimal currency payments. For
            // example, if you want to charge a customer 99.991 KD for a
            // transaction, you should pass the value for the amount parameter
            // as 99990 and not 99991" (same page). PNLCS money is two-decimal
            // throughout - Invoice totals are cast and rounded to 2 - so the
            // hundredths are rounded first and the required trailing zero comes
            // out of the multiplication rather than being imposed afterwards.
            return (int) round($amount * 100) * 10;
        }

        return (int) round($amount * 100);
    }

    /**
     * A figure Razorpay has reported, read back into the units the shop counts in.
     *
     * Razorpay names the currency on both objects these paths already parse -
     * the order (https://razorpay.com/docs/api/orders/entity/) and the payment
     * entity (https://razorpay.com/docs/api/payments/entity/) - so neither
     * inbound leg has to guess at one.
     *
     * Outside the two special sets the expression these paths have always used
     * is kept exactly, down to its type: 2500/100 is the int 25 in PHP and
     * 5050/100 is the float 50.5. Routing the ordinary case through arithmetic
     * of its own would change 25 into 25.0 for every two-decimal shop - which
     * is nearly all of them - for no gain, since both callers cast to float
     * before anything is written down (GatewayWebhookController::razorpay and
     * ::razorpayCapture). Invisible is not the same as absent, and the shops
     * that are fine today stay byte-identical.
     *
     * A missing or non-string currency falls back to that same division, which
     * is what both paths did for every currency until now, so an unrecognisable
     * payload behaves as it always has rather than newly refusing.
     */
    private function reportedMajorUnits(mixed $minorAmount, mixed $currency): int|float
    {
        if (is_string($currency)) {
            $currency = strtoupper($currency);

            if (in_array($currency, self::ZERO_DECIMAL_CURRENCIES, true)) {
                return (int) $minorAmount;
            }

            if (in_array($currency, self::THREE_DECIMAL_CURRENCIES, true)) {
                return (int) $minorAmount / 1000;
            }
        }

        return $minorAmount / 100;
    }

    /**
     * The currency an amount about to be refunded is counted in.
     *
     * A refund request names a payment id and an amount and nothing else -
     * Razorpay reads the currency off the payment - and the gateway contract
     * hands this method exactly those two things. So the unit has to be
     * recovered, and the honest question is what unit the number handed to us
     * is already in rather than what Razorpay will read it as.
     *
     * It comes from PaymentService::refundInvoice(), which works the figure out
     * from the payments recorded against one invoice and then picks the
     * settling transaction to refund against (app/Services/PaymentService.php).
     * So the number is in that invoice's own currency - the one stamped on the
     * row the day it was raised, in Invoice::booted() (app/Models/Invoice.php:43-44)
     * - and that row is findable here by the id being refunded against.
     *
     * The shop's current currency is the fallback, for an id with no row behind
     * it and for an invoice raised before that column existed. It is also what
     * this method used implicitly until now, so nothing moves for a shop that
     * has always sold in one currency: source_currency and shop_currency_code()
     * are then the same three letters.
     *
     * Razorpay is the authority on what currency the payment is in and is
     * deliberately not asked. That would be a second API call on every refund,
     * including the two-decimal refunds that are nearly all of them, and this
     * repair is not allowed to add a request to a path that works today.
     *
     * The lookup does not filter on the gateway: the id is a Razorpay id
     * already, and a row written under a differently-cased gateway name would
     * be missed by such a filter and silently fall back.
     */
    private function refundCurrency(string $transactionId): string
    {
        $invoice = Transaction::query()
            ->where('transaction_id', $transactionId)
            ->whereNotNull('invoice_id')
            ->latest('id')
            ->first()?->invoice;

        return strtoupper($invoice?->source_currency ?: shop_currency_code());
    }

    public function getPaymentForm(Invoice $invoice): string
    {
        $keyId = htmlspecialchars($this->getSetting('key_id') ?? '', ENT_QUOTES, 'UTF-8');
        // No amount is computed here on purpose. The figure the checkout is
        // opened with is data.amount, which comes back from capture() above
        // already in Razorpay's units; the line that used to sit here multiplied
        // amountDue() by a hundred, was never interpolated into the markup
        // below, and would have been wrong for a zero- or three-decimal shop if
        // anyone had ever reached for it. $displayAmount is the customer-facing
        // figure and stays in major units, formatted, which is a different job.
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
            // Razorpay's own figure, read back through the currency Razorpay
            // names on the order it came from.
            'amount'         => $this->reportedMajorUnits((int) ($order['amount_paid'] ?? $order['amount'] ?? 0), $order['currency'] ?? null),
        ];
    }

    public function processWebhook(array $data): array
    {
        $webhookSecret = $this->getSetting('webhook_secret');
        $rawPayload = $data['_raw_payload'] ?? '';
        $sigHeader = $data['_signature_header'] ?? '';

        // r170-unsigned: prove who sent this before acting on it.
        //
        // Verification used to run only when a webhook secret happened to be
        // configured. Without one, an unsigned POST to the public webhook URL
        // naming an invoice id in its notes marked that invoice paid - and
        // payment then does everything payment does: the order is accepted, the
        // service provisioned, a suspended one switched back on. The
        // Authorize.net module already refuses in exactly this case.
        if (!$webhookSecret || !$rawPayload || !$sigHeader) {
            Log::warning('Razorpay: webhook refused - no signature to check against');
            return ['success' => false, 'message' => 'Unsigned webhook.'];
        }

        $expected = hash_hmac('sha256', $rawPayload, $webhookSecret);
        if (!hash_equals($expected, $sigHeader)) {
            Log::warning('Razorpay: webhook signature verification failed');
            return ['success' => false, 'message' => 'Invalid webhook signature.'];
        }

        $event = $data['event'] ?? '';
        if ($event !== 'payment.captured') {
            return ['success' => true, 'message' => "Event ignored: {$event}"];
        }

        $payment = $data['payload']['payment']['entity'] ?? [];
        $paymentId = $payment['id'] ?? null;
        $invoiceId = $payment['notes']['invoice_id'] ?? null;
        $amountPaise = $payment['amount'] ?? 0;
        // The payment entity carries its own currency - "currency string: The
        // currency in which the payment is made"
        // (https://razorpay.com/docs/api/payments/entity/) - so this leg never
        // has to guess, and both the log line and the credited figure read it
        // the same way.
        $amountMajor = $this->reportedMajorUnits($amountPaise, $payment['currency'] ?? null);

        Log::info('Razorpay webhook: payment.captured', [
            'payment_id' => $paymentId,
            'invoice_id' => $invoiceId,
            'amount' => $amountMajor,
        ]);

        return [
            'success' => true,
            'transaction_id' => $paymentId,
            'invoice_id' => $invoiceId,
            'amount' => $amountMajor,
            'gateway' => 'razorpay',
        ];
    }
}
