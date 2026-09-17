<?php

use App\Enums\ChargeAttemptState;
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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Modules\Gateways\Stripe\StripeModule;

/*
 * B-REV-1. AN ANSWER THAT NEVER LOOKED AT THE ORIGINAL REQUEST RESOLVES IT.
 *
 * The round before this one made 5xx, 424 and a dropped connection
 * indeterminate and left 429 determinate, on Stripe's own sentence: "a request
 * that's rate limited with a `429` can produce a different result with the same
 * idempotency key because rate limiters run before the API's idempotency layer"
 * (https://docs.stripe.com/error-low-level, fetched 2026-09-17). On a FIRST
 * send that reasoning is exactly right — nothing executed, nothing was cached,
 * and a fresh charge three days later is a first charge.
 *
 * ON A REPLAY IT IS THE WRONG QUESTION. "Nothing executed" is a statement about
 * THIS request. The unknown the whole machinery exists for is what became of
 * the FIRST one, and an answer produced before Stripe ever consulted the record
 * of that first request cannot possibly speak to it. Believed anyway, it turned
 * an unknown outcome into an ordinary scheduled retry — and that retry goes out
 * three days later, forty-eight hours after Stripe pruned the key, as a
 * brand-new charge on top of a charge Stripe's documentation forbids us to
 * assume failed.
 *
 * SO THE WIRE HERE MODELS THE IDEMPOTENCY LAYER, which no other file in this
 * family does. A key Stripe is still holding answers out of the saved result
 * and no card is touched; a key reused past twenty-four hours is a new request
 * that reaches the payment network; and an answer the rate limiter or the
 * authenticator produces is handed back WITHOUT the store being consulted at
 * all, which is the whole of Stripe's sentence turned into code. What is
 * counted is therefore not POSTs but REAL REQUESTS — the ones that could have
 * moved somebody's money.
 *
 * Time is walked, never jumped, for the same reason the sibling file walks it:
 * the defect is made of the runs in between. routes/console.php:22 (06:45
 * daily) and :47 (everyFifteenMinutes rescue), tick by tick, past seventy-two
 * hours.
 */

beforeEach(function () {
    Sleep::fake();
});

afterEach(function () {
    Sleep::fake(false);
});

/** The feature on, and Stripe switched on with the keys it authenticates with. */
function replayShopOn(): void
{
    Setting::set('AutoChargeEnabled', '1');
    Setting::set('SystemEmailAddress', 'ops@example.test');

    foreach (['active' => '1', 'publishable_key' => 'pk_test_x', 'secret_key' => 'sk_test_x'] as $setting => $value) {
        GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => $setting], ['value' => $value]);
    }
}

/** A due invoice with one stored Stripe card. @return array{0: Invoice, 1: PaymentMethod} */
function replayInvoice(float $total): array
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
 * The crontab, stepped in the intervals it really runs at.
 *
 * routes/console.php registers the daily collection at 06:45 before the
 * everyFifteenMinutes rescue, which is the order ScheduleRunCommand walks due
 * events in, so on the tick they share the daily goes first.
 */
function replayCrontab(object $case, int $hours): void
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
 * does, and that refuses some requests before it ever looks at that memory.
 *
 * $script is keyed by the ordinal of the POST it applies to, with 'default' for
 * every later one. Each entry is one of three things:
 *
 *   ['before_the_api' => true, 'status' => n, 'body' => [...]]
 *        the rate limiter or the authenticator answering. The key store is
 *        never read and never written, and nothing at the payment network
 *        happens — which is exactly why this is the answer that must not be
 *        allowed to resolve an unknown outcome.
 *   ['status' => n, 'body' => [...]]
 *        the API method ran. The result is saved against the key ("Stripe's
 *        idempotency works by saving the resulting status code and body of the
 *        first request made for any given idempotency key, regardless of
 *        whether it succeeds or fails", https://docs.stripe.com/api/
 *        idempotent_requests) and this counts as a REAL request.
 *   ['status' => n, 'body' => [...], 'drop' => true]
 *        the same, except our end never hears it: the connection dies after
 *        Stripe has executed and cached. The card was still asked.
 *
 * A POST whose key is still held skips the script entirely and is answered out
 * of the store, because that is what Stripe does. Past twenty-four hours the
 * key is pruned and the same POST is a new request.
 *
 * @param  array<int|string, array<string, mixed>>  $script
 * @param  list<Carbon>  $posts  every POST this end sent
 * @param  list<Carbon>  $real  the ones that reached the payment network
 */
function replayWire(array $script, array &$posts, array &$real): Closure
{
    $store = [];
    $n = 0;

    return function ($request) use ($script, &$posts, &$real, &$store, &$n) {
        if (! str_contains($request->url(), 'payment_intents')) {
            return Http::response([], 200);
        }

        $n++;
        $posts[] = now()->copy();

        $key = $request->header('Idempotency-Key')[0] ?? null;
        $answer = $script[$n] ?? $script['default'];

        if ($answer['before_the_api'] ?? false) {
            return Http::response($answer['body'], $answer['status']);
        }

        // Stripe keeps a key "at least 24 hours"; 24 exactly is the boundary
        // this test is about, so the model is deliberately generous to Stripe
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
function replayServerError(): array
{
    return ['status' => 500, 'body' => ['error' => ['type' => 'api_error', 'message' => 'An unexpected error occurred.']]];
}

/** The rate limiter, which runs before the idempotency layer. */
function replayRateLimit(): array
{
    return [
        'before_the_api' => true,
        'status' => 429,
        'body' => ['error' => ['type' => 'rate_limit_error', 'code' => 'rate_limit', 'message' => 'Too many requests hit the API too quickly.']],
    ];
}

/** Money taken. */
function replaySucceeded(): array
{
    return ['status' => 200, 'body' => ['id' => 'pi_'.fake()->numerify('##########'), 'status' => 'succeeded', 'amount_received' => 10000]];
}

test('a rate limit answering a replay is not allowed to schedule a second real debit', function () {
    replayShopOn();

    $posts = [];
    $real = [];

    // The first charge goes out and Stripe has a bad minute: the API method ran
    // — the card may well have been charged — and a 500 is what comes back and
    // is cached. Fifteen minutes later the rescue sweep replays it under the
    // same key, and THAT one is rate limited: an answer produced before Stripe
    // ever looked at what became of the first request. Every real request after
    // those two succeeds, so that a second debit shows up as exactly what it is.
    Http::fake(replayWire([
        1 => replayServerError(),
        2 => replayRateLimit(),
        'default' => replaySucceeded(),
    ], $posts, $real));

    $this->travelTo(Carbon::parse('2026-10-05 06:45:00'));

    [$invoice] = replayInvoice(100.0);

    Artisan::call('pnlcs:auto-charge');

    expect($posts)->toHaveCount(1)->and($real)->toHaveCount(1);

    $firstRequest = $real[0]->copy();

    // Seventy-three hours: past the lease, past the replay window, past Stripe's
    // twenty-four-hour retention, and past the three-day retry the defect
    // schedules.
    replayCrontab($this, 73);

    $row = Attempt::forInvoice($invoice);

    expect($real)->toHaveCount(1,
        'the card may be asked once for this invoice; a second real request is a second debit'
    )
        // The same fact said in the way the customer's statement says it: no
        // request reached the payment network after Stripe stopped holding the
        // key that would have made a repeat a replay.
        ->and(array_values(array_filter($real, fn ($at) => $firstRequest->diffInSeconds($at) >= 24 * 3600)))
        ->toBeEmpty('a request sent after the key is pruned is a brand-new charge, not a replay')
        // A RESTING PLACE, AND A PERSON FETCHED TO IT. The rate limit told us
        // nothing about the first send, so the first send is still unknown and
        // the bounded replay machinery owns it exactly as it owns a 5xx.
        ->and($row->state)->toBe(ChargeAttemptState::NeedsReview)
        ->and($row->next_attempt_at)->toBeNull()
        ->and($row->attempts)->toBe(1)
        ->and($row->replays)->toBe(Attempt::MAX_REPLAYS)
        ->and(Attempt::query()->stuck()->count())->toBe(0)
        // Nothing credited on a maybe. With the defect the row reads succeeded,
        // the invoice reads paid and one transaction is written — while the
        // customer has been debited twice and only the second one is on record.
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value)
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(0);
});

test('a rate limit on a first send is an ordinary retryable failure and is never parked for a person', function () {
    replayShopOn();

    // THE CONTROL, AND THE REASON THIS IS NOT CLOSED BY MAKING 429
    // INDETERMINATE EVERYWHERE. Nothing has been sent yet, so there is no
    // unknown outcome for a rate limit to fail to speak to: nothing executed,
    // nothing was cached, and the retry three days from now is a first charge.
    // A build that parked this would answer a rate limit by refusing to collect
    // and by putting a human in front of every invoice Stripe throttled.
    $posts = [];
    $real = [];

    Http::fake(replayWire(['default' => replayRateLimit()], $posts, $real));

    $this->travelTo(Carbon::parse('2026-10-05 06:45:00'));

    [$invoice, $card] = replayInvoice(100.0);

    Artisan::call('pnlcs:auto-charge');

    replayCrontab($this, 73);

    $row = Attempt::forInvoice($invoice);

    expect($posts)->toHaveCount(2)
        // AutoCharge::DEFAULT_RETRY_DAYS later, taken at the start of the day
        // and collected by the 06:45 run — the ordinary dunning schedule.
        ->and((int) $posts[0]->diffInHours($posts[1]))->toBe(72)
        // TWO ATTEMPTS, NOT ONE ATTEMPT TWICE. This is what the operator's cap
        // and the dunning emails count.
        ->and($row->attempts)->toBe(2)
        ->and($row->replays)->toBe(0)
        ->and($row->state)->toBe(ChargeAttemptState::Scheduled)
        ->and($row->next_attempt_at)->not->toBeNull()
        ->and(Attempt::query()->where('state', ChargeAttemptState::NeedsReview->value)->count())->toBe(0)
        // And the issuer was never asked, on either morning, which is why
        // neither of them can have taken anything.
        ->and($real)->toBeEmpty()
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value)
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(0)
        ->and($card->fresh()->status)->toBe(PaymentMethod::STATUS_ACTIVE);
});

test('an answer the gateway produced without consulting the original request never resolves a replay', function (array $answer) {
    replayShopOn();

    // 429 WAS FOUND BY ASKING THE QUESTION ONCE; THIS ASKS IT OF THE REST.
    // Every answer below is one Stripe can produce without reading the saved
    // record of the first request — the rate limiter, the authenticator, and
    // the idempotency layer refusing THIS presentation — so not one of them is
    // evidence about what the first request did. Two of them are retryable and
    // schedule a second real debit; two are not and bury the unknown outcome in
    // `exhausted`, where none of the five operator channels fires. Both are the
    // same defect: an answer about this POST closing a question about another.
    $posts = [];
    $real = [];

    Http::fake(replayWire([
        1 => replayServerError(),
        2 => $answer,
        'default' => replaySucceeded(),
    ], $posts, $real));

    $this->travelTo(Carbon::parse('2026-10-05 06:45:00'));

    [$invoice] = replayInvoice(100.0);

    Artisan::call('pnlcs:auto-charge');

    replayCrontab($this, 73);

    $row = Attempt::forInvoice($invoice);

    expect($real)->toHaveCount(1)
        ->and($row->state)->toBe(ChargeAttemptState::NeedsReview)
        ->and($row->attempts)->toBe(1)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value)
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(0);
})->with([
    'rate limited before the idempotency layer' => fn () => replayRateLimit(),
    'the api key was rotated between the send and the replay' => fn () => [
        'before_the_api' => true,
        'status' => 401,
        'body' => ['error' => ['type' => 'invalid_request_error', 'message' => 'Invalid API Key provided: sk_test_***']],
    ],
    'the idempotency layer refusing this presentation outright' => fn () => [
        'before_the_api' => true,
        'status' => 409,
        'body' => ['error' => ['type' => 'invalid_request_error', 'code' => 'idempotency_key_in_use', 'message' => 'The idempotency key provided is currently being used in another request.']],
    ],
    'the same key arriving with different parameters' => fn () => [
        'before_the_api' => true,
        'status' => 400,
        'body' => ['error' => ['type' => 'idempotency_error', 'message' => 'Keys for idempotent requests can only be used with the same parameters they were first used with.']],
    ],
]);

test('a replay answered out of the saved record still resolves the row exactly as it does today', function (string $declineCode, ChargeAttemptState $state, string $cardStatus) {
    replayShopOn();

    // THE OTHER HALF OF THE RULE, AND THE ONE A CARELESS FIX BREAKS. The first
    // charge reached the issuer and the issuer refused it; the connection died
    // before we heard. Stripe cached that refusal, so the replay fifteen
    // minutes later is answered out of the record of the ORIGINAL request —
    // which is a definitive statement about it, and must go on resolving the
    // row the way it always has: a retryable decline back into the dunning
    // cycle, a dead card written down and finished with. A build that parked
    // these would stop collecting from every crashed attempt that really did
    // decline.
    $posts = [];
    $real = [];

    Http::fake(replayWire([
        1 => [
            'status' => 402,
            'drop' => true,
            'body' => ['error' => [
                'type' => 'card_error',
                'code' => 'card_declined',
                'decline_code' => $declineCode,
                'message' => 'Your card was declined.',
            ]],
        ],
        'default' => replaySucceeded(),
    ], $posts, $real));

    $this->travelTo(Carbon::parse('2026-10-05 06:45:00'));

    [$invoice, $card] = replayInvoice(100.0);

    Artisan::call('pnlcs:auto-charge');

    // Six hours: long enough for the replay at +15 minutes to land and for the
    // row to come to rest, and short of the retry that would send a genuinely
    // new request.
    replayCrontab($this, 6);

    $row = Attempt::forInvoice($invoice);

    expect($posts)->toHaveCount(2)
        // The card was asked once. The replay was answered out of Stripe's
        // record and never reached the payment network.
        ->and($real)->toHaveCount(1)
        ->and($row->state)->toBe($state)
        ->and($row->last_decline_code)->toBe($declineCode)
        ->and($row->replays)->toBe(1)
        ->and($row->attempts)->toBe(1)
        ->and(Attempt::query()->where('state', ChargeAttemptState::NeedsReview->value)->count())->toBe(0)
        ->and($card->fresh()->status)->toBe($cardStatus)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value)
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(0);
})->with([
    'a decline worth another morning' => ['insufficient_funds', ChargeAttemptState::Scheduled, PaymentMethod::STATUS_ACTIVE],
    'a card the issuer has finished with' => ['lost_card', ChargeAttemptState::Exhausted, PaymentMethod::STATUS_REQUIRES_UPDATE],
]);

test('a refusal this end made before anything was sent never closes a replay', function (string $break, bool $retryableOnAFirstSend) {
    replayShopOn();

    // THE MODULE ON ITS OWN, because the service cannot reach this and a module
    // has to be right about what its own answers mean whoever is asking.
    // AutoChargeService resolves a chargeable card before it claims anything,
    // so most of these refusals are unreachable on a replay through it today —
    // which is precisely the kind of "unreachable" that stops being true when
    // somebody adds a second caller.
    //
    // Every refusal here is made before the POST, so it is a fact about right
    // now and not an answer about the charge that went out fifteen minutes ago.
    // On a first send that is the whole truth. On a replay, believing it closes
    // an open question with an answer to a different one — and a missing secret
    // key is the expensive version, because it is retryable: a fresh charge
    // three days out, against a key Stripe pruned two days earlier.
    Http::fake(fn () => Http::response(['id' => 'pi_never', 'status' => 'succeeded', 'amount_received' => 5000], 200));

    [$invoice, $card] = replayInvoice(50.0);

    match ($break) {
        'no secret key' => GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => 'secret_key'], ['value' => '']),
        'the customer removed the card' => $card->delete(),
        'the issuer has finished with the card' => $card->update(['status' => PaymentMethod::STATUS_REQUIRES_UPDATE]),
    };

    $card = $card->fresh() ?? PaymentMethod::withTrashed()->find($card->id);

    $module = app(StripeModule::class);

    $firstSend = $module->chargeStoredMethod($invoice, $card, 50.0);
    $replay = $module->chargeStoredMethod($invoice, $card, 50.0, ['replay' => true]);

    expect($firstSend['outcome_unknown'] ?? false)->toBeFalse('nothing was sent and nothing was in flight, so nothing is unknown')
        ->and($firstSend['status'])->toBe('failed')
        ->and($firstSend['retryable'] ?? false)->toBe($retryableOnAFirstSend)
        // And on a replay the same refusal is silence about the charge already
        // sent, so it goes to the machinery that owns unknown outcomes.
        ->and($replay['outcome_unknown'] ?? false)->toBeTrue()
        ->and($replay['retryable'] ?? false)->toBeFalse()
        ->and($replay['transaction_id'] ?? null)->toBeNull();
})->with([
    ['no secret key', true],
    ['the customer removed the card', false],
    ['the issuer has finished with the card', false],
]);
