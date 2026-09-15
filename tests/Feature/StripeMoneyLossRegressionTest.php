<?php

use App\Models\Client;
use App\Models\GatewayCustomer;
use App\Models\GatewayEvent;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Gateways\Stripe\StripeModule;

/**
 * Eight ways this module was shown to lose money, each one held open here so it
 * cannot close again quietly.
 *
 * The tests elsewhere in this suite describe how the Stripe module is meant to
 * work. These describe eight specific things it did instead, every one of which
 * was demonstrated on a real database against real money movements, and every
 * one of which looked entirely reasonable in the diff that introduced it. Each
 * test below is written to fail against the behaviour that was there — not
 * against a paraphrase of it — so that the same mistake made again is caught by
 * this file rather than by a customer.
 *
 * What they were, in the order they appear:
 *
 *  1. A webhook that crashed halfway was never delivered again. The event was
 *     claimed before the work and marked finished regardless of whether the
 *     work happened, so Stripe's retry — the retry it sends precisely because
 *     it never got a 2xx — was answered "already handled". Money at Stripe, an
 *     unpaid invoice here, and a suspension for a customer who paid. The same
 *     loss survived the first repair in a quieter form: the claim expired, but
 *     a retry arriving before it expired was still answered 2xx, and the
 *     exponential back off starts in seconds. That retry was the rescue, and
 *     one 2xx was the end of it.
 *  2. Two equal partial refunds became one. An idempotency key built from the
 *     transaction and the amount cannot tell a retry of one refund from a
 *     second genuine refund of the same size, so Stripe replayed the first and
 *     this end booked both.
 *  3. The same refusal got two opposite verdicts. A bank asking for the
 *     cardholder was final on the charge path and worth retrying tomorrow on
 *     the webhook path, which is a card charged nightly for an authentication
 *     nobody is present to give.
 *  4. Every coded error was a card decline. Reading the error's code before its
 *     type meant a detached card, an amount below Stripe's minimum and a
 *     missing parameter all came back worth trying again — forever.
 *  5. A shop selling in yen was charged a hundred times the invoice, because a
 *     zero-decimal currency has no minor unit to multiply into.
 *  6. A removed card was still chargeable and a dead card still showed as
 *     active, because nothing read the card's state before charging and nothing
 *     ever wrote that a card had been refused for good.
 *  7. A client who abandoned the card form was given a second Stripe customer
 *     the next day, scattering their cards across records nothing joins up.
 *  8. A card Stripe had accepted but this end failed to write down was
 *     acknowledged anyway, so the event was never redelivered and the row never
 *     existed.
 */
function stripeIsConfigured(): void
{
    foreach (['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_test'] as $setting => $value) {
        GatewaySettings::updateOrCreate(
            ['gateway' => 'stripe', 'setting' => $setting],
            ['value' => $value]
        );
    }
}

/**
 * An event as it reaches the module: the parsed body, plus the raw payload and
 * the signature header the webhook controller hands down with it.
 */
function signedStripeEvent(array $payload): array
{
    $body = json_encode($payload);
    $timestamp = time();

    return array_merge($payload, [
        '_raw_payload' => $body,
        '_signature_header' => "t={$timestamp},v1=".hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test'),
    ]);
}

/**
 * The same event over the wire, through the route Stripe actually posts to.
 *
 * Several of these failures only exist end to end: the invoice is credited by
 * the webhook controller after the module has returned and outside the try/catch
 * that guards it, so a test that stops at the module's return value cannot see
 * the half of the job where the money is recorded.
 */
function deliverStripeEvent(array $payload)
{
    $body = json_encode($payload);
    $timestamp = time();

    return test()->call('POST', '/gateway/stripe/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1=".hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test'),
    ], $body);
}

function unpaidInvoiceFor(float $total = 100.0): Invoice
{
    return Invoice::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'status' => 'unpaid',
        'total' => $total,
    ]);
}

/**
 * An invoice a customer has already paid by card, which is the only kind that
 * can be refunded through a gateway.
 */
function invoiceSettledByStripe(float $total, string $intentId): Invoice
{
    $invoice = unpaidInvoiceFor($total);

    app(PaymentService::class)->applyPayment($invoice, 'stripe', $intentId, $total);

    return $invoice->fresh();
}

/**
 * A card stored against this invoice's own client, written the way the
 * setup_intent.succeeded webhook writes one.
 */
function storedCardFor(Invoice $invoice, string $token = 'pm_stored'): PaymentMethod
{
    return PaymentMethod::create([
        'client_id' => $invoice->client_id,
        'gateway_name' => 'stripe',
        'payment_type' => 'cc',
        'remote_token' => $token,
        'gateway_customer_id' => 'cus_stored',
        'last_four' => '4242',
        'expiry_date' => '2030-07',
    ]);
}

/**
 * Hands back a way to charge a card and have Stripe refuse it in whatever terms
 * the test names.
 *
 * One stub, its answer swapped between calls: a second Http::fake() does not
 * replace the first, so re-faking inside a loop would put the same refusal to
 * the module over and over. The card is returned to active before each attempt
 * because a refusal that ends a card writes that on the row, and the next charge
 * would then refuse before sending anything — right, and fatal to a test whose
 * subject is how each separate refusal is read.
 */
function refusalFromStripe(Invoice $invoice, PaymentMethod $card): Closure
{
    $refusal = ['error' => [], 'status' => 402];

    Http::fake(function () use (&$refusal) {
        return Http::response(['error' => $refusal['error']], $refusal['status']);
    });

    return function (array $error, int $status = 402) use (&$refusal, $invoice, $card) {
        $refusal = ['error' => $error, 'status' => $status];
        $card->update(['status' => PaymentMethod::STATUS_ACTIVE]);

        return app(StripeModule::class)->chargeStoredMethod($invoice, $card, 50.0);
    };
}

// ============ 1. a delivery that crashed must happen, exactly once ============

test('the retry after a delivery that died before the ledger still pays the invoice', function () {
    stripeIsConfigured();

    $invoice = unpaidInvoiceFor(100.0);
    $event = [
        'id' => 'evt_crash',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => [
            'id' => 'pi_crash',
            'metadata' => ['invoice_id' => (string) $invoice->id],
            'amount_received' => 10000,
        ]],
    ];

    // A transient database fault at the exact moment the payment is written
    // down — a deadlock, a lock-wait timeout, a connection lost. Standing a
    // broken table in front of the real one for the length of this request is
    // the honest way to produce one: a temporary table shadows transactions for
    // this connection only, so the write fails where the real thing fails.
    DB::statement('DROP TEMPORARY TABLE IF EXISTS transactions');
    DB::statement('CREATE TEMPORARY TABLE transactions (id BIGINT PRIMARY KEY)');

    // The controller credits the invoice outside the try/catch that guards the
    // module, so the fault escapes as a 500 and Stripe never gets its 2xx.
    deliverStripeEvent($event)->assertStatus(500);

    DB::statement('DROP TEMPORARY TABLE transactions');

    expect($invoice->fresh()->status)->toBe('unpaid')
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(0);

    // Stripe redelivers "for up to three days with an exponential back off in
    // live mode" (https://docs.stripe.com/webhooks). Six minutes later it knocks
    // again and finds the abandoned claim cold. Answering that knock with
    // "already handled" is what turned a crash into a payment nobody recorded.
    $this->travel(6)->minutes();

    deliverStripeEvent($event)->assertOk();

    expect($invoice->fresh()->status)->toBe('paid')
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(1);
});

test('two deliveries of one payment credit the invoice once, held claim or expired', function () {
    stripeIsConfigured();

    $invoice = unpaidInvoiceFor(100.0);
    $event = [
        'id' => 'evt_together',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => [
            'id' => 'pi_together',
            'metadata' => ['invoice_id' => (string) $invoice->id],
            'amount_received' => 10000,
        ]],
    ];

    // Two deliveries of one event arriving together, which is exactly what a
    // gateway retrying on a slow response produces. The first holds the claim;
    // the second is told the work is not its own and to come back, because at
    // the moment it asks there is no way of knowing whether the first will get
    // as far as the ledger. Costing a redelivery is the price of never
    // answering "done" for work that might not happen.
    deliverStripeEvent($event)->assertOk();
    deliverStripeEvent($event)->assertStatus(409);

    expect($invoice->fresh()->status)->toBe('paid')
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(1);

    // And letting the claim expire has to be safe on its own merits, because
    // that expiry is what rescues a crashed delivery. It is: the crediting
    // behind it refuses a transaction id it has already recorded, so a
    // redelivery that runs the work a second time changes nothing.
    $this->travel(6)->minutes();

    deliverStripeEvent($event)->assertOk();

    expect(Transaction::where('invoice_id', $invoice->id)->count())->toBe(1);
});

test('a retry that lands while the dead delivery still holds the claim is never answered as done', function () {
    stripeIsConfigured();

    $invoice = unpaidInvoiceFor(100.0);
    $event = [
        'id' => 'evt_warm',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => [
            'id' => 'pi_warm',
            'metadata' => ['invoice_id' => (string) $invoice->id],
            'amount_received' => 10000,
        ]],
    ];

    // The same crash as above, and the same claim left behind by it.
    DB::statement('DROP TEMPORARY TABLE IF EXISTS transactions');
    DB::statement('CREATE TEMPORARY TABLE transactions (id BIGINT PRIMARY KEY)');

    deliverStripeEvent($event)->assertStatus(500);

    DB::statement('DROP TEMPORARY TABLE transactions');

    expect($invoice->fresh()->status)->toBe('unpaid')
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(0);

    // What is different is when Stripe comes back. The retries are exponential
    // and they start small, so the first one arrives long before the lease has
    // run out — a minute after the crash, not six. It meets its own dead
    // predecessor's claim, still inside its five minutes.
    $this->travel(60)->seconds();

    $retry = deliverStripeEvent($event);

    // This answer is the whole test. Only a 2xx tells Stripe the delivery
    // landed, and a delivery that landed is never sent again: answering this
    // one 200 ends the redeliveries with the money at Stripe, the invoice
    // unpaid and not one transaction row to show for it. Anything else brings
    // the event back, because Stripe retries a failed delivery "for up to three
    // days with an exponential back off in live mode"
    // (https://docs.stripe.com/webhooks).
    expect($retry->getStatusCode())->toBeGreaterThanOrEqual(
        300,
        'a 2xx here stops Stripe retrying and the payment is lost'
    );

    $retry->assertStatus(409);

    expect($invoice->fresh()->status)->toBe('unpaid')
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(0);

    // And because it does come back, the payment is recorded in the end: the
    // next knock finds the lease cold, takes the event over and does the work
    // the dead delivery never finished.
    $this->travel(5)->minutes();

    deliverStripeEvent($event)->assertOk();

    expect($invoice->fresh()->status)->toBe('paid')
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(1);
});

// ================ 2. two refunds are two refunds ================

test('two equal partial refunds both happen, and the books say what Stripe did', function () {
    stripeIsConfigured();

    // A Stripe that behaves the way Stripe documents. "Subsequent requests with
    // the same key return the same result" — a repeated Idempotency-Key is not
    // performed again, it is answered from storage.
    // https://docs.stripe.com/api/idempotent_requests
    $moved = [];
    $replay = [];

    Http::fake(function ($request) use (&$moved, &$replay) {
        if (! str_contains($request->url(), '/v1/refunds')) {
            return Http::response([], 200);
        }

        $key = $request->header('Idempotency-Key')[0] ?? null;

        if ($key !== null && isset($replay[$key])) {
            return Http::response($replay[$key], 200);
        }

        $answer = ['id' => 're_'.(count($moved) + 1), 'status' => 'succeeded'];
        $moved[] = (float) $request->data()['amount'] / 100;

        if ($key !== null) {
            $replay[$key] = $answer;
        }

        return Http::response($answer, 200);
    });

    $invoice = invoiceSettledByStripe(100.0, 'pi_settled');
    $payments = app(PaymentService::class);

    // Two line items refunded separately, or a correction, or two agents. They
    // reach the gateway as the same settling transaction id and the same
    // amount, because that is all a refund is given to identify itself by.
    $payments->refundInvoice($invoice->fresh(), 25.0);
    $payments->refundInvoice($invoice->fresh(), 25.0);

    $refundRows = Transaction::where('invoice_id', $invoice->id)->where('amount_out', '>', 0)->get();

    expect(array_sum($moved))->toBe(50.0, 'Stripe must have been asked to move both refunds')
        ->and(round((float) $refundRows->sum('amount_out'), 2))->toBe(50.0)
        // The books and the bank have to agree about which refunds exist, not
        // merely about how much they add up to. Two rows pointing at one refund
        // id is the shape of money this end says it returned and never did.
        ->and($refundRows->pluck('transaction_id')->unique()->count())->toBe(2);
});

// ============ 3. one refusal, one verdict, wherever it is read ============

test('a bank asking for the cardholder is read the same way by every judge of it', function () {
    stripeIsConfigured();

    $invoice = unpaidInvoiceFor(50.0);
    $card = storedCardFor($invoice);
    $charge = refusalFromStripe($invoice, $card);

    // Both are documented decline codes for exactly this situation, and Stripe
    // words the first of them as an error code as well as a decline code, so
    // either field alone has to be enough. https://docs.stripe.com/declines/codes
    foreach (['authentication_required', 'mobile_device_authentication_required'] as $code) {
        $error = ['type' => 'card_error', 'code' => 'card_declined', 'decline_code' => $code, 'message' => 'Authenticate', 'payment_intent' => ['id' => 'pi_'.$code]];

        $charged = $charge($error);

        // The charge path recognised this by its HTTP status as well as its
        // code. A refusal that arrives in any other shape must not therefore
        // fall through to being called worth another go.
        $oddlyShaped = $charge($error, 400);

        $reported = app(StripeModule::class)->processWebhook(signedStripeEvent([
            'id' => 'evt_auth_'.$code,
            'type' => 'payment_intent.payment_failed',
            'data' => ['object' => [
                'id' => 'pi_'.$code,
                'payment_method' => 'pm_stored',
                'metadata' => ['invoice_id' => (string) $invoice->id],
                'last_payment_error' => $error,
            ]],
        ]));

        expect($charged['status'])->toBe('requires_action', $code)
            ->and($charged['retryable'])->toBeFalse("{$code} must not be retried by the charge")
            ->and($oddlyShaped['retryable'])->toBeFalse("{$code} must not be retried whatever shape it arrives in")
            ->and($reported['retryable'])->toBeFalse("{$code} must not be retried by the webhook either")
            // And the card is left usable, because it is: it works perfectly
            // well with its owner in front of it, and telling them to replace
            // it would lose a payment that was one confirmation away.
            ->and($card->fresh()->status)->toBe(PaymentMethod::STATUS_ACTIVE, $code);
    }
});

// ============ 4. what kind of error it is decides, not that it has a code ============

test('an invalid request is never worth repeating and an empty account always is', function () {
    stripeIsConfigured();

    $invoice = unpaidInvoiceFor(50.0);
    $card = storedCardFor($invoice);
    $charge = refusalFromStripe($invoice, $card);

    // Every one of these carries a code, and reading the code before the type
    // was what made them all come back retryable: a card detached at Stripe, an
    // amount below Stripe's minimum, a parameter we failed to send. None of
    // them becomes true by being sent again tomorrow, and an unattended loop
    // would send it every night forever.
    foreach (['resource_missing', 'amount_too_small', 'amount_too_large', 'parameter_missing', 'payment_method_unactivated'] as $code) {
        $result = $charge(['type' => 'invalid_request_error', 'code' => $code, 'message' => 'Invalid'], 400);

        expect($result['retryable'])->toBeFalse("{$code} must not be retried")
            // Nor is it the customer's card's fault. Sending them to their bank
            // over our own mistake would be a lie, and would cost the renewal.
            ->and($card->fresh()->status)->toBe(PaymentMethod::STATUS_ACTIVE, $code);
    }

    // The other half of the same judgement: an issuer decline that will clear
    // on its own must stay retryable, or writing the invoice off today loses
    // money that was going to arrive on payday.
    $skint = $charge(['type' => 'card_error', 'code' => 'card_declined', 'decline_code' => 'insufficient_funds', 'message' => 'No funds']);

    expect($skint['retryable'])->toBeTrue()
        ->and($skint['decline_code'])->toBe('insufficient_funds')
        ->and($card->fresh()->status)->toBe(PaymentMethod::STATUS_ACTIVE);
});

// ================ 5. the minor unit is not always a hundredth ================

test('a shop selling in yen is charged the invoice, not a hundred times it', function () {
    stripeIsConfigured();
    Http::fake(['*' => Http::response(['id' => 'pi_jpy', 'status' => 'succeeded', 'amount_received' => 5000], 200)]);

    $invoice = unpaidInvoiceFor(5000.0);

    // "Enter 10 to charge 10 JPY (or any other zero-decimal currency)"
    // https://docs.stripe.com/currencies#zero-decimal
    //
    // The shop's currency is operator-set free text with nothing validating it,
    // and this is an off-session charge, so a hundredfold overcharge happens
    // with nobody watching and is discovered as chargebacks.
    $result = app(StripeModule::class)->chargeStoredMethod($invoice, storedCardFor($invoice), 5000.0, ['currency' => 'jpy']);

    Http::assertSent(fn ($request) => $request['amount'] === 5000 && $request['currency'] === 'jpy');

    // Read back through the same conversion, so the invoice is credited with
    // what Stripe took rather than a hundredth of it. Correcting one direction
    // and not the other turns a loud disaster into a quiet bookkeeping error.
    expect($result['amount'])->toBe(5000.0);
});

test('an ordinary currency is still sent in hundredths', function () {
    stripeIsConfigured();
    Http::fake(['*' => Http::response(['id' => 'pi_eur', 'status' => 'succeeded', 'amount_received' => 5050], 200)]);

    $invoice = unpaidInvoiceFor(50.5);

    // The other direction of the same mistake, and the reason the fix is a list
    // rather than a rule: undercharging every two-decimal shop by a hundred
    // would be the same failure wearing the opposite sign.
    $result = app(StripeModule::class)->chargeStoredMethod($invoice, storedCardFor($invoice), 50.5, ['currency' => 'eur']);

    Http::assertSent(fn ($request) => $request['amount'] === 5050);

    expect($result['amount'])->toBe(50.5);
});

// ================ 6. the state of the card is read before it is charged ================

test('a card the customer removed and a card already refused are never sent to Stripe', function () {
    stripeIsConfigured();
    Http::fake(['*' => Http::response(['id' => 'pi_no', 'status' => 'succeeded', 'amount_received' => 5000], 200)]);

    $invoice = unpaidInvoiceFor(50.0);

    $removed = storedCardFor($invoice, 'pm_removed');
    $removed->delete();

    $refused = storedCardFor($invoice, 'pm_refused');
    $refused->update(['status' => PaymentMethod::STATUS_REQUIRES_UPDATE]);

    $stripe = app(StripeModule::class);

    // Removing a card here does not detach it at Stripe, so the token on the
    // deleted row very probably still works — which is precisely why this end
    // has to refuse rather than leave it to Stripe. And a card the issuer has
    // already finished with is not worth asking about again: issuers read
    // repeated attempts on a dead card as fraud, which drags acceptance down on
    // the cards that would have worked.
    foreach ([$removed, $refused] as $card) {
        $result = $stripe->chargeStoredMethod($invoice, $card, 50.0);

        expect($result['success'])->toBeFalse()
            ->and($result['status'])->toBe('failed')
            ->and($result['retryable'])->toBeFalse();
    }

    Http::assertNothingSent();
});

test('a card the issuer has finished with is written down, and written back when it is reissued', function () {
    stripeIsConfigured();

    $invoice = unpaidInvoiceFor(50.0);
    $card = storedCardFor($invoice, 'pm_dead');

    // First from the charge, which is where an unattended renewal meets the
    // refusal. The column existed and nothing ever wrote to it, so a dead card
    // went on showing as active in the panel and every renewal walked into the
    // same refusal.
    refusalFromStripe($invoice, $card)(['type' => 'card_error', 'code' => 'card_declined', 'decline_code' => 'lost_card', 'message' => 'Declined']);

    expect($card->fresh()->status)->toBe(PaymentMethod::STATUS_REQUIRES_UPDATE);

    $card->update(['status' => PaymentMethod::STATUS_ACTIVE]);

    // And again from the failure webhook, which is where a payment this module
    // never sent arrives. Both read the same list, so a refusal cannot be final
    // in one place and survivable in the other.
    app(StripeModule::class)->processWebhook(signedStripeEvent([
        'id' => 'evt_dead',
        'type' => 'payment_intent.payment_failed',
        'data' => ['object' => [
            'id' => 'pi_dead',
            'payment_method' => 'pm_dead',
            'metadata' => ['invoice_id' => (string) $invoice->id],
            'last_payment_error' => ['type' => 'card_error', 'code' => 'card_declined', 'decline_code' => 'stolen_card', 'message' => 'Declined'],
        ]],
    ]));

    expect($card->fresh()->status)->toBe(PaymentMethod::STATUS_REQUIRES_UPDATE);

    // The half that lets a customer out again. Stripe's card updater replaces
    // the card behind the token we already hold, and a card the issuer has just
    // reissued is a card it expects to be charged. Without this the status is a
    // one-way door and the customer is asked to re-enter a card that works.
    app(StripeModule::class)->processWebhook(signedStripeEvent([
        'id' => 'evt_reissued',
        'type' => 'payment_method.automatically_updated',
        'data' => ['object' => [
            'id' => 'pm_dead',
            'card' => ['brand' => 'visa', 'last4' => '5555', 'exp_month' => 11, 'exp_year' => 2033],
        ]],
    ]));

    expect($card->fresh()->status)->toBe(PaymentMethod::STATUS_ACTIVE)
        ->and($card->fresh()->last_four)->toBe('5555');
});

// ================ 7. one client, one customer at Stripe ================

test('a client who abandons the card form and comes back the next day keeps one customer', function () {
    stripeIsConfigured();

    // A Stripe that mints a new customer every time it is asked, which is
    // exactly what it does once the idempotency key from the first attempt has
    // been pruned: "We generate a new request if a key is reused after the
    // original is pruned" — after twenty-four hours.
    // https://docs.stripe.com/api/idempotent_requests
    $created = 0;

    Http::fake(function ($request) use (&$created) {
        if (str_contains($request->url(), '/v1/customers')) {
            $created++;

            return Http::response(['id' => 'cus_'.$created], 200);
        }

        return Http::response(['id' => 'seti_1', 'client_secret' => 'seti_1_secret'], 200);
    });

    $client = Client::factory()->create();
    $stripe = app(StripeModule::class);

    // The first visit is abandoned: no card is ever confirmed, so no
    // setup_intent.succeeded ever arrives. An id written down only when a card
    // lands is an id that was never written down at all.
    $first = $stripe->beginVaulting($client);
    $second = $stripe->beginVaulting($client);

    expect($created)->toBe(1, "a second Stripe customer scatters this client's cards")
        ->and($second['customer_id'])->toBe($first['customer_id'])
        ->and(GatewayCustomer::where('client_id', $client->id)->count())->toBe(1);
});

test('two card forms open at once still leave the client with one customer', function () {
    $client = Client::factory()->create();

    // Both tabs reached Stripe and both came back with an id, so a key is no
    // help: the two requests were in flight together. Only one of them can be
    // this client's customer from here on, and the unique key picks it rather
    // than whichever happened to write last. The loser is told the winner's id,
    // so the card it is about to store hangs off the same record.
    $firstTab = GatewayCustomer::remember('stripe', (int) $client->id, 'cus_tab_one');
    $secondTab = GatewayCustomer::remember('stripe', (int) $client->id, 'cus_tab_two');

    expect($firstTab)->toBe('cus_tab_one')
        ->and($secondTab)->toBe('cus_tab_one')
        ->and(GatewayCustomer::where('client_id', $client->id)->count())->toBe(1);
});

// ================ 8. a card that was not written down is not finished ================

test('a card that could not be stored is not called finished, and the next delivery stores it', function () {
    stripeIsConfigured();
    Http::fake(['*/v1/payment_methods/*' => Http::response([
        'id' => 'pm_vaulted',
        'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 7, 'exp_year' => 2030],
    ], 200)]);

    $client = Client::factory()->create();
    $event = [
        'id' => 'evt_setup_fault',
        'type' => 'setup_intent.succeeded',
        'data' => ['object' => [
            'id' => 'seti_fault',
            'payment_method' => 'pm_vaulted',
            'customer' => 'cus_vaulted',
            'metadata' => ['client_id' => (string) $client->id],
        ]],
    ];

    // Stripe has the card either way. Acknowledging an event whose row was
    // never written leaves the customer with a card at Stripe that this system
    // has no idea exists — the renewal it was stored for still fails, and
    // nothing anywhere says why.
    DB::statement('DROP TEMPORARY TABLE IF EXISTS payment_methods');
    DB::statement('CREATE TEMPORARY TABLE payment_methods (id BIGINT PRIMARY KEY)');

    deliverStripeEvent($event)->assertStatus(500);

    DB::statement('DROP TEMPORARY TABLE payment_methods');

    expect(PaymentMethod::where('remote_token', 'pm_vaulted')->count())->toBe(0)
        ->and(GatewayEvent::where('event_id', 'evt_setup_fault')->first()->processed_at)->toBeNull();

    // The whole worth of leaving it unstamped: Stripe brings the same event
    // back, and the delivery that follows writes the row the first one could
    // not.
    $this->travel(6)->minutes();

    deliverStripeEvent($event)->assertOk();

    $stored = PaymentMethod::where('remote_token', 'pm_vaulted')->first();

    expect($stored)->not->toBeNull()
        ->and($stored->gateway_customer_id)->toBe('cus_vaulted')
        ->and($stored->last_four)->toBe('4242')
        ->and(GatewayEvent::where('event_id', 'evt_setup_fault')->first()->processed_at)->not->toBeNull();
});
