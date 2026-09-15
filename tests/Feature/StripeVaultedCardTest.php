<?php

use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Http;
use Modules\Gateways\Stripe\StripeModule;

/**
 * Storing a card, and charging it later with nobody watching.
 *
 * Two failures are worth naming, because both cost money quietly.
 *
 * The first is the customer record. Stripe hangs stored cards off a customer,
 * and a client who is given a new customer every time they save a card ends up
 * with their cards scattered across records that nothing joins back together —
 * the dashboard shows the same person five times and the card the renewal
 * needs is attached to none of them.
 *
 * The second is what the bank says when the cardholder is absent. A renewal
 * charge has three possible answers, not two: taken, refused, or "bring the
 * cardholder back and let them authenticate". That third answer is not a
 * decline — the money is there and the customer only has to confirm — and
 * telling them their card was declined is how a renewal becomes a
 * cancellation. Nor is every refusal worth another go: card networks cap how
 * often one charge may be reattempted and issuers read repeated attempts on a
 * dead card as fraud, which drags down acceptance on the cards that would have
 * worked.
 */
function stripeVaultConfigured(): void
{
    GatewaySettings::updateOrCreate(
        ['gateway' => 'stripe', 'setting' => 'secret_key'],
        ['value' => 'sk_test_x']
    );
}

/**
 * An event as it reaches the module, signed the way Stripe signs one.
 *
 * Here so that a refusal can be put to the charge and to the failure webhook in
 * the same breath: the two used to read the same refusal from different lists
 * and answer differently, and the only way to hold them to one answer is to ask
 * both in one test.
 */
function stripeVaultDelivery(array $payload): array
{
    GatewaySettings::updateOrCreate(
        ['gateway' => 'stripe', 'setting' => 'webhook_secret'],
        ['value' => 'whsec_test']
    );

    $body = json_encode($payload);
    $timestamp = time();

    return array_merge($payload, [
        '_raw_payload' => $body,
        '_signature_header' => "t={$timestamp},v1=".hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test'),
    ]);
}

function stripeVaultInvoice(float $total = 50.0): Invoice
{
    return Invoice::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'status' => 'unpaid',
        'total' => $total,
    ]);
}

/**
 * A card stored against the invoice's own client, written the way the
 * setup_intent.succeeded webhook writes one.
 */
function vaultedStripeCard(Invoice $invoice, string $customerId = 'cus_1'): PaymentMethod
{
    return PaymentMethod::create([
        'client_id' => $invoice->client_id,
        'gateway_name' => 'stripe',
        'payment_type' => 'cc',
        'remote_token' => 'pm_1',
        'gateway_customer_id' => $customerId,
        'last_four' => '4242',
        'expiry_date' => '2030-07',
    ]);
}

/**
 * Hands back a way to charge this card and have Stripe refuse it in whatever
 * terms the test names.
 *
 * One stub is registered and its answer swapped between calls, because a
 * second Http::fake() does not replace the first — the stub registered first
 * goes on answering, so re-faking inside a loop would test the first refusal
 * over and over.
 *
 * The card is put back to active before each charge. A refusal that ends a card
 * writes that on the row, and the next charge then refuses before it sends
 * anything — correct behaviour, proven on its own further down, and fatal to a
 * loop whose subject is how each separate refusal is read rather than what the
 * one before it did to the card.
 */
function refusedCharge(Invoice $invoice, PaymentMethod $card): Closure
{
    $refusal = ['error' => [], 'status' => 402];

    // Written the long way round rather than as an arrow function: an arrow
    // function captures by value, so the stub would answer with the empty
    // refusal it was built with however many times the test swapped it.
    Http::fake(function () use (&$refusal) {
        return Http::response(['error' => $refusal['error']], $refusal['status']);
    });

    return function (array $error, int $status = 402) use (&$refusal, $invoice, $card) {
        $refusal = ['error' => $error, 'status' => $status];
        $card->update(['status' => PaymentMethod::STATUS_ACTIVE]);

        return app(StripeModule::class)->chargeStoredMethod($invoice, $card, 50.0);
    };
}

// ===================== storing a card =====================

test('the first card a client stores creates the Stripe customer it hangs off', function () {
    stripeVaultConfigured();
    Http::fake([
        '*/v1/customers' => Http::response(['id' => 'cus_new'], 200),
        '*/v1/setup_intents' => Http::response(['id' => 'seti_1', 'client_secret' => 'seti_1_secret'], 200),
    ]);

    $client = Client::factory()->create(['email' => 'vault@example.com']);

    $result = app(StripeModule::class)->beginVaulting($client);

    expect($result['success'])->toBeTrue()
        ->and($result['customer_id'])->toBe('cus_new')
        ->and($result['setup_intent_id'])->toBe('seti_1')
        // The client_secret is the whole point: it is what Stripe.js needs to
        // finish collecting the card in the browser, so the card number never
        // reaches this server.
        ->and($result['client_secret'])->toBe('seti_1_secret');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.stripe.com/v1/setup_intents'
        && $request['customer'] === 'cus_new'
        && $request['metadata[client_id]'] === $client->id);
});

test('the second card a client stores reuses the customer they already have', function () {
    stripeVaultConfigured();
    Http::fake([
        '*/v1/customers' => Http::response(['id' => 'cus_SECOND'], 200),
        '*/v1/setup_intents' => Http::response(['id' => 'seti_2', 'client_secret' => 'seti_2_secret'], 200),
    ]);

    // What the first save left behind. The webhook that writes this row is
    // proven in StripeStoredCardWebhookTest; here it stands for a client who
    // already has a card on file.
    $invoice = stripeVaultInvoice();
    vaultedStripeCard($invoice, 'cus_first');

    $result = app(StripeModule::class)->beginVaulting($invoice->client);

    expect($result['customer_id'])->toBe('cus_first')
        ->and($result['setup_intent_id'])->toBe('seti_2');

    // Nothing was asked of /v1/customers at all: no second customer exists to
    // scatter this client's cards across.
    Http::assertNotSent(fn ($request) => $request->url() === 'https://api.stripe.com/v1/customers');
    Http::assertSentCount(1);
});

test('a client who removed their only card still has their Stripe customer', function () {
    stripeVaultConfigured();
    Http::fake([
        '*/v1/customers' => Http::response(['id' => 'cus_SECOND'], 200),
        '*/v1/setup_intents' => Http::response(['id' => 'seti_3', 'client_secret' => 'seti_3_secret'], 200),
    ]);

    $invoice = stripeVaultInvoice();
    vaultedStripeCard($invoice, 'cus_first')->delete();

    // The customer record is still there at Stripe — deleting our row did not
    // delete theirs — and making a second one because ours is in the bin is
    // exactly how the scattering starts.
    expect(app(StripeModule::class)->beginVaulting($invoice->client)['customer_id'])->toBe('cus_first');

    Http::assertNotSent(fn ($request) => $request->url() === 'https://api.stripe.com/v1/customers');
});

test('vaulting refuses the way the rest of the module refuses when nothing is configured', function () {
    GatewaySettings::where('gateway', 'stripe')->delete();
    Http::fake();

    expect(app(StripeModule::class)->beginVaulting(Client::factory()->create()))->toBe([
        'success' => false,
        'message' => 'Stripe secret key not configured.',
    ]);

    Http::assertNothingSent();
});

// ===================== charging it =====================

test('a stored card that pays reports the money Stripe says it took', function () {
    stripeVaultConfigured();
    Http::fake(['*' => Http::response(['id' => 'pi_ok', 'status' => 'succeeded', 'amount_received' => 5000], 200)]);

    $invoice = stripeVaultInvoice();

    $result = app(StripeModule::class)->chargeStoredMethod($invoice, vaultedStripeCard($invoice), 50.0);

    expect($result)->toBe([
        'success' => true,
        'status' => 'succeeded',
        'transaction_id' => 'pi_ok',
        'amount' => 50.0,
    ]);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.stripe.com/v1/payment_intents'
        && $request['off_session'] === 'true'
        && $request['confirm'] === 'true'
        && $request['customer'] === 'cus_1'
        && $request['payment_method'] === 'pm_1'
        && $request['metadata[invoice_id]'] === $invoice->id);
});

test('a bank that wants the cardholder back is never reported as a decline', function () {
    stripeVaultConfigured();
    Http::fake(['*' => Http::response(['error' => [
        'type' => 'card_error',
        'code' => 'authentication_required',
        'decline_code' => 'authentication_required',
        'message' => 'This payment requires authentication.',
        'payment_intent' => ['id' => 'pi_3ds', 'status' => 'requires_action'],
    ]], 402)]);

    $invoice = stripeVaultInvoice();

    $result = app(StripeModule::class)->chargeStoredMethod($invoice, vaultedStripeCard($invoice), 50.0);

    expect($result['status'])->toBe('requires_action')
        // success mirrors 'succeeded' and nothing else, so a caller that reads
        // only ['success'] — the way every other gateway call is read — cannot
        // credit an invoice nobody has paid yet.
        ->and($result['success'])->toBeFalse()
        // The intent the customer has to come back and confirm. Losing it here
        // means the payment cannot be finished at all and the customer is
        // asked for their card again.
        ->and($result['transaction_id'])->toBe('pi_3ds')
        ->and($result['decline_code'])->toBe('authentication_required')
        ->and($result['retryable'])->toBeFalse();
});

test('a declined card comes back failed, with the reason the bank gave', function () {
    stripeVaultConfigured();
    Http::fake(['*' => Http::response(['error' => [
        'type' => 'card_error',
        'code' => 'card_declined',
        'decline_code' => 'insufficient_funds',
        'message' => 'Your card has insufficient funds.',
    ]], 402)]);

    $invoice = stripeVaultInvoice();

    $result = app(StripeModule::class)->chargeStoredMethod($invoice, vaultedStripeCard($invoice), 50.0);

    expect($result['success'])->toBeFalse()
        ->and($result['status'])->toBe('failed')
        ->and($result['decline_code'])->toBe('insufficient_funds')
        ->and($result['message'])->toBe('Stripe error: Your card has insufficient funds.')
        ->and($result)->not->toHaveKey('amount');
});

test('a card that will not work again is not tried again', function () {
    stripeVaultConfigured();

    $invoice = stripeVaultInvoice();
    $charge = refusedCharge($invoice, vaultedStripeCard($invoice));

    $gone = [
        'lost_card', 'stolen_card', 'pickup_card', 'revocation_of_authorization',
        'expired_card', 'incorrect_number', 'invalid_account', 'fraudulent',
        'duplicate_transaction',
    ];

    foreach ($gone as $code) {
        $result = $charge(['type' => 'card_error', 'code' => 'card_declined', 'decline_code' => $code, 'message' => 'Declined']);

        expect($result['status'])->toBe('failed', $code)
            ->and($result['decline_code'])->toBe($code)
            ->and($result['retryable'])->toBeFalse("{$code} must not be charged again");
    }
});

test('a card that might work later is worth charging again', function () {
    stripeVaultConfigured();

    $invoice = stripeVaultInvoice();
    $charge = refusedCharge($invoice, vaultedStripeCard($invoice));

    // Every one of these either clears on its own — the salary lands, the
    // daily limit resets, the issuer comes back up — or is Stripe's documented
    // "attempt the payment again". Writing the invoice off today loses money
    // that was going to arrive.
    $later = [
        'insufficient_funds', 'card_velocity_exceeded', 'withdrawal_count_limit_exceeded',
        'processing_error', 'issuer_not_available', 'reenter_transaction',
        'generic_decline', 'do_not_honor', 'call_issuer',
    ];

    foreach ($later as $code) {
        $result = $charge(['type' => 'card_error', 'code' => 'card_declined', 'decline_code' => $code, 'message' => 'Declined']);

        expect($result['status'])->toBe('failed', $code)
            ->and($result['retryable'])->toBeTrue("{$code} is worth another attempt");
    }
});

test('what Stripe itself advises about retrying beats our list', function () {
    stripeVaultConfigured();

    $invoice = stripeVaultInvoice();
    $charge = refusedCharge($invoice, vaultedStripeCard($invoice));

    // The issuer's own advice travels with the refusal and is more current
    // than any table kept in this repository — in both directions.
    expect($charge(['type' => 'card_error', 'decline_code' => 'insufficient_funds', 'advice_code' => 'do_not_try_again'])['retryable'])->toBeFalse()
        ->and($charge(['type' => 'card_error', 'decline_code' => 'lost_card', 'advice_code' => 'try_again_later'])['retryable'])->toBeTrue()
        // A refusal that never reached the bank is judged on its own terms:
        // Stripe having a bad minute is worth repeating, being asked something
        // invalid is not.
        ->and($charge(['type' => 'api_error', 'message' => 'Stripe is having trouble'], 500)['retryable'])->toBeTrue()
        ->and($charge(['type' => 'invalid_request_error', 'message' => 'No such customer'], 400)['retryable'])->toBeFalse();
});

test('an unfinished payment is never reported as money taken', function () {
    stripeVaultConfigured();
    Http::fake(['*' => Http::response(['id' => 'pi_processing', 'status' => 'processing'], 200)]);

    $invoice = stripeVaultInvoice();

    $result = app(StripeModule::class)->chargeStoredMethod($invoice, vaultedStripeCard($invoice), 50.0);

    // Stripe has the request but has not said the money is there. Crediting
    // the invoice on that would hand over the service for a payment that may
    // still fail; refusing to look again would lose one that succeeds.
    expect($result['success'])->toBeFalse()
        ->and($result['status'])->toBe('failed')
        ->and($result['retryable'])->toBeTrue();
});

test('a card belonging to somebody else is never charged', function () {
    stripeVaultConfigured();
    Http::fake(['*' => Http::response(['id' => 'pi_no', 'status' => 'succeeded', 'amount_received' => 5000], 200)]);

    $invoice = stripeVaultInvoice();
    $strangersCard = vaultedStripeCard(stripeVaultInvoice());

    $result = app(StripeModule::class)->chargeStoredMethod($invoice, $strangersCard, 50.0);

    expect($result['success'])->toBeFalse()
        ->and($result['status'])->toBe('failed');

    // Refused here, before anything was asked of the card: finding out from
    // the cardholder costs incomparably more than this comparison.
    Http::assertNothingSent();
});

test('a card with no Stripe customer behind it is refused before anything is sent', function () {
    stripeVaultConfigured();
    Http::fake();

    $invoice = stripeVaultInvoice();
    $card = PaymentMethod::create([
        'client_id' => $invoice->client_id,
        'gateway_name' => 'stripe',
        'payment_type' => 'cc',
        'remote_token' => 'pm_1',
    ]);

    expect(app(StripeModule::class)->chargeStoredMethod($invoice, $card, 50.0)['status'])->toBe('failed');

    Http::assertNothingSent();
});

test('a card stored with another gateway is not handed to Stripe', function () {
    stripeVaultConfigured();
    Http::fake();

    $invoice = stripeVaultInvoice();
    $card = PaymentMethod::create([
        'client_id' => $invoice->client_id,
        'gateway_name' => 'iyzico',
        'payment_type' => 'cc',
        'remote_token' => 'tok_iyzico',
        'gateway_customer_id' => 'cus_iyzico',
    ]);

    expect(app(StripeModule::class)->chargeStoredMethod($invoice, $card, 50.0)['status'])->toBe('failed');

    Http::assertNothingSent();
});

// ===================== one refusal, one verdict =====================

test('a bank asking for the cardholder is never called worth retrying', function () {
    stripeVaultConfigured();

    $invoice = stripeVaultInvoice();
    $charge = refusedCharge($invoice, vaultedStripeCard($invoice));

    // The same refusal used to get two opposite answers: the charge called it
    // requires_action and final, while the failure webhook read the same code
    // off the same list and called it worth trying tomorrow. A dunning loop
    // reading that would have charged an absent customer's card night after
    // night for an authentication only they can give.
    foreach (['authentication_required', 'mobile_device_authentication_required'] as $code) {
        $error = ['type' => 'card_error', 'code' => 'card_declined', 'decline_code' => $code, 'message' => 'Authenticate'];

        $charged = $charge($error);

        $reported = app(StripeModule::class)->processWebhook(stripeVaultDelivery([
            'id' => 'evt_auth_'.$code,
            'type' => 'payment_intent.payment_failed',
            'data' => ['object' => [
                'id' => 'pi_auth_'.$code,
                'metadata' => ['invoice_id' => (string) $invoice->id],
                'last_payment_error' => $error,
            ]],
        ]));

        expect($charged['retryable'])->toBeFalse("{$code} must not be retried by the charge")
            ->and($reported['retryable'])->toBeFalse("{$code} must not be retried by the webhook either");
    }
});

test('authentication is answered as authentication however Stripe words it', function () {
    stripeVaultConfigured();

    $invoice = stripeVaultInvoice();
    $charge = refusedCharge($invoice, vaultedStripeCard($invoice));

    // Stripe documents this both as an error code and as a decline code, so
    // either one alone has to be enough.
    expect($charge(['type' => 'card_error', 'code' => 'authentication_required', 'message' => 'Authenticate', 'payment_intent' => ['id' => 'pi_a']])['status'])
        ->toBe('requires_action')
        ->and($charge(['type' => 'card_error', 'decline_code' => 'mobile_device_authentication_required', 'message' => 'Tap again', 'payment_intent' => ['id' => 'pi_b']])['status'])
        ->toBe('requires_action');
});

test('being asked something invalid is not worth asking again', function () {
    stripeVaultConfigured();

    $invoice = stripeVaultInvoice();
    $charge = refusedCharge($invoice, vaultedStripeCard($invoice));

    // Every one of these carries a code, and reading the code before the type
    // was what made them all come back retryable: a card detached at Stripe, an
    // amount below the minimum, a missing parameter. None of them changes by
    // being sent again tomorrow, and an unattended loop would send it forever.
    foreach (['resource_missing', 'amount_too_small', 'amount_too_large', 'parameter_missing', 'payment_method_unactivated'] as $code) {
        $result = $charge(['type' => 'invalid_request_error', 'code' => $code, 'message' => 'Invalid'], 400);

        expect($result['retryable'])->toBeFalse("{$code} must not be retried");
    }
});

test('an invalid request that describes a moment rather than a mistake is retried', function () {
    stripeVaultConfigured();

    $invoice = stripeVaultInvoice();
    $charge = refusedCharge($invoice, vaultedStripeCard($invoice));

    // The three exceptions, each with a documented next step of "try again":
    // something else holds the object, the same key is in flight, or we are
    // going too fast.
    foreach (['lock_timeout', 'idempotency_key_in_use', 'rate_limit'] as $code) {
        expect($charge(['type' => 'invalid_request_error', 'code' => $code, 'message' => 'Busy'], 400)['retryable'])
            ->toBeTrue("{$code} is worth another attempt");
    }

    // And too many requests is worth repeating whatever the body says.
    expect($charge(['type' => 'invalid_request_error', 'code' => 'resource_missing', 'message' => 'Slow down'], 429)['retryable'])->toBeTrue();
});

// ===================== the shop's own currency =====================

test('a shop selling in yen is not charged a hundred times the invoice', function () {
    stripeVaultConfigured();
    Http::fake(['*' => Http::response(['id' => 'pi_jpy', 'status' => 'succeeded', 'amount_received' => 5000], 200)]);

    $invoice = stripeVaultInvoice();

    // "Enter 10 to charge 10 JPY (or any other zero-decimal currency)"
    // — https://docs.stripe.com/currencies#zero-decimal. Multiplying by a
    // hundred regardless takes a hundred times the invoice from a customer
    // nobody is watching. The currency reaches the same variable whether it
    // comes from here or from the shop's own setting.
    $result = app(StripeModule::class)->chargeStoredMethod($invoice, vaultedStripeCard($invoice), 5000.0, ['currency' => 'jpy']);

    Http::assertSent(fn ($request) => $request['amount'] === 5000 && $request['currency'] === 'jpy');

    // And read back the same way, so the invoice is credited with what Stripe
    // actually took rather than a hundredth of it.
    expect($result['amount'])->toBe(5000.0);
});

test('a currency that is zero-decimal but sent as two decimals keeps its trailing zeroes', function () {
    stripeVaultConfigured();
    Http::fake(['*' => Http::response(['id' => 'pi_isk', 'status' => 'succeeded', 'amount_received' => 50000], 200)]);

    $invoice = stripeVaultInvoice();

    // ISK "transitioned to a zero-decimal currency, but backward compatibility
    // requires you to represent it as a two-decimal value, where the decimal
    // amount is always 00 ... You can't charge fractions of ISK."
    // https://docs.stripe.com/currencies#special-cases
    app(StripeModule::class)->chargeStoredMethod($invoice, vaultedStripeCard($invoice), 500.4, ['currency' => 'isk']);

    Http::assertSent(fn ($request) => $request['amount'] === 50000);
});

test('an ordinary two-decimal currency is sent exactly as it always was', function () {
    stripeVaultConfigured();
    Http::fake(['*' => Http::response(['id' => 'pi_eur', 'status' => 'succeeded', 'amount_received' => 5050], 200)]);

    $invoice = stripeVaultInvoice();

    $result = app(StripeModule::class)->chargeStoredMethod($invoice, vaultedStripeCard($invoice), 50.5, ['currency' => 'eur']);

    Http::assertSent(fn ($request) => $request['amount'] === 5050);

    expect($result['amount'])->toBe(50.5);
});

// ===================== the state of the card =====================

test('a card the customer removed is never charged', function () {
    stripeVaultConfigured();
    Http::fake(['*' => Http::response(['id' => 'pi_no', 'status' => 'succeeded', 'amount_received' => 5000], 200)]);

    $invoice = stripeVaultInvoice();
    $card = vaultedStripeCard($invoice);
    $card->delete();

    // Removing a card here does not detach it at Stripe, so the token on this
    // row very probably still works — which is exactly why this end has to
    // refuse rather than leave it to Stripe to.
    $result = app(StripeModule::class)->chargeStoredMethod($invoice, $card, 50.0);

    expect($result['success'])->toBeFalse()
        ->and($result['status'])->toBe('failed')
        ->and($result['retryable'])->toBeFalse();

    Http::assertNothingSent();
});

test('a card already known to need the customer is not asked about again', function () {
    stripeVaultConfigured();
    Http::fake(['*' => Http::response(['id' => 'pi_no', 'status' => 'succeeded', 'amount_received' => 5000], 200)]);

    $invoice = stripeVaultInvoice();
    $card = vaultedStripeCard($invoice);
    $card->update(['status' => PaymentMethod::STATUS_REQUIRES_UPDATE]);

    expect(app(StripeModule::class)->chargeStoredMethod($invoice, $card, 50.0)['retryable'])->toBeFalse();

    // Issuers read repeated attempts on a card they have already refused as
    // fraud, which drags down acceptance on the cards that would have worked.
    Http::assertNothingSent();
});

test('a card the issuer has finished with is written down as needing the customer', function () {
    stripeVaultConfigured();

    $invoice = stripeVaultInvoice();
    $card = vaultedStripeCard($invoice);

    refusedCharge($invoice, $card)(['type' => 'card_error', 'code' => 'card_declined', 'decline_code' => 'lost_card', 'message' => 'Declined']);

    // The column existed and nothing ever wrote to it, so the panel showed a
    // dead card as active and every renewal ran into the same refusal.
    expect($card->fresh()->status)->toBe(PaymentMethod::STATUS_REQUIRES_UPDATE);
});

test('a card that only needs authenticating is left active', function () {
    stripeVaultConfigured();

    $invoice = stripeVaultInvoice();
    $card = vaultedStripeCard($invoice);

    refusedCharge($invoice, $card)([
        'type' => 'card_error',
        'code' => 'authentication_required',
        'decline_code' => 'authentication_required',
        'message' => 'Authenticate',
        'payment_intent' => ['id' => 'pi_3ds'],
    ]);

    // That card works perfectly well with its owner in front of it. Telling
    // them to replace it would be wrong, and would lose the payment that was
    // one confirmation away.
    expect($card->fresh()->status)->toBe(PaymentMethod::STATUS_ACTIVE);
});

test('our own mistake is not written on the customer card', function () {
    stripeVaultConfigured();

    $invoice = stripeVaultInvoice();
    $card = vaultedStripeCard($invoice);

    refusedCharge($invoice, $card)(['type' => 'invalid_request_error', 'code' => 'amount_too_small', 'message' => 'Too small'], 400);

    // An amount below Stripe's minimum is this end getting something wrong.
    // Sending the customer to their bank over it would be a lie.
    expect($card->fresh()->status)->toBe(PaymentMethod::STATUS_ACTIVE);
});
