<?php

use App\Enums\GatewayEventClaim;
use App\Models\Client;
use App\Models\GatewayEvent;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Gateways\Stripe\StripeModule;

/**
 * The same webhook delivered twice must only be acted on once — and a delivery
 * that died halfway must still be able to happen at all.
 *
 * Stripe keeps delivering an event until it gets a 2xx, and it will redeliver
 * one that was in fact handled but answered too slowly — a slow renewal run,
 * a restart mid-request, a timeout on our side is all it takes. Until now the
 * only thing standing between a redelivery and a second application of it was
 * PaymentService refusing a transaction id it had already recorded, which
 * covers a payment arriving twice and nothing else: a card update, a failed
 * payment, a stored card confirmed are all events with nothing to compare.
 *
 * So a delivery takes the event before acting on it, and the database decides
 * who took it. The insert is the check, because asking first and writing
 * afterwards leaves a gap that two simultaneous deliveries fit through neatly —
 * and two simultaneous deliveries is exactly what a gateway retrying on a slow
 * response produces.
 *
 * Taking it is a lease, not a gravestone, and that distinction is the point of
 * this file. Stripe retries an event precisely because it never got its 2xx,
 * which means the retry it sends is the delivery whose first attempt crashed.
 * A record that only meant "seen" would meet that retry with "already done" and
 * the payment it was carrying would never be recorded anywhere: money at
 * Stripe, an unpaid invoice here, and a suspension for a customer who paid. So
 * the claim expires, and an event is only marked finished once the work behind
 * it is actually finished — which, for a payment, is after the caller has
 * credited the invoice, not when this module hands it over.
 *
 * What a redelivery is told depends on which redelivery it is, and there are two
 * of them wearing one face. A delivery of an event that is finished is
 * acknowledged: the work is done, and success tells Stripe the delivery landed
 * rather than that anything happened this time. A delivery of an event another
 * delivery is holding is told the opposite — come back — because nothing has
 * been settled yet and the holder may be the request that died. One answer for
 * both was the same lost payment in a subtler form: the retry Stripe sends
 * after a crash arrives inside the five-minute lease, is met with the claim its
 * own dead predecessor left behind, and a 2xx ends the redeliveries for good.
 *
 * Everything the signature check did before is checked again at the bottom of
 * this file. The new code sits behind that gate, and a gate that moved would
 * be worse than no dedup at all.
 */
/**
 * Push an event's claim back in time, the way a delivery that crashed leaves it.
 */
function ageStripeClaim(string $eventId, int $seconds = GatewayEvent::CLAIM_LEASE_SECONDS + 60): void
{
    GatewayEvent::where('event_id', $eventId)->update(['claimed_at' => now()->subSeconds($seconds)]);
}
function stripeSigningSecret(string $secret = 'whsec_test'): void
{
    GatewaySettings::updateOrCreate(
        ['gateway' => 'stripe', 'setting' => 'webhook_secret'],
        ['value' => $secret]
    );
}

/**
 * An event as it reaches the module: the parsed body, plus the raw payload and
 * the signature header the controller hands down with it.
 */
function stripeDelivery(array $payload, string $secret = 'whsec_test', ?int $timestamp = null): array
{
    $body = json_encode($payload);
    $timestamp ??= time();

    return array_merge($payload, [
        '_raw_payload' => $body,
        '_signature_header' => "t={$timestamp},v1=".hash_hmac('sha256', $timestamp.'.'.$body, $secret),
    ]);
}

/**
 * The same event arriving over the wire, the way Stripe sends it.
 */
function postStripeEvent(array $payload, string $secret = 'whsec_test')
{
    $body = json_encode($payload);
    $timestamp = time();

    return test()->call('POST', '/gateway/stripe/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1=".hash_hmac('sha256', $timestamp.'.'.$body, $secret),
    ], $body);
}

function stripeInvoiceAwaitingPayment(float $total = 100.0): Invoice
{
    return Invoice::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'status' => 'unpaid',
        'total' => $total,
    ]);
}

// ===================== a repeat is not a second payment =====================

test('an event delivered twice pays the invoice once', function () {
    stripeSigningSecret();

    $invoice = stripeInvoiceAwaitingPayment(100.0);
    $event = [
        'id' => 'evt_e2e',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => [
            'id' => 'pi_e2e',
            'metadata' => ['invoice_id' => (string) $invoice->id],
            'amount_received' => 10000,
        ]],
    ];

    postStripeEvent($event)->assertOk();

    expect($invoice->fresh()->status)->toBe('paid')
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(1);

    // Stripe knocking again because the first answer was slow. The claim the
    // first delivery left is still warm, and from here there is no way to tell
    // a delivery that finished from one that died on the way to the ledger — so
    // this one is asked to come back rather than told the work is done.
    postStripeEvent($event)->assertStatus(409);

    expect(Transaction::where('invoice_id', $invoice->id)->count())->toBe(1);

    // It comes back to a cold lease, takes the event over and runs the work
    // again — and the invoice is still paid exactly once, because the crediting
    // refuses a transaction id it has already recorded. That is what makes
    // asking for a redelivery free: the answer converges either way.
    $this->travel(6)->minutes();

    postStripeEvent($event)->assertOk();

    expect($invoice->fresh()->status)->toBe('paid')
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(1);
});

test('a repeat arriving while the first delivery still holds the event asks to be sent again', function () {
    stripeSigningSecret();

    $event = [
        'id' => 'evt_dup',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => [
            'id' => 'pi_dup',
            'metadata' => ['invoice_id' => '9'],
            'amount_received' => 1000,
        ]],
    ];

    $stripe = app(StripeModule::class);
    $first = $stripe->processWebhook(stripeDelivery($event));
    $second = $stripe->processWebhook(stripeDelivery($event));

    expect($first['transaction_id'])->toBe('pi_dup')
        // No transaction_id, so the webhook controller has nothing to record
        // and the invoice cannot be paid twice — and no acknowledgement either.
        // Nothing about this event is settled: the delivery holding it is the
        // one crediting the invoice, and it may not get there. Saying "already
        // handled" to the delivery that would have rescued it is the whole bug.
        ->and($second)->not->toHaveKey('transaction_id')
        ->and($second['success'])->toBeFalse()
        ->and($second['retry_delivery'])->toBeTrue()
        ->and(GatewayEvent::where('event_id', 'evt_dup')->count())->toBe(1);
});

test('a repeat of an event that is finished is acknowledged and asks for nothing', function () {
    stripeSigningSecret();

    // The other kind of repeat, and the one that must still be answered with
    // success: the card update was written by the first delivery and there is
    // no second act anybody could still be working on. Asking Stripe to come
    // back for this would be asking forever.
    $event = [
        'id' => 'evt_settled',
        'type' => 'payment_method.automatically_updated',
        'data' => ['object' => [
            'id' => 'pm_settled',
            'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 1, 'exp_year' => 2033],
        ]],
    ];

    $stripe = app(StripeModule::class);
    $stripe->processWebhook(stripeDelivery($event));
    $second = $stripe->processWebhook(stripeDelivery($event));

    expect(GatewayEvent::where('event_id', 'evt_settled')->first()->processed_at)->not->toBeNull()
        ->and($second['success'])->toBeTrue()
        ->and($second['message'])->toContain('already handled')
        ->and($second)->not->toHaveKey('retry_delivery');
});

// ===================== a crash is not a repeat =====================

test('a payment is not marked finished when the crediting still has to happen', function () {
    stripeSigningSecret();

    app(StripeModule::class)->processWebhook(stripeDelivery([
        'id' => 'evt_unfinished',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => [
            'id' => 'pi_unfinished',
            'metadata' => ['invoice_id' => '9'],
            'amount_received' => 1000,
        ]],
    ]));

    // The webhook controller credits the invoice after this module has returned
    // and outside the try/catch that guards it, so at this moment nobody knows
    // whether the money was recorded. Calling the event finished here is what
    // turned a crash into a lost payment.
    $row = GatewayEvent::where('event_id', 'evt_unfinished')->first();

    expect($row->claimed_at)->not->toBeNull()
        ->and($row->processed_at)->toBeNull();
});

test('an event this module finishes by itself is marked finished', function () {
    stripeSigningSecret();

    // Nothing is handed on: the card update is written here and there is no
    // second act for anyone else to perform.
    app(StripeModule::class)->processWebhook(stripeDelivery([
        'id' => 'evt_done',
        'type' => 'payment_method.automatically_updated',
        'data' => ['object' => [
            'id' => 'pm_nobody_has',
            'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 1, 'exp_year' => 2033],
        ]],
    ]));

    expect(GatewayEvent::where('event_id', 'evt_done')->first()->processed_at)->not->toBeNull();
});

test('the claim says which of the three states it found', function () {
    // The distinction the caller cannot make for itself, and the reason this is
    // three answers rather than true and false. Held and Finished were one
    // answer once — "not yours" — and the caller, having nothing else to go on,
    // acknowledged both. One of them was a delivery still in flight.
    expect(GatewayEvent::claim('stripe', 'evt_states', 'payment_intent.succeeded'))
        ->toBe(GatewayEventClaim::Taken)
        ->and(GatewayEvent::claim('stripe', 'evt_states', 'payment_intent.succeeded'))
        ->toBe(GatewayEventClaim::Held);

    // Cold lease: the delivery that took it never came back, and this one may.
    ageStripeClaim('evt_states');

    expect(GatewayEvent::claim('stripe', 'evt_states', 'payment_intent.succeeded'))
        ->toBe(GatewayEventClaim::Taken);

    GatewayEvent::markProcessed('stripe', 'evt_states', 'payment_intent.succeeded');

    // And finished stays finished, however cold the claim gets — there is
    // nothing left to rescue, so nobody is asked to come back.
    ageStripeClaim('evt_states');

    expect(GatewayEvent::claim('stripe', 'evt_states', 'payment_intent.succeeded'))
        ->toBe(GatewayEventClaim::Finished)
        ->and(GatewayEvent::where('event_id', 'evt_states')->count())->toBe(1);
});

test('the retry after a crashed delivery still pays the invoice', function () {
    stripeSigningSecret();

    $invoice = stripeInvoiceAwaitingPayment(100.0);
    $event = [
        'id' => 'evt_crash',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => [
            'id' => 'pi_crash',
            'metadata' => ['invoice_id' => (string) $invoice->id],
            'amount_received' => 10000,
        ]],
    ];

    // The first delivery got as far as this module and then died on the way to
    // recording the payment — a deadlock, a lost connection, a worker killed.
    // Stripe never saw a 2xx, so it will come back.
    app(StripeModule::class)->processWebhook(stripeDelivery($event));

    expect($invoice->fresh()->status)->toBe('unpaid')
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(0);

    // Stripe redelivers for up to three days, so by the time it knocks again
    // the abandoned claim has gone cold and this delivery takes it over.
    ageStripeClaim('evt_crash');

    postStripeEvent($event)->assertOk();

    expect($invoice->fresh()->status)->toBe('paid')
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(1);
});

test('taking over an abandoned event still cannot pay the same invoice twice', function () {
    stripeSigningSecret();

    $invoice = stripeInvoiceAwaitingPayment(100.0);
    $event = [
        'id' => 'evt_twice',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => [
            'id' => 'pi_twice_paid',
            'metadata' => ['invoice_id' => (string) $invoice->id],
            'amount_received' => 10000,
        ]],
    ];

    postStripeEvent($event)->assertOk();

    // The first delivery did credit the invoice; it simply never got to say so
    // on the row. Letting the lease expire therefore has to be safe on its own
    // merits, and it is: PaymentService refuses a transaction id it has already
    // recorded, so the second run changes nothing.
    ageStripeClaim('evt_twice');

    postStripeEvent($event)->assertOk();

    expect($invoice->fresh()->status)->toBe('paid')
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(1);
});

test('a delivery that is still running keeps the event, and the lease is not taken from it', function () {
    stripeSigningSecret();

    $invoice = stripeInvoiceAwaitingPayment(100.0);
    $event = [
        'id' => 'evt_race',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => [
            'id' => 'pi_race',
            'metadata' => ['invoice_id' => (string) $invoice->id],
            'amount_received' => 10000,
        ]],
    ];

    // Two deliveries of one event arriving together, which is what a gateway
    // retrying on a slow response produces. The claim is fresh, so the second
    // does not get the work — the expiry only ever applies to a delivery that
    // has gone quiet, never to one that is under way, and the held claim must
    // come back out of this untouched or the first delivery's lease would be
    // silently extended by every duplicate that bounced off it.
    $stripe = app(StripeModule::class);
    $first = $stripe->processWebhook(stripeDelivery($event));

    $heldSince = GatewayEvent::where('event_id', 'evt_race')->first()->claimed_at;

    $this->travel(30)->seconds();

    $second = $stripe->processWebhook(stripeDelivery($event));

    expect($first['transaction_id'])->toBe('pi_race')
        ->and($second)->not->toHaveKey('transaction_id')
        ->and($second['retry_delivery'])->toBeTrue()
        ->and(GatewayEvent::where('event_id', 'evt_race')->count())->toBe(1)
        ->and(GatewayEvent::where('event_id', 'evt_race')->first()->claimed_at->equalTo($heldSince))->toBeTrue();
});

test('a card that could not be stored is left for the next delivery', function () {
    stripeSigningSecret();

    // A transient database fault while writing the card. Acknowledging it would
    // leave the customer with a card at Stripe that this system has no row for
    // and no way of ever learning about, so the delivery fails and the event
    // stays unfinished — Stripe brings it back.
    $client = Client::factory()->create();

    Http::fake(['*/v1/payment_methods/*' => Http::response([
        'id' => 'pm_transient',
        'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 7, 'exp_year' => 2030],
    ], 200)]);

    DB::statement('DROP TEMPORARY TABLE IF EXISTS payment_methods');

    $delivery = stripeDelivery([
        'id' => 'evt_card_fault',
        'type' => 'setup_intent.succeeded',
        'data' => ['object' => [
            'id' => 'seti_fault',
            'payment_method' => 'pm_transient',
            'customer' => 'cus_fault',
            'metadata' => ['client_id' => (string) $client->id],
        ]],
    ]);

    // Stand a broken table in front of the real one for the length of this
    // insert: a temporary table shadows payment_methods for this connection
    // only and is dropped with the transaction.
    DB::statement('CREATE TEMPORARY TABLE payment_methods (id BIGINT PRIMARY KEY)');

    expect(fn () => app(StripeModule::class)->processWebhook($delivery))
        ->toThrow(\Illuminate\Database\QueryException::class);

    DB::statement('DROP TEMPORARY TABLE payment_methods');

    expect(GatewayEvent::where('event_id', 'evt_card_fault')->first()->processed_at)->toBeNull();
});

test('a fresh event id for a payment that already succeeded is still a repeat', function () {
    stripeSigningSecret();

    // A retry given a new id is the redelivery an event id alone would miss.
    // An intent succeeds once, so a second event about the same succeeded
    // intent is the same event however Stripe labelled it.
    $event = fn (string $eventId) => [
        'id' => $eventId,
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => [
            'id' => 'pi_same',
            'metadata' => ['invoice_id' => '9'],
            'amount_received' => 1000,
        ]],
    ];

    $stripe = app(StripeModule::class);
    $stripe->processWebhook(stripeDelivery($event('evt_a')));
    $second = $stripe->processWebhook(stripeDelivery($event('evt_b')));

    // Caught by the object key, and told what a repeat of an unfinished event
    // is told: nothing here for the caller to record, and come back — the
    // delivery that took this intent is still the one crediting the invoice.
    expect($second)->not->toHaveKey('transaction_id')
        ->and($second['retry_delivery'])->toBeTrue()
        ->and(GatewayEvent::count())->toBe(1);
});

test('a second genuine failure on the same intent is not swallowed as a repeat', function () {
    stripeSigningSecret();

    // The other side of the coin. A payment intent can fail on Monday and
    // fail again on Tuesday; recognising a repeat by the object rather than
    // by the event id would drop the second real failure and nobody would
    // chase the invoice.
    $event = fn (string $eventId) => [
        'id' => $eventId,
        'type' => 'payment_intent.payment_failed',
        'data' => ['object' => [
            'id' => 'pi_twice',
            'metadata' => ['invoice_id' => '4'],
            'last_payment_error' => ['decline_code' => 'insufficient_funds', 'message' => 'no funds'],
        ]],
    ];

    $stripe = app(StripeModule::class);
    $first = $stripe->processWebhook(stripeDelivery($event('evt_f1')));
    $second = $stripe->processWebhook(stripeDelivery($event('evt_f2')));
    $redelivery = $stripe->processWebhook(stripeDelivery($event('evt_f2')));

    expect($first['payment_intent_id'])->toBe('pi_twice')
        ->and($second['payment_intent_id'])->toBe('pi_twice')
        ->and($redelivery)->not->toHaveKey('payment_intent_id')
        ->and($redelivery['message'])->toContain('already handled');
});

test('an event that does not name itself is handled exactly as it was before', function () {
    stripeSigningSecret();

    // An event id is what makes two deliveries recognisable as one. A caller
    // that sends none — which is how this module was always called from the
    // suite and from anything hand-rolled — must be left exactly as it was
    // rather than quietly refused.
    $event = [
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => [
            'id' => 'pi_noid',
            'metadata' => ['invoice_id' => '3'],
            'amount_received' => 500,
        ]],
    ];

    $stripe = app(StripeModule::class);
    $first = $stripe->processWebhook(stripeDelivery($event));
    $second = $stripe->processWebhook(stripeDelivery($event));

    expect($first['transaction_id'])->toBe('pi_noid')
        ->and($second['transaction_id'])->toBe('pi_noid')
        ->and(GatewayEvent::count())->toBe(0);
});

// ===================== the events this module knows =====================

test('a failed payment is handled, and never pays the invoice', function () {
    stripeSigningSecret();

    $invoice = stripeInvoiceAwaitingPayment(100.0);

    postStripeEvent([
        'id' => 'evt_failed',
        'type' => 'payment_intent.payment_failed',
        'data' => ['object' => [
            'id' => 'pi_failed',
            'metadata' => ['invoice_id' => (string) $invoice->id],
            'last_payment_error' => ['code' => 'card_declined', 'decline_code' => 'lost_card', 'message' => 'Card reported lost'],
        ]],
    ])->assertOk();

    expect($invoice->fresh()->status)->toBe('unpaid')
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(0);
});

test('a failed payment never carries a transaction id home', function () {
    stripeSigningSecret();

    $result = app(StripeModule::class)->processWebhook(stripeDelivery([
        'id' => 'evt_fail',
        'type' => 'payment_intent.payment_failed',
        'data' => ['object' => [
            'id' => 'pi_failed',
            'metadata' => ['invoice_id' => '11'],
            'last_payment_error' => ['code' => 'card_declined', 'decline_code' => 'lost_card', 'message' => 'Card reported lost'],
        ]],
    ]));

    // The webhook controller records a payment the moment it sees an
    // invoice_id and a transaction_id in the same array. A failed payment that
    // paid an invoice would be the worst bug in the module, so the intent goes
    // home under its own name with the reason beside it.
    expect($result['success'])->toBeTrue()
        ->and($result)->not->toHaveKey('transaction_id')
        ->and($result['payment_failed'])->toBeTrue()
        ->and($result['payment_intent_id'])->toBe('pi_failed')
        ->and($result['invoice_id'])->toBe('11')
        ->and($result['decline_code'])->toBe('lost_card')
        ->and($result['retryable'])->toBeFalse();
});

test('an event nobody here acts on is still ignored, in the same words', function () {
    stripeSigningSecret();

    $result = app(StripeModule::class)->processWebhook(stripeDelivery([
        'id' => 'evt_ignored',
        'type' => 'charge.updated',
        'data' => ['object' => ['id' => 'ch_1']],
    ]));

    expect($result)->toBe(['success' => true, 'message' => 'Event ignored: charge.updated'])
        // And it leaves nothing behind. Recording an event that is going to be
        // ignored anyway only fills a table nobody will read.
        ->and(GatewayEvent::count())->toBe(0);
});

// ===================== nothing the gate did has moved =====================

test('a module that failed without asking to be sent again is still answered 200', function () {
    stripeSigningSecret();

    // The category that was already here, and the one every other gateway
    // module lives in: an answer of failure with nothing added to it means the
    // delivery is not worth repeating, and the controller says so with a 200 on
    // purpose. Only a module that explicitly asks gets the new answer, so the
    // seven modules that know nothing about any of this keep the replies they
    // have always had. An unsigned webhook is that case end to end — refused by
    // the module, and never worth Stripe's sending again.
    $response = test()->call('POST', '/gateway/stripe/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode([
        'id' => 'evt_unsigned_route',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_unsigned', 'metadata' => ['invoice_id' => '1'], 'amount_received' => 100]],
    ]));

    $response->assertOk();

    expect(GatewayEvent::count())->toBe(0);
});

test('an unsigned webhook is still refused', function () {
    stripeSigningSecret();

    $result = app(StripeModule::class)->processWebhook([
        'id' => 'evt_unsigned',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_1', 'metadata' => ['invoice_id' => '1'], 'amount_received' => 100]],
    ]);

    expect($result)->toBe(['success' => false, 'message' => 'Unsigned webhook.'])
        ->and(GatewayEvent::count())->toBe(0);
});

test('a webhook replayed an hour later is still refused', function () {
    stripeSigningSecret();

    $result = app(StripeModule::class)->processWebhook(stripeDelivery([
        'id' => 'evt_stale',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_1', 'metadata' => ['invoice_id' => '1'], 'amount_received' => 100]],
    ], 'whsec_test', time() - 3600));

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe('Webhook timestamp is too old.')
        ->and(GatewayEvent::count())->toBe(0);
});

test('a webhook signed with the wrong secret is still refused', function () {
    stripeSigningSecret();

    $result = app(StripeModule::class)->processWebhook(stripeDelivery([
        'id' => 'evt_forged',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_1', 'metadata' => ['invoice_id' => '1'], 'amount_received' => 100]],
    ], 'whsec_wrong'));

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe('Invalid webhook signature.')
        ->and(GatewayEvent::count())->toBe(0);
});

test('a successful payment still answers exactly as it did', function () {
    stripeSigningSecret();

    $result = app(StripeModule::class)->processWebhook(stripeDelivery([
        'id' => 'evt_shape',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_123', 'metadata' => ['invoice_id' => '7'], 'amount_received' => 2500]],
    ]));

    // Keys, order and types alike, down to 2500/100 still being the int 25 it
    // has always been. Everything downstream of this array — the webhook
    // controller, PaymentService, the invoice — reads it unchanged.
    expect($result)->toBe([
        'success' => true,
        'transaction_id' => 'pi_123',
        'invoice_id' => '7',
        'amount' => 25,
        'gateway' => 'stripe',
    ]);
});
