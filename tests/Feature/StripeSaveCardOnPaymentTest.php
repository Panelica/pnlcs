<?php

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Gateways\Stripe\StripeModule;

/*
 * A card that paid an invoice through the Stripe pay form was gone afterwards:
 * the intent was made with no customer and no setup_future_usage, so nothing
 * was left to charge the next renewal with and automatic payment never ran for
 * anyone who paid by hand. The pay form now offers "keep this card" while
 * automatic payment is on; ticked, the intent is made for the client's Stripe
 * customer with setup_future_usage=off_session, and the card is stored when
 * the payment succeeds (the confirm, or the webhook when the tab was closed).
 */

function sscpOn(): void
{
    Setting::set('AutoChargeEnabled', '1');

    foreach (['active' => '1', 'publishable_key' => 'pk_test_x', 'secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_test'] as $setting => $value) {
        GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => $setting], ['value' => $value]);
    }
}

/** @return array{0: User, 1: Client, 2: Invoice} */
function sscpCustomer(array $clientAttributes = []): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create($clientAttributes);
    $user->clients()->attach($client->id);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 12.5, 'source_currency' => 'EUR']);

    return [$user, $client, $invoice];
}

/** A succeeded intent that was made with "keep this card" ticked. */
function sscpPaidIntent(Invoice $invoice, array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 'pi_1',
        'status' => 'succeeded',
        'amount' => 1250,
        'amount_received' => 1250,
        'currency' => 'eur',
        'customer' => 'cus_1',
        'payment_method' => 'pm_paid',
        'metadata' => ['invoice_id' => (string) $invoice->id, 'client_id' => (string) $invoice->client_id, 'save_card' => '1'],
    ], $overrides);
}

function sscpCardFake(string $id = 'pm_paid', string $last4 = '4242'): array
{
    return ['id' => $id, 'card' => ['brand' => 'visa', 'last4' => $last4, 'exp_month' => 7, 'exp_year' => 2030]];
}

function sscpSigned(array $payload): array
{
    $body = json_encode($payload);
    $timestamp = time();

    return array_merge($payload, [
        '_raw_payload' => $body,
        '_signature_header' => "t={$timestamp},v1=".hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test'),
    ]);
}

test('the pay form offers to keep the card while automatic payment is on, unticked', function () {
    sscpOn();
    [, , $invoice] = sscpCustomer();
    app()->setLocale('tr');

    $form = app(StripeModule::class)->getPaymentForm($invoice);

    expect($form)
        ->toContain('id="stripe-save-card"')
        ->not->toContain('id="stripe-save-card" value="1" checked')
        ->toContain(htmlspecialchars(__('messages.stripe.save_card'), ENT_QUOTES, 'UTF-8'))
        ->toContain('save.addEventListener("change", prepare);')
        ->toContain('body: JSON.stringify({ save_card: !!(save && save.checked) })');
    expect(__('messages.stripe.save_card'))->toContain('otomatik öde');
});

test('with automatic payment off there is nothing to keep a card for', function () {
    sscpOn();
    Setting::set('AutoChargeEnabled', '0');
    [, , $invoice] = sscpCustomer();

    expect(app(StripeModule::class)->getPaymentForm($invoice))->not->toContain('id="stripe-save-card"');
});

test('ticked, the intent is made for the client\'s Stripe customer to be charged off-session', function () {
    sscpOn();
    [$user, $client, $invoice] = sscpCustomer();
    Http::fake([
        'api.stripe.com/v1/customers' => Http::response(['id' => 'cus_1']),
        'api.stripe.com/v1/payment_intents' => Http::response(['id' => 'pi_1', 'client_secret' => 'pi_1_secret']),
    ]);

    $this->actingAs($user)->postJson(route('gateway.stripe.intent', $invoice), ['save_card' => true])
        ->assertOk()->assertJson(['success' => true, 'client_secret' => 'pi_1_secret']);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v1/payment_intents')
        && $request['customer'] === 'cus_1'
        && $request['setup_future_usage'] === 'off_session'
        && $request['metadata[save_card]'] === '1'
        && (int) $request['metadata[client_id]'] === $client->id
        && $request['amount'] == 1250);
});

test('unticked, or with automatic payment off, the intent is the plain one it always was', function (bool $ticked, string $autoCharge) {
    sscpOn();
    Setting::set('AutoChargeEnabled', $autoCharge);
    [$user, , $invoice] = sscpCustomer();
    Http::fake(['api.stripe.com/v1/payment_intents' => Http::response(['id' => 'pi_1', 'client_secret' => 'pi_1_secret'])]);

    $this->actingAs($user)->postJson(route('gateway.stripe.intent', $invoice), ['save_card' => $ticked])->assertOk();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => ! isset($request['customer']) && ! isset($request['setup_future_usage']) && ! isset($request['metadata[save_card]']));
})->with([
    'unticked' => [false, '1'],
    'feature off' => [true, '0'],
]);

test('the confirm stores the card that paid, makes it the default and records the agreement', function () {
    sscpOn();
    [$user, $client, $invoice] = sscpCustomer(['auto_charge' => false]);
    Http::fake([
        'api.stripe.com/v1/payment_intents/pi_1' => Http::response(sscpPaidIntent($invoice)),
        'api.stripe.com/v1/payment_methods/pm_paid' => Http::response(sscpCardFake()),
    ]);

    $this->actingAs($user)->postJson(route('gateway.stripe.confirm', $invoice), ['payment_intent_id' => 'pi_1'])
        ->assertOk()->assertJson(['success' => true]);

    $card = PaymentMethod::where('client_id', $client->id)->sole();
    expect($card->remote_token)->toBe('pm_paid')
        ->and($card->gateway_name)->toBe('stripe')
        ->and($card->gateway_customer_id)->toBe('cus_1')
        ->and($card->last_four)->toBe('4242')
        ->and($card->expiry_date)->toBe('2030-07')
        ->and($card->payment_type)->toBe('cc')
        ->and((bool) $card->is_default)->toBeTrue()
        ->and($invoice->fresh()->status)->toBe('paid')
        ->and($client->fresh()->auto_charge)->toBeTrue()
        ->and(ActivityLog::where('client_id', $client->id)->latest('id')->value('description'))->toContain('agreed to automatic payment')
        ->and(session('success'))->toBe(__('client.payment_methods.card_stored'));
});

test('a payment made without the tick stores nothing', function () {
    sscpOn();
    [$user, $client, $invoice] = sscpCustomer();
    $intent = sscpPaidIntent($invoice);
    unset($intent['metadata']['save_card'], $intent['customer']);
    Http::fake(['api.stripe.com/v1/payment_intents/pi_1' => Http::response($intent)]);

    $this->actingAs($user)->postJson(route('gateway.stripe.confirm', $invoice), ['payment_intent_id' => 'pi_1'])->assertOk();

    expect(PaymentMethod::where('client_id', $client->id)->count())->toBe(0)
        ->and($invoice->fresh()->status)->toBe('paid');
    Http::assertSentCount(1);
});

test('the webhook stores the card when the tab was closed before the confirm, and a repeat adds nothing', function () {
    sscpOn();
    [, $client, $invoice] = sscpCustomer();
    Http::fake(['api.stripe.com/v1/payment_methods/pm_paid' => Http::response(sscpCardFake())]);
    $stripe = app(StripeModule::class);

    $result = $stripe->processWebhook(sscpSigned(['id' => 'evt_1', 'type' => 'payment_intent.succeeded', 'data' => ['object' => sscpPaidIntent($invoice)]]));
    $stripe->processWebhook(sscpSigned(['id' => 'evt_2', 'type' => 'payment_intent.succeeded', 'data' => ['object' => sscpPaidIntent($invoice)]]));

    expect($result['transaction_id'])->toBe('pi_1')
        ->and(PaymentMethod::where('client_id', $client->id)->count())->toBe(1);
});

test('the same card paid with again is not stored twice', function () {
    sscpOn();
    [$user, $client, $invoice] = sscpCustomer();
    PaymentMethod::create([
        'client_id' => $client->id, 'gateway_name' => 'stripe', 'remote_token' => 'pm_old', 'payment_type' => 'cc',
        'card_brand' => 'visa', 'last_four' => '4242', 'exp_month' => 7, 'exp_year' => 2030, 'status' => PaymentMethod::STATUS_ACTIVE,
    ]);
    Http::fake([
        'api.stripe.com/v1/payment_intents/pi_1' => Http::response(sscpPaidIntent($invoice)),
        'api.stripe.com/v1/payment_methods/pm_paid' => Http::response(sscpCardFake()),
    ]);

    $this->actingAs($user)->postJson(route('gateway.stripe.confirm', $invoice), ['payment_intent_id' => 'pi_1'])->assertOk();

    expect(PaymentMethod::where('client_id', $client->id)->pluck('remote_token')->all())->toBe(['pm_old']);
});

test('a card Stripe cannot describe does not turn a taken payment into a failed one', function () {
    sscpOn();
    [$user, $client, $invoice] = sscpCustomer();
    Http::fake([
        'api.stripe.com/v1/payment_intents/pi_1' => Http::response(sscpPaidIntent($invoice)),
        'api.stripe.com/v1/payment_methods/pm_paid' => Http::response(['error' => ['message' => 'down']], 503),
    ]);

    $this->actingAs($user)->postJson(route('gateway.stripe.confirm', $invoice), ['payment_intent_id' => 'pi_1'])
        ->assertOk()->assertJson(['success' => true]);

    expect($invoice->fresh()->status)->toBe('paid')
        ->and(PaymentMethod::where('client_id', $client->id)->count())->toBe(0);
});
