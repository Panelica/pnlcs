<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Modules\Gateways\Razorpay\RazorpayModule;

/**
 * Razorpay takes amounts in the currency's smallest sub-unit, and it publishes
 * an exponent per currency rather than assuming a hundredth:
 *
 *   "Payment amount in the smallest currency sub-unit ... In the case of three
 *   decimal currencies, such as KWD, BHD and OMR, to accept a payment of
 *   295.991, pass the value as 295990. And in the case of zero decimal
 *   currencies such as JPY, to accept a payment of 295, pass the value as 295."
 *   https://razorpay.com/docs/api/orders/create/
 *
 * The module multiplied by a hundred outbound and divided by a hundred inbound
 * whatever the currency, which is wrong in both directions at once - the only
 * reason a yen shop's books agreed with themselves while the customer was
 * charged a hundredfold.
 *
 * Razorpay's exponents are NOT Stripe's. Razorpay lists ISK and UGX as
 * exponent 0 and MGA as exponent 2; Stripe has it the other way round on all
 * three. Razorpay also has exponent-3 currencies, which Stripe has no
 * equivalent of. Those disagreements are asserted here on purpose, because
 * they are the reason the two modules keep separate tables.
 */
beforeEach(function () {
    foreach (['key_id' => 'rzp_key', 'key_secret' => 'rzp_secret', 'webhook_secret' => 'whsec'] as $k => $v) {
        GatewaySettings::create(['gateway' => 'razorpay', 'setting' => $k, 'value' => $v]);
    }
});

function rzpModule(): RazorpayModule
{
    return new RazorpayModule();
}

/** An invoice stamped with a given source currency. */
function rzpInvoice(string $currency, float $total = 5000.0): Invoice
{
    $client = Client::factory()->create();

    return Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'total' => $total,
        'source_currency' => $currency,
    ]);
}

function rzpFakeOrderOk(): void
{
    Http::fake(['*/v1/orders' => Http::response(['id' => 'order_1'], 200)]);
}

/** The JSON body of the single outbound request Http::fake recorded. */
function rzpSentBody(): array
{
    $sent = Http::recorded();
    expect($sent)->toHaveCount(1);

    return $sent[0][0]->data();
}

// ============================================================
// Outbound: capture() — FAIL before the fix
// ============================================================

test('a yen order is raised for the invoice amount, not a hundred times it', function () {
    rzpFakeOrderOk();

    $invoice = rzpInvoice('JPY');
    $result = rzpModule()->capture($invoice, 5000.0);

    // 5,000 yen is 5000, not 500000.
    expect(rzpSentBody()['amount'])->toBe(5000)
        ->and(rzpSentBody()['currency'])->toBe('JPY')
        ->and($result['amount'])->toBe(5000);
});

test('every zero-exponent currency Razorpay lists is sent in whole units', function (string $code) {
    rzpFakeOrderOk();

    rzpModule()->capture(rzpInvoice($code, 250.0), 250.0);

    expect(rzpSentBody()['amount'])->toBe(250);
})->with(['BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF']);

test('a three-decimal currency is sent in thousandths with the last digit zero', function () {
    rzpFakeOrderOk();

    // Razorpay's own worked example: 99.991 KD must be sent as 99990, "and not
    // 99991". PNLCS money is two-decimal, so 99.99 rounds into that same shape.
    rzpModule()->capture(rzpInvoice('KWD', 99.99), 99.99);

    expect(rzpSentBody()['amount'])->toBe(99990);
});

test('every three-exponent currency Razorpay lists is sent in thousandths', function (string $code) {
    rzpFakeOrderOk();

    rzpModule()->capture(rzpInvoice($code, 12.50), 12.50);

    expect(rzpSentBody()['amount'])->toBe(12500);
})->with(['BHD', 'IQD', 'JOD', 'KWD', 'OMR', 'TND']);

test('Razorpay exponents are followed where they disagree with Stripe', function () {
    // ISK is exponent 0 at Razorpay; Stripe wants it multiplied by a hundred.
    rzpFakeOrderOk();
    rzpModule()->capture(rzpInvoice('ISK', 500.0), 500.0);
    expect(rzpSentBody()['amount'])->toBe(500);

    // MGA is exponent 2 at Razorpay; Stripe calls it zero-decimal.
    Http::fake(['*/v1/orders' => Http::response(['id' => 'order_2'], 200)]);
    rzpModule()->capture(rzpInvoice('MGA', 500.0), 500.0);
    expect(rzpSentBody()['amount'])->toBe(50000);
});

// ============================================================
// Outbound: refund() — FAIL before the fix
// ============================================================

test('a yen refund asks for the amount paid, not a hundred times it', function () {
    Http::fake(['*/v1/payments/*/refund' => Http::response(['id' => 'rfnd_1'], 200)]);

    $invoice = rzpInvoice('JPY');
    Transaction::create([
        'client_id' => $invoice->client_id,
        'invoice_id' => $invoice->id,
        'gateway' => 'razorpay',
        'date' => now()->toDateString(),
        'amount_in' => 5000.0,
        'transaction_id' => 'pay_yen',
    ]);

    rzpModule()->refund('pay_yen', 5000.0);

    expect(rzpSentBody()['amount'])->toBe(5000);
});

test('refund and capture read the same currency, so they cannot disagree', function () {
    // The shop currency is changed after the invoice was raised. The invoice
    // keeps its own stamp, and both legs must follow that stamp - if one read
    // the shop instead, the refund would be a hundredfold out.
    $invoice = rzpInvoice('JPY');

    rzpFakeOrderOk();
    rzpModule()->capture($invoice, 5000.0);
    $captured = rzpSentBody()['amount'];

    Transaction::create([
        'client_id' => $invoice->client_id,
        'invoice_id' => $invoice->id,
        'gateway' => 'razorpay',
        'date' => now()->toDateString(),
        'amount_in' => 5000.0,
        'transaction_id' => 'pay_yen',
    ]);

    Http::fake(['*/v1/payments/*/refund' => Http::response(['id' => 'rfnd_1'], 200)]);
    rzpModule()->refund('pay_yen', 5000.0);

    expect(rzpSentBody()['amount'])->toBe($captured);
});

// ============================================================
// Inbound: verifyPayment() and the webhook — FAIL before the fix
// ============================================================

test('a yen payment is credited at the figure Razorpay reports, not a hundredth of it', function () {
    $invoice = rzpInvoice('JPY');

    $signature = hash_hmac('sha256', 'order_1|pay_1', 'rzp_secret');
    Http::fake(['*/v1/orders/order_1' => Http::response([
        'id' => 'order_1',
        'status' => 'paid',
        'amount' => 5000,
        'amount_paid' => 5000,
        'currency' => 'JPY',
        'notes' => ['invoice_id' => $invoice->id],
    ], 200)]);

    $result = rzpModule()->verifyPayment('order_1', 'pay_1', $signature, (int) $invoice->id);

    expect($result['success'])->toBeTrue()
        ->and($result['amount'])->toBe(5000);
});

test('a yen webhook credits the figure Razorpay reports', function () {
    $payload = [
        'event' => 'payment.captured',
        'payload' => ['payment' => ['entity' => [
            'id' => 'pay_1',
            'amount' => 5000,
            'currency' => 'JPY',
            'notes' => ['invoice_id' => 42],
        ]]],
    ];
    $raw = json_encode($payload);

    $result = rzpModule()->processWebhook($payload + [
        '_raw_payload' => $raw,
        '_signature_header' => hash_hmac('sha256', $raw, 'whsec'),
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['amount'])->toBe(5000);
});

test('a three-decimal webhook is read back in thousandths', function () {
    $payload = [
        'event' => 'payment.captured',
        'payload' => ['payment' => ['entity' => [
            'id' => 'pay_k',
            'amount' => 99990,
            'currency' => 'KWD',
            'notes' => ['invoice_id' => 42],
        ]]],
    ];
    $raw = json_encode($payload);

    $result = rzpModule()->processWebhook($payload + [
        '_raw_payload' => $raw,
        '_signature_header' => hash_hmac('sha256', $raw, 'whsec'),
    ]);

    expect($result['amount'])->toBe(99.99);
});

// ============================================================
// NON-REGRESSION: the ordinary two-decimal shop, which is nearly all of them.
// These must pass on both sides of the change.
// ============================================================

test('a two-decimal order body is unchanged field by field', function () {
    rzpFakeOrderOk();

    // The overwhelmingly common installation: one two-decimal currency, never
    // changed, so the invoice's own stamp and the shop's answer are the same
    // three letters and the whole request body is byte-for-byte what it was.
    // This test passes on BOTH sides of the change; that is its job.
    $shop = strtoupper(shop_currency_code());
    $invoice = rzpInvoice($shop, 299.0);
    $result = rzpModule()->capture($invoice, 299.0);

    $body = rzpSentBody();

    // Razorpay's own example: 299 in a two-decimal currency is 29900.
    expect($body)->toBe([
        'amount' => 29900,
        'currency' => $shop,
        'receipt' => 'invoice_' . ($invoice->invoice_num ?? $invoice->id),
        'notes' => [
            'invoice_id' => $invoice->id,
            'invoice_num' => $invoice->invoice_num ?? $invoice->id,
        ],
    ])
        ->and($result['success'])->toBeTrue()
        ->and($result['order_id'])->toBe('order_1')
        ->and($result['key_id'])->toBe('rzp_key')
        ->and($result['amount'])->toBe(29900)
        ->and($result['currency'])->toBe($shop);
});

test('an invoice stamped before the shop currency changed keeps its own currency', function () {
    // The deliberate change of source, mirroring StripeModule: capture() reads
    // the invoice's stamp rather than the shop's current setting, so that an
    // old invoice is not reinterpreted at a new sign and so that capture and
    // refund cannot disagree. This is the one behaviour that moves for a shop
    // that HAS changed currency, and it moves in the direction of the stamp.
    rzpFakeOrderOk();

    $shop = strtoupper(shop_currency_code());
    $invoice = rzpInvoice($shop === 'INR' ? 'SGD' : 'INR', 299.0);

    rzpModule()->capture($invoice, 299.0);

    expect(rzpSentBody()['currency'])->toBe($invoice->source_currency)
        ->and(rzpSentBody()['currency'])->not->toBe($shop);
});

test('an explicit currency parameter still wins over the invoice', function () {
    rzpFakeOrderOk();

    rzpModule()->capture(rzpInvoice('INR', 10.0), 10.0, ['currency' => 'usd']);

    expect(rzpSentBody()['currency'])->toBe('USD')
        ->and(rzpSentBody()['amount'])->toBe(1000);
});

test('an invoice with no source currency falls back to the shop, as it always did', function () {
    rzpFakeOrderOk();

    $invoice = rzpInvoice('INR', 42.5);
    $invoice->forceFill(['source_currency' => null])->save();

    rzpModule()->capture($invoice->fresh(), 42.5);

    expect(rzpSentBody()['currency'])->toBe(strtoupper(shop_currency_code()))
        ->and(rzpSentBody()['amount'])->toBe(4250);
});

test('a two-decimal refund asks for the same figure it always did', function () {
    Http::fake(['*/v1/payments/*/refund' => Http::response(['id' => 'rfnd_1'], 200)]);

    $invoice = rzpInvoice('INR', 120.0);
    Transaction::create([
        'client_id' => $invoice->client_id,
        'invoice_id' => $invoice->id,
        'gateway' => 'razorpay',
        'date' => now()->toDateString(),
        'amount_in' => 120.0,
        'transaction_id' => 'pay_inr',
    ]);

    $result = rzpModule()->refund('pay_inr', 20.0);

    expect(rzpSentBody())->toBe(['amount' => 2000])
        ->and($result['success'])->toBeTrue()
        ->and($result['refund_id'])->toBe('rfnd_1')
        ->and($result['transaction_id'])->toBe('pay_inr');
});

test('a refund against an unknown transaction id still falls back to the shop currency', function () {
    Http::fake(['*/v1/payments/*/refund' => Http::response(['id' => 'rfnd_1'], 200)]);

    rzpModule()->refund('pay_nowhere', 20.0);

    // The shop is two-decimal in the test suite, so this is the old behaviour.
    expect(rzpSentBody()['amount'])->toBe(2000);
});

test('a two-decimal inbound figure keeps its exact int-or-float type', function () {
    $invoice = rzpInvoice('INR', 500.0);
    $signature = hash_hmac('sha256', 'order_1|pay_1', 'rzp_secret');

    // 50000/100 is the int 25-style case: PHP gives an int when it divides evenly.
    Http::fake(['*/v1/orders/order_1' => Http::response([
        'id' => 'order_1', 'status' => 'paid',
        'amount' => 50000, 'amount_paid' => 50000, 'currency' => 'INR',
        'notes' => ['invoice_id' => $invoice->id],
    ], 200)]);

    $result = rzpModule()->verifyPayment('order_1', 'pay_1', $signature, (int) $invoice->id);

    expect($result['amount'])->toBe(500)
        ->and($result['amount'])->toBeInt();
});

test('a two-decimal webhook with a fractional figure stays a float', function () {
    $payload = [
        'event' => 'payment.captured',
        'payload' => ['payment' => ['entity' => [
            'id' => 'pay_1', 'amount' => 5050, 'currency' => 'INR',
            'notes' => ['invoice_id' => 42],
        ]]],
    ];
    $raw = json_encode($payload);

    $result = rzpModule()->processWebhook($payload + [
        '_raw_payload' => $raw,
        '_signature_header' => hash_hmac('sha256', $raw, 'whsec'),
    ]);

    expect($result['amount'])->toBe(50.5)
        ->and($result['amount'])->toBeFloat();
});

test('a payload naming no currency at all divides by a hundred, as it always did', function () {
    $payload = [
        'event' => 'payment.captured',
        'payload' => ['payment' => ['entity' => [
            'id' => 'pay_1', 'amount' => 2500,
            'notes' => ['invoice_id' => 42],
        ]]],
    ];
    $raw = json_encode($payload);

    $result = rzpModule()->processWebhook($payload + [
        '_raw_payload' => $raw,
        '_signature_header' => hash_hmac('sha256', $raw, 'whsec'),
    ]);

    expect($result['amount'])->toBe(25);
});

test('an unrecognised currency code still means multiply by a hundred', function () {
    rzpFakeOrderOk();

    rzpModule()->capture(rzpInvoice('ZZZ', 10.0), 10.0);

    expect(rzpSentBody()['amount'])->toBe(1000);
});

test('the payment form carries no amount of its own and shows the major-unit figure', function () {
    $invoice = rzpInvoice('JPY', 5000.0);

    $html = rzpModule()->getPaymentForm($invoice);

    // The checkout is opened with data.amount, which comes back from capture().
    expect($html)->toContain('amount: data.amount')
        // The customer-facing figure is formatted major units, never 500000.
        ->and($html)->not->toContain('500000');
});
