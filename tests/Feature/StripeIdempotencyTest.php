<?php

use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Http;
use Modules\Gateways\Stripe\StripeModule;

/**
 * Which Stripe calls say "this is a repeat of a request you have already seen",
 * and — just as important — which deliberately do not.
 *
 * An Idempotency-Key makes Stripe keep the answer it gave the first time and
 * replay it for the next twenty-four hours instead of doing the work again.
 * That is exactly right for a request nobody is watching: a job that crashed
 * between the POST and writing down what happened can be run again without
 * taking the money twice. It is wrong wherever two genuinely different requests
 * cannot be told apart by what goes into the key, because then the second one
 * silently does not happen while this end records that it did.
 *
 * Refunds are that case, and it is the reason the rule is written down here
 * rather than left to whoever adds the next header. PaymentService always
 * refunds against the same settling transaction id, and the admin refund form
 * takes a free-text amount, so "refund EUR 25" today and "refund EUR 25"
 * tomorrow are the same two figures describing two different refunds. Keyed on
 * them, Stripe returns the first refund's id, this module reports success, and
 * PNLCS credits the customer twice for money that moved once.
 *
 * The rest of the file is the other half of the bargain: capture() and refund()
 * send exactly what they have always sent and answer exactly as they always
 * did, header included — which is to say, no header.
 */
function stripeKeyConfigured(): void
{
    GatewaySettings::updateOrCreate(
        ['gateway' => 'stripe', 'setting' => 'secret_key'],
        ['value' => 'sk_test_x']
    );
}

/**
 * Every Stripe call the test made: where it went, the idempotency key it
 * carried, and the form body that went with it.
 */
function stripeCalls(): array
{
    return collect(Http::recorded())
        ->map(fn ($pair) => [
            'url' => $pair[0]->url(),
            'key' => $pair[0]->header('Idempotency-Key')[0] ?? null,
            'body' => $pair[0]->data(),
        ])
        ->all();
}

function invoiceToPay(float $total = 100.0): Invoice
{
    return Invoice::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'invoice_num' => 'INV-777001',
        'status' => 'unpaid',
        'total' => $total,
    ]);
}

function cardToCharge(Invoice $invoice): PaymentMethod
{
    return PaymentMethod::create([
        'client_id' => $invoice->client_id,
        'gateway_name' => 'stripe',
        'payment_type' => 'cc',
        'remote_token' => 'pm_key',
        'gateway_customer_id' => 'cus_key',
    ]);
}

// ===================== where a key belongs =====================

test('an off-session charge goes out with an idempotency key', function () {
    stripeKeyConfigured();
    Http::fake(['*' => Http::response(['id' => 'pi_1', 'status' => 'succeeded', 'amount_received' => 10000], 200)]);

    $invoice = invoiceToPay();

    app(StripeModule::class)->chargeStoredMethod($invoice, cardToCharge($invoice), 100.0);

    // The one call in this module that nobody is watching. A renewal run that
    // dies between the POST and recording the answer has to be safe to run
    // again, and this header is what makes the second run the same request.
    expect(stripeCalls()[0]['key'])->toStartWith('pnlcs-offsession-');
});

test('a second attempt at the same unattended charge is the same request to Stripe', function () {
    stripeKeyConfigured();
    Http::fake(['*' => Http::response(['id' => 'pi_1', 'status' => 'succeeded', 'amount_received' => 10000], 200)]);

    $invoice = invoiceToPay();
    $card = cardToCharge($invoice);
    $stripe = app(StripeModule::class);

    $stripe->chargeStoredMethod($invoice, $card, 100.0);
    $stripe->chargeStoredMethod($invoice, $card, 100.0);

    $calls = stripeCalls();

    expect($calls)->toHaveCount(2)
        ->and($calls[1]['key'])->toBe($calls[0]['key']);
});

test('an unattended charge for a different amount is a different request', function () {
    stripeKeyConfigured();
    Http::fake(['*' => Http::response(['id' => 'pi_1', 'status' => 'succeeded', 'amount_received' => 4000], 200)]);

    $invoice = invoiceToPay();
    $card = cardToCharge($invoice);
    $stripe = app(StripeModule::class);

    // A part payment and the full amount are two genuinely different things to
    // ask for, and they must not share a key: Stripe answers a repeated key
    // with the first answer, which would take the smaller sum twice.
    $stripe->chargeStoredMethod($invoice, $card, 40.0);
    $stripe->chargeStoredMethod($invoice, $card, 100.0);

    $calls = stripeCalls();

    expect($calls[1]['key'])->not->toBe($calls[0]['key']);
});

test('creating the customer a card hangs off carries a key of its own', function () {
    stripeKeyConfigured();
    Http::fake([
        '*/v1/customers' => Http::response(['id' => 'cus_new'], 200),
        '*/v1/setup_intents' => Http::response(['id' => 'seti_1', 'client_secret' => 'seti_1_secret'], 200),
    ]);

    app(StripeModule::class)->beginVaulting(Client::factory()->create());

    expect(stripeCalls()[0]['key'])->toStartWith('pnlcs-customer-')
        // The SetupIntent deliberately has none: it is consumed by the browser
        // that confirms it, so a customer coming back an hour later needs a
        // live intent rather than a replay of the spent one.
        ->and(stripeCalls()[1]['key'])->toBeNull();
});

test('one client does not inherit another client customer key', function () {
    stripeKeyConfigured();
    Http::fake([
        '*/v1/customers' => Http::response(['id' => 'cus_new'], 200),
        '*/v1/setup_intents' => Http::response(['id' => 'seti_1', 'client_secret' => 'seti_1_secret'], 200),
    ]);

    $stripe = app(StripeModule::class);
    $stripe->beginVaulting(Client::factory()->create());
    $stripe->beginVaulting(Client::factory()->create());

    $calls = collect(stripeCalls())->where('url', 'https://api.stripe.com/v1/customers')->values();

    expect($calls)->toHaveCount(2)
        ->and($calls[1]['key'])->not->toBe($calls[0]['key']);
});

test('the key belongs to this installation and to these figures', function () {
    stripeKeyConfigured();
    Http::fake(['*' => Http::response(['id' => 'pi_1', 'status' => 'succeeded', 'amount_received' => 10000], 200)]);

    $invoice = invoiceToPay();
    $card = cardToCharge($invoice);

    app(StripeModule::class)->chargeStoredMethod($invoice, $card, 100.0);

    // Worked out independently here, which is the whole check: the key is a
    // function of this application key and of the very figures being sent, so
    // it comes out the same on a retry and cannot be guessed or collided with
    // by a second installation sharing the same Stripe account. Nothing random
    // could satisfy this.
    $expected = 'pnlcs-offsession-'.substr(
        hash_hmac('sha256', $invoice->id.'|'.$card->id.'|10000|usd', (string) config('app.key')),
        0,
        40
    );

    expect(stripeCalls()[0]['key'])->toBe($expected);
});

// ===================== where a key does not belong =====================

test('two equal partial refunds are two refunds, not one', function () {
    stripeKeyConfigured();
    Http::fake(['*' => Http::response(['id' => 're_1', 'status' => 'succeeded'], 200)]);

    $stripe = app(StripeModule::class);
    $stripe->refund('pi_x', 25.0);
    $stripe->refund('pi_x', 25.0);

    $calls = stripeCalls();

    // Nothing tells Stripe these are the same request, because they are not:
    // two line items refunded separately, or a correction, or two agents, all
    // arrive here as the same transaction id and the same amount. A key built
    // from those would have Stripe replay the first refund and return its id,
    // and PaymentService would write a second refund transaction against money
    // that never moved.
    expect($calls)->toHaveCount(2)
        ->and($calls[0]['key'])->toBeNull()
        ->and($calls[1]['key'])->toBeNull();
});

test('the pay form does not hand back a client secret it was given yesterday', function () {
    stripeKeyConfigured();
    Http::fake(['*' => Http::response(['id' => 'pi_1', 'client_secret' => 'cs_1'], 200)]);

    $invoice = invoiceToPay();
    $stripe = app(StripeModule::class);

    // This endpoint is hit on every opening of the pay form. A key would have
    // Stripe replay the first intent for a day, including after that intent has
    // succeeded or been cancelled, and a spent client_secret cannot take the
    // money — the customer would be unable to pay until the key expired.
    $stripe->capture($invoice, 100.0);
    $stripe->capture($invoice, 100.0);

    $calls = stripeCalls();

    expect($calls)->toHaveCount(2)
        ->and($calls[0]['key'])->toBeNull()
        ->and($calls[1]['key'])->toBeNull();
});

// ===================== nothing else moved =====================

test('capture still sends what it always sent and answers as it always did', function () {
    stripeKeyConfigured();
    Http::fake(['*' => Http::response(['id' => 'pi_live', 'client_secret' => 'cs_live'], 200)]);

    $invoice = invoiceToPay(100.0);

    $result = app(StripeModule::class)->capture($invoice, 100.0);

    $call = stripeCalls()[0];

    expect($result)->toBe([
        'success' => true,
        'client_secret' => 'cs_live',
        'intent_id' => 'pi_live',
    ])
        ->and($call['url'])->toBe('https://api.stripe.com/v1/payment_intents')
        ->and($call['body'])->toBe([
            'amount' => 10000,
            'currency' => 'usd',
            'payment_method_types[]' => 'card',
            'description' => 'Invoice #INV-777001',
            'metadata[invoice_id]' => $invoice->id,
            'metadata[invoice_num]' => 'INV-777001',
        ]);
});

test('refund still sends what it always sent and answers as it always did', function () {
    stripeKeyConfigured();
    Http::fake(['*' => Http::response(['id' => 're_live', 'status' => 'succeeded'], 200)]);

    $result = app(StripeModule::class)->refund('pi_live', 40.0);

    $call = stripeCalls()[0];

    expect($result)->toBe([
        'success' => true,
        'refund_id' => 're_live',
        'status' => 'succeeded',
        'transaction_id' => 'pi_live',
    ])
        ->and($call['url'])->toBe('https://api.stripe.com/v1/refunds')
        ->and($call['body'])->toBe([
            'payment_intent' => 'pi_live',
            'amount' => 4000,
        ]);
});

test('a refused capture still comes back in the shape the callers read', function () {
    stripeKeyConfigured();
    Http::fake(['*' => Http::response(['error' => ['message' => 'Your card was declined.']], 402)]);

    $result = app(StripeModule::class)->capture(invoiceToPay(), 100.0);

    expect($result)->toBe([
        'success' => false,
        'message' => 'Stripe error: Your card was declined.',
    ]);
});

test('an unconfigured gateway still refuses before it sends anything', function () {
    GatewaySettings::where('gateway', 'stripe')->delete();
    Http::fake();

    $stripe = app(StripeModule::class);

    expect($stripe->capture(invoiceToPay(), 100.0))->toBe([
        'success' => false,
        'message' => 'Stripe secret key not configured.',
    ])
        ->and($stripe->refund('pi_x', 100.0))->toBe([
            'success' => false,
            'message' => 'Stripe secret key not configured.',
        ]);

    Http::assertNothingSent();
});
