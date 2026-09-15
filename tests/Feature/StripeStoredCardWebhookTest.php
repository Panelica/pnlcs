<?php

use App\Mail\CreditCardExpiryMail;
use App\Models\Client;
use App\Models\GatewayCustomer;
use App\Models\GatewayEvent;
use App\Models\GatewaySettings;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Modules\Gateways\Stripe\StripeModule;

/**
 * What happens after the customer has typed their card in.
 *
 * The browser confirms the SetupIntent with Stripe directly — the card number
 * never comes here, which is the point of vaulting — so the only thing that
 * knows the card was accepted is the webhook that follows. If that event is
 * not turned into a row, the customer has stored a card that this system has
 * no idea exists and the renewal it was stored for still fails.
 *
 * The event does not describe the card, only names it, so the brand and the
 * four digits are asked for separately. That lookup is allowed to fail: the
 * token is what charges the card, and the brand is only what the customer
 * recognises it by on a list. A card that cannot be described is still a card
 * that can be charged, and refusing to store it would be the worse outcome.
 *
 * Later the issuer reissues the card — new number, new expiry, same customer —
 * and Stripe swaps it in behind the token we already hold. Nothing needs
 * re-vaulting; what goes stale is the digits on screen and the expiry the
 * monthly alert mails the customer about.
 */
function stripeCardSecrets(): void
{
    foreach (['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_test'] as $setting => $value) {
        GatewaySettings::updateOrCreate(
            ['gateway' => 'stripe', 'setting' => $setting],
            ['value' => $value]
        );
    }
}

function storedCardDelivery(array $payload): array
{
    $body = json_encode($payload);
    $timestamp = time();

    return array_merge($payload, [
        '_raw_payload' => $body,
        '_signature_header' => "t={$timestamp},v1=".hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test'),
    ]);
}

// ===================== a card finishes being stored =====================

test('a confirmed setup intent stores the card with everything the panel needs', function () {
    stripeCardSecrets();
    Http::fake(['*/v1/payment_methods/*' => Http::response([
        'id' => 'pm_new',
        'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 7, 'exp_year' => 2030],
    ], 200)]);

    $client = Client::factory()->create();

    $result = app(StripeModule::class)->processWebhook(storedCardDelivery([
        'id' => 'evt_setup',
        'type' => 'setup_intent.succeeded',
        'data' => ['object' => [
            'id' => 'seti_1',
            'payment_method' => 'pm_new',
            'customer' => 'cus_9',
            'metadata' => ['client_id' => (string) $client->id],
        ]],
    ]));

    $stored = PaymentMethod::where('client_id', $client->id)->first();

    expect($result['success'])->toBeTrue()
        // No transaction_id anywhere near it: nothing was charged here, and
        // the webhook controller pays an invoice the moment it sees one.
        ->and($result)->not->toHaveKey('transaction_id')
        ->and($stored->remote_token)->toBe('pm_new')
        ->and($stored->gateway_customer_id)->toBe('cus_9')
        ->and($stored->card_brand)->toBe('visa')
        ->and($stored->last_four)->toBe('4242')
        ->and($stored->exp_month)->toBe(7)
        ->and($stored->exp_year)->toBe(2030)
        ->and($stored->expiry_date)->toBe('2030-07')
        ->and($stored->payment_type)->toBe('cc')
        ->and($stored->status)->toBe('active')
        ->and($stored->description)->toBe('Stripe Visa');
});

test('a vaulted card is warned about before it expires, like any other', function () {
    stripeCardSecrets();
    Mail::fake();

    $expiringNextMonth = now()->addMonthNoOverflow();

    Http::fake(['*/v1/payment_methods/*' => Http::response([
        'id' => 'pm_expiring',
        'card' => [
            'brand' => 'visa',
            'last4' => '4242',
            'exp_month' => (int) $expiringNextMonth->format('n'),
            'exp_year' => (int) $expiringNextMonth->format('Y'),
        ],
    ], 200)]);

    $client = Client::factory()->create();

    app(StripeModule::class)->processWebhook(storedCardDelivery([
        'id' => 'evt_setup_expiring',
        'type' => 'setup_intent.succeeded',
        'data' => ['object' => [
            'id' => 'seti_exp',
            'payment_method' => 'pm_expiring',
            'customer' => 'cus_9',
            'metadata' => ['client_id' => (string) $client->id],
        ]],
    ]));

    // The monthly alert reads payment_type 'cc', last_four and an expiry_date
    // written as 'Y-m'. A stored card whose owner is never warned that it is
    // about to die is how a renewal fails silently, so the new columns sit
    // beside that one rather than instead of it — and the command is not
    // touched at all.
    $this->artisan('pnlcs:cc-expiry-alerts')->assertSuccessful();

    Mail::assertQueued(CreditCardExpiryMail::class, fn ($mail) => $mail->hasTo($client->email));
});

test('a card Stripe will not describe is still stored', function () {
    stripeCardSecrets();
    Http::fake(['*/v1/payment_methods/*' => Http::response(['error' => ['message' => 'No such payment method']], 404)]);

    $client = Client::factory()->create();

    app(StripeModule::class)->processWebhook(storedCardDelivery([
        'id' => 'evt_setup_bare',
        'type' => 'setup_intent.succeeded',
        'data' => ['object' => [
            'id' => 'seti_2',
            'payment_method' => 'pm_bare',
            'customer' => 'cus_9',
            'metadata' => ['client_id' => (string) $client->id],
        ]],
    ]));

    $stored = PaymentMethod::where('client_id', $client->id)->first();

    // "No such payment method" will be just as true tomorrow, so the card is
    // kept with what little is known rather than lost over four digits. The
    // cost is named in the log and it is real: without a last_four and an
    // expiry_date this customer is the one the monthly alert will skip.
    expect($stored->remote_token)->toBe('pm_bare')
        ->and($stored->gateway_customer_id)->toBe('cus_9')
        ->and($stored->last_four)->toBeNull();
});

test('a card Stripe could not describe this minute is asked about again', function () {
    stripeCardSecrets();
    Http::fake(['*/v1/payment_methods/*' => Http::response(['error' => ['message' => 'Something went wrong']], 503)]);

    $client = Client::factory()->create();

    // A bad minute at Stripe is not a card without digits. Storing one on that
    // basis would leave this customer permanently outside the expiry alert over
    // a fault that lasted seconds — so the delivery fails, the event stays
    // unfinished, and Stripe brings it back for up to three days.
    expect(fn () => app(StripeModule::class)->processWebhook(storedCardDelivery([
        'id' => 'evt_setup_flaky',
        'type' => 'setup_intent.succeeded',
        'data' => ['object' => [
            'id' => 'seti_flaky',
            'payment_method' => 'pm_flaky',
            'customer' => 'cus_9',
            'metadata' => ['client_id' => (string) $client->id],
        ]],
    ])))->toThrow(RuntimeException::class);

    expect(PaymentMethod::where('remote_token', 'pm_flaky')->count())->toBe(0)
        ->and(GatewayEvent::where('event_id', 'evt_setup_flaky')->first()->processed_at)->toBeNull();
});

test('a setup intent that names no client stores nothing', function () {
    stripeCardSecrets();
    Http::fake();

    $result = app(StripeModule::class)->processWebhook(storedCardDelivery([
        'id' => 'evt_setup_orphan',
        'type' => 'setup_intent.succeeded',
        'data' => ['object' => ['id' => 'seti_3', 'payment_method' => 'pm_orphan', 'customer' => 'cus_9']],
    ]));

    // Acknowledged, because redelivering it would carry the same nothing.
    expect($result['success'])->toBeTrue()
        ->and(PaymentMethod::where('remote_token', 'pm_orphan')->count())->toBe(0);

    Http::assertNothingSent();
});

// ===================== the issuer reissues the card =====================

test('the card updater refreshes what the customer sees', function () {
    stripeCardSecrets();

    $client = Client::factory()->create();
    PaymentMethod::create([
        'client_id' => $client->id,
        'gateway_name' => 'stripe',
        'payment_type' => 'cc',
        'remote_token' => 'pm_upd',
        'gateway_customer_id' => 'cus_9',
        'last_four' => '1111',
        'expiry_date' => '2026-01',
        'status' => PaymentMethod::STATUS_REQUIRES_UPDATE,
    ]);

    $result = app(StripeModule::class)->processWebhook(storedCardDelivery([
        'id' => 'evt_upd',
        'type' => 'payment_method.automatically_updated',
        'data' => ['object' => [
            'id' => 'pm_upd',
            'card' => ['brand' => 'mastercard', 'last4' => '5555', 'exp_month' => 11, 'exp_year' => 2031],
        ]],
    ]));

    $stored = PaymentMethod::where('remote_token', 'pm_upd')->first();

    expect($result['success'])->toBeTrue()
        ->and($stored->last_four)->toBe('5555')
        ->and($stored->card_brand)->toBe('mastercard')
        ->and($stored->expiry_date)->toBe('2031-11')
        // Back to active: a card the updater has just replaced is a card the
        // issuer expects to be charged again.
        ->and($stored->status)->toBe('active');
});

test('a card that is not stored here is left alone', function () {
    stripeCardSecrets();

    // Two installations on one Stripe account, or a card removed here and
    // still live there. Nothing to do, and not an error — refusing would only
    // buy a redelivery of the same event.
    $result = app(StripeModule::class)->processWebhook(storedCardDelivery([
        'id' => 'evt_upd_foreign',
        'type' => 'payment_method.automatically_updated',
        'data' => ['object' => [
            'id' => 'pm_not_ours',
            'card' => ['brand' => 'visa', 'last4' => '9999', 'exp_month' => 1, 'exp_year' => 2032],
        ]],
    ]));

    expect($result['success'])->toBeTrue()
        ->and(PaymentMethod::where('remote_token', 'pm_not_ours')->count())->toBe(0);
});

test('a card can be reissued more than once', function () {
    stripeCardSecrets();

    $client = Client::factory()->create();
    PaymentMethod::create([
        'client_id' => $client->id,
        'gateway_name' => 'stripe',
        'payment_type' => 'cc',
        'remote_token' => 'pm_twice',
        'gateway_customer_id' => 'cus_9',
        'last_four' => '1111',
    ]);

    $update = fn (string $eventId, string $last4) => storedCardDelivery([
        'id' => $eventId,
        'type' => 'payment_method.automatically_updated',
        'data' => ['object' => [
            'id' => 'pm_twice',
            'card' => ['brand' => 'visa', 'last4' => $last4, 'exp_month' => 1, 'exp_year' => 2032],
        ]],
    ]);

    $stripe = app(StripeModule::class);
    $stripe->processWebhook($update('evt_u1', '2222'));
    $stripe->processWebhook($update('evt_u2', '3333'));

    // A card is reissued more than once in its life, so the second update is a
    // real event and not a redelivery of the first. Recognising repeats by the
    // card rather than by the event id would have left the customer looking at
    // digits that stopped being true a year ago.
    expect(PaymentMethod::where('remote_token', 'pm_twice')->first()->last_four)->toBe('3333');
});

// ===================== the two halves meet =====================

test('the customer the first card was stored against is the one the second hangs off', function () {
    stripeCardSecrets();
    Http::fake([
        '*/v1/customers' => Http::response(['id' => 'cus_only'], 200),
        '*/v1/setup_intents' => Http::response(['id' => 'seti_1', 'client_secret' => 'seti_1_secret'], 200),
        '*/v1/payment_methods/*' => Http::response([
            'id' => 'pm_first',
            'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 7, 'exp_year' => 2030],
        ], 200),
    ]);

    $client = Client::factory()->create();
    $stripe = app(StripeModule::class);

    // The whole round trip: the customer opens the card form, Stripe confirms
    // the card, the webhook writes it down. Then they come back to add a
    // second card.
    $first = $stripe->beginVaulting($client);

    $stripe->processWebhook(storedCardDelivery([
        'id' => 'evt_setup_chain',
        'type' => 'setup_intent.succeeded',
        'data' => ['object' => [
            'id' => 'seti_1',
            'payment_method' => 'pm_first',
            'customer' => $first['customer_id'],
            'metadata' => ['client_id' => (string) $client->id],
        ]],
    ]));

    $second = $stripe->beginVaulting($client);

    expect($first['customer_id'])->toBe('cus_only')
        ->and($second['customer_id'])->toBe('cus_only');

    // One customer created across both visits. Any more and this client's
    // cards start scattering across records that nothing joins up.
    expect(collect(Http::recorded())
        ->filter(fn ($pair) => $pair[0]->url() === 'https://api.stripe.com/v1/customers')
        ->count())->toBe(1);
});

test('the customer is written down the moment it exists, not when a card lands', function () {
    stripeCardSecrets();
    Http::fake([
        '*/v1/customers' => Http::response(['id' => 'cus_written'], 200),
        '*/v1/setup_intents' => Http::response(['id' => 'seti_1', 'client_secret' => 'seti_1_secret'], 200),
    ]);

    $client = Client::factory()->create();

    app(StripeModule::class)->beginVaulting($client);

    // The card form can be abandoned, and used to be: the id only reached the
    // database when setup_intent.succeeded landed, so a customer who wandered
    // off left nothing behind and was given a second Stripe customer the next
    // time they tried. Stripe's idempotency key hid that for twenty-four hours
    // and no longer.
    expect(GatewayCustomer::idFor('stripe', (int) $client->id))->toBe('cus_written');
});

test('a client who comes back a week later keeps the customer they were given', function () {
    stripeCardSecrets();
    Http::fake([
        // If this were reached, the client would end up with two customers and
        // their cards split between them.
        '*/v1/customers' => Http::response(['id' => 'cus_SECOND'], 200),
        '*/v1/setup_intents' => Http::response(['id' => 'seti_1', 'client_secret' => 'seti_1_secret'], 200),
    ]);

    $client = Client::factory()->create();
    GatewayCustomer::create(['gateway' => 'stripe', 'client_id' => $client->id, 'customer_id' => 'cus_first']);

    expect(app(StripeModule::class)->beginVaulting($client)['customer_id'])->toBe('cus_first');

    Http::assertNotSent(fn ($request) => $request->url() === 'https://api.stripe.com/v1/customers');
});

test('two card forms open at once still leave the client with one customer', function () {
    $client = Client::factory()->create();

    // Both tabs reached Stripe and both came back with an id. Only one of them
    // can be this client's customer from here on, and the unique key picks it
    // rather than whichever wrote last. The loser is told the winner's id, so
    // the card it is about to store hangs off the same record.
    $first = GatewayCustomer::remember('stripe', (int) $client->id, 'cus_tab_one');
    $second = GatewayCustomer::remember('stripe', (int) $client->id, 'cus_tab_two');

    expect($first)->toBe('cus_tab_one')
        ->and($second)->toBe('cus_tab_one')
        ->and(GatewayCustomer::where('client_id', $client->id)->count())->toBe(1);
});

test('a customer from before that table existed is adopted rather than remade', function () {
    stripeCardSecrets();
    Http::fake([
        '*/v1/customers' => Http::response(['id' => 'cus_SECOND'], 200),
        '*/v1/setup_intents' => Http::response(['id' => 'seti_1', 'client_secret' => 'seti_1_secret'], 200),
    ]);

    $client = Client::factory()->create();
    PaymentMethod::create([
        'client_id' => $client->id,
        'gateway_name' => 'stripe',
        'payment_type' => 'cc',
        'remote_token' => 'pm_old',
        'gateway_customer_id' => 'cus_old',
    ]);

    expect(app(StripeModule::class)->beginVaulting($client)['customer_id'])->toBe('cus_old')
        // And copied forward on the way past, so the older place is only ever
        // read once per client.
        ->and(GatewayCustomer::idFor('stripe', (int) $client->id))->toBe('cus_old');
});

// ===================== a card the bank has finished with =====================

test('a card the issuer refuses for good is marked as needing the customer', function () {
    stripeCardSecrets();

    $client = Client::factory()->create();
    $card = PaymentMethod::create([
        'client_id' => $client->id,
        'gateway_name' => 'stripe',
        'payment_type' => 'cc',
        'remote_token' => 'pm_dead',
        'gateway_customer_id' => 'cus_9',
        'last_four' => '4242',
    ]);

    app(StripeModule::class)->processWebhook(storedCardDelivery([
        'id' => 'evt_dead_card',
        'type' => 'payment_intent.payment_failed',
        'data' => ['object' => [
            'id' => 'pi_dead',
            'payment_method' => 'pm_dead',
            'metadata' => ['invoice_id' => '4'],
            'last_payment_error' => ['type' => 'card_error', 'code' => 'card_declined', 'decline_code' => 'stolen_card', 'message' => 'Declined'],
        ]],
    ]));

    // The constant existed and nothing ever wrote it, so a dead card went on
    // showing as active in the panel and every renewal walked into the same
    // refusal. The card updater already puts one back to active when the issuer
    // reissues it; this is the other half of that.
    expect($card->fresh()->status)->toBe(PaymentMethod::STATUS_REQUIRES_UPDATE);
});

test('a card that will work again next month is left alone', function () {
    stripeCardSecrets();

    $client = Client::factory()->create();
    $card = PaymentMethod::create([
        'client_id' => $client->id,
        'gateway_name' => 'stripe',
        'payment_type' => 'cc',
        'remote_token' => 'pm_skint',
        'gateway_customer_id' => 'cus_9',
        'last_four' => '4242',
    ]);

    app(StripeModule::class)->processWebhook(storedCardDelivery([
        'id' => 'evt_skint',
        'type' => 'payment_intent.payment_failed',
        'data' => ['object' => [
            'id' => 'pi_skint',
            'payment_method' => 'pm_skint',
            'metadata' => ['invoice_id' => '4'],
            'last_payment_error' => ['type' => 'card_error', 'code' => 'card_declined', 'decline_code' => 'insufficient_funds', 'message' => 'No funds'],
        ]],
    ]));

    // The salary lands and the card works. Asking the customer to replace it
    // would lose a renewal that was going to pay itself.
    expect($card->fresh()->status)->toBe(PaymentMethod::STATUS_ACTIVE);
});

test('a payment that failed on a card nobody stored here changes nothing', function () {
    stripeCardSecrets();

    $result = app(StripeModule::class)->processWebhook(storedCardDelivery([
        'id' => 'evt_foreign_fail',
        'type' => 'payment_intent.payment_failed',
        'data' => ['object' => [
            'id' => 'pi_foreign',
            'payment_method' => 'pm_not_ours',
            'metadata' => ['invoice_id' => '4'],
            'last_payment_error' => ['type' => 'card_error', 'code' => 'card_declined', 'decline_code' => 'lost_card', 'message' => 'Declined'],
        ]],
    ]));

    // A card typed into the pay form once and never stored, or one belonging to
    // another installation on the same Stripe account.
    expect($result['success'])->toBeTrue()
        ->and(PaymentMethod::where('remote_token', 'pm_not_ours')->count())->toBe(0);
});
