<?php

use App\Enums\ChargeAttemptState;
use App\Enums\ChargeClaim;
use App\Enums\InvoiceStatus;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\InvoiceChargeAttempt as Attempt;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Modules\Gateways\Stripe\StripeModule;

/*
 * B-REV-2. THE REPLAY PROVED FOUR OF THE KEY'S FIVE INPUTS.
 *
 * A replay is only a replay if it reaches the gateway under the same
 * idempotency key as the request it repeats. StripeModule DERIVED that key —
 * hash_hmac over the invoice, the card, the minor amount and the currency,
 * keyed on config('app.key') — and InvoiceChargeAttempt proved the first four
 * before letting a rescuer send. Nothing proved the fifth, and the fifth can
 * move on its own: Laravel documents APP_KEY rotation, config/app.php supports
 * APP_PREVIOUS_KEYS, and App\Casts\EncryptedValue returns a value it cannot
 * decrypt as-is, so on an install whose gateway_settings row predates that cast
 * the Stripe secret survives a rotation with no rotation machinery at all.
 * Either way the gateway goes on authenticating and nothing signals that the
 * key has changed. The row swears the replay is provably the same request; the
 * request goes out under a key Stripe has never seen; Stripe has no saved
 * result to answer from, so it charges the card. A second debit on top of a
 * first charge whose outcome was unknown, with the row resting at succeeded,
 * the invoice reading paid, one transaction row, and needs_review empty.
 *
 * THE REPAIR IS TO STOP DERIVING IT. The key is written on the attempt row
 * before the first request goes out and handed back unchanged for every repeat
 * of it, so it cannot drift for THIS reason or for any other — not an
 * application key, not an edit to the derivation, not a corrected amount, not
 * an input somebody adds next year. A stored string is stable by construction;
 * a derived one is only as stable as its least stable input.
 *
 * THE WIRE HERE MODELS STRIPE'S KEY RETENTION, like its sibling in
 * AutoChargeReplayAnswerTest: a key still held is answered out of the saved
 * result and touches no card, a key that is unknown or pruned EXECUTES and is
 * counted as a real request at the payment network. What is counted is
 * therefore not POSTs but requests that could have moved somebody's money.
 *
 * Time is walked, never jumped. routes/console.php:22 (06:45 daily) and :47
 * (everyFifteenMinutes rescue), tick by tick.
 */

beforeEach(function () {
    Sleep::fake();
});

afterEach(function () {
    Sleep::fake(false);
});

/** The feature on, and Stripe switched on with the keys it authenticates with. */
function driftShopOn(): void
{
    Setting::set('AutoChargeEnabled', '1');
    Setting::set('SystemEmailAddress', 'ops@example.test');

    foreach (['active' => '1', 'publishable_key' => 'pk_test_x', 'secret_key' => 'sk_test_x'] as $setting => $value) {
        GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => $setting], ['value' => $value]);
    }
}

/** A due invoice with one stored Stripe card. @return array{0: Invoice, 1: PaymentMethod} */
function driftInvoice(float $total): array
{
    $invoice = Invoice::factory()->create([
        'status' => InvoiceStatus::Unpaid->value,
        'subtotal' => $total,
        'total' => $total,
        'due_date' => now()->addDay(),
    ]);

    $card = PaymentMethod::create([
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

    return [$invoice, $card];
}

/**
 * An operator rotating the application key, exactly as Laravel documents it.
 *
 * The already-resolved encrypter keeps the key it was built with, which is what
 * makes this the realistic shape of the incident rather than a contrived one:
 * every encrypted value goes on decrypting (that is what APP_PREVIOUS_KEYS buys
 * an operator, and EncryptedValue's legacy-plaintext fallback buys it for
 * nothing), the Stripe secret still authenticates, the panel is in every
 * visible respect working — and config('app.key'), which is the only thing the
 * derived idempotency key ever read, now answers differently.
 */
function driftRotateTheApplicationKey(): void
{
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
}

/**
 * The crontab, stepped in the intervals it really runs at.
 *
 * routes/console.php registers the daily collection at 06:45 before the
 * everyFifteenMinutes rescue, which is the order ScheduleRunCommand walks due
 * events in, so on the tick they share the daily goes first.
 */
function driftCrontab(object $case, int $hours): void
{
    foreach (range(1, $hours * 4) as $ignored) {
        $case->travel(15)->minutes();

        if (now()->format('H:i') === '06:45') {
            Artisan::call('pnlcs:auto-charge');
        }

        Artisan::call('pnlcs:auto-charge', ['--rescue' => true]);
    }
}

/**
 * A Stripe that remembers idempotency keys the way the documentation says it
 * does, and that hands the test every key it was shown.
 *
 * @param  array<int|string, array<string, mixed>>  $script
 * @param  list<Carbon>  $posts  every POST this end sent
 * @param  list<Carbon>  $real  the ones that reached the payment network
 * @param  list<string|null>  $keys  the Idempotency-Key each POST carried
 */
function driftWire(array $script, array &$posts, array &$real, array &$keys): Closure
{
    $store = [];
    $n = 0;

    return function ($request) use ($script, &$posts, &$real, &$keys, &$store, &$n) {
        if (! str_contains($request->url(), 'payment_intents')) {
            return Http::response([], 200);
        }

        $n++;
        $posts[] = now()->copy();

        $key = $request->header('Idempotency-Key')[0] ?? null;
        $keys[] = $key;
        $answer = $script[$n] ?? $script['default'];

        if ($answer['before_the_api'] ?? false) {
            return Http::response($answer['body'], $answer['status']);
        }

        // "You can remove keys from the system automatically after they're at
        // least 24 hours old", so the model is deliberately generous to Stripe
        // and prunes at exactly twenty-four.
        if ($key !== null && isset($store[$key]) && $store[$key]['at']->diffInSeconds(now()) < 24 * 3600) {
            return Http::response($store[$key]['body'], $store[$key]['status']);
        }

        $real[] = now()->copy();

        if ($key !== null) {
            $store[$key] = ['at' => now()->copy(), 'status' => $answer['status'], 'body' => $answer['body']];
        }

        if ($answer['drop'] ?? false) {
            throw new ConnectionException('cURL error 28: Operation timed out');
        }

        return Http::response($answer['body'], $answer['status']);
    };
}

/** Stripe's server error, in the shape their API returns it. */
function driftServerError(): array
{
    return ['status' => 500, 'body' => ['error' => ['type' => 'api_error', 'message' => 'An unexpected error occurred.']]];
}

/** Money taken. */
function driftSucceeded(): array
{
    return ['status' => 200, 'body' => ['id' => 'pi_'.fake()->numerify('##########'), 'status' => 'succeeded', 'amount_received' => 10000]];
}

/** The issuer's own refusal, which is a definitive statement about the charge. */
function driftDeclined(): array
{
    return ['status' => 402, 'body' => ['error' => [
        'type' => 'card_error',
        'code' => 'card_declined',
        'decline_code' => 'insufficient_funds',
        'message' => 'Your card has insufficient funds.',
    ]]];
}

test('an application key rotated between a send and its replay never asks the card a second time', function () {
    driftShopOn();

    // THE MEASUREMENT THE FINDING WAS MADE ON. The first charge executes at
    // Stripe — the card may well have been taken — and a 500 is what comes back
    // and is cached against the key. The operator rotates APP_KEY in the next
    // minute, for any of the ordinary reasons somebody rotates an application
    // key. Fifteen minutes later the rescue sweep replays, and everything after
    // the first request succeeds so that a second debit shows up as exactly
    // what it is.
    $posts = [];
    $real = [];
    $keys = [];

    Http::fake(driftWire([
        1 => driftServerError(),
        'default' => driftSucceeded(),
    ], $posts, $real, $keys));

    $this->travelTo(Carbon::parse('2026-10-05 06:45:00'));

    [$invoice, $card] = driftInvoice(100.0);

    Artisan::call('pnlcs:auto-charge');

    expect($posts)->toHaveCount(1)->and($real)->toHaveCount(1);

    $firstRequest = $real[0]->copy();

    // THE PREMISE, ASSERTED RATHER THAN ASSUMED. A test that rotated nothing
    // would pass against the defect and prove nothing at all, so the rotation
    // is shown to be material: worked out here the way StripeModule used to
    // work it out, the key this charge would be sent under is a DIFFERENT
    // string after the rotation than before it. That difference is the whole
    // finding, and everything below measures what the product does with it.
    $derivedUnder = fn () => substr(
        hash_hmac('sha256', $invoice->id.'|'.$card->id.'|10000|usd', (string) config('app.key')),
        0,
        40
    );

    $before = $derivedUnder();
    driftRotateTheApplicationKey();

    expect($derivedUnder())->not->toBe($before, 'the rotation must actually move the derived key, or this test proves nothing');

    // Seventy-three hours: past the lease, past the replay window, past Stripe's
    // twenty-four-hour retention, and past the three-day retry an ordinary
    // failure would have scheduled.
    driftCrontab($this, 73);

    $row = Attempt::forInvoice($invoice);

    expect($real)->toHaveCount(1,
        'the card may be asked once for this invoice; a second real request is a second debit'
    )
        // The same fact said the way a bank statement says it.
        ->and(array_values(array_filter($real, fn ($at) => $firstRequest->diffInSeconds($at) >= 24 * 3600)))
        ->toBeEmpty('a request sent after the key is pruned is a brand-new charge, not a replay')
        // ONE NAME THROUGHOUT. Every POST after the first was a repeat, and a
        // repeat under a second name is not a repeat at all.
        ->and(array_values(array_unique($keys)))->toHaveCount(1)
        // A RESTING PLACE, AND A PERSON FETCHED TO IT. The gateway only ever
        // said 500, so what became of the first charge is still unknown and the
        // bounded machinery owns it.
        ->and($row->state)->toBe(ChargeAttemptState::NeedsReview)
        ->and($row->next_attempt_at)->toBeNull()
        ->and($row->attempts)->toBe(1)
        ->and($row->replays)->toBe(Attempt::MAX_REPLAYS)
        ->and(Attempt::query()->stuck()->count())->toBe(0)
        // Nothing credited on a maybe. With the defect the row reads succeeded,
        // the invoice reads paid and one transaction is written — while the
        // customer has been debited twice and only the second one is on record.
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value)
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(0)
        ->and($card->fresh()->status)->toBe(PaymentMethod::STATUS_ACTIVE);
});

test('the key a charge goes out under is written on the row before the card is asked', function () {
    driftShopOn();

    // WHAT MAKES THE TEST ABOVE TRUE FOR A REASON RATHER THAN BY LUCK. The row
    // is the memory: the name is written with the claim, before the POST, so a
    // worker that dies between the two leaves the next one able to say what was
    // sent. A row whose key were minted after the answer came back, or a module
    // that ignored the row and worked its own key out, would both pass a test
    // that only counted requests in the common case and fail the customer in
    // the one that matters.
    $posts = [];
    $real = [];
    $keys = [];

    Http::fake(driftWire(['default' => driftServerError()], $posts, $real, $keys));

    $this->travelTo(Carbon::parse('2026-10-05 06:45:00'));

    [$invoice] = driftInvoice(100.0);

    Artisan::call('pnlcs:auto-charge');

    $row = Attempt::forInvoice($invoice);

    expect($row->state)->toBe(ChargeAttemptState::InFlight)
        ->and($row->idempotency_key)->toBeString()
        ->and($row->idempotency_key)->not->toBe('')
        ->and($keys)->toBe([$row->idempotency_key]);

    $sentAs = $row->idempotency_key;

    // And the replay fifteen minutes later is the same name again, read back
    // rather than worked out — the rotation in between changes nothing, because
    // nothing is recomputed.
    driftRotateTheApplicationKey();
    driftCrontab($this, 1);

    expect($keys)->toHaveCount(5)
        ->and(array_values(array_unique($keys)))->toBe([$sentAs])
        ->and(Attempt::forInvoice($invoice)->idempotency_key)->toBe($sentAs);
});

test('a genuinely new attempt on a reused row is a new request with a new name', function () {
    driftShopOn();

    // THE OTHER DIRECTION, AND THE ONE A STORED KEY COULD GET WRONG. restart()
    // reuses the row for a later dunning attempt, and a later attempt is a NEW
    // operation: the issuer refused on Monday and is being asked again on
    // Thursday. Sent under the name the first attempt carried it would be
    // answered out of the gateway's saved record — Monday's decline copied and
    // handed back as Thursday's, or, inside the retention window, no charge at
    // all — and the money the shop is owed would never be collected.
    $posts = [];
    $real = [];
    $keys = [];

    Http::fake(driftWire(['default' => driftDeclined()], $posts, $real, $keys));

    $this->travelTo(Carbon::parse('2026-10-05 06:45:00'));

    [$invoice] = driftInvoice(100.0);

    Artisan::call('pnlcs:auto-charge');

    $firstAttemptKey = Attempt::forInvoice($invoice)->idempotency_key;

    driftCrontab($this, 73);

    $row = Attempt::forInvoice($invoice);

    expect($keys)->toHaveCount(2)
        // Three days apart: AutoCharge::DEFAULT_RETRY_DAYS, the ordinary
        // dunning schedule.
        ->and((int) $posts[0]->diffInHours($posts[1]))->toBe(72)
        ->and($keys[0])->toBe($firstAttemptKey)
        ->and($keys[1])->not->toBe($keys[0])
        ->and($row->idempotency_key)->toBe($keys[1])
        // TWO ATTEMPTS AND TWO REAL REQUESTS. Both reached the issuer, which is
        // the whole point of a retry.
        ->and($real)->toHaveCount(2)
        ->and($row->attempts)->toBe(2)
        ->and($row->replays)->toBe(0)
        ->and($row->state)->toBe(ChargeAttemptState::Scheduled)
        ->and($row->last_decline_code)->toBe('insufficient_funds');
});

test('a row that cannot name the key its charge was sent under is parked, not replayed', function () {
    driftShopOn();

    // ROWS THAT PREDATE THE COLUMN, and the one answer that is honest about
    // them. What key such a row was sent under cannot be recovered: it was
    // derived from an application key that may since have moved, which is the
    // whole finding. So the replay is refused and a person is fetched — loudly,
    // through the same shout every unprovable replay goes through — rather than
    // a charge being sent under a name nobody can vouch for. It costs one
    // review per in-flight row, once, and the operator's release exit
    // (InvoiceController::releaseChargeReview) leaves the invoice collectable.
    $posts = [];
    $real = [];
    $keys = [];

    Http::fake(driftWire(['default' => driftSucceeded()], $posts, $real, $keys));

    $this->travelTo(Carbon::parse('2026-10-05 06:45:00'));

    [$invoice, $card] = driftInvoice(100.0);

    $row = Attempt::create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $card->id,
        'state' => ChargeAttemptState::InFlight,
        'attempts' => 1,
        'replays' => 0,
        'amount' => 100.0,
        'currency' => 'USD',
        'claimed_at' => now(),
        'first_sent_at' => now(),
    ]);

    // Straight at the column, because this is exactly what a row written before
    // the migration looks like and no code path can produce one now.
    DB::table('invoice_charge_attempts')->where('id', $row->id)->update(['idempotency_key' => null]);

    // Past the lease, so the rescue sweep takes the row.
    driftCrontab($this, 1);

    $parked = Attempt::forInvoice($invoice);

    expect($posts)->toBeEmpty('a charge whose key cannot be named must not be sent again')
        ->and($real)->toBeEmpty()
        ->and($parked->state)->toBe(ChargeAttemptState::NeedsReview)
        ->and($parked->replays)->toBe(0)
        ->and($parked->attempts)->toBe(1)
        ->and($parked->next_attempt_at)->toBeNull()
        ->and($parked->last_message)->toBe(Attempt::UNRECORDED_OUTCOME)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value)
        ->and($card->fresh()->status)->toBe(PaymentMethod::STATUS_ACTIVE);
});

test('a late fee that moves the amount still refuses the replay, stored key or not', function () {
    driftShopOn();

    // THE GUARD THAT MUST SURVIVE THE REPAIR. Storing the key proves the NAME
    // is the same; it says nothing about the parameters, and Stripe's
    // idempotency layer "compares incoming parameters to those of the original
    // request and errors if they're not the same to prevent accidental misuse"
    // (https://docs.stripe.com/api/idempotent_requests). Presenting the stored
    // key with an amount the 07:30 late fee has moved does not replay anything:
    // it is an error that says nothing about the charge in flight. So the row
    // is parked exactly as it was before, and the stored key is left alone
    // rather than reminted — the charge in flight still went out under it.
    $this->travelTo(Carbon::parse('2026-10-05 06:45:00'));

    [$invoice, $card] = driftInvoice(100.0);

    $row = Attempt::create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $card->id,
        'state' => ChargeAttemptState::InFlight,
        'attempts' => 1,
        'replays' => 0,
        'amount' => 100.0,
        'currency' => 'USD',
        'claimed_at' => now(),
        'first_sent_at' => now(),
    ]);

    $sentAs = $row->idempotency_key;

    // The lease has gone cold and the late fee has been applied.
    $this->travel(16)->minutes();

    expect(Attempt::claim($invoice, $card, 110.0, 'USD'))->toBe(ChargeClaim::Parked);

    $parked = Attempt::forInvoice($invoice);

    expect($parked->state)->toBe(ChargeAttemptState::NeedsReview)
        ->and($parked->replays)->toBe(0)
        ->and($parked->idempotency_key)->toBe($sentAs)
        // And the same claim on the same figures is still a replay, so the
        // refusal above is about the amount and not about everything.
        ->and($parked->amount)->toEqual(100.0);
});

test('a replay this end cannot name a key for is never sent', function () {
    // THE MODULE ON ITS OWN. AutoChargeService always carries the row's key, so
    // this is unreachable through it today — which is precisely the kind of
    // unreachable that stops being true when somebody adds a second caller. A
    // caller that says "you have already sent this" and cannot say what it was
    // sent as is asking for a charge nobody can prove is a repeat, and the
    // module has to be right about that whoever is asking.
    driftShopOn();

    Http::fake(['*' => Http::response(['id' => 'pi_never', 'status' => 'succeeded', 'amount_received' => 10000], 200)]);

    [$invoice, $card] = driftInvoice(100.0);

    $module = app(StripeModule::class);

    $blind = $module->chargeStoredMethod($invoice, $card, 100.0, ['replay' => true]);

    expect($blind['outcome_unknown'] ?? false)->toBeTrue()
        ->and($blind['success'])->toBeFalse()
        ->and($blind['retryable'] ?? false)->toBeFalse()
        ->and($blind['transaction_id'] ?? null)->toBeNull();

    Http::assertNothingSent();

    // THE CONTROL, because a module that refused every replay would be safe and
    // useless: told the name, it sends it, and sends it verbatim.
    $named = $module->chargeStoredMethod($invoice, $card, 100.0, [
        'replay' => true,
        'idempotency_key' => 'pnlcs-offsession-0123456789abcdef0123456789abcdef01234567',
    ]);

    expect($named['status'])->toBe('succeeded')
        ->and(collect(Http::recorded())->map(fn ($pair) => $pair[0]->header('Idempotency-Key')[0] ?? null)->all())
        ->toBe(['pnlcs-offsession-0123456789abcdef0123456789abcdef01234567']);
});

test('with the feature switched off nothing moves, whatever the application key does', function () {
    // INVARIANT ZERO, over the state this repair touches. A row left in flight
    // by an operator who then switched the feature off is the one row that
    // could tempt a sweep into doing something clever; with the switch off both
    // commands read it and stop, and rotating the application key underneath
    // them changes nothing because nothing is recomputed and nothing is sent.
    Setting::set('AutoChargeEnabled', '0');

    foreach (['active' => '1', 'publishable_key' => 'pk_test_x', 'secret_key' => 'sk_test_x'] as $setting => $value) {
        GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => $setting], ['value' => $value]);
    }

    Http::fake(['*' => Http::response(['id' => 'pi_never', 'status' => 'succeeded', 'amount_received' => 10000], 200)]);

    $this->travelTo(Carbon::parse('2026-10-05 06:45:00'));

    [$invoice, $card] = driftInvoice(100.0);

    $row = Attempt::create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $card->id,
        'state' => ChargeAttemptState::InFlight,
        'attempts' => 1,
        'replays' => 0,
        'amount' => 100.0,
        'currency' => 'USD',
        'claimed_at' => now(),
        'first_sent_at' => now(),
    ]);

    $sentAs = $row->idempotency_key;

    driftRotateTheApplicationKey();

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    // A full week of the real crontab.
    driftCrontab($this, 168);

    $touched = array_values(array_filter($queries, fn (string $sql) => str_contains($sql, 'invoice_charge_attempts')
        || str_contains($sql, 'invoices')
        || str_contains($sql, 'transactions')
        || str_contains($sql, 'payment_methods')
        || str_contains($sql, 'gateway_settings')));

    $writes = array_values(array_filter($queries, fn (string $sql) => ! str_starts_with(strtolower($sql), 'select')));

    Http::assertNothingSent();

    expect($touched)->toBe([])
        ->and($writes)->toBe([])
        ->and(Attempt::forInvoice($invoice)->idempotency_key)->toBe($sentAs)
        ->and(Attempt::forInvoice($invoice)->state)->toBe(ChargeAttemptState::InFlight)
        ->and(Attempt::forInvoice($invoice)->replays)->toBe(0)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value);
});

test('a replay answered by the saved success credits the invoice once and asks no card again', function () {
    driftShopOn();

    // THE COMMONEST GOOD OUTCOME OF THE WHOLE MACHINERY, AND IT WAS PINNED BY
    // NOTHING END TO END. The charge reaches the payment network and succeeds;
    // the connection dies before we hear; the replay inside the window is
    // answered out of the gateway's saved record. Every other test in this
    // family walks a first send that FAILED, so the 2xx half of
    // StripeModule::answersForTheOriginalRequest() could be deleted with the
    // entire AutoCharge/Stripe family staying green — a regression there would
    // leave the customer's money at the gateway, the invoice never credited,
    // and the row parked for a person with no transaction id to find the
    // payment by. Reported as a gap in the net rather than a defect in the
    // code, and this is the net.
    //
    // It belongs in this file because it is also where the stored key earns its
    // keep: the replay is only answered out of that record because it arrives
    // under the name the first request carried.
    $posts = [];
    $real = [];
    $keys = [];

    Http::fake(driftWire([
        1 => ['status' => 200, 'drop' => true, 'body' => ['id' => 'pi_saved_success', 'status' => 'succeeded', 'amount_received' => 10000]],
        'default' => driftSucceeded(),
    ], $posts, $real, $keys));

    $this->travelTo(Carbon::parse('2026-10-05 06:45:00'));

    [$invoice, $card] = driftInvoice(100.0);

    Artisan::call('pnlcs:auto-charge');

    // Six hours: long enough for the replay at +15 minutes to land and for the
    // row to come to rest, short of any retry that would be a new request.
    driftCrontab($this, 6);

    $row = Attempt::forInvoice($invoice);

    expect($posts)->toHaveCount(2)
        ->and($real)->toHaveCount(1, 'the card was asked once and the replay came out of the saved record')
        ->and(array_values(array_unique($keys)))->toHaveCount(1)
        ->and($row->state)->toBe(ChargeAttemptState::Succeeded)
        ->and($row->last_transaction_id)->toBe('pi_saved_success')
        ->and($row->replays)->toBe(1)
        ->and($row->attempts)->toBe(1)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value)
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(1)
        ->and(Attempt::query()->needsReview()->count())->toBe(0)
        ->and($card->fresh()->status)->toBe(PaymentMethod::STATUS_ACTIVE);
});
