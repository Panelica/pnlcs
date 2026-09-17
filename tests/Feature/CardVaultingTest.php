<?php

use App\Enums\ChargeAttemptState;
use App\Enums\InvoiceStatus;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\InvoiceChargeAttempt;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/*
 * The way in, and the way out.
 *
 * The charger that this branch is built around could not collect a penny,
 * because nothing anywhere called beginVaulting(): there was no screen on which
 * a customer could store a card, so there was never a card to charge. These
 * tests are about the three things that had to be true before any of it was
 * real — a customer can store a card, the first one they store is the one the
 * charger will pick, and removing one stops the gateway holding it too — and
 * about the two the customer is owed once it is: a way to say no, and a way to
 * find out what happened.
 *
 * Every one of them also has to be invisible while the shop has the feature
 * switched off, which is the state every installation is in today.
 */

/** The shop collects by card. */
function vaultingOn(): void
{
    Setting::set('AutoChargeEnabled', '1');
}

/** Stripe, switched on and configured, which is what usableGateways() asks. */
function vaultingGatewayConfigured(array $overrides = []): void
{
    $settings = $overrides + [
        'active' => '1',
        'publishable_key' => 'pk_test_visible',
        'secret_key' => 'sk_test_secret',
    ];

    foreach ($settings as $setting => $value) {
        GatewaySettings::updateOrCreate(
            ['gateway' => 'stripe', 'setting' => $setting],
            ['value' => $value]
        );
    }
}

/** A signed-in customer. @return array{0: User, 1: Client} */
function vaultingCustomer(array $clientAttributes = []): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create($clientAttributes);
    $user->clients()->attach($client->id);

    return [$user, $client];
}

/** What Stripe answers while a card is being stored. */
function vaultingStripeFakes(array $overrides = []): void
{
    Http::fake($overrides + [
        '*/v1/setup_intents' => Http::response(['id' => 'seti_1', 'client_secret' => 'seti_1_secret'], 200),
        '*/v1/customers*' => Http::response(['id' => 'cus_1'], 200),
        '*/v1/payment_methods/*' => Http::response([
            'id' => 'pm_stored',
            'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 7, 'exp_year' => 2030],
        ], 200),
    ]);
}

/** The SetupIntent Stripe hands back when the browser has finished with it. */
function vaultingConfirmedIntent(Client $client, array $overrides = []): array
{
    return $overrides + [
        'id' => 'seti_1',
        'status' => 'succeeded',
        'payment_method' => 'pm_stored',
        'customer' => 'cus_1',
        'metadata' => ['client_id' => (string) $client->id],
    ];
}

// ---------------------------------------------------------------------------
// (a) a customer can store a card
// ---------------------------------------------------------------------------

test('the card form is reachable, carries the publishable key and never the secret one', function () {
    vaultingOn();
    vaultingGatewayConfigured();
    vaultingStripeFakes();
    [$user] = vaultingCustomer();

    $response = $this->actingAs($user)->get(route('client.payment-methods.add-card'));

    $response->assertOk()
        // The browser needs both of these and neither is a secret: the
        // publishable key names the account and the client secret confirms this
        // one SetupIntent.
        ->assertSee('pk_test_visible', false)
        ->assertSee('seti_1_secret', false)
        ->assertSee('js.stripe.com/v3/', false);

    // The key that can move money is not on the page at any price.
    expect($response->getContent())->not->toContain('sk_test_secret');
});

test('the payment methods page offers card storage only where a card could be charged', function () {
    vaultingGatewayConfigured();
    [$user] = vaultingCustomer();

    // Feature off: the page is the page it has always been.
    $this->actingAs($user)->get(route('client.payment-methods.index'))
        ->assertOk()
        ->assertDontSee(route('client.payment-methods.add-card'));

    vaultingOn();

    $this->actingAs($user)->get(route('client.payment-methods.index'))
        ->assertOk()
        ->assertSee(route('client.payment-methods.add-card'));
});

test('a confirmed card setup stores the card through the same write the webhook uses', function () {
    vaultingOn();
    vaultingGatewayConfigured();
    [$user, $client] = vaultingCustomer();

    // The intent Stripe will describe when it is asked about seti_1.
    Http::fake([
        '*/v1/setup_intents/seti_1' => Http::response(vaultingConfirmedIntent($client), 200),
        '*/v1/payment_methods/pm_stored' => Http::response([
            'id' => 'pm_stored',
            'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 7, 'exp_year' => 2030],
        ], 200),
    ]);

    $this->actingAs($user)->post(route('client.payment-methods.store-card'), [
        'session_id' => 'seti_1',
        'consent' => '1',
    ])->assertRedirect(route('client.payment-methods.index'));

    $stored = PaymentMethod::where('client_id', $client->id)->first();

    expect($stored)->not->toBeNull()
        ->and($stored->gateway_name)->toBe('stripe')
        ->and($stored->remote_token)->toBe('pm_stored')
        ->and($stored->gateway_customer_id)->toBe('cus_1')
        // 'cc' rather than 'card': it is what the expiry alert looks for. The
        // row is written by webhookSetupSucceeded and this proves it, rather
        // than a second copy of that write living in the controller.
        ->and($stored->payment_type)->toBe('cc')
        ->and($stored->last_four)->toBe('4242')
        ->and($stored->status)->toBe(PaymentMethod::STATUS_ACTIVE);
});

test('a card setup that was opened for another account stores nothing', function () {
    vaultingOn();
    vaultingGatewayConfigured();
    [$user, $client] = vaultingCustomer();
    $stranger = Client::factory()->create();

    Http::fake([
        '*/v1/setup_intents/seti_1' => Http::response(vaultingConfirmedIntent($stranger), 200),
    ]);

    $this->actingAs($user)->post(route('client.payment-methods.store-card'), [
        'session_id' => 'seti_1',
        'consent' => '1',
    ])->assertRedirect(route('client.payment-methods.index'));

    // Neither account gains a card: not the caller's, because the intent is not
    // theirs, and not the stranger's, because it was refused before the write.
    expect(PaymentMethod::count())->toBe(0);
});

test('an unfinished card setup stores nothing', function () {
    vaultingOn();
    vaultingGatewayConfigured();
    [$user, $client] = vaultingCustomer();

    Http::fake([
        '*/v1/setup_intents/seti_1' => Http::response(
            vaultingConfirmedIntent($client, ['status' => 'requires_action']),
            200
        ),
    ]);

    $this->actingAs($user)->post(route('client.payment-methods.store-card'), [
        'session_id' => 'seti_1',
        'consent' => '1',
    ]);

    expect(PaymentMethod::count())->toBe(0);
});

test('a card cannot be stored without the customer agreeing to what it is for', function () {
    vaultingOn();
    vaultingGatewayConfigured();
    [$user, $client] = vaultingCustomer();

    Http::fake([
        '*/v1/setup_intents/seti_1' => Http::response(vaultingConfirmedIntent($client), 200),
    ]);

    $this->actingAs($user)->post(route('client.payment-methods.store-card'), [
        'session_id' => 'seti_1',
    ])->assertSessionHasErrors('consent');

    expect(PaymentMethod::count())->toBe(0);
    Http::assertNothingSent();
});

test('the agreement to automatic payment is written down where the account can be read', function () {
    vaultingOn();
    vaultingGatewayConfigured();
    // Somebody who had previously said no, saying yes in writing.
    [$user, $client] = vaultingCustomer(['auto_charge' => false]);

    Http::fake([
        '*/v1/setup_intents/seti_1' => Http::response(vaultingConfirmedIntent($client), 200),
        '*/v1/payment_methods/pm_stored' => Http::response(['id' => 'pm_stored', 'card' => []], 200),
    ]);

    $this->actingAs($user)->post(route('client.payment-methods.store-card'), [
        'session_id' => 'seti_1',
        'consent' => '1',
    ]);

    $record = ActivityLog::where('client_id', $client->id)->latest('id')->first();

    expect($record)->not->toBeNull()
        ->and($record->description)->toContain('agreed to automatic payment')
        ->and($client->fresh()->auto_charge)->toBeTrue();
});

test('with the feature off the card form does not exist', function () {
    vaultingGatewayConfigured();
    [$user] = vaultingCustomer();

    $this->actingAs($user)->get(route('client.payment-methods.add-card'))->assertNotFound();
    $this->actingAs($user)->post(route('client.payment-methods.store-card'), [
        'session_id' => 'seti_1',
        'consent' => '1',
    ])->assertNotFound();

    Http::assertNothingSent();
});

test('with no gateway that can charge a stored card the form does not exist either', function () {
    vaultingOn();
    // Switched off at the gateway: usableGateways() will not name it, so there
    // is nothing to store a card with even though the feature is on.
    vaultingGatewayConfigured(['active' => '0']);
    [$user] = vaultingCustomer();

    $this->actingAs($user)->get(route('client.payment-methods.add-card'))->assertNotFound();
});

// ---------------------------------------------------------------------------
// (b) the first card stored is the one the charger will pick
// ---------------------------------------------------------------------------

test('the first card a client stores becomes their default', function () {
    vaultingOn();
    vaultingGatewayConfigured();
    [$user, $client] = vaultingCustomer();

    Http::fake([
        '*/v1/setup_intents/seti_1' => Http::response(vaultingConfirmedIntent($client), 200),
        '*/v1/payment_methods/pm_stored' => Http::response(['id' => 'pm_stored', 'card' => ['last4' => '4242']], 200),
    ]);

    $this->actingAs($user)->post(route('client.payment-methods.store-card'), [
        'session_id' => 'seti_1',
        'consent' => '1',
    ]);

    expect(PaymentMethod::where('client_id', $client->id)->first()->is_default)->toBeTrue();
});

test('a second card does not take the default off the first', function () {
    vaultingOn();
    vaultingGatewayConfigured();
    [$user, $client] = vaultingCustomer();

    $first = PaymentMethod::create([
        'client_id' => $client->id,
        'gateway_name' => 'stripe',
        'payment_type' => 'cc',
        'remote_token' => 'pm_first',
        'gateway_customer_id' => 'cus_1',
        'is_default' => true,
    ]);

    Http::fake([
        '*/v1/setup_intents/seti_2' => Http::response(
            vaultingConfirmedIntent($client, ['id' => 'seti_2', 'payment_method' => 'pm_second']),
            200
        ),
        '*/v1/payment_methods/pm_second' => Http::response(['id' => 'pm_second', 'card' => ['last4' => '1111']], 200),
    ]);

    $this->actingAs($user)->post(route('client.payment-methods.store-card'), [
        'session_id' => 'seti_2',
        'consent' => '1',
    ]);

    $second = PaymentMethod::where('remote_token', 'pm_second')->first();

    expect($first->fresh()->is_default)->toBeTrue()
        ->and($second->is_default)->toBeFalse();
});

test('a default the customer chose for themselves is never moved by a new card', function () {
    vaultingOn();
    vaultingGatewayConfigured();
    [$user, $client] = vaultingCustomer();

    $bank = PaymentMethod::create([
        'client_id' => $client->id,
        'gateway_name' => 'banktransfer',
        'payment_type' => 'BankAccount',
        'description' => 'Their own bank account',
        'is_default' => true,
    ]);

    Http::fake([
        '*/v1/setup_intents/seti_1' => Http::response(vaultingConfirmedIntent($client), 200),
        '*/v1/payment_methods/pm_stored' => Http::response(['id' => 'pm_stored', 'card' => []], 200),
    ]);

    $this->actingAs($user)->post(route('client.payment-methods.store-card'), [
        'session_id' => 'seti_1',
        'consent' => '1',
    ]);

    expect($bank->fresh()->is_default)->toBeTrue()
        ->and(PaymentMethod::where('remote_token', 'pm_stored')->first()->is_default)->toBeFalse();
});

test('a card stored through the page is one the charger will actually present', function () {
    vaultingOn();
    vaultingGatewayConfigured();
    [$user, $client] = vaultingCustomer();

    Http::fake([
        '*/v1/setup_intents/seti_1' => Http::response(vaultingConfirmedIntent($client), 200),
        '*/v1/payment_methods/pm_stored' => Http::response(['id' => 'pm_stored', 'card' => ['last4' => '4242']], 200),
        // The charge itself, taken by the real module against the stored token.
        '*/v1/payment_intents' => Http::response(['id' => 'pi_1', 'status' => 'succeeded', 'amount_received' => 10000], 200),
    ]);

    $this->actingAs($user)->post(route('client.payment-methods.store-card'), [
        'session_id' => 'seti_1',
        'consent' => '1',
    ]);

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => InvoiceStatus::Unpaid->value,
        'subtotal' => 100.0,
        'total' => 100.0,
        'due_date' => now()->addDay(),
    ]);

    Artisan::call('pnlcs:auto-charge');

    // End to end: a customer stored a card on a page that did not exist before,
    // and the morning's run collected the invoice with it.
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value)
        ->and(InvoiceChargeAttempt::where('invoice_id', $invoice->id)->first()->state)
        ->toBe(ChargeAttemptState::Succeeded);
});

// ---------------------------------------------------------------------------
// (c) removing a card stops the gateway holding it
// ---------------------------------------------------------------------------

test('removing a card asks no gateway anything inside the customer request', function () {
    vaultingOn();
    vaultingGatewayConfigured();
    Http::fake();
    [$user, $client] = vaultingCustomer();

    $card = PaymentMethod::create([
        'client_id' => $client->id,
        'gateway_name' => 'stripe',
        'payment_type' => 'cc',
        'remote_token' => 'pm_stored',
        'gateway_customer_id' => 'cus_1',
    ]);

    $this->actingAs($user)->delete(route('client.payment-methods.destroy', $card));

    // The card is gone from the customer's point of view immediately, the
    // request to remove it is written down, and nothing waited on a third
    // party to make that true.
    Http::assertNothingSent();

    $row = PaymentMethod::withTrashed()->find($card->id);

    expect($row->trashed())->toBeTrue()
        ->and($row->detach_requested_at)->not->toBeNull()
        ->and($row->detached_at)->toBeNull();
});

test('the sweep detaches the card at the gateway and stops asking about it', function () {
    vaultingGatewayConfigured();
    Http::fake(['*/v1/payment_methods/pm_stored/detach' => Http::response(['id' => 'pm_stored'], 200)]);
    [, $client] = vaultingCustomer();

    $card = PaymentMethod::create([
        'client_id' => $client->id,
        'gateway_name' => 'stripe',
        'payment_type' => 'cc',
        'remote_token' => 'pm_stored',
        'gateway_customer_id' => 'cus_1',
    ]);
    $card->requestGatewayDetach();
    $card->delete();

    Artisan::call('pnlcs:detach-payment-methods');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/payment_methods/pm_stored/detach'));

    expect(PaymentMethod::withTrashed()->find($card->id)->detached_at)->not->toBeNull();

    // And a second tick does not ask again.
    Http::fake();
    Artisan::call('pnlcs:detach-payment-methods');
    Http::assertNothingSent();
});

test('a gateway that refuses leaves the request outstanding for the next sweep', function () {
    vaultingGatewayConfigured();
    // The gateway is down for the first sweep and back for the second.
    Http::fake(['*/v1/payment_methods/pm_stored/detach' => Http::sequence()
        ->push(['error' => ['message' => 'down']], 500)
        ->push(['id' => 'pm_stored'], 200)]);
    [, $client] = vaultingCustomer();

    $card = PaymentMethod::create([
        'client_id' => $client->id,
        'gateway_name' => 'stripe',
        'payment_type' => 'cc',
        'remote_token' => 'pm_stored',
        'gateway_customer_id' => 'cus_1',
    ]);
    $card->requestGatewayDetach();
    $card->delete();

    Artisan::call('pnlcs:detach-payment-methods');

    // Not marked done. Claiming the card had been let go of when it had not is
    // the one answer that cannot be corrected later, because nothing would ever
    // look at the row again.
    expect(PaymentMethod::withTrashed()->find($card->id)->detached_at)->toBeNull();

    Artisan::call('pnlcs:detach-payment-methods');

    expect(PaymentMethod::withTrashed()->find($card->id)->detached_at)->not->toBeNull();
});

test('a token the gateway has never heard of is finished, not retried for ever', function () {
    vaultingGatewayConfigured();
    Http::fake(['*/v1/payment_methods/pm_gone/detach' => Http::response([
        'error' => ['code' => 'resource_missing', 'message' => 'No such PaymentMethod'],
    ], 404)]);
    [, $client] = vaultingCustomer();

    $card = PaymentMethod::create([
        'client_id' => $client->id,
        'gateway_name' => 'stripe',
        'payment_type' => 'cc',
        'remote_token' => 'pm_gone',
        'gateway_customer_id' => 'cus_1',
    ]);
    $card->requestGatewayDetach();
    $card->delete();

    Artisan::call('pnlcs:detach-payment-methods');

    expect(PaymentMethod::withTrashed()->find($card->id)->detached_at)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// (c.1) the sweep has to survive having something to say
// ---------------------------------------------------------------------------

test('the sweep reports a card the gateway will not let go of instead of dying on the line that reports it', function () {
    vaultingGatewayConfigured();
    // Stripe is down and stays down. The request ages past SHOUT_AFTER_HOURS,
    // which is the branch whose whole purpose is to fetch a person.
    Http::fake(['*/v1/payment_methods/pm_stored/detach' => Http::response(['error' => ['message' => 'down']], 500)]);
    [, $client] = vaultingCustomer();

    $card = PaymentMethod::create([
        'client_id' => $client->id,
        'gateway_name' => 'stripe',
        'payment_type' => 'cc',
        'remote_token' => 'pm_stored',
        'gateway_customer_id' => 'cus_1',
    ]);
    $card->requestGatewayDetach();
    $card->delete();

    // Thirty hours later, which is how cron reaches this branch: the request is
    // older than the twenty-four the command waits before saying anything.
    $this->travel(30)->hours();

    // THE REPORTING PATH IS THE POINT OF THIS TEST. It called
    // $this->getOutput()->getErrorOutput(), and getErrorOutput() is protected
    // on Symfony\Component\Console\Style\OutputStyle — so the one branch that
    // exists to raise the alarm was a PHP Error, every five minutes, for ever,
    // from the first card a gateway would not release. A test that never made
    // the command report would not have seen it.
    $exit = Artisan::call('pnlcs:detach-payment-methods');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('still stored at the gateway and need a person')
        ->and($output)->toContain('#'.$card->id)
        // And it did not lie about the outcome to get there.
        ->and(PaymentMethod::withTrashed()->find($card->id)->detached_at)->toBeNull();
});

test('a gateway refusal that will not change its mind is reported on the first sweep, not after a day', function () {
    vaultingGatewayConfigured();
    // The operator pasted test keys over live ones. Stripe answers
    // livemode_mismatch, which StripeModule reports as not retryable — the
    // other way into the reporting branch, and it fires immediately.
    Http::fake(['*/v1/payment_methods/pm_stored/detach' => Http::response([
        'error' => ['type' => 'invalid_request_error', 'code' => 'livemode_mismatch', 'message' => 'Test key used on a live object'],
    ], 400)]);
    [, $client] = vaultingCustomer();

    $card = PaymentMethod::create([
        'client_id' => $client->id,
        'gateway_name' => 'stripe',
        'payment_type' => 'cc',
        'remote_token' => 'pm_stored',
        'gateway_customer_id' => 'cus_1',
    ]);
    $card->requestGatewayDetach();
    $card->delete();

    $exit = Artisan::call('pnlcs:detach-payment-methods');

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('need a person');
});

// ---------------------------------------------------------------------------
// (c.2) a request nothing can carry out is never recorded
// ---------------------------------------------------------------------------

test('removing a card stored by a gateway that cannot detach records no request that nothing can satisfy', function () {
    // THE FEATURE IS NEVER SWITCHED ON. This is an ordinary iyzico shop:
    // GatewayWebhookController::rememberIyzicoCard writes a remote_token on any
    // payment where the customer ticked 'save my card', with no setting
    // consulted, and IyzicoModule implements GatewayModuleInterface only.
    Http::fake();
    [$user, $client] = vaultingCustomer();

    expect(App\Support\AutoCharge::enabled())->toBeFalse();

    $card = PaymentMethod::create([
        'client_id' => $client->id,
        'gateway_name' => 'iyzico',
        'payment_type' => 'cc',
        'remote_token' => json_encode(['cardUserKey' => 'cuk_1', 'cardToken' => 'ct_1']),
    ]);

    $this->actingAs($user)->delete(route('client.payment-methods.destroy', $card));

    $row = PaymentMethod::withTrashed()->find($card->id);

    // The customer's half of the promise is kept: PNLCS has stopped using it.
    expect($row->trashed())->toBeTrue()
        // And no request is written, because no code in this product could ever
        // satisfy one. It used to be: the row was outstanding for the life of
        // the installation, the five-minute sweep counted it, logged an error
        // about it and came back — 288 error lines a day per removed card, on a
        // shop that never turned automatic charging on.
        ->and($row->detach_requested_at)->toBeNull()
        ->and(PaymentMethod::query()->awaitingGatewayDetach()->count())->toBe(0);

    // Three sweeps a day apart. Nothing to do, nothing said, nothing asked of
    // any gateway.
    foreach (range(1, 3) as $ignored) {
        $this->travel(25)->hours();
        expect(Artisan::call('pnlcs:detach-payment-methods'))->toBe(0);
    }

    Http::assertNothingSent();
});

test('a card already waiting on a gateway that cannot detach stops being asked about', function () {
    Http::fake();
    [, $client] = vaultingCustomer();

    // Written by an earlier build, before the request was refused at the click.
    $card = PaymentMethod::create([
        'client_id' => $client->id,
        'gateway_name' => 'iyzico',
        'payment_type' => 'cc',
        'remote_token' => 'ct_1',
    ]);
    $card->forceFill(['detach_requested_at' => now()->subDays(3)])->save();
    $card->delete();

    $exit = Artisan::call('pnlcs:detach-payment-methods');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        // Not shouted about as something a person can fix at this end, because
        // they cannot: it is said once, plainly, as work for the gateway's own
        // dashboard.
        ->and($output)->not->toContain('need a person')
        ->and($output)->toContain('cannot detach at');

    $row = PaymentMethod::withTrashed()->find($card->id);

    // And nothing was written on the row. detached_at would claim the gateway
    // had let the card go when it has not; clearing the request would erase the
    // customer having asked.
    expect($row->detached_at)->toBeNull()
        ->and($row->detach_requested_at)->not->toBeNull();

    Http::assertNothingSent();
});

test('removing a bank account reference asks for no detach at all', function () {
    vaultingGatewayConfigured();
    Http::fake();
    [$user, $client] = vaultingCustomer();

    $bank = PaymentMethod::create([
        'client_id' => $client->id,
        'gateway_name' => 'banktransfer',
        'payment_type' => 'BankAccount',
        'description' => 'Their own bank account',
    ]);

    $this->actingAs($user)->delete(route('client.payment-methods.destroy', $bank));

    expect(PaymentMethod::withTrashed()->find($bank->id)->detach_requested_at)->toBeNull();

    Artisan::call('pnlcs:detach-payment-methods');

    Http::assertNothingSent();
});

test('the sweep touches nothing when no card is waiting', function () {
    vaultingGatewayConfigured();
    Http::fake();

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    Artisan::call('pnlcs:detach-payment-methods');

    Http::assertNothingSent();

    // One look at the one table, and no gateway, no settings, no writes.
    expect(array_values(array_filter($queries, fn (string $sql) => ! str_starts_with($sql, 'select'))))->toBe([])
        ->and(array_values(array_filter($queries, fn (string $sql) => ! str_contains($sql, 'payment_methods'))))->toBe([]);
});

// ---------------------------------------------------------------------------
// (d) the customer's own way of saying no
// ---------------------------------------------------------------------------

test('a customer who has switched automatic payment off is not charged', function () {
    vaultingOn();
    vaultingGatewayConfigured();
    Http::fake(['*' => Http::response(['id' => 'pi_1', 'status' => 'succeeded', 'amount_received' => 10000], 200)]);
    [, $client] = vaultingCustomer(['auto_charge' => false]);

    PaymentMethod::create([
        'client_id' => $client->id,
        'gateway_name' => 'stripe',
        'payment_type' => 'cc',
        'remote_token' => 'pm_stored',
        'gateway_customer_id' => 'cus_1',
        'is_default' => true,
    ]);

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => InvoiceStatus::Unpaid->value,
        'subtotal' => 100.0,
        'total' => 100.0,
        'due_date' => now()->addDay(),
    ]);

    Artisan::call('pnlcs:auto-charge');

    Http::assertNothingSent();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value)
        ->and(InvoiceChargeAttempt::count())->toBe(0);
});

test('the switch is on the page the cards are on, and the customer can work it', function () {
    vaultingOn();
    vaultingGatewayConfigured();
    [$user, $client] = vaultingCustomer();

    $this->actingAs($user)->get(route('client.payment-methods.index'))
        ->assertOk()
        ->assertSee(__('client.payment_methods.auto_charge_turn_off'));

    $this->actingAs($user)->post(route('client.payment-methods.auto-charge'), ['auto_charge' => '0']);

    expect($client->fresh()->auto_charge)->toBeFalse();
});

// ---------------------------------------------------------------------------
// (e) the customer can see what happened
// ---------------------------------------------------------------------------

/** An invoice of this client's with the charger's verdict on it. */
function vaultingAttempt(Client $client, ChargeAttemptState $state, array $attributes = []): Invoice
{
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => InvoiceStatus::Unpaid->value,
        'subtotal' => 100.0,
        'total' => 100.0,
        'due_date' => now()->addDay(),
    ]);

    InvoiceChargeAttempt::create($attributes + [
        'invoice_id' => $invoice->id,
        'state' => $state->value,
        'attempts' => 1,
        'amount' => 100.0,
        'currency' => 'USD',
    ]);

    return $invoice;
}

test('a customer whose bank wants them is told so, and given the way to finish it', function () {
    vaultingOn();
    vaultingGatewayConfigured();
    [$user, $client] = vaultingCustomer();

    $invoice = vaultingAttempt($client, ChargeAttemptState::ActionRequired, ['last_transaction_id' => 'pi_stuck']);

    $this->actingAs($user)->get(route('client.invoices.show', $invoice))
        ->assertOk()
        ->assertSee(__('client.invoices.charge_action_required_title'))
        ->assertSee(__('client.invoices.charge_authenticate_button'));
});

test('a customer whose card was refused is told that, and when we will try again', function () {
    vaultingOn();
    vaultingGatewayConfigured();
    [$user, $client] = vaultingCustomer();

    $invoice = vaultingAttempt($client, ChargeAttemptState::Scheduled, [
        'next_attempt_at' => now()->addDays(3)->startOfDay(),
    ]);

    $this->actingAs($user)->get(route('client.invoices.show', $invoice))
        ->assertOk()
        ->assertSee(__('client.invoices.charge_failed_title'));
});

test('a payment whose fate is unknown warns the customer before they pay it twice', function () {
    vaultingOn();
    vaultingGatewayConfigured();
    [$user, $client] = vaultingCustomer();

    $invoice = vaultingAttempt($client, ChargeAttemptState::NeedsReview, ['last_transaction_id' => 'pi_unknown']);

    $this->actingAs($user)->get(route('client.invoices.show', $invoice))
        ->assertOk()
        ->assertSee(__('client.invoices.charge_checking_title'));
});

test('the authentication a bank asked for can be finished from the invoice', function () {
    vaultingOn();
    vaultingGatewayConfigured();
    [$user, $client] = vaultingCustomer();

    $invoice = vaultingAttempt($client, ChargeAttemptState::ActionRequired, ['last_transaction_id' => 'pi_stuck']);

    Http::fake(['*/v1/payment_intents/pi_stuck' => Http::response([
        'id' => 'pi_stuck',
        'status' => 'requires_action',
        'client_secret' => 'pi_stuck_secret',
        'metadata' => ['invoice_id' => (string) $invoice->id],
    ], 200)]);

    $response = $this->actingAs($user)
        ->post(route('gateway.stripe.authenticate', $invoice))
        ->assertOk();

    expect($response->json('success'))->toBeTrue()
        ->and($response->json('client_secret'))->toBe('pi_stuck_secret')
        // The one thing that must never travel to a browser.
        ->and($response->getContent())->not->toContain('sk_test_secret');
});

test('an intent the charger did not record cannot be resumed from the browser', function () {
    vaultingOn();
    vaultingGatewayConfigured();
    Http::fake();
    [$user, $client] = vaultingCustomer();

    // An invoice with no attempt row at all: nothing was ever sent to a card
    // for it, so there is nothing to confirm.
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => InvoiceStatus::Unpaid->value,
        'total' => 100.0,
        'due_date' => now()->addDay(),
    ]);

    $response = $this->actingAs($user)->post(route('gateway.stripe.authenticate', $invoice));

    expect($response->json('success'))->toBeFalse();
    Http::assertNothingSent();
});

test('one customer cannot resume another customer\'s payment', function () {
    vaultingOn();
    vaultingGatewayConfigured();
    Http::fake();
    [$user] = vaultingCustomer();
    $stranger = Client::factory()->create();

    $invoice = vaultingAttempt($stranger, ChargeAttemptState::ActionRequired, ['last_transaction_id' => 'pi_stuck']);

    $this->actingAs($user)->post(route('gateway.stripe.authenticate', $invoice))->assertForbidden();

    Http::assertNothingSent();
});

test('the id of the payment to confirm comes from what the charger wrote down, never from the browser', function () {
    vaultingOn();
    vaultingGatewayConfigured();
    [$user, $client] = vaultingCustomer();

    // Their OWN invoice, with a genuine intent the charger recorded. The two
    // guards that stop this becoming a way of reading somebody else's payment
    // — GatewayWebhookController taking the id from the attempt row rather than
    // the request, and StripeModule refusing an intent whose metadata names
    // another invoice — had no test between them: a reviewer removed each in
    // turn and the suite stayed green, and with both gone a signed-in customer
    // could post another customer's intent id and be handed its client_secret.
    $invoice = vaultingAttempt($client, ChargeAttemptState::ActionRequired, ['last_transaction_id' => 'pi_mine']);

    Http::fake([
        '*/v1/payment_intents/pi_mine' => Http::response([
            'id' => 'pi_mine', 'status' => 'requires_action',
            'client_secret' => 'pi_mine_secret', 'metadata' => ['invoice_id' => (string) $invoice->id],
        ], 200),
        '*/v1/payment_intents/pi_victim' => Http::response([
            'id' => 'pi_victim', 'status' => 'requires_action',
            'client_secret' => 'pi_victim_secret', 'metadata' => ['invoice_id' => '999999'],
        ], 200),
    ]);

    $response = $this->actingAs($user)->post(
        route('gateway.stripe.authenticate', $invoice),
        ['payment_intent_id' => 'pi_victim']
    );

    expect($response->json('client_secret'))->toBe('pi_mine_secret');

    // And the browser's id was not so much as looked up.
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'pi_victim'));
});

test('an intent belonging to another invoice is refused even when it is the id on the row', function () {
    vaultingOn();
    vaultingGatewayConfigured();
    [$user, $client] = vaultingCustomer();

    // The second guard on its own: the row names an intent, and the gateway's
    // own copy of that intent says it belongs to a different invoice. Whatever
    // put that id on the row, the client_secret is not handed over.
    $invoice = vaultingAttempt($client, ChargeAttemptState::ActionRequired, ['last_transaction_id' => 'pi_elsewhere']);

    Http::fake(['*/v1/payment_intents/pi_elsewhere' => Http::response([
        'id' => 'pi_elsewhere', 'status' => 'requires_action',
        'client_secret' => 'pi_elsewhere_secret', 'metadata' => ['invoice_id' => '999999'],
    ], 200)]);

    $response = $this->actingAs($user)->post(route('gateway.stripe.authenticate', $invoice));

    expect($response->json('success'))->toBeFalse()
        ->and($response->getContent())->not->toContain('pi_elsewhere_secret');
});

test('an invoice cannot be parked for a person without an error log being raised', function () {
    // B7's first channel, which had nothing behind it: markNeedsReview is the
    // only public way into the state precisely so that it cannot be entered
    // quietly, and the Log::error inside it was free to be deleted.
    [, $client] = vaultingCustomer();
    $invoice = vaultingAttempt($client, ChargeAttemptState::InFlight, ['last_transaction_id' => 'pi_unknown']);

    $shouted = [];
    Illuminate\Support\Facades\Log::listen(function ($message) use (&$shouted) {
        if ($message->level === 'error') {
            $shouted[] = $message->message;
        }
    });

    InvoiceChargeAttempt::forInvoice($invoice)->markNeedsReview('The gateway never answered.');

    expect($shouted)->toHaveCount(1)
        ->and($shouted[0])->toContain('left for a person');
});

// ---------------------------------------------------------------------------
// invariant zero: none of this exists while the feature is off
// ---------------------------------------------------------------------------

test('with the feature off the invoice page says nothing about charging and asks nothing about it', function () {
    vaultingGatewayConfigured();
    [$user, $client] = vaultingCustomer();

    // A row from a shop that used the feature and then switched it off, which
    // is the hardest case: the data exists and must still not be read.
    $invoice = vaultingAttempt($client, ChargeAttemptState::ActionRequired, ['last_transaction_id' => 'pi_stuck']);

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    $response = $this->actingAs($user)->get(route('client.invoices.show', $invoice))->assertOk();

    expect(array_values(array_filter($queries, fn (string $sql) => str_contains($sql, 'invoice_charge_attempts'))))->toBe([])
        ->and($response->getContent())->not->toContain(__('client.invoices.charge_action_required_title'))
        ->and($response->getContent())->not->toContain(__('client.invoices.charge_authenticate_button'));

    // And the endpoint behind the button is not there either.
    $this->actingAs($user)->post(route('gateway.stripe.authenticate', $invoice))->assertNotFound();
});

/*
 * The tag every fetch() in the client area reads its token from.
 *
 * It was in the admin layout and not in this one, so the Stripe payment form
 * the gateway module renders on an invoice — which reads exactly this tag —
 * posted an empty token and was refused with a 419. The card page does not
 * depend on it (its token is a form field), but the pay form the customer is
 * sent to when a charge needs their bank does.
 */
test('the client layout carries the token its own scripts post with', function () {
    [$user] = vaultingCustomer();

    $this->actingAs($user)->get(route('client.payment-methods.index'))
        ->assertOk()
        ->assertSee('name="csrf-token"', false);
});
