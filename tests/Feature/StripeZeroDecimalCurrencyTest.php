<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Http;
use Modules\Gateways\Stripe\StripeModule;

/**
 * A shop that does not sell in cents.
 *
 * Stripe is told amounts in the currency's minor unit — "Enter 1099 to charge
 * 10.99 USD (or any other two-decimal currency). Enter 10 to charge 10 JPY (or
 * any other zero-decimal currency)", https://docs.stripe.com/currencies — and
 * this module used to multiply by a hundred on the way out and divide by a
 * hundred on the way back, whatever the currency.
 *
 * For a shop selling in yen that is a hundredfold overcharge which the books
 * then hide: 5,000 JPY went out as 500000, the customer was charged half a
 * million yen, and the webhook divided Stripe's honest amount_received of
 * 500000 back down to 5,000, which matched the invoice exactly and marked it
 * paid. Both errors had to be corrected in the same change or the ledger would
 * have started disagreeing with itself while the overcharge went on.
 *
 * Three kinds of currency are exercised here, because the difference between
 * them is the whole point:
 *
 *   JPY  zero-decimal. The amount IS the minor unit: 5000 means 5000.
 *   ISK  zero-decimal in fact, two-decimal on the wire. 5 ISK is sent as 500,
 *        "where the decimal amount is always 00 ... You can't charge fractions
 *        of ISK" (https://docs.stripe.com/currencies#special-cases). UGX the
 *        same, word for word.
 *   USD  two-decimal, and the one that matters most here: nearly every shop
 *        running this software is in one of these, and for them every request
 *        and every recorded figure has to come out of this change untouched,
 *        down to whether a number is an int or a float.
 */
function zdcStripeConfigured(): void
{
    foreach (['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_test'] as $setting => $value) {
        GatewaySettings::updateOrCreate(
            ['gateway' => 'stripe', 'setting' => $setting],
            ['value' => $value]
        );
    }
}

/**
 * What the shop sells in.
 *
 * shop_currency_code() answers from a container binding it fills once from the
 * default currency row and then keeps (app/Support/formatting.php:109-120), so
 * putting the currency there is the same thing the running application does,
 * one step earlier.
 */
function zdcShopSellsIn(string $code): void
{
    app()->instance('pnlcs.currency', ['currency' => new Currency(['code' => $code])]);
}

function zdcInvoice(float $total, ?string $sourceCurrency = null): Invoice
{
    return Invoice::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'status' => 'unpaid',
        'total' => $total,
        'source_currency' => $sourceCurrency,
    ]);
}

/** The event over the wire, through the route Stripe actually posts to. */
function zdcDeliver(array $payload)
{
    $body = json_encode($payload);
    $timestamp = time();

    return test()->call('POST', '/gateway/stripe/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1=".hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test'),
    ], $body);
}

function zdcSucceededEvent(Invoice $invoice, string $intentId, int $amountReceived, ?string $currency): array
{
    $object = [
        'id' => $intentId,
        'metadata' => ['invoice_id' => (string) $invoice->id],
        'amount_received' => $amountReceived,
    ];

    if ($currency !== null) {
        $object['currency'] = $currency;
    }

    return ['id' => 'evt_'.$intentId, 'type' => 'payment_intent.succeeded', 'data' => ['object' => $object]];
}

// ===================== on the way out: creating the charge =====================

test('a yen invoice asks Stripe for the invoice, not a hundred times the invoice', function () {
    zdcStripeConfigured();
    zdcShopSellsIn('JPY');
    Http::fake(['*' => Http::response(['id' => 'pi_jpy', 'client_secret' => 'pi_jpy_secret'], 200)]);

    $invoice = zdcInvoice(5000.0);

    app(StripeModule::class)->capture($invoice, $invoice->total);

    // 500000 here is half a million yen taken from a customer who owed five
    // thousand, and nothing downstream would have said so.
    Http::assertSent(fn ($request) => $request->url() === 'https://api.stripe.com/v1/payment_intents'
        && $request['amount'] === 5000
        && $request['currency'] === 'jpy');
});

test('a dollar invoice sends the request it has always sent, field for field', function () {
    zdcStripeConfigured();
    zdcShopSellsIn('USD');
    Http::fake(['*' => Http::response(['id' => 'pi_usd', 'client_secret' => 'pi_usd_secret'], 200)]);

    $invoice = zdcInvoice(100.0);

    app(StripeModule::class)->capture($invoice, 100.0);

    // The whole body, not just the amount: this is the proof that a two-decimal
    // shop — which is nearly all of them — cannot tell that anything happened.
    Http::assertSent(function ($request) use ($invoice) {
        expect($request->data())->toBe([
            'amount' => 10000,
            'currency' => 'usd',
            'payment_method_types[]' => 'card',
            'description' => 'Invoice #'.$invoice->invoice_num,
            'metadata[invoice_id]' => $invoice->id,
            'metadata[invoice_num]' => $invoice->invoice_num,
        ]);

        // And still no idempotency key, for the reason written where it sends.
        expect($request->hasHeader('Idempotency-Key'))->toBeFalse();

        return true;
    });
});

test('a fractional dollar amount rounds exactly as it did', function () {
    zdcStripeConfigured();
    zdcShopSellsIn('USD');
    Http::fake(['*' => Http::response(['id' => 'pi_usd', 'client_secret' => 's'], 200)]);

    // 50.555 * 100 is 5055.4999... in binary floating point; round() before the
    // cast is what has always kept that a penny rather than losing it, and the
    // helper does the same thing in the same order.
    app(StripeModule::class)->capture(zdcInvoice(50.555), 50.555);

    Http::assertSent(fn ($request) => $request['amount'] === 5056);
});

test('a krona charge carries the two zeroes Stripe still wants after it', function () {
    zdcStripeConfigured();
    zdcShopSellsIn('ISK');
    Http::fake(['*' => Http::response(['id' => 'pi_isk', 'client_secret' => 's'], 200)]);

    // ISK is zero-decimal in fact but "backward compatibility requires you to
    // represent it as a two-decimal value, where the decimal amount is always
    // 00. For example, to charge 5 ISK, provide an amount value of 500."
    // Sending 500 for 500 ISK would have charged five krona instead of five
    // hundred, so this is not the same rule as yen and cannot share its branch.
    app(StripeModule::class)->capture(zdcInvoice(500.0), 500.0);

    Http::assertSent(fn ($request) => $request['amount'] === 50000 && $request['currency'] === 'isk');
});

test('a shilling charge cannot carry a fraction', function () {
    zdcStripeConfigured();
    zdcShopSellsIn('UGX');
    Http::fake(['*' => Http::response(['id' => 'pi_ugx', 'client_secret' => 's'], 200)]);

    // "You can't charge fractions of UGX." The whole unit is rounded first and
    // the two decimal places are always zero, which is what separates this list
    // from the zero-decimal one.
    app(StripeModule::class)->capture(zdcInvoice(5000.4), 5000.4);

    Http::assertSent(fn ($request) => $request['amount'] === 500000);
});

// ===================== on the way back: the browser's confirmation =====================

test('a yen payment is credited with the yen Stripe took', function () {
    zdcStripeConfigured();
    zdcShopSellsIn('JPY');

    $invoice = zdcInvoice(5000.0);

    Http::fake(['*' => Http::response([
        'id' => 'pi_jpy',
        'status' => 'succeeded',
        'currency' => 'jpy',
        'amount_received' => 5000,
        'metadata' => ['invoice_id' => (string) $invoice->id],
    ], 200)]);

    $verified = app(StripeModule::class)->verifyPaymentIntent('pi_jpy', (int) $invoice->id);

    // Fifty yen against a five-thousand yen invoice is what the division used
    // to make of this, and the invoice would have stayed all but unpaid.
    expect($verified['success'])->toBeTrue()
        ->and($verified['amount'])->toBe(5000.0);
});

test('a dollar payment is read back exactly as it was, type included', function () {
    zdcStripeConfigured();
    zdcShopSellsIn('USD');

    $invoice = zdcInvoice(100.0);

    Http::fake(['*' => Http::response([
        'id' => 'pi_usd',
        'status' => 'succeeded',
        'currency' => 'usd',
        'amount_received' => 10000,
        'metadata' => ['invoice_id' => (string) $invoice->id],
    ], 200)]);

    $verified = app(StripeModule::class)->verifyPaymentIntent('pi_usd', (int) $invoice->id);

    // toBe is identical-to: 10000/100 has always been the int 100 here and
    // still is. A float 100.0 would pass a loose comparison and would be a
    // change to a path that was never wrong.
    expect($verified['amount'])->toBe(100)
        ->and($verified)->toBe([
            'success' => true,
            'transaction_id' => 'pi_usd',
            'amount' => 100,
        ]);
});

test('a payment intent that names no currency is read the way it always was', function () {
    zdcStripeConfigured();
    zdcShopSellsIn('USD');

    $invoice = zdcInvoice(25.0);

    Http::fake(['*' => Http::response([
        'id' => 'pi_bare',
        'status' => 'succeeded',
        'amount_received' => 2500,
        'metadata' => ['invoice_id' => (string) $invoice->id],
    ], 200)]);

    // Stripe puts a currency on every intent it returns, so this is a payload
    // nobody will see in production; what it pins down is that the fallback is
    // the old behaviour rather than a refusal or a guess at the shop currency.
    expect(app(StripeModule::class)->verifyPaymentIntent('pi_bare', (int) $invoice->id)['amount'])->toBe(25);
});

test('a krona payment is credited the krona, not a hundredth of them', function () {
    zdcStripeConfigured();
    zdcShopSellsIn('ISK');

    $invoice = zdcInvoice(500.0);

    Http::fake(['*' => Http::response([
        'id' => 'pi_isk',
        'status' => 'succeeded',
        'currency' => 'isk',
        'amount_received' => 50000,
        'metadata' => ['invoice_id' => (string) $invoice->id],
    ], 200)]);

    // The wire figure is two-decimal for ISK, so the division is right here and
    // has to stay right: 50000 on the wire is 500 krona.
    expect((float) app(StripeModule::class)->verifyPaymentIntent('pi_isk', (int) $invoice->id)['amount'])->toBe(500.0);
});

// ===================== on the way back: the webhook =====================

test('a yen webhook settles the yen invoice exactly', function () {
    zdcStripeConfigured();
    zdcShopSellsIn('JPY');

    $invoice = zdcInvoice(5000.0);

    zdcDeliver(zdcSucceededEvent($invoice, 'pi_jpy_hook', 5000, 'jpy'))->assertOk();

    $invoice->refresh();

    expect($invoice->status)->toBe('paid')
        ->and((float) app(PaymentService::class)->balance($invoice))->toBe(0.0)
        ->and((float) Transaction::where('transaction_id', 'pi_jpy_hook')->sole()->amount_in)->toBe(5000.0);
});

test('a dollar webhook records the dollars it always recorded', function () {
    zdcStripeConfigured();
    zdcShopSellsIn('USD');

    $invoice = zdcInvoice(100.0);

    zdcDeliver(zdcSucceededEvent($invoice, 'pi_usd_hook', 10000, 'usd'))->assertOk();

    $invoice->refresh();

    expect($invoice->status)->toBe('paid')
        ->and((float) Transaction::where('transaction_id', 'pi_usd_hook')->sole()->amount_in)->toBe(100.0);
});

test('the answer a dollar webhook hands back is unchanged, down to its types', function () {
    zdcStripeConfigured();
    zdcShopSellsIn('USD');

    $event = zdcSucceededEvent(zdcInvoice(25.0), 'pi_shape', 2500, 'usd');
    $body = json_encode($event);
    $timestamp = time();

    $result = app(StripeModule::class)->processWebhook(array_merge($event, [
        '_raw_payload' => $body,
        '_signature_header' => "t={$timestamp},v1=".hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test'),
    ]));

    // The sibling test in StripeWebhookDeduplicationTest pins this same shape
    // for an event with no currency on it. This one pins it for an event that
    // does carry one, which is every real event: naming the currency must not
    // turn the int 25 into a float.
    expect($result['amount'])->toBe(25);
});

test('a krona webhook credits the krona', function () {
    zdcStripeConfigured();
    zdcShopSellsIn('ISK');

    $invoice = zdcInvoice(500.0);

    zdcDeliver(zdcSucceededEvent($invoice, 'pi_isk_hook', 50000, 'isk'))->assertOk();

    expect((float) Transaction::where('transaction_id', 'pi_isk_hook')->sole()->amount_in)->toBe(500.0)
        ->and($invoice->refresh()->status)->toBe('paid');
});

test('a yen invoice charged and then credited comes out level', function () {
    zdcStripeConfigured();
    zdcShopSellsIn('JPY');

    $invoice = zdcInvoice(5000.0);

    Http::fake(['*' => Http::response(['id' => 'pi_round', 'client_secret' => 's'], 200)]);
    app(StripeModule::class)->capture($invoice, $invoice->amountDue());

    $charged = null;
    Http::assertSent(function ($request) use (&$charged) {
        $charged = $request['amount'];

        return true;
    });

    // What Stripe would then report back is what it was asked for, so the
    // webhook is fed the very figure the request carried rather than a figure
    // chosen to make the test pass.
    zdcDeliver(zdcSucceededEvent($invoice, 'pi_round', $charged, 'jpy'))->assertOk();

    $invoice->refresh();

    expect($charged)->toBe(5000)
        ->and($invoice->status)->toBe('paid')
        ->and((float) app(PaymentService::class)->balance($invoice))->toBe(0.0);
});

// ===================== refunds =====================

test('a yen refund hands back the yen', function () {
    zdcStripeConfigured();
    zdcShopSellsIn('JPY');

    $invoice = zdcInvoice(5000.0, 'JPY');
    app(PaymentService::class)->applyPayment($invoice, 'stripe', 'pi_jpy_paid', 5000.0);

    Http::fake(['*' => Http::response(['id' => 're_1', 'status' => 'succeeded'], 200)]);

    $result = app(PaymentService::class)->refundInvoice($invoice->fresh(), 5000.0);

    expect($result['success'])->toBeTrue();

    // 500000 would be a refund of half a million yen against a charge of five
    // thousand. Stripe refuses that one, so the customer gets nothing back and
    // the operator is left reading "refund failed" with no idea why.
    Http::assertSent(fn ($request) => $request->url() === 'https://api.stripe.com/v1/refunds'
        && $request['payment_intent'] === 'pi_jpy_paid'
        && $request['amount'] === 5000);
});

test('a dollar refund sends the request it has always sent', function () {
    zdcStripeConfigured();
    zdcShopSellsIn('USD');

    $invoice = zdcInvoice(50.0, 'USD');
    app(PaymentService::class)->applyPayment($invoice, 'stripe', 'pi_usd_paid', 50.0);

    Http::fake(['*' => Http::response(['id' => 're_2', 'status' => 'succeeded'], 200)]);

    app(PaymentService::class)->refundInvoice($invoice->fresh(), 25.0);

    Http::assertSent(function ($request) {
        expect($request->data())->toBe([
            'payment_intent' => 'pi_usd_paid',
            'amount' => 2500,
        ]);

        expect($request->hasHeader('Idempotency-Key'))->toBeFalse();

        return true;
    });
});

test('a refund against an id this end has no record of falls back to the shop currency', function () {
    zdcStripeConfigured();
    zdcShopSellsIn('JPY');
    Http::fake(['*' => Http::response(['id' => 're_3', 'status' => 'succeeded'], 200)]);

    // Called straight, with no transaction row behind the id — an old payment,
    // a manual call, a test. There is nothing to read the currency off, so the
    // shop's own currency answers, which is the only thing this method ever had
    // to go on before.
    app(StripeModule::class)->refund('pi_nowhere', 5000.0);

    Http::assertSent(fn ($request) => $request['amount'] === 5000);
});

test('the refund takes its currency from the invoice being refunded, not from what the shop sells in today', function () {
    zdcStripeConfigured();

    // The invoice was raised and paid in yen. The operator has since switched
    // the shop to dollars — the currency the amounts on that row are in is
    // stamped on the row itself for exactly this reason (Invoice::booted()).
    $invoice = zdcInvoice(5000.0, 'JPY');
    zdcShopSellsIn('JPY');
    app(PaymentService::class)->applyPayment($invoice, 'stripe', 'pi_was_jpy', 5000.0);

    zdcShopSellsIn('USD');
    Http::fake(['*' => Http::response(['id' => 're_4', 'status' => 'succeeded'], 200)]);

    app(PaymentService::class)->refundInvoice($invoice->fresh(), 5000.0);

    // Today's shop currency would make this 500000 and Stripe would refuse it.
    Http::assertSent(fn ($request) => $request['amount'] === 5000);
});

// ============ the two legs must read the same column ============
//
// The charge converted with shop_currency_code() while the refund converted
// with the invoice's own source_currency. Those agree until an operator changes
// the shop currency, and from then on an old invoice is charged at the new
// sign - the failure add_source_currency_to_invoices was written to stop, "a
// 264.89 lira invoice reprinted as 264.89 dollars", except that charging it
// costs money rather than ink. When exactly one of the two is zero-decimal the
// disagreement is a hundredfold.

test('an invoice is charged in its own currency, not the shop current one', function () {
    zdcStripeConfigured();
    // The invoice was raised while the shop sold in yen. The operator has since
    // switched the shop to dollars; the invoice still says 5,000 yen.
    zdcShopSellsIn('USD');
    Http::fake(['*' => Http::response(['id' => 'pi_old_jpy', 'client_secret' => 's'], 200)]);

    $invoice = zdcInvoice(5000.0, 'JPY');

    app(StripeModule::class)->capture($invoice, $invoice->total);

    // Reading the shop instead would send amount=500000 currency=usd: half a
    // million yen's worth of dollars, for an invoice of five thousand yen.
    Http::assertSent(fn ($request) => $request->url() === 'https://api.stripe.com/v1/payment_intents'
        && $request['amount'] === 5000
        && $request['currency'] === 'jpy');
});

test('the charge and the refund of one invoice agree on its currency', function () {
    zdcStripeConfigured();
    zdcShopSellsIn('USD');

    $invoice = zdcInvoice(5000.0, 'JPY');

    Http::fake(['*' => Http::response(['id' => 'pi_agree', 'client_secret' => 's'], 200)]);
    app(StripeModule::class)->capture($invoice, $invoice->total);

    $charged = null;
    Http::assertSent(function ($request) use (&$charged) {
        if ($request->url() === 'https://api.stripe.com/v1/payment_intents') {
            $charged = $request['amount'];
        }

        return true;
    });

    app(PaymentService::class)->applyPayment($invoice, 'stripe', 'pi_agree', 5000.0);

    Http::fake(['*' => Http::response(['id' => 're_agree', 'status' => 'succeeded'], 200)]);
    app(PaymentService::class)->refundInvoice($invoice->fresh(), 5000.0);

    $refunded = null;
    Http::assertSent(function ($request) use (&$refunded) {
        if ($request->url() === 'https://api.stripe.com/v1/refunds') {
            $refunded = $request['amount'];
        }

        return true;
    });

    // Whatever the figure is, giving back a different number of minor units
    // than were taken is the defect. They were 5000 and 500000 apart.
    expect($refunded)->toBe($charged)->and($charged)->toBe(5000);
});

test('an invoice from before the currency column falls back to the shop', function () {
    zdcStripeConfigured();
    zdcShopSellsIn('USD');
    Http::fake(['*' => Http::response(['id' => 'pi_legacy', 'client_secret' => 's'], 200)]);

    // add_source_currency_to_invoices backfilled nothing, so every invoice
    // raised before it carries null. Those must behave exactly as they did.
    $invoice = zdcInvoice(100.0, null);

    app(StripeModule::class)->capture($invoice, 100.0);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.stripe.com/v1/payment_intents'
        && $request['amount'] === 10000
        && $request['currency'] === 'usd');
});
