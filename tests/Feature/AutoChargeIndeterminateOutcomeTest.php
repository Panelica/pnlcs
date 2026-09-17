<?php

use App\Enums\ChargeAttemptState;
use App\Enums\InvoiceStatus;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\InvoiceChargeAttempt as Attempt;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/*
 * B-ADV-2. THE GATEWAY HAS A BAD MINUTE, AND THE CUSTOMER IS DEBITED TWICE.
 *
 * Every other file in this family fakes the gateway module. This one does not:
 * it drives the real StripeModule through the real AutoChargeService on the
 * real crontab, with only the wire faked, because the defect lived in the seam
 * between them. StripeModule classified an HTTP 5xx as an ordinary retryable
 * decline; AutoChargeService believed it and scheduled a retry three days out;
 * InvoiceChargeAttempt::nextAttemptMoment puts that retry outside twenty-four
 * hours ON PURPOSE, so that an ordinary decline is retried as a NEW request
 * rather than answered out of Stripe's saved copy. Each half is reasonable. The
 * two together send the identical request, under the identical idempotency key,
 * seventy-two hours after Stripe stopped holding that key — "We generate a new
 * request if a key is reused after the original is pruned"
 * (https://docs.stripe.com/api/idempotent_requests) — against a first charge
 * Stripe's own documentation forbids us to assume failed: "You should treat the
 * result of a 500 request as indeterminate" (https://docs.stripe.com/
 * error-low-level, both fetched 2026-09-17).
 *
 * WHICH IS WHY TIME IS WALKED HERE AND NEVER JUMPED. A test that travels three
 * days and then runs the charger once proves nothing about a defect that is
 * made of the runs in between: the 06:45 daily that finds the scheduled row,
 * and the fifteen-minute rescue sweep that either brings the row to rest inside
 * the window or does not. The loop below is routes/console.php:22 and :47 as
 * they are actually written, tick by tick, for longer than three days.
 */

beforeEach(function () {
    Sleep::fake();
});

afterEach(function () {
    Sleep::fake(false);
});

/** The feature on, and Stripe switched on with the keys it authenticates with. */
function indeterminateShopOn(): void
{
    Setting::set('AutoChargeEnabled', '1');
    Setting::set('SystemEmailAddress', 'ops@example.test');

    foreach (['active' => '1', 'publishable_key' => 'pk_test_x', 'secret_key' => 'sk_test_x'] as $setting => $value) {
        GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => $setting], ['value' => $value]);
    }
}

/** A due invoice with one stored Stripe card. @return array{0: Invoice, 1: PaymentMethod} */
function indeterminateInvoice(float $total): array
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
 * routes/console.php:22 registers the daily collection at 06:45 and :47
 * registers `pnlcs:auto-charge --rescue` everyFifteenMinutes(), in that order,
 * which is the order ScheduleRunCommand walks due events in — so on the tick
 * they share, the daily goes first.
 */
function indeterminateCrontab(object $case, int $hours): void
{
    foreach (range(1, $hours * 4) as $ignored) {
        $case->travel(15)->minutes();

        if (now()->format('H:i') === '06:45') {
            Artisan::call('pnlcs:auto-charge');
        }

        Artisan::call('pnlcs:auto-charge', ['--rescue' => true]);
    }
}

test('a gateway that answers 5xx is never presented the same charge after its idempotency key has been pruned', function () {
    indeterminateShopOn();

    // Every outbound charge, with the moment it went out. What must not happen
    // is not "too many calls" but "a call after Stripe stopped holding the key",
    // and only the timestamps can tell those apart.
    $sent = [];

    Http::fake(function () use (&$sent) {
        $sent[] = now()->copy();

        // A production incident at Stripe, in the shape their API returns it.
        return Http::response(['error' => ['type' => 'api_error', 'message' => 'An unexpected error occurred.']], 500);
    });

    // 06:45 is the hour the collection actually runs at, and starting on it is
    // what makes the daily runs below land on it too.
    $this->travelTo(Carbon::parse('2026-10-05 06:45:00'));

    [$invoice] = indeterminateInvoice(100.0);

    $operatorEmails = 0;
    Illuminate\Support\Facades\Event::listen(Illuminate\Mail\Events\MessageSending::class, function () use (&$operatorEmails) { $operatorEmails++; });

    Artisan::call('pnlcs:auto-charge');

    expect($sent)->toHaveCount(1);

    $firstSend = $sent[0]->copy();

    // SEVENTY-THREE HOURS, WALKED. Past the lease, past the replay window, past
    // Stripe's twenty-four-hour retention, and past the three-day retry the
    // defect scheduled — 292 rescue sweeps and three daily collections.
    indeterminateCrontab($this, 73);

    $row = Attempt::forInvoice($invoice);

    $afterThePrune = array_values(array_filter(
        $sent,
        fn ($at) => $firstSend->diffInSeconds($at) >= 24 * 3600
    ));

    expect($afterThePrune)->toBeEmpty(
        'a request sent more than 24h after the first is a brand-new charge at Stripe, not a replay'
    )
        // One request and its bounded replays, and nothing else, for ever.
        // With the defect this count is 2: the second is the 06:45 run three
        // days later, sending byte-for-byte the same POST under the same
        // Idempotency-Key, which Stripe answers as a new charge.
        ->and($sent)->toHaveCount(1 + Attempt::MAX_REPLAYS)
        // Every one of them inside the window the replay is provable in,
        // measured from the FIRST send rather than from the lease.
        ->and($firstSend->diffInSeconds(end($sent)))->toBeLessThan(Attempt::REPLAY_WINDOW_SECONDS)
        // A RESTING PLACE, and a person fetched to it. Not scheduled, not
        // in flight, not quietly abandoned.
        ->and($row->state)->toBe(ChargeAttemptState::NeedsReview)
        ->and($row->next_attempt_at)->toBeNull()
        // At the gateway this was one request throughout, and the row agrees.
        ->and($row->attempts)->toBe(1)
        ->and($row->replays)->toBe(Attempt::MAX_REPLAYS)
        // It has left the sweep's queue, so it cannot starve the rows behind it.
        ->and(Attempt::query()->stuck()->count())->toBe(0)
        // Nothing was credited on a maybe, and the customer was never told
        // their card failed — because nobody here knows that it did.
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value)
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(0)
        // One operator email for the one parked invoice, not one every fifteen
        // minutes for three days.
        ->and($operatorEmails)->toBe(1);
});

test('an issuer decline is still retried days later, and is never parked for a person', function () {
    indeterminateShopOn();

    // The control for the test above, on the same clock and the same crontab.
    // A determinate refusal — the issuer answered, nothing was taken — MUST go
    // on behaving exactly as it did: an ordinary retry on the operator's
    // schedule, which is deliberately outside the idempotency window because
    // there the repeat has to reach the bank rather than Stripe's saved copy.
    // A fix that swept real declines into needs_review would stop collecting
    // money the shop is owed and bury every operator in reviews.
    $sent = [];

    Http::fake(function () use (&$sent) {
        $sent[] = now()->copy();

        return Http::response(['error' => [
            'type' => 'card_error',
            'code' => 'card_declined',
            'decline_code' => 'insufficient_funds',
            'message' => 'Your card has insufficient funds.',
        ]], 402);
    });

    $this->travelTo(Carbon::parse('2026-10-05 06:45:00'));

    [$invoice, $card] = indeterminateInvoice(100.0);

    Artisan::call('pnlcs:auto-charge');

    $firstSend = $sent[0]->copy();

    indeterminateCrontab($this, 73);

    $row = Attempt::forInvoice($invoice);

    expect($sent)->toHaveCount(2)
        // Three days later to the minute: AutoCharge::DEFAULT_RETRY_DAYS, taken
        // at the start of the day and collected by the 06:45 run.
        ->and((int) $firstSend->diffInHours($sent[1]))->toBe(72)
        // TWO REQUESTS, NOT ONE REQUEST TWICE. The attempt count moved, which
        // is what the operator's cap and the dunning emails are counted from.
        ->and($row->attempts)->toBe(2)
        ->and($row->replays)->toBe(0)
        ->and($row->state)->toBe(ChargeAttemptState::Scheduled)
        ->and($row->next_attempt_at)->not->toBeNull()
        ->and($row->last_decline_code)->toBe('insufficient_funds')
        // Not a person's problem: this is the dunning cycle working.
        ->and(Attempt::query()->where('state', ChargeAttemptState::NeedsReview->value)->count())->toBe(0)
        // And a card the issuer may honour tomorrow is left alone.
        ->and($card->fresh()->status)->toBe(PaymentMethod::STATUS_ACTIVE);
});

test('an unknown outcome is refused a replay once the key is pruned, with replays still to spare', function () {
    indeterminateShopOn();

    // THE OTHER BOUND, AND THE ONE EVERY TEST IN THIS FAMILY LEAVES TO ITS
    // NEIGHBOUR. At the fifteen-minute cadence MAX_REPLAYS binds after an hour,
    // so a test that walks the real sweep never reaches the deadline at all and
    // passes just as well against a build whose deadline never arrives — which
    // is how InvoiceChargeAttempt.php's first_sent_at anchor came to have no
    // test of its own while being the repair the round was named after.
    //
    // A SPARSE SWEEP IS WHAT SEPARATES THEM, and it is the case MAX_REPLAYS'
    // own docblock argues the deadline exists for: "A sweep that is down for a
    // day, then runs, then is down for another day, then runs, has used two of
    // four replays and sent the second of them against a key Stripe pruned
    // twenty-four hours earlier." Here the scheduler is broken and an operator
    // is running the rescue by hand — once at twenty-two hours, once at
    // twenty-four and a half. The clock is still walked in fifteen-minute steps
    // throughout; it is the crontab that is absent, not the time.
    $sent = [];

    Http::fake(function () use (&$sent) {
        $sent[] = now()->copy();

        return Http::response(['error' => ['type' => 'api_error', 'message' => 'An unexpected error occurred.']], 500);
    });

    $this->travelTo(Carbon::parse('2026-10-05 12:00:00'));

    [$invoice] = indeterminateInvoice(100.0);

    Artisan::call('pnlcs:auto-charge');

    $firstSend = $sent[0]->copy();

    foreach (range(1, 25 * 4) as $tick) {
        $this->travel(15)->minutes();

        $minutesSinceTheFirstSend = $tick * 15;

        if ($minutesSinceTheFirstSend === 22 * 60 || $minutesSinceTheFirstSend === 24 * 60 + 30) {
            Artisan::call('pnlcs:auto-charge', ['--rescue' => true]);
        }
    }

    $row = Attempt::forInvoice($invoice);

    expect($sent)->toHaveCount(2)
        // The one at twenty-two hours is a genuine replay: inside the window,
        // answered out of Stripe's record of the first request, no card asked.
        ->and((int) $firstSend->diffInHours($sent[1]))->toBe(22)
        // AND THE ONE AT TWENTY-FOUR AND A HALF NEVER HAPPENED. Stripe pruned
        // the key half an hour earlier and StripeModule's key carries no time
        // component, so that request would have been a second real debit.
        // Against a deadline read off claimed_at it does happen: the replay at
        // twenty-two hours moved the lease, so the row looks two and a half
        // hours old to the very clock that is meant to stop it.
        ->and(array_values(array_filter(
            $sent,
            fn ($at) => $firstSend->diffInSeconds($at) >= Attempt::REPLAY_WINDOW_SECONDS
        )))->toBeEmpty('nothing may be presented after the idempotency key is pruned')
        ->and($row->state)->toBe(ChargeAttemptState::NeedsReview)
        // WITH THREE REPLAYS UNSPENT, which is the whole point: the cap did not
        // stop this and could not have. Only the deadline did.
        ->and($row->replays)->toBe(1)
        ->and($row->replays)->toBeLessThan(Attempt::MAX_REPLAYS)
        ->and($row->attempts)->toBe(1)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value)
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(0);
});
