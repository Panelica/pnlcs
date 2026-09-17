<?php

use App\Contracts\GatewayModuleInterface;
use App\Contracts\TokenizableGatewayInterface;
use App\Enums\ChargeAttemptState;
use App\Enums\ClientStatus;
use App\Enums\InvoiceStatus;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\InvoiceChargeAttempt as Attempt;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\AutoChargeService;
use App\Services\InvoiceService;
use App\Services\Module\ModuleRegistry;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;

/*
 * The command that takes money.
 *
 * Everything here is about one of two questions. Is this invoice one we are
 * allowed to charge, and can this run take the money twice? The second is the
 * one worth reading: an overlapping cron, a process killed with a charge in
 * flight, a webhook that credited the invoice while we were not looking, and a
 * late fee that moved the total between one attempt and the next all have their
 * own test below, and each of them charges the card exactly once or not at all.
 *
 * The waits are faked for the whole file so the suite does not spend a minute
 * sleeping, and unfaked in the one test that measures the pacing for real.
 */

beforeEach(function () {
    Sleep::fake();
});

afterEach(function () {
    // Sleep::fake() is global and the framework's TestCase does not undo it.
    // Leaving it on would fake every sleep in every later file of the run.
    Sleep::fake(false);
});

/** The feature, switched on the way an operator switches it on. */
function autoChargeOn(array $settings = []): void
{
    Setting::set('AutoChargeEnabled', '1');

    foreach ($settings as $key => $value) {
        Setting::set($key, (string) $value);
    }
}

/**
 * A gateway that can charge a stored card, and a record of what it was asked.
 *
 * Registered under 'stripe' and switched on in gateway_settings, because
 * ModuleRegistry::usableGateways() is what the charger asks and it wants both.
 *
 * @param  callable|null  $responder  fn (Invoice, PaymentMethod, float $amount, int $call): array
 */
function autoChargeGateway(?callable $responder = null): ArrayObject
{
    $calls = new ArrayObject;

    $responder ??= fn ($invoice, $method, $amount) => [
        'success' => true,
        'status' => 'succeeded',
        'transaction_id' => 'pi_'.$invoice->id,
        'amount' => $amount,
    ];

    $fake = Mockery::mock(GatewayModuleInterface::class.', '.TokenizableGatewayInterface::class);
    $fake->shouldReceive('getModuleName')->andReturn('fake');
    $fake->shouldReceive('isTokenised')->andReturn(true);
    $fake->shouldReceive('getConfigFields')->andReturn([]);
    $fake->shouldReceive('chargeStoredMethod')->andReturnUsing(
        function ($invoice, $method, $amount, $params = []) use ($calls, $responder) {
            $amount = round((float) $amount, 2);
            $calls[] = ['invoice' => $invoice->id, 'method' => $method->id, 'amount' => $amount];

            return $responder($invoice, $method, $amount, count($calls));
        }
    );

    app()->instance(GatewayModuleInterface::class, $fake);
    app(ModuleRegistry::class)->registerGateway('stripe', GatewayModuleInterface::class);

    GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => 'active'], ['value' => '1']);

    return $calls;
}

/** An invoice that is due inside the charging window. */
function autoChargeInvoice(float $total = 100.0, array $attributes = []): Invoice
{
    return Invoice::factory()->create($attributes + [
        'status' => InvoiceStatus::Unpaid->value,
        'subtotal' => $total,
        'total' => $total,
        'due_date' => now()->addDay(),
    ]);
}

/** A card the customer has stored with the tokenising gateway. */
function autoChargeCard(Invoice $invoice, array $attributes = []): PaymentMethod
{
    return PaymentMethod::create($attributes + [
        'client_id' => $invoice->client_id,
        'gateway_name' => 'stripe',
        'payment_type' => 'cc',
        'description' => 'Visa 4242',
        'remote_token' => 'pm_'.fake()->unique()->numerify('##########'),
        'gateway_customer_id' => 'cus_'.fake()->unique()->numerify('##########'),
        'last_four' => '4242',
        'expiry_date' => '2030-07',
        'status' => PaymentMethod::STATUS_ACTIVE,
    ]);
}

/** A due invoice with one card stored against it. @return array{0: Invoice, 1: PaymentMethod} */
function autoChargeReady(float $total = 100.0, array $attributes = []): array
{
    $invoice = autoChargeInvoice($total, $attributes);

    return [$invoice, autoChargeCard($invoice)];
}

function autoChargeRun(array $arguments = []): void
{
    Artisan::call('pnlcs:auto-charge', $arguments);
}

function autoChargeAttempt(Invoice $invoice): Attempt
{
    return Attempt::forInvoice($invoice) ?? test()->fail('no attempt row for invoice '.$invoice->id);
}

// ---------------------------------------------------------------------------
// The master switch
// ---------------------------------------------------------------------------

test('with the switch off the command returns without reading a single invoice', function () {
    $calls = autoChargeGateway();
    [$invoice] = autoChargeReady();

    // Not switched on. Nothing else about this installation differs from one
    // that has the feature and uses it.
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    autoChargeRun();

    $touched = array_values(array_filter($queries, fn (string $sql) => str_contains($sql, 'invoices')
        || str_contains($sql, 'invoice_charge_attempts')
        || str_contains($sql, 'payment_methods')
        || str_contains($sql, 'gateway_settings')));

    expect($touched)->toBe([])
        ->and($calls->count())->toBe(0)
        ->and(Attempt::count())->toBe(0)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value);
});

test('the service refuses on its own account, not only through the command', function () {
    autoChargeGateway();
    autoChargeReady();

    // Switch deliberately not set: anything that reaches past the command gets
    // the same answer.
    $summary = app(AutoChargeService::class)->run();

    expect($summary['enabled'])->toBeFalse()
        ->and($summary['considered'])->toBe(0)
        ->and(Attempt::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Taking the money
// ---------------------------------------------------------------------------

test('a due invoice is charged for its balance and credited through PaymentService', function () {
    autoChargeOn();
    $calls = autoChargeGateway();
    [$invoice, $card] = autoChargeReady(100.0);

    autoChargeRun();

    expect($calls->getArrayCopy())->toBe([['invoice' => $invoice->id, 'method' => $card->id, 'amount' => 100.0]]);

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Paid->value);

    // Recorded by PaymentService, not written here: one transaction, under the
    // gateway the card was stored with and the id the gateway returned, which
    // is the same pair the webhook would have used.
    $transactions = Transaction::where('invoice_id', $invoice->id)->get();
    expect($transactions)->toHaveCount(1)
        ->and($transactions->first()->gateway)->toBe('stripe')
        ->and($transactions->first()->transaction_id)->toBe('pi_'.$invoice->id)
        ->and((float) $transactions->first()->amount_in)->toBe(100.0);

    $row = autoChargeAttempt($invoice);
    expect($row->state)->toBe(ChargeAttemptState::Succeeded)
        ->and($row->last_transaction_id)->toBe('pi_'.$invoice->id)
        ->and($row->claimed_at)->toBeNull()
        ->and($row->next_attempt_at)->toBeNull();
});

test('the card is asked for what is left, not for the whole invoice', function () {
    autoChargeOn();
    $calls = autoChargeGateway();
    [$invoice, $card] = autoChargeReady(100.0);

    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'BT-1', 40.0);

    autoChargeRun();

    expect($calls[0]['amount'])->toBe(60.0)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value);
});

test('account credit already applied to the invoice is not charged a second time', function () {
    autoChargeOn();
    $calls = autoChargeGateway();
    [$invoice, $card] = autoChargeReady(100.0);

    $invoice->client->update(['credit' => 30.0]);
    app(InvoiceService::class)->applyCredit($invoice->fresh(), 30.0);

    autoChargeRun();

    // applyCredit lowered the total and raised invoices.credit as one
    // transaction, so the balance - and the charge - is what is left after it.
    $invoice->refresh();
    expect($calls[0]['amount'])->toBe(70.0)
        ->and((float) $invoice->credit)->toBe(30.0)
        ->and((float) $invoice->client->fresh()->credit)->toBe(0.0)
        ->and($invoice->status)->toBe(InvoiceStatus::Paid->value);
});

test('an invoice already covered by credit is never presented to a card', function () {
    autoChargeOn();
    $calls = autoChargeGateway();
    [$invoice, $card] = autoChargeReady(100.0);

    $invoice->client->update(['credit' => 100.0]);
    app(InvoiceService::class)->applyCredit($invoice->fresh(), 100.0);

    autoChargeRun();

    expect($calls->count())->toBe(0)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value);
});

// ---------------------------------------------------------------------------
// What it refuses to touch
// ---------------------------------------------------------------------------

test('an invoice that is settled or not yet a bill is never charged', function (string $status) {
    autoChargeOn();
    $calls = autoChargeGateway();
    [$invoice] = autoChargeReady(100.0, ['status' => $status]);

    autoChargeRun();

    expect($calls->count())->toBe(0)
        ->and(Attempt::count())->toBe(0);
})->with([
    InvoiceStatus::Paid->value,
    InvoiceStatus::Cancelled->value,
    InvoiceStatus::Refunded->value,
    // Never issued to anybody.
    InvoiceStatus::Draft->value,
    // The customer says they have paid it by bank transfer and an admin is
    // looking; taking it by card as well is the one outcome nobody wants.
    InvoiceStatus::PaymentPending->value,
    // Nothing in the codebase writes this, so its meaning is whatever the
    // operator who set it by hand meant.
    InvoiceStatus::Collections->value,
]);

test('an invoice that belongs to an order is left to the checkout', function () {
    autoChargeOn();
    $calls = autoChargeGateway();
    [$invoice] = autoChargeReady(100.0);

    Order::factory()->create(['client_id' => $invoice->client_id, 'invoice_id' => $invoice->id]);

    autoChargeRun();

    // Paying it would accept the order and provision an account, which is a
    // great deal more than collecting a bill. RenewOnPaymentListener draws the
    // same line from the other side.
    expect($calls->count())->toBe(0);
});

test('an Add Funds invoice is never charged on a schedule', function () {
    autoChargeOn();
    $calls = autoChargeGateway();
    [$invoice] = autoChargeReady(100.0);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'client_id' => $invoice->client_id,
        'type' => 'AddFunds',
        'description' => 'Add Funds',
        'amount' => 100.0,
        'taxed' => false,
    ]);

    autoChargeRun();

    expect($calls->count())->toBe(0);
});

test('a card the customer removed is never charged', function () {
    autoChargeOn();
    $calls = autoChargeGateway();
    [$invoice, $card] = autoChargeReady();

    $card->delete();

    autoChargeRun();

    // Removing a card at this end does not detach it at the gateway, so the
    // token would very probably still be accepted. That is why this refuses
    // rather than letting the gateway decide.
    expect($calls->count())->toBe(0)
        ->and(Attempt::count())->toBe(0);
});

test('a card the issuer has ended is never charged', function () {
    autoChargeOn();
    $calls = autoChargeGateway();
    [$invoice, $card] = autoChargeReady();

    $card->update(['status' => PaymentMethod::STATUS_REQUIRES_UPDATE]);

    autoChargeRun();

    expect($calls->count())->toBe(0);
});

test('a client who is not trading is not charged', function () {
    autoChargeOn();
    $calls = autoChargeGateway();
    [$invoice] = autoChargeReady();

    $invoice->client->update(['status' => ClientStatus::Closed->value]);

    autoChargeRun();

    expect($calls->count())->toBe(0);
});

test('a gateway the operator has switched off charges nothing', function () {
    autoChargeOn();
    $calls = autoChargeGateway();
    autoChargeReady();

    GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => 'active'], ['value' => '0']);

    autoChargeRun();

    expect($calls->count())->toBe(0);
});

test('an invoice is not charged before the window the operator set', function () {
    autoChargeOn(['AutoChargeDaysBefore' => 3]);
    $calls = autoChargeGateway();
    [$invoice] = autoChargeReady(100.0, ['due_date' => now()->addDays(10)]);

    autoChargeRun();

    expect($calls->count())->toBe(0);

    // The same invoice, once the operator says a fortnight is fine.
    Setting::set('AutoChargeDaysBefore', '14');
    autoChargeRun();

    expect($calls->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Which card
// ---------------------------------------------------------------------------

test('several stored cards and no default is refused rather than guessed at', function () {
    autoChargeOn();
    $calls = autoChargeGateway();
    [$invoice] = autoChargeReady();
    autoChargeCard($invoice);

    autoChargeRun();

    // Charging the oldest or the newest would be a guess made with somebody's
    // money, and one click on the payment methods page settles it.
    expect($calls->count())->toBe(0)
        ->and(Attempt::count())->toBe(0);
});

test('the default card is the one charged', function () {
    autoChargeOn();
    $calls = autoChargeGateway();
    [$invoice] = autoChargeReady();
    $chosen = autoChargeCard($invoice, ['is_default' => true]);

    autoChargeRun();

    expect($calls[0]['method'])->toBe($chosen->id);
});

test('pay_method_id names the card and nothing else is tried', function () {
    autoChargeOn();
    $calls = autoChargeGateway();
    [$invoice, $first] = autoChargeReady();
    $named = autoChargeCard($invoice);
    autoChargeCard($invoice, ['is_default' => true]);

    // The dormant hook, used as what it is: which stored method pays this
    // invoice. It outranks the default.
    $invoice->update(['pay_method_id' => $named->id]);

    autoChargeRun();

    expect($calls[0]['method'])->toBe($named->id)
        // And it is read, never written: pinning the invoice to a card would
        // stop a replacement card from reopening it.
        ->and($invoice->fresh()->pay_method_id)->toBe($named->id);
});

test('a named card that has been removed does not fall back to another one', function () {
    autoChargeOn();
    $calls = autoChargeGateway();
    [$invoice, $card] = autoChargeReady();
    $spare = autoChargeCard($invoice, ['is_default' => true]);

    $invoice->update(['pay_method_id' => $card->id]);
    $card->delete();

    autoChargeRun();

    expect($calls->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Two runs at once
// ---------------------------------------------------------------------------

test('a second run that starts inside the first charges nothing', function () {
    autoChargeOn();

    // The overlap, made exact: the second run begins while the first is inside
    // the gateway call, which is the window a cron mutex cannot close and an
    // operator running the command by hand goes straight through.
    $calls = autoChargeGateway(function ($invoice, $method, $amount, $call) {
        if ($call === 1) {
            autoChargeRun();
        }

        return ['success' => true, 'status' => 'succeeded', 'transaction_id' => 'pi_'.$invoice->id, 'amount' => $amount];
    });

    [$invoice, $card] = autoChargeReady(100.0);

    autoChargeRun();

    expect($calls->count())->toBe(1)
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(1)
        ->and(autoChargeAttempt($invoice)->attempts)->toBe(1);
});

test('with the arbitrator row gone mid-charge the same overlap charges the card twice', function () {
    autoChargeOn();

    // The control for the test above. Nothing else changes: the row that says
    // "this invoice is being charged" is removed at the moment the overlapping
    // run looks for it, and the card is presented a second time. This is what
    // the unique key on invoice_charge_attempts.invoice_id is buying.
    $calls = autoChargeGateway(function ($invoice, $method, $amount, $call) {
        if ($call === 1) {
            Attempt::where('invoice_id', $invoice->id)->delete();
            autoChargeRun();
        }

        return ['success' => true, 'status' => 'succeeded', 'transaction_id' => 'pi_'.$invoice->id.'_'.$call, 'amount' => $amount];
    });

    [$invoice, $card] = autoChargeReady(100.0);

    autoChargeRun();

    expect($calls->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// Killed with a charge in flight
// ---------------------------------------------------------------------------

test('an attempt that never came back is replayed and the invoice credited once', function () {
    autoChargeOn();
    $calls = autoChargeGateway();
    [$invoice, $card] = autoChargeReady(100.0);

    // Yesterday's run sent the charge and died before it could write down what
    // happened. The money may or may not have moved.
    Attempt::create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $card->id,
        'state' => ChargeAttemptState::InFlight,
        'attempts' => 1,
        'amount' => 100.00,
        'currency' => shop_currency_code(),
        'claimed_at' => now()->subHour(),
    ]);

    autoChargeRun();

    $row = autoChargeAttempt($invoice);

    expect($calls->count())->toBe(1)
        // The same request at the gateway, answered from its own record of the
        // first one - so not a second attempt here either.
        ->and($row->attempts)->toBe(1)
        ->and($row->state)->toBe(ChargeAttemptState::Succeeded)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value)
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(1);
});

test('an abandoned attempt whose payment the webhook already recorded closes without touching the card', function () {
    autoChargeOn();
    $calls = autoChargeGateway();
    [$invoice, $card] = autoChargeReady(100.0);

    Attempt::create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $card->id,
        'state' => ChargeAttemptState::InFlight,
        'attempts' => 1,
        'amount' => 100.00,
        'currency' => shop_currency_code(),
        'claimed_at' => now()->subHour(),
    ]);

    // Stripe delivered payment_intent.succeeded while we were not looking and
    // GatewayWebhookController credited the invoice - the same door, the same
    // (gateway, transaction id) pair.
    app(PaymentService::class)->applyPayment($invoice, 'stripe', 'pi_webhook', 100.0);

    autoChargeRun();

    $row = autoChargeAttempt($invoice);

    expect($calls->count())->toBe(0)
        ->and($row->state)->toBe(ChargeAttemptState::Succeeded)
        ->and($row->last_transaction_id)->toBe('pi_webhook')
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(1);
});

test('an abandoned attempt is handed to a person once a late fee has moved the total', function () {
    autoChargeOn();
    $calls = autoChargeGateway();
    [$invoice, $card] = autoChargeReady(100.0);

    Attempt::create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $card->id,
        'state' => ChargeAttemptState::InFlight,
        'attempts' => 1,
        'amount' => 100.00,
        'currency' => shop_currency_code(),
        'claimed_at' => now()->subHour(),
    ]);

    // pnlcs:apply-late-fees ran between the crash and this morning. The amount
    // is inside the gateway idempotency key, so asking for 110 now would be a
    // brand new request on top of a 100 that may already have gone through.
    $invoice->update(['total' => 110.00]);

    autoChargeRun();

    $row = autoChargeAttempt($invoice);

    expect($calls->count())->toBe(0)
        ->and($row->state)->toBe(ChargeAttemptState::NeedsReview)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value);
});

// ---------------------------------------------------------------------------
// Retries
// ---------------------------------------------------------------------------

test('a retry after a late fee is a new charge for the new amount', function () {
    autoChargeOn();

    $calls = autoChargeGateway(function ($invoice, $method, $amount, $call) {
        if ($call === 1) {
            return ['success' => false, 'status' => 'failed', 'message' => 'Insufficient funds.', 'decline_code' => 'insufficient_funds', 'retryable' => true];
        }

        return ['success' => true, 'status' => 'succeeded', 'transaction_id' => 'pi_'.$invoice->id, 'amount' => $amount];
    });

    [$invoice, $card] = autoChargeReady(100.0);

    autoChargeRun();

    $row = autoChargeAttempt($invoice);
    expect($row->state)->toBe(ChargeAttemptState::Scheduled)
        ->and($row->attempts)->toBe(1)
        ->and($row->next_attempt_at)->not->toBeNull();

    // Three days on, with the late fee applied in between.
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'client_id' => $invoice->client_id,
        'type' => 'LateFee',
        'description' => 'Late Fee',
        'amount' => 10.0,
        'taxed' => false,
    ]);
    $invoice->update(['total' => 110.00]);

    test()->travel(3)->days();
    autoChargeRun();

    $row = autoChargeAttempt($invoice);

    expect($calls->count())->toBe(2)
        ->and($calls[1]['amount'])->toBe(110.0)
        ->and($row->attempts)->toBe(2)
        ->and((float) $row->amount)->toBe(110.0)
        ->and($row->state)->toBe(ChargeAttemptState::Succeeded)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value);
});

test('a retry that is not due yet is not made', function () {
    autoChargeOn();
    $calls = autoChargeGateway(fn () => ['success' => false, 'status' => 'failed', 'message' => 'Insufficient funds.', 'decline_code' => 'insufficient_funds', 'retryable' => true]);
    [$invoice, $card] = autoChargeReady(100.0);

    autoChargeRun();
    test()->travel(1)->days();
    autoChargeRun();

    expect($calls->count())->toBe(1)
        ->and(autoChargeAttempt($invoice)->attempts)->toBe(1);
});

test('a refusal the customer has to act on ends the dunning, a temporary one is scheduled', function (bool $retryable, ChargeAttemptState $state) {
    autoChargeOn();
    $calls = autoChargeGateway(fn () => [
        'success' => false,
        'status' => 'failed',
        'message' => 'Your card was declined.',
        'decline_code' => $retryable ? 'insufficient_funds' : 'lost_card',
        'retryable' => $retryable,
    ]);

    [$invoice, $card] = autoChargeReady(100.0);

    autoChargeRun();

    $row = autoChargeAttempt($invoice);

    expect($row->state)->toBe($state)
        ->and($row->last_decline_code)->toBe($retryable ? 'insufficient_funds' : 'lost_card')
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value)
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(0);

    if ($retryable) {
        expect($row->next_attempt_at)->not->toBeNull();
    } else {
        expect($row->next_attempt_at)->toBeNull();
    }
})->with([
    'temporary' => [true, ChargeAttemptState::Scheduled],
    'final' => [false, ChargeAttemptState::Exhausted],
]);

test('the attempt cap the operator set is the number of times a card is presented', function () {
    autoChargeOn(['AutoChargeMaxAttempts' => 2, 'AutoChargeRetryDays' => 1]);
    $calls = autoChargeGateway(fn () => ['success' => false, 'status' => 'failed', 'message' => 'Insufficient funds.', 'decline_code' => 'insufficient_funds', 'retryable' => true]);
    [$invoice, $card] = autoChargeReady(100.0);

    autoChargeRun();
    test()->travel(1)->days();
    autoChargeRun();
    test()->travel(1)->days();
    autoChargeRun();

    expect($calls->count())->toBe(2)
        ->and(autoChargeAttempt($invoice)->state)->toBe(ChargeAttemptState::Exhausted);
});

// ---------------------------------------------------------------------------
// The bank wants the cardholder
// ---------------------------------------------------------------------------

test('a payment that needs authentication leaves the invoice alone and stops the next run', function () {
    autoChargeOn();
    $calls = autoChargeGateway(fn ($invoice) => [
        'success' => false,
        'status' => 'requires_action',
        'message' => "The cardholder's bank wants them to authenticate this payment.",
        'transaction_id' => 'pi_3ds_'.$invoice->id,
        'decline_code' => 'authentication_required',
        'retryable' => false,
    ]);

    [$invoice, $card] = autoChargeReady(100.0);

    autoChargeRun();

    // No money moved, so nothing is credited and the invoice is exactly where
    // the rest of the billing chain expects to find it.
    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Unpaid->value)
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(0);

    $row = autoChargeAttempt($invoice);
    expect($row->state)->toBe(ChargeAttemptState::ActionRequired)
        // The only place this id is written down: no transaction row exists
        // for a payment that has not happened, and without it the customer
        // cannot be sent back to finish the authentication.
        ->and($row->last_transaction_id)->toBe('pi_3ds_'.$invoice->id)
        ->and($row->next_attempt_at)->toBeNull();

    // A week of mornings later, the issuer has still not been asked again.
    test()->travel(7)->days();
    autoChargeRun();

    expect($calls->count())->toBe(1)
        ->and(autoChargeAttempt($invoice)->attempts)->toBe(1);
});

test('a card the customer replaces reopens an invoice that was waiting on authentication', function () {
    autoChargeOn();
    $calls = autoChargeGateway(function ($invoice, $method, $amount, $call) {
        if ($call === 1) {
            return ['success' => false, 'status' => 'requires_action', 'message' => 'authenticate', 'transaction_id' => 'pi_3ds', 'decline_code' => 'authentication_required', 'retryable' => false];
        }

        return ['success' => true, 'status' => 'succeeded', 'transaction_id' => 'pi_new', 'amount' => $amount];
    });

    [$invoice, $card] = autoChargeReady(100.0);

    autoChargeRun();

    // The customer stores a different card and removes the old one.
    $card->delete();
    $replacement = autoChargeCard($invoice);

    autoChargeRun();

    $row = autoChargeAttempt($invoice);

    expect($calls->count())->toBe(2)
        ->and($calls[1]['method'])->toBe($replacement->id)
        ->and($row->attempts)->toBe(1)
        ->and($row->state)->toBe(ChargeAttemptState::Succeeded)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value);
});

// ---------------------------------------------------------------------------
// A gateway that answers badly
// ---------------------------------------------------------------------------

test('a success the gateway cannot name is not credited', function () {
    autoChargeOn();
    $calls = autoChargeGateway(fn ($invoice, $method, $amount) => [
        'success' => true,
        'status' => 'succeeded',
        'transaction_id' => null,
        'amount' => $amount,
    ]);

    [$invoice, $card] = autoChargeReady(100.0);

    autoChargeRun();

    // PaymentService skips its duplicate check when there is no transaction id,
    // so crediting this would let a replay credit the same money twice and the
    // books would say more arrived than did. An unpaid invoice and a loud log
    // is the safe direction.
    expect(Transaction::where('invoice_id', $invoice->id)->count())->toBe(0)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value)
        ->and(autoChargeAttempt($invoice)->last_message)->toContain('no transaction id');
});

test('a payment is recorded under the same gateway key the webhook would use', function () {
    autoChargeOn();
    $calls = autoChargeGateway();
    $invoice = autoChargeInvoice(100.0);

    // A card row spelled the way a third-party module might spell it. The key
    // matters because PaymentService dedupes on (gateway, transaction id) as
    // strings: recorded as 'Stripe' here and 'stripe' by the webhook, the same
    // payment would be credited twice.
    autoChargeCard($invoice, ['gateway_name' => 'Stripe']);

    autoChargeRun();

    expect($calls->count())->toBe(1)
        ->and(Transaction::where('invoice_id', $invoice->id)->first()->gateway)->toBe('stripe');

    // The webhook arrives afterwards with the same intent, as it always does.
    app(PaymentService::class)->applyPayment($invoice->fresh(), 'stripe', 'pi_'.$invoice->id, 100.0);

    expect(Transaction::where('invoice_id', $invoice->id)->count())->toBe(1);
});

test('what the gateway says it took is what is recorded, not what was asked for', function () {
    autoChargeOn();
    $calls = autoChargeGateway(fn ($invoice) => [
        'success' => true,
        'status' => 'succeeded',
        'transaction_id' => 'pi_partial',
        // An issuer that partially authorised.
        'amount' => 60.0,
    ]);

    [$invoice, $card] = autoChargeReady(100.0);

    autoChargeRun();

    $invoice->refresh();

    expect((float) Transaction::where('invoice_id', $invoice->id)->first()->amount_in)->toBe(60.0)
        ->and($invoice->status)->toBe(InvoiceStatus::PartiallyPaid->value);
});

// ---------------------------------------------------------------------------
// Pacing
// ---------------------------------------------------------------------------

test('it waits between gateway requests, and not before the first', function () {
    autoChargeOn();
    $calls = autoChargeGateway();

    foreach (range(1, 4) as $ignored) {
        autoChargeReady(25.0);
    }

    autoChargeRun();

    expect($calls->count())->toBe(4);

    // Four charges, three waits: the pacing is between requests, so a run of
    // one is not slowed down at all.
    Sleep::assertSleptTimes(3);
    Sleep::assertSequence([
        Sleep::for(AutoChargeService::REQUEST_SPACING_MS)->milliseconds(),
        Sleep::for(AutoChargeService::REQUEST_SPACING_MS)->milliseconds(),
        Sleep::for(AutoChargeService::REQUEST_SPACING_MS)->milliseconds(),
    ]);
});

test('an invoice refused before the gateway costs no wait at all', function () {
    autoChargeOn();
    $calls = autoChargeGateway();

    autoChargeReady(25.0);
    autoChargeReady(25.0);

    // Two more that are thrown out before a gateway is reached.
    [$removed, $card] = autoChargeReady(25.0);
    $card->delete();
    autoChargeInvoice(25.0);

    autoChargeRun();

    expect($calls->count())->toBe(2);
    Sleep::assertSleptTimes(1);
});

test('the pacing is real time, measured rather than asserted', function () {
    // The only test in the file that does not fake the wait. Three charges
    // means two waits of half a second, so a run that really paces itself
    // cannot come back in under a second.
    Sleep::fake(false);

    autoChargeOn();
    $calls = autoChargeGateway();

    foreach (range(1, 3) as $ignored) {
        autoChargeReady(25.0);
    }

    $started = hrtime(true);
    autoChargeRun();
    $elapsed = (hrtime(true) - $started) / 1_000_000_000;

    expect($calls->count())->toBe(3)
        ->and($elapsed)->toBeGreaterThan(0.95)
        ->and($elapsed)->toBeLessThan(10.0);
});

test('the run limit caps how many cards one run presents', function () {
    autoChargeOn();
    $calls = autoChargeGateway();

    foreach (range(1, 5) as $ignored) {
        autoChargeReady(25.0);
    }

    autoChargeRun(['--limit' => 2]);

    expect($calls->count())->toBe(2)
        ->and(Attempt::count())->toBe(2);
});

test('the oldest debt is collected first when a run cannot take them all', function () {
    autoChargeOn();
    $calls = autoChargeGateway();

    [$newest] = autoChargeReady(25.0, ['due_date' => now()->addDay()]);
    [$oldest] = autoChargeReady(25.0, ['due_date' => now()->subDays(20)]);

    autoChargeRun(['--limit' => 1]);

    expect($calls->count())->toBe(1)
        ->and($calls[0]['invoice'])->toBe($oldest->id);
});

// ---------------------------------------------------------------------------
// Looking without touching
// ---------------------------------------------------------------------------

test('a dry run claims nothing and charges nothing', function () {
    autoChargeOn();
    $calls = autoChargeGateway();
    [$invoice, $card] = autoChargeReady(100.0);

    autoChargeRun(['--dry-run' => true]);

    expect($calls->count())->toBe(0)
        ->and(Attempt::count())->toBe(0)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value);

    expect(Artisan::output())->toContain('would charge');
});
