<?php

use App\Enums\ChargeAttemptState;
use App\Enums\ChargeClaim;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Invoice;
use App\Models\InvoiceChargeAttempt;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Support\AutoCharge;
use Illuminate\Database\UniqueConstraintViolationException;

/*
 * The arbitrator that stands between an invoice and a second charge.
 *
 * Nothing charges anything yet. What is tested here is the thing the charger
 * will not be allowed to go around: a worker has to take an invoice before it
 * may present a card, and these are the answers it gets.
 *
 * The cases worth reading twice are the ones about an attempt that was sent and
 * never came back. A cold lease is not permission to charge again; it is a
 * question, and only Stripe's idempotency layer can answer it - the same
 * request repeated inside the retention window is answered from Stripe's record
 * rather than by charging the card a second time. Where that cannot be proven,
 * the correct behaviour is to stop and fetch a person, and every one of those
 * paths is pinned below.
 */

function chargeInvoice(array $attributes = []): Invoice
{
    return Invoice::factory()->create($attributes + ['total' => 50.00, 'status' => 'unpaid']);
}

function storedCard(Invoice $invoice, array $attributes = []): PaymentMethod
{
    return PaymentMethod::create($attributes + [
        'client_id' => $invoice->client_id,
        'gateway_name' => 'stripe',
        'payment_type' => 'cc',
        'remote_token' => 'pm_'.fake()->unique()->numerify('##########'),
        'gateway_customer_id' => 'cus_'.fake()->unique()->numerify('##########'),
        'last_four' => '4242',
        'expiry_date' => '2030-07',
    ]);
}

function attemptRow(Invoice $invoice): InvoiceChargeAttempt
{
    return InvoiceChargeAttempt::forInvoice($invoice) ?? test()->fail('no attempt row for invoice '.$invoice->id);
}

test('the first claim on an invoice takes it and writes down what is being charged', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);
    $now = now();

    expect(InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'usd', 3, $now))->toBe(ChargeClaim::Taken);

    $row = attemptRow($invoice);

    expect($row->state)->toBe(ChargeAttemptState::InFlight)
        ->and($row->attempts)->toBe(1)
        ->and($row->payment_method_id)->toBe($card->id)
        ->and((float) $row->amount)->toBe(50.00)
        // Upper case on the way in: the currency is half of the gateway
        // idempotency key, and 'usd' and 'USD' must not read as two charges.
        ->and($row->currency)->toBe('USD')
        ->and($row->claimed_at)->not->toBeNull()
        ->and($row->next_attempt_at)->toBeNull();
});

test('a second worker arriving while the first is inside the charge is held', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);
    $now = now();

    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now);

    // A minute later, an overlapping cron. The first worker is still talking to
    // the gateway; this is the run that must do nothing at all.
    expect(InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now->copy()->addMinute()))
        ->toBe(ChargeClaim::Held);

    expect(attemptRow($invoice)->attempts)->toBe(1);
});

test('the database itself refuses a second arbitrator row for one invoice', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);

    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD');

    expect(fn () => InvoiceChargeAttempt::create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $card->id,
        'state' => ChargeAttemptState::InFlight,
        'attempts' => 1,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('a retry that has come due is taken and counted', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);
    $now = now();

    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now);
    attemptRow($invoice)->recordFailure('Insufficient funds.', 'insufficient_funds', true, 3, 3, $now);

    expect(InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now->copy()->addDays(3)))
        ->toBe(ChargeClaim::Taken);

    expect(attemptRow($invoice)->attempts)->toBe(2);
});

test('a retry that has not come due is refused', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);
    $now = now();

    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now);
    attemptRow($invoice)->recordFailure('Insufficient funds.', 'insufficient_funds', true, 3, 3, $now);

    expect(InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now->copy()->addDays(2)))
        ->toBe(ChargeClaim::NotDue);

    expect(attemptRow($invoice)->attempts)->toBe(1);
});

test('an invoice that has been paid is never taken again', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);
    $now = now();

    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now);
    attemptRow($invoice)->recordSuccess('pi_paid');

    expect(InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now->copy()->addDays(30)))
        ->toBe(ChargeClaim::Closed);

    $row = attemptRow($invoice);

    expect($row->state)->toBe(ChargeAttemptState::Succeeded)
        ->and($row->last_transaction_id)->toBe('pi_paid')
        ->and($row->attempts)->toBe(1);
});

test('an attempt that never came back is replayed only as the same request', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);
    $now = now();

    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now);

    // The worker died with the charge in flight. An hour later nothing has
    // recorded an outcome, so the lease is cold - but the invoice, the card,
    // the money and the currency are all unchanged, which is what makes the
    // repeat the same request at the gateway rather than a second one.
    $later = $now->copy()->addHour();

    expect(InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $later))
        ->toBe(ChargeClaim::Taken);

    $row = attemptRow($invoice);

    expect($row->state)->toBe(ChargeAttemptState::InFlight)
        // Not a second attempt at the gateway, so not a second one here.
        ->and($row->attempts)->toBe(1)
        ->and($row->claimed_at->timestamp)->toBe($later->timestamp);
});

test('an attempt that never came back is handed to a person once a late fee has changed the total', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);
    $now = now();

    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now);

    // apply-late-fees ran between the crash and this morning. The amount is
    // part of the gateway idempotency key, so charging 55 now would be a brand
    // new request - on top of a 50 that may already have gone through.
    // Parked rather than Closed: the worker whose write landed is the one that
    // has just left an invoice for a person, and the charger announces that
    // instead of counting it beside the invoices it finished with.
    expect(InvoiceChargeAttempt::claim($invoice, $card, 55.00, 'USD', 3, $now->copy()->addHour()))
        ->toBe(ChargeClaim::Parked);

    $row = attemptRow($invoice);

    expect($row->state)->toBe(ChargeAttemptState::NeedsReview)
        ->and((float) $row->amount)->toBe(50.00)
        ->and($row->last_message)->toContain('never recorded');
});

test('an attempt that never came back is handed to a person once the gateway has forgotten the key', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);
    $now = now();

    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now);

    // Stripe keeps an idempotency key for at least twenty-four hours. Past the
    // window a repeat is executed as a new request, so the replay stops being
    // provable at exactly the moment it stops being safe.
    $tooLate = $now->copy()->addSeconds(InvoiceChargeAttempt::REPLAY_WINDOW_SECONDS + 1);

    expect(InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $tooLate))
        ->toBe(ChargeClaim::Parked);

    expect(attemptRow($invoice)->state)->toBe(ChargeAttemptState::NeedsReview);
});

test('a replay refreshes the lease and never the deadline it is measured against', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);
    $now = now();

    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now);

    $firstSent = attemptRow($invoice)->first_sent_at;

    // Two sweeps at the cadence routes/console.php actually runs. Each one
    // finds a cold lease, proves the repeat is the same request, and takes it.
    foreach ([15, 30] as $minutes) {
        $at = $now->copy()->addMinutes($minutes);

        expect(InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $at))->toBe(ChargeClaim::Taken);

        $row = attemptRow($invoice);

        // THE TWO CLOCKS PART COMPANY, WHICH IS THE WHOLE FIX. claimed_at is
        // the lease and has to move, because moving it is what makes two
        // rescuers produce one winner. first_sent_at is when Stripe's
        // retention started running and must not move, because moving it
        // postpones the deadline by fifteen minutes every fifteen minutes —
        // which is how one charge reached the gateway 289 times.
        expect($row->claimed_at->timestamp)->toBe($at->timestamp)
            ->and($row->first_sent_at->timestamp)->toBe($firstSent->timestamp)
            ->and($row->replays)->toBe((int) ($minutes / 15))
            // Still one request at the gateway, so still one attempt here.
            ->and($row->attempts)->toBe(1);
    }
});

test('an attempt the gateway never answers is handed to a person after a fixed number of replays', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);
    $now = now();

    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now);

    foreach (range(1, InvoiceChargeAttempt::MAX_REPLAYS) as $replay) {
        expect(InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now->copy()->addMinutes(15 * $replay)))
            ->toBe(ChargeClaim::Taken);
    }

    // Still well inside the twenty-three-hour window, so the deadline is not
    // what stops this: the gateway has simply been asked four times over an
    // hour and has said nothing four times, and going on asking it is not a
    // plan. Parked rather than quietly left alone — a charge whose fate nobody
    // knows is exactly what needs_review is for, and a row that merely stopped
    // being replayed would be the silent hole this feature has closed twice.
    $at = $now->copy()->addMinutes(15 * (InvoiceChargeAttempt::MAX_REPLAYS + 1));

    expect(InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $at))->toBe(ChargeClaim::Parked);

    $row = attemptRow($invoice);

    expect($row->state)->toBe(ChargeAttemptState::NeedsReview)
        ->and($row->replays)->toBe(InvoiceChargeAttempt::MAX_REPLAYS)
        ->and($row->claimed_at)->toBeNull()
        ->and($row->last_message)->toContain('never recorded');
});

test('a genuinely new attempt on a reused row gets its own deadline and its own replays', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);
    $now = now();

    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now);

    // A sweep replayed it an hour later and the gateway finally answered — with
    // an ordinary retryable decline, so the row is scheduled for another
    // morning.
    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now->copy()->addHour());

    expect(attemptRow($invoice)->replays)->toBe(1);

    attemptRow($invoice)->recordFailure('Insufficient funds.', 'insufficient_funds', true, 3, 3, $now->copy()->addHour());

    expect(attemptRow($invoice)->state)->toBe(ChargeAttemptState::Scheduled);

    // Four days on, the retry comes due and the row is re-taken. This is a NEW
    // request at the gateway: the key from four days ago was pruned three days
    // ago, so nothing about the old attempt constrains this one.
    $second = $now->copy()->addDays(4);

    expect(InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $second))->toBe(ChargeClaim::Taken);

    $row = attemptRow($invoice);

    expect($row->attempts)->toBe(2)
        ->and($row->replays)->toBe(0)
        ->and($row->first_sent_at->timestamp)->toBe($second->timestamp);

    // AND THE POINT OF RESETTING IT: this attempt crashes too, and its rescue
    // an hour later is provably safe and must be allowed. Carry the first
    // attempt's deadline forward — which is what created_at would have done,
    // and what makes created_at unusable as the anchor — and this is refused,
    // the invoice goes to a person, and money the shop could have collected
    // safely is left uncollected.
    expect(InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $second->copy()->addHour()))
        ->toBe(ChargeClaim::Taken);

    expect(attemptRow($invoice)->state)->toBe(ChargeAttemptState::InFlight)
        ->and(attemptRow($invoice)->replays)->toBe(1);
});

test('a card the customer has replaced starts the deadline again', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);
    $now = now();

    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now);
    attemptRow($invoice)->recordFailure('Expired card.', 'expired_card', false, 3, 3, $now);

    $replacement = storedCard($invoice);
    $later = $now->copy()->addDays(9);

    expect(InvoiceChargeAttempt::claim($invoice, $replacement, 50.00, 'USD', 3, $later))->toBe(ChargeClaim::Taken);

    // A different card is a different idempotency key, so the gateway has never
    // seen this request. Its deadline starts now, not nine days ago.
    expect(attemptRow($invoice)->first_sent_at->timestamp)->toBe($later->timestamp)
        ->and(attemptRow($invoice)->replays)->toBe(0);
});

test('an attempt that never came back is handed to a person when the card has changed', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);
    $replacement = storedCard($invoice);
    $now = now();

    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now);

    // A different card is a different idempotency key, so this would be a
    // second charge - and the first one's fate is still unknown.
    expect(InvoiceChargeAttempt::claim($invoice, $replacement, 50.00, 'USD', 3, $now->copy()->addHour()))
        ->toBe(ChargeClaim::Parked);

    expect(attemptRow($invoice)->state)->toBe(ChargeAttemptState::NeedsReview);
});

test('an invoice waiting on a person is never taken again, not even with a new card', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);
    $replacement = storedCard($invoice);
    $now = now();

    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now);
    InvoiceChargeAttempt::claim($invoice, $card, 55.00, 'USD', 3, $now->copy()->addHour());

    expect(attemptRow($invoice)->state)->toBe(ChargeAttemptState::NeedsReview);

    expect(InvoiceChargeAttempt::claim($invoice, $replacement, 55.00, 'USD', 3, $now->copy()->addDays(7)))
        ->toBe(ChargeClaim::Closed);

    expect(attemptRow($invoice)->state)->toBe(ChargeAttemptState::NeedsReview);
});

test('a card the customer has replaced reopens an invoice we had given up on', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);
    $now = now();

    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 1, $now);
    attemptRow($invoice)->recordFailure('Your card has expired.', 'expired_card', false, 1, 3, $now);

    expect(attemptRow($invoice)->state)->toBe(ChargeAttemptState::Exhausted);

    // The same dead card gets nothing.
    expect(InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 1, $now->copy()->addDay()))
        ->toBe(ChargeClaim::Closed);

    $replacement = storedCard($invoice);

    expect(InvoiceChargeAttempt::claim($invoice, $replacement, 50.00, 'USD', 1, $now->copy()->addDay()))
        ->toBe(ChargeClaim::Taken);

    $row = attemptRow($invoice);

    expect($row->state)->toBe(ChargeAttemptState::InFlight)
        ->and($row->payment_method_id)->toBe($replacement->id)
        // The old card's refusals belonged to the old card.
        ->and($row->attempts)->toBe(1);
});

test('a card the customer has replaced does not wait out the old card backoff', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);
    $now = now();

    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now);
    attemptRow($invoice)->recordFailure('Insufficient funds.', 'insufficient_funds', true, 3, 3, $now);

    // The scheduled retry is still two days out, but that schedule was the old
    // card's. A customer who has just fixed their payment details should not be
    // made to wait for a backoff that belonged to the card they replaced.
    $replacement = storedCard($invoice);

    expect(InvoiceChargeAttempt::claim($invoice, $replacement, 50.00, 'USD', 3, $now->copy()->addDay()))
        ->toBe(ChargeClaim::Taken);

    $row = attemptRow($invoice);

    expect($row->payment_method_id)->toBe($replacement->id)
        ->and($row->attempts)->toBe(1)
        ->and($row->next_attempt_at)->toBeNull();
});

test('a card the customer has replaced does not reopen an invoice that was paid', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);
    $now = now();

    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now);
    attemptRow($invoice)->recordSuccess('pi_paid');

    $replacement = storedCard($invoice);

    expect(InvoiceChargeAttempt::claim($invoice, $replacement, 50.00, 'USD', 3, $now->copy()->addDay()))
        ->toBe(ChargeClaim::Closed);

    expect(attemptRow($invoice)->state)->toBe(ChargeAttemptState::Succeeded);
});

test('a card the customer has replaced reopens an invoice that was waiting on an authentication', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);
    $now = now();

    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now);
    attemptRow($invoice)->recordActionRequired('pi_3ds', 'The cardholder must authenticate.', 'authentication_required');

    $row = attemptRow($invoice);

    expect($row->state)->toBe(ChargeAttemptState::ActionRequired)
        ->and($row->last_transaction_id)->toBe('pi_3ds')
        ->and($row->next_attempt_at)->toBeNull();

    // Nothing unattended satisfies an authentication...
    expect(InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now->copy()->addDays(3)))
        ->toBe(ChargeClaim::Closed);

    // ...but a different card is a different question.
    expect(InvoiceChargeAttempt::claim($invoice, storedCard($invoice), 50.00, 'USD', 3, $now->copy()->addDays(3)))
        ->toBe(ChargeClaim::Taken);
});

test('a fresh attempt carries none of the previous attempt verdict', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);
    $now = now();

    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now);
    attemptRow($invoice)->recordActionRequired('pi_old_3ds', 'Authenticate please.', 'authentication_required');

    $replacement = storedCard($invoice);

    expect(InvoiceChargeAttempt::claim($invoice, $replacement, 50.00, 'USD', 3, $now->copy()->addDay()))
        ->toBe(ChargeClaim::Taken);

    $row = attemptRow($invoice);

    // The old intent belongs to a card the customer has replaced. Offering it
    // to them as the payment to go and confirm sends them to a charge that can
    // never complete.
    expect($row->last_transaction_id)->toBeNull()
        ->and($row->last_decline_code)->toBeNull()
        ->and($row->last_message)->toBeNull();
});

test('a decline that will not change its mind ends the attempts', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);
    $now = now();

    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 5, $now);
    attemptRow($invoice)->recordFailure('Your card was declined.', 'do_not_honor', false, 5, 3, $now);

    $row = attemptRow($invoice);

    expect($row->state)->toBe(ChargeAttemptState::Exhausted)
        ->and($row->next_attempt_at)->toBeNull()
        ->and($row->last_decline_code)->toBe('do_not_honor')
        ->and($row->claimed_at)->toBeNull();
});

test('a retryable decline is scheduled for the interval the operator set', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);
    $now = now();

    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now);
    attemptRow($invoice)->recordFailure('Insufficient funds.', 'insufficient_funds', true, 3, 4, $now);

    $row = attemptRow($invoice);

    // The start of the fourth day, not the clock time of this refusal. What
    // reads the column is a once-a-day job and the moment an outcome is written
    // is always later in a run than the moment a claim is evaluated, so a
    // clock-time schedule is missed by a second or two and slips a whole day.
    // InvoiceChargeAttempt::nextAttemptMoment() has the full reasoning.
    expect($row->state)->toBe(ChargeAttemptState::Scheduled)
        ->and($row->next_attempt_at->timestamp)->toBe($now->copy()->addDays(4)->startOfDay()->timestamp);
});

test('the last attempt allowed ends the attempts even when the decline was retryable', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);
    $now = now();

    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 1, $now);
    attemptRow($invoice)->recordFailure('Insufficient funds.', 'insufficient_funds', true, 1, 3, $now);

    expect(attemptRow($invoice)->state)->toBe(ChargeAttemptState::Exhausted);
});

test('lowering the attempt cap ends the invoices that were scheduled under the old one', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);
    $now = now();

    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 5, $now);
    attemptRow($invoice)->recordFailure('Insufficient funds.', 'insufficient_funds', true, 5, 3, $now);

    expect(attemptRow($invoice)->state)->toBe(ChargeAttemptState::Scheduled);

    // The operator has since decided one attempt is enough. The row scheduled
    // under the old cap must not outlive the decision.
    expect(InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 1, $now->copy()->addDays(3)))
        ->toBe(ChargeClaim::Closed);

    $row = attemptRow($invoice);

    expect($row->state)->toBe(ChargeAttemptState::Exhausted)
        ->and($row->next_attempt_at)->toBeNull();
});

test('a retry interval shorter than the gateway remembers is not allowed', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);
    $now = now();

    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD', 3, $now);
    // Zero days would put the next attempt inside Stripe's idempotency window,
    // where the repeat is answered from its record and the card is never asked.
    attemptRow($invoice)->recordFailure('Insufficient funds.', 'insufficient_funds', true, 3, 0, $now);

    $scheduled = attemptRow($invoice)->next_attempt_at;

    // Clamped to the minimum, rounded to a day boundary so the daily run
    // catches it, and then pushed clear of the window if that boundary still
    // fell inside it — which at an interval of one day it always does, because
    // midnight is less than twenty-four hours from any time of day.
    expect($scheduled->timestamp)->toBeGreaterThanOrEqual($now->copy()->addDay()->timestamp)
        ->and($scheduled->toTimeString())->toBe('00:00:00')
        ->and($scheduled->timestamp)->toBe($now->copy()->addDays(2)->startOfDay()->timestamp);
});

test('the due scope offers only the rows whose time has come', function () {
    $now = now();

    $due = chargeInvoice();
    InvoiceChargeAttempt::claim($due, storedCard($due), 50.00, 'USD', 3, $now);
    attemptRow($due)->recordFailure('Insufficient funds.', 'insufficient_funds', true, 3, 3, $now);

    $waiting = chargeInvoice();
    InvoiceChargeAttempt::claim($waiting, storedCard($waiting), 50.00, 'USD', 3, $now);
    attemptRow($waiting)->recordFailure('Insufficient funds.', 'insufficient_funds', true, 3, 10, $now);

    $paid = chargeInvoice();
    InvoiceChargeAttempt::claim($paid, storedCard($paid), 50.00, 'USD', 3, $now);
    attemptRow($paid)->recordSuccess('pi_paid');

    $inFlight = chargeInvoice();
    InvoiceChargeAttempt::claim($inFlight, storedCard($inFlight), 50.00, 'USD', 3, $now);

    $ids = InvoiceChargeAttempt::due($now->copy()->addDays(4))->pluck('invoice_id')->all();

    expect($ids)->toBe([$due->id]);
});

test('the stuck scope finds the attempts that never came back', function () {
    $now = now();

    $abandoned = chargeInvoice();
    InvoiceChargeAttempt::claim($abandoned, storedCard($abandoned), 50.00, 'USD', 3, $now);

    $working = chargeInvoice();
    InvoiceChargeAttempt::claim($working, storedCard($working), 50.00, 'USD', 3, $now->copy()->addHour());

    $ids = InvoiceChargeAttempt::stuck($now->copy()->addSeconds(InvoiceChargeAttempt::LEASE_SECONDS))
        ->pluck('invoice_id')->all();

    expect($ids)->toBe([$abandoned->id]);
});

test('the gateway message is cut to the width of the column', function () {
    $invoice = chargeInvoice();
    $card = storedCard($invoice);

    InvoiceChargeAttempt::claim($invoice, $card, 50.00, 'USD');
    attemptRow($invoice)->recordFailure(str_repeat('x', 900), 'generic_decline', false);

    expect(mb_strlen(attemptRow($invoice)->last_message))->toBe(500);
});

test('only one of the claim outcomes lets a card be charged', function () {
    // The charger reads this and nothing else before it presents a card, the
    // way StripeModule already reads GatewayEventClaim::mayProceed().
    expect(ChargeClaim::Taken->mayProceed())->toBeTrue()
        ->and(ChargeClaim::Held->mayProceed())->toBeFalse()
        ->and(ChargeClaim::NotDue->mayProceed())->toBeFalse()
        ->and(ChargeClaim::Closed->mayProceed())->toBeFalse()
        // Parked is Closed with a person attached to it. It must be just as
        // incapable of reaching a gateway.
        ->and(ChargeClaim::Parked->mayProceed())->toBeFalse();
});

/*
 * The switches. Off is the only default that can be shipped to a host who has
 * never heard of this feature.
 */

test('automatic payment is off until an operator turns it on', function () {
    expect(AutoCharge::enabled())->toBeFalse()
        ->and(AutoCharge::daysBeforeDue())->toBe(3)
        ->and(AutoCharge::maxAttempts())->toBe(3)
        ->and(AutoCharge::retryDays())->toBe(3);
});

test('the settings that cannot sensibly be zero are floored', function () {
    Setting::set('AutoChargeMaxAttempts', '0');
    Setting::set('AutoChargeRetryDays', '0');
    Setting::set('AutoChargeDaysBefore', '-5');

    expect(AutoCharge::maxAttempts())->toBe(1)
        ->and(AutoCharge::retryDays())->toBe(AutoCharge::MINIMUM_RETRY_DAYS)
        ->and(AutoCharge::daysBeforeDue())->toBe(0);
});

test('the operator can switch automatic payment on and off from the settings screen', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    $this->actingAs($admin, 'admin')->post(route('admin.settings.general.update'), [
        'CompanyName' => 'Test Co',
        'AutoChargeEnabled' => '1',
        'AutoChargeDaysBefore' => '5',
        'AutoChargeMaxAttempts' => '4',
        'AutoChargeRetryDays' => '2',
    ])->assertRedirect();

    expect(AutoCharge::enabled())->toBeTrue()
        ->and(AutoCharge::daysBeforeDue())->toBe(5)
        ->and(AutoCharge::maxAttempts())->toBe(4)
        ->and(AutoCharge::retryDays())->toBe(2);

    // Clearing the box posts the hidden '0' that sits in front of it, so the
    // form states the switch rather than leaving it to be inferred from an
    // absence - which is what another screen posting to this same endpoint
    // looks like.
    $this->actingAs($admin, 'admin')->post(route('admin.settings.general.update'), [
        'CompanyName' => 'Test Co',
        'AutoChargeEnabled' => '0',
    ])->assertRedirect();

    expect(AutoCharge::enabled())->toBeFalse();
});

test('a screen that does not carry the switch cannot turn it off', function () {
    Setting::set('AutoChargeEnabled', '1');

    // The AI section of the languages screen posts to the same endpoint and
    // carries nothing else. It has no opinion about automatic payment, and
    // saving it must not be read as one.
    $this->actingAs(
        Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]),
        'admin'
    )->post(route('admin.settings.general.update'), ['OpenAIModel' => 'gpt-4o-mini'])->assertRedirect();

    expect(AutoCharge::enabled())->toBeTrue();
});

test('the settings screen offers the automatic payment fields', function () {
    $html = $this->actingAs(
        Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]),
        'admin'
    )->get(route('admin.settings.general'))->assertOk()->getContent();

    expect($html)->toContain('name="AutoChargeEnabled"')
        ->toContain('name="AutoChargeDaysBefore"')
        ->toContain('name="AutoChargeMaxAttempts"')
        ->toContain('name="AutoChargeRetryDays"')
        // The hidden partner of the checkbox, without which a cleared box
        // vanishes from the request instead of posting a value.
        ->toContain('<input type="hidden" name="AutoChargeEnabled" value="0">');
});
