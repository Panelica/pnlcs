<?php

namespace App\Services;

use App\Contracts\TokenizableGatewayInterface;
use App\Enums\ChargeAttemptState;
use App\Enums\ChargeClaim;
use App\Enums\ClientStatus;
use App\Enums\InvoiceStatus;
use App\Mail\AutoChargeActionRequiredMail;
use App\Mail\AutoChargeFailedMail;
use App\Models\Invoice;
use App\Models\InvoiceChargeAttempt;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\Module\ModuleRegistry;
use App\Support\AutoCharge;
use Illuminate\Support\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;

/**
 * Pay the invoices PNLCS has already raised, with the cards customers have
 * already stored.
 *
 * PNLCS bills perfectly well and collects nothing. InvoiceGenerationService
 * raises the renewals every morning, the overdue marking, the late fee, the
 * suspension and the reminders all follow, and at no point does anything
 * present a card — a customer can store one and it is never used. This is the
 * collection half, and it is the dangerous half: a generator that runs twice
 * writes an embarrassing invoice, a charger that runs twice takes somebody's
 * money twice.
 *
 * WHAT STOPS THAT IS NOT THIS CLASS. Every charge goes through
 * InvoiceChargeAttempt::claim() first, and the unique key on
 * invoice_charge_attempts.invoice_id is what actually arbitrates: a second
 * worker's insert loses on the key rather than on being slower. This class is
 * only allowed to charge what the claim handed it, and it is written so that
 * there is no other way through.
 *
 * ---------------------------------------------------------------------------
 * THE FIVE QUESTIONS, ANSWERED WHERE THEY ARE DECIDED
 * ---------------------------------------------------------------------------
 *
 * 1. TWO CRONS OVERLAP. WHAT STOPS A DOUBLE CHARGE?
 *    Not ->withoutOverlapping() on the schedule entry. That is a cache mutex:
 *    it is per-installation, it expires, and it knows nothing about an operator
 *    typing `php artisan pnlcs:auto-charge` while the cron is mid-run. It is
 *    there because it is free, and it is not the answer.
 *    The answer is the claim. Two workers reaching the same invoice both try to
 *    INSERT the same invoice_id; one wins, the other is told Held and charges
 *    nothing. charge() below cannot reach the gateway without a Taken.
 *    Proven in AutoChargeCommandTest by re-entering this very command from
 *    inside the gateway call — the innermost run sees the in-flight row and
 *    makes no request — and by the control test beside it, which deletes the
 *    row mid-charge and shows the same scenario charging the card twice.
 *
 * 2. KILLED AFTER STRIPE TOOK THE MONEY, BEFORE PNLCS RECORDED IT.
 *    Two answers, and the first one is the one that matters.
 *
 *    FIRST, THE ID IS WRITTEN DOWN BEFORE THE MONEY IS CREDITED. settle()
 *    calls recordCharged() the instant the gateway names a transaction, and
 *    only then asks PaymentService to credit it. A fault between the two — a
 *    deadlock on applyPayment's own lockForUpdate is the ordinary way it
 *    happens on a busy install — therefore leaves PNLCS's own data saying
 *    "this card was charged, under this id, for this much", and rescue() below
 *    credits it on the next sweep without asking the gateway anything. Before
 *    that ordering the money was simply lost: the card was charged, the
 *    invoice stayed unpaid, and only a webhook nobody is obliged to subscribe
 *    to could have rescued it.
 *
 *    SECOND, WHERE EVEN THE ID IS UNKNOWN, THE REPLAY. The row is left
 *    in_flight with a claimed_at that goes cold. The next sweep asks whether
 *    repeating the request would be the SAME request at the gateway — same
 *    invoice, same card, same amount, same currency, still inside Stripe's
 *    idempotency window, and not already asked a fixed number of times without
 *    an answer — and only then hands it back. Stripe answers the repeat from
 *    its record of the first one, so the money does not move again and we learn
 *    what became of it. Where sameness cannot be proven, or where the replays
 *    run out, the row goes to needs_review, no card is touched, and a person is
 *    told: an error log, a line in the run's output, an email, and a count on
 *    the dashboard.
 *
 *    THE WINDOW IS MEASURED FROM THE FIRST SEND AND NOT FROM THE LEASE. They
 *    were the same column, and the replay refreshes the lease — so the window
 *    never closed, the row never rested, and the sweep that exists to rescue it
 *    was the thing keeping it in flight. Three simulated days of the real
 *    schedule: 289 presentations of one charge, everything past the
 *    twenty-fourth hour a second genuine charge against a pruned key.
 *
 *    THAT SWEEP IS NOT THE DAILY RUN. Stripe keeps an idempotency key for "at
 *    least 24 hours" (https://docs.stripe.com/api/idempotent_requests, fetched
 *    2026-09-16), so the window in which a replay is provably the same request
 *    is 23 hours — while consecutive daily runs are 24 hours apart. A daily job
 *    therefore looks at its own wreckage exactly one hour after the only thing
 *    that could rescue it has expired, and every interrupted charge lands in
 *    needs_review. The window cannot move: it is Stripe's retention, not ours,
 *    and stretching it past their pruning is how a replay becomes a second
 *    charge. So the cadence moves. `pnlcs:auto-charge --rescue` runs every
 *    fifteen minutes over in-flight rows only (routes/console.php) — inside the
 *    lease, well inside the window, and ahead of the 07:30 late fee that would
 *    change the amount and make the replay unprovable.
 *
 *    EXACTLY ONE PATH CREDITS. The webhook credits the same payment by the same
 *    (gateway, transaction id) pair, and PaymentService::applyPayment refuses a
 *    pair it already holds inside lockForUpdate — so all of them may run and
 *    only one writes a Transaction. And at least one always does: the webhook,
 *    or the rescue crediting the id we wrote down, or the replay learning it
 *    from Stripe, or reconciliation closing our row against the payment the
 *    webhook already recorded.
 *
 * 3. THE TOTAL CHANGED BETWEEN ATTEMPT 1 AND ATTEMPT 2 (apply-late-fees).
 *    Then attempt 2 is a NEW charge, not a retry, because StripeModule puts the
 *    amount inside the idempotency key. That is right — the customer now owes
 *    more — and the row's amount is rewritten when it is re-taken. The case
 *    that must NOT be treated that way is the crash replay in (2): a changed
 *    amount is exactly what makes a replay unprovable, so it stops and asks for
 *    a person instead of charging 55 on top of a 50 that may already be gone.
 *    The schedule keeps the two apart as well: this runs at 06:45 and the late
 *    fee at 07:30, so a fee never lands between a charge and its own retry.
 *
 * 4. THE CLIENT HAS ACCOUNT CREDIT COVERING PART OF THE INVOICE.
 *    The card is asked for PaymentService::balance(), which is the invoice
 *    total minus what has been recorded against it — and the total is already
 *    net of any credit that has been applied (InvoiceService::applyCredit
 *    lowers total and raises invoices.credit as one transaction). So credit
 *    that has been applied is never charged, and an invoice fully covered by it
 *    has no balance and is not selected at all.
 *    Credit the customer holds but that has NOT been applied to this invoice is
 *    left alone, deliberately. Applying it is InvoiceService's job and it
 *    already happens when the invoice is raised (InvoiceService:87); spending a
 *    balance from the collection path would be a new money behaviour that no
 *    other payment route in PNLCS performs. The Pay Now button charges
 *    amountDue() and so does this — the two paths must not disagree about what
 *    a customer owes.
 *
 * 5. THE CHARGE RETURNS requires_action.
 *    No money was taken, so nothing is credited and the invoice is left exactly
 *    as it was — unpaid or overdue, to be chased by the machinery that already
 *    chases it. The attempt row goes to action_required with the intent id kept
 *    on it, which is the only place that id is written down, and with
 *    next_attempt_at NULL. The next run's claim() answers Closed for that card,
 *    so nothing unattended asks the issuer again: the bank wants the cardholder,
 *    and asking it three more times teaches it to distrust every card we send.
 *    A card the customer replaces reopens it with a clean count.
 */
class AutoChargeService
{
    /**
     * The smallest gap between two gateway requests, in milliseconds.
     *
     * Not a setting. Stripe's published limits are 100 requests per second in
     * live mode, 25 in a sandbox, and 25 per second for an individual endpoint
     * unless stated otherwise — /v1/payment_intents being an individual
     * endpoint (https://docs.stripe.com/rate-limits, fetched 2026-09-16; that
     * page also names "a large volume of closely-spaced requests" as the
     * commonest cause of rate limiting and says to control the rate on the
     * client side). Two per second is an order of magnitude under the tightest
     * of those, which is where an unattended job should sit: nothing is won by
     * collecting the morning's money in nine seconds instead of nine minutes,
     * and a 429 costs a real customer a real day.
     *
     * An operator cannot set this because an operator has no way of knowing it.
     * What they can set — whether to charge at all, how early, how often, how
     * many times — is in AutoCharge, and that list was kept to four on purpose.
     */
    public const REQUEST_SPACING_MS = 500;

    /**
     * How many cards one run may present.
     *
     * The arithmetic is the reason for the number. This is scheduled at 06:45
     * and pnlcs:auto-suspend runs at 07:00, so a run has fifteen minutes before
     * it starts overlapping the job that switches customers off. At two
     * requests a second, a thousand charges take eight minutes and twenty
     * seconds, which fits with room for the slow ones. A backlog larger than
     * that drains over the following mornings rather than running into the
     * suspension job, and `--limit` lets an operator working by hand say
     * otherwise.
     */
    public const DEFAULT_RUN_LIMIT = 1000;

    /**
     * How many abandoned attempts one rescue sweep will finish.
     *
     * Every row this sweep touches reaches a resting place — credited, closed
     * against a payment somebody else recorded, or parked for a person — so a
     * row cannot sit at the front of the queue for ever the way the old
     * reconcile pass allowed (it skipped what it could not close and had no
     * ordering, so five hundred unclosable rows would have starved it
     * permanently).
     *
     * A REPLAY IS NOT ONE OF THOSE PLACES; it is a step towards them, and this
     * docblock used to list it as a rest it never was. It is bounded twice so
     * that the difference is enforced rather than assumed:
     * InvoiceChargeAttempt::MAX_REPLAYS caps how many a row may have, and
     * REPLAY_WINDOW_SECONDS caps how long they may go on for — measured from
     * the first send, not from the lease each replay refreshes. A row that
     * exhausts either is parked on the next sweep. With the deadline read off
     * the lease it was bounded by neither, and the same charge came back to
     * this queue every fifteen minutes for ever.
     *
     * Oldest claim first, because the oldest is the one closest to falling out
     * of the gateway's idempotency window.
     */
    public const RESCUE_LIMIT = 500;

    /** Invoice statuses this will collect against, and the reason for each omission. */
    private const COLLECTABLE_STATUSES = [
        InvoiceStatus::Unpaid->value,
        InvoiceStatus::Overdue->value,
        // Somebody has paid part of it; the rest is still owed and still
        // collectable. balance() is what gets charged, never the total.
        InvoiceStatus::PartiallyPaid->value,
        // 'draft' is absent: a draft has not been issued to anybody, and
        // charging a card for a bill the customer has never seen is indefensible.
        // 'payment_pending' is absent: the customer has told us they have paid
        // by bank transfer and an admin is looking at it. Taking the money by
        // card as well is the one outcome nobody wants.
        // 'collections' is absent: nothing in this codebase ever writes it
        // (grep across app/ finds only readers), so its meaning is whatever the
        // operator who set it by hand meant. Guessing at that with somebody's
        // card is not on.
        // paid / cancelled / refunded are settled by InvoiceStatus::settled().
    ];

    /**
     * The only service statuses an invoice line may still be collected for.
     *
     * A WHITELIST, NOT A LIST OF ENDED STATES, and the direction is the point.
     * This was ['cancelled', 'terminated', 'fraud'] — everything else was
     * charged, so 'pending' and 'completed' were collected for, and so would
     * any status a later migration adds. The generator selects `status =
     * 'active'` and nothing else; a charger that names the bad states instead
     * of the good ones is one column value away from charging for something
     * nobody has decided about.
     *
     * 'suspended' IS HERE AND IS THE ONE DELIBERATE DIFFERENCE from the
     * generator, which raises lines for active services only. Suspension is
     * almost always non-payment, and paying is what lifts it — refusing to
     * collect for a suspended service would make this feature unable to fix the
     * exact situation it exists to prevent. AddonService::dueQuery takes the
     * same position in the same words ('A suspended service is still owed for —
     * suspension is usually non-payment and paying is what lifts it'), so this
     * is the house's rule rather than this class's opinion.
     *
     * 'pending' is not here, for the reason AddonService gives beside it: a
     * service is marked pending while it is waiting to be provisioned or
     * accepted, and billing a renewal for an account that was never set up is
     * charging for something that does not exist. Its own first invoice is an
     * order invoice, which candidates() excludes outright.
     */
    private const COLLECTABLE_SERVICE_STATUSES = ['active', 'suspended'];

    /**
     * The only domain statuses an invoice line may still be collected for.
     *
     * InvoiceGenerationService's own pair, verbatim: 'Grace counts as billable:
     * the registry still renews at the ordinary price and the customer can
     * still keep the domain.' Everything else — pending, redemption, expired,
     * cancelled, fraud, transferred_away — is a domain the generator would not
     * raise a renewal for, and a charger that collects one is taking money for
     * a name the customer no longer has or has not got yet.
     */
    private const COLLECTABLE_DOMAIN_STATUSES = ['active', 'grace'];

    /**
     * An addon in one of these has been ended.
     *
     * Named states rather than a whitelist here, and this is the one place the
     * direction is reversed on purpose. AddonService::dueQuery uses
     * scopeBillable (status = 'active') because it is choosing RENEWALS; the
     * charger also collects the invoice that BUYS an addon, and
     * AddonService::purchaseForService writes that row as 'pending' and raises
     * its invoice in the same breath. A whitelist of 'active' would therefore
     * make every addon purchase uncollectable — refusing money the customer had
     * just asked to spend. What says no is AddonService::cancel, which writes
     * 'cancelled', and the terminated state beside it.
     */
    private const ENDED_ADDON_STATUSES = ['cancelled', 'terminated'];

    /**
     * Cards presented to a gateway in this run — what the pacing counts and
     * what the run limit caps. A dry run counts what it would have presented,
     * so that `--limit` means the same thing whether or not money moves.
     */
    private int $presented = 0;

    /**
     * Cards that have already been refused in this run, by id.
     *
     * ONE REFUSAL PER CARD PER MORNING. A customer who owes three invoices has
     * one card, and without this the run presents that same card three times in
     * ten seconds — three declines in a row on one card, which is exactly what
     * issuers read as fraud and is the reason HARD_DECLINE_CODES exists at all.
     * The second and third invoices are not failed, not scheduled and not
     * counted against the customer's attempts: they are simply not tried today,
     * and tomorrow's run takes the oldest of them first.
     *
     * A card that SUCCEEDS is not registered here. It has just proved it works,
     * and the customer's other invoices are exactly what it should pay next.
     *
     * requires_action counts as a refusal for this purpose even though it is
     * not one. The same card will ask for the same person on the next invoice,
     * so the only things a second attempt produces are a second pending intent
     * and a second email about it.
     *
     * @var array<int, true>
     */
    private array $refusedCards = [];

    /**
     * Invoices this run has already acted on, by id.
     *
     * The rescue sweep runs before the candidate loop and works from a
     * different query, so the two can meet the same invoice. Without this the
     * second meeting would claim it again and report it twice — Held, or
     * NotDue, over work that has just been done.
     *
     * @var array<int, true>
     */
    private array $handled = [];

    public function __construct(
        private PaymentService $payments,
        private ModuleRegistry $modules,
    ) {}

    /**
     * Collect what is due.
     *
     * @param  int|null  $limit  Cards this run may present; null = DEFAULT_RUN_LIMIT
     * @param  bool  $dryRun  Look, report, touch nothing — no claim, no charge
     * @param  bool  $rescueOnly  Finish abandoned attempts and stop: collect nothing new
     * @return array<string, mixed>
     */
    public function run(?int $limit = null, bool $dryRun = false, bool $rescueOnly = false): array
    {
        $summary = [
            'enabled' => AutoCharge::enabled(),
            'considered' => 0,
            'charged' => 0,
            'collected' => 0.0,
            'action_required' => 0,
            'failed' => 0,
            'held' => 0,
            'not_due' => 0,
            'closed' => 0,
            'reconciled' => 0,
            // Money that had already been taken and was credited from what this
            // system wrote down, without asking the gateway anything.
            'recovered' => 0,
            // A card was charged and the payment could not be recorded. The id
            // is on the attempt row and the next rescue sweep credits it; it is
            // reported here because an operator should hear about it now.
            'taken_not_recorded' => 0,
            // Parked for a person THIS RUN. Not a standing total: a row that
            // was already waiting for somebody is not counted or alerted again.
            'needs_review' => 0,
            'review_invoices' => [],
            // Charges that were sent and never answered. Left in flight on
            // purpose; the rescue sweep replays them a bounded number of times
            // inside the gateway's idempotency window, and parks whatever it
            // has not settled by the time either bound runs out.
            'outcome_unknown' => 0,
            'unknown_invoices' => [],
            // Invoices whose collection threw and were stepped over.
            'errors' => 0,
            // Retries whose date has not arrived, counted across the whole
            // table rather than only inside this run's window.
            'waiting' => 0,
            'requests' => 0,
            'skipped' => [],
            'would_charge' => [],
        ];

        // The master switch, asked before anything else is read. A host that
        // has never heard of this feature must see no change whatsoever, and
        // "no change" includes not running the queries.
        if (! $summary['enabled']) {
            return $summary;
        }

        $limit = $limit !== null ? max(1, $limit) : self::DEFAULT_RUN_LIMIT;

        // Gateways that are switched on AND have the keys they authenticate
        // with, narrowed to the ones that can charge a card with nobody
        // watching. isTokenised() is not that promise — it only says a gateway
        // keeps a handle to a card — so the capability interface is the test.
        $gateways = $this->chargeableGateways();

        if ($gateways === []) {
            $summary['skipped']['no_tokenising_gateway'] = 1;

            return $summary;
        }

        // Finish what a killed run left behind before starting anything new.
        // A dry run writes nothing at all — the reconcile pass this replaced
        // used to close rows even when the operator had asked only to look.
        if (! $dryRun) {
            $this->rescue($gateways, $limit, $summary);
        }

        if (! $rescueOnly) {
            foreach ($this->candidates($gateways, $limit) as $invoice) {
                if (isset($this->handled[(int) $invoice->id])) {
                    // The rescue sweep above has already acted on this invoice.
                    continue;
                }

                if ($this->presented >= $limit) {
                    $summary['skipped']['over_run_limit'] = ($summary['skipped']['over_run_limit'] ?? 0) + 1;

                    continue;
                }

                $summary['considered']++;

                // ONE BAD INVOICE MUST NOT ABANDON THE MORNING'S COLLECTION.
                //
                // Without this, a single throwable on invoice #3 stops
                // invoices #4 to #1000 being looked at at all: they are not
                // charged, not reported and not retried until tomorrow, and
                // every one of those customers is suspended at 07:00 and fee'd
                // at 07:30 for a bill the shop was configured to pay. The
                // realistic thrower is not the gateway (StripeModule catches
                // its own connection errors) but a deadlock or a lock-wait
                // timeout inside PaymentService::applyPayment's lockForUpdate,
                // at 06:45, while the rest of the billing chain is running.
                //
                // This is the shape every other unattended per-item loop in
                // PNLCS already uses — InvoiceGenerationService.php:141 and
                // PaymentReminderCommand.php:63 both try / Log::error /
                // continue — and there is no reason for the one loop that
                // handles money to be the exception.
                //
                // A throw AFTER the card was charged is not handled here but in
                // settle(), which has already written the transaction id down;
                // this catch is for everything else.
                try {
                    $this->collect($invoice, $gateways, $dryRun, $summary);
                } catch (\Throwable $e) {
                    $summary['errors']++;

                    Log::error('AutoCharge: this invoice could not be collected, and the run moved on to the next one', [
                        'invoice' => $invoice->id,
                        'client' => $invoice->client_id,
                        'exception' => $e::class,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $summary['waiting'] = $this->waitingForARetry();
        $summary['requests'] = $this->presented;
        $summary['collected'] = round($summary['collected'], 2);

        // Somebody has to be fetched, and a log line is not fetching anybody.
        if ($summary['needs_review'] > 0) {
            $this->alertOperator($summary);
        }

        return $summary;
    }

    /**
     * One invoice, from the refusals through to the money.
     *
     * The order is deliberate and is the cheapest-first order: everything that
     * can refuse this invoice without a database write happens before the
     * claim, so an invoice that was never chargeable never gets an attempt row
     * and never burns one of the customer's three attempts.
     */
    private function collect(Invoice $candidate, array $gateways, bool $dryRun, array &$summary): void
    {
        // WHAT THE CANDIDATE QUERY HANDED OVER IS A TO-DO LIST, NOT A FACT.
        //
        // candidates() reads the invoices and their clients once, and this runs
        // at 500ms a charge for up to a thousand invoices — the last one is
        // acted on more than eight minutes after that reading was taken. In
        // those eight minutes an operator can cancel an invoice, a customer can
        // switch automatic payment off, credit can be applied that lowers what
        // is owed, and a cancellation can be processed. Every one of those was
        // charged anyway, off the stale snapshot, and each is the same failure:
        // money taken from somebody who had said no.
        //
        // So the row from the query is used for nothing but its id, and
        // everything the decision rests on is read again here, immediately
        // before the claim and the charge. Three point reads on primary and
        // unique keys against a 500ms pacing budget per charge is not a cost
        // worth trading a wrong charge for.
        //
        // BEFORE THE CLAIM RATHER THAN AFTER IT, deliberately. Re-checking
        // after the claim would mean unwinding a claim on an invoice we then
        // decided not to charge — and the claim has already incremented the
        // customer's attempt count, which the dunning cycle and the operator's
        // cap are counted from. Deleting the row loses that count; leaving it
        // strands an in-flight row that the rescue sweep would have to reason
        // about. Read first, claim second, charge third: the window between the
        // reading and the request is one INSERT wide instead of eight minutes.
        $invoice = Invoice::with('client')->find($candidate->id);

        if ($invoice === null) {
            $summary['skipped']['invoice_gone'] = ($summary['skipped']['invoice_gone'] ?? 0) + 1;

            return;
        }

        $this->handled[(int) $invoice->id] = true;

        if (($stop = $this->refusedNow($invoice)) !== null) {
            $summary['skipped'][$stop] = ($summary['skipped'][$stop] ?? 0) + 1;

            Log::info('AutoCharge: this invoice was not charged because the answer changed while the run was in progress', [
                'invoice' => $invoice->id,
                'client' => $invoice->client_id,
                'reason' => $stop,
            ]);

            return;
        }

        [$method, $refusal] = $this->cardFor($invoice, $gateways);

        if ($method === null) {
            $summary['skipped'][$refusal] = ($summary['skipped'][$refusal] ?? 0) + 1;

            return;
        }

        // This card has already been refused once this morning. Asked before
        // the claim on purpose: the customer's other invoices must not burn an
        // attempt apiece on a card whose answer is already known, and an
        // invoice that was never presented should not carry a row saying it
        // failed. Nothing is written; tomorrow's run finds them unchanged.
        if (isset($this->refusedCards[(int) $method->id])) {
            $summary['skipped']['card_refused_earlier_in_run'] = ($summary['skipped']['card_refused_earlier_in_run'] ?? 0) + 1;

            return;
        }

        // Read fresh, and read through the same method the invoice page and
        // every Pay Now button read: total minus what has been recorded
        // against it. A payment that landed between the query above and this
        // line lowers it, and a fully covered invoice drops out here rather
        // than being presented to a card for nothing.
        $amount = round($this->payments->balance($invoice), 2);

        if ($amount <= 0.009) {
            $summary['skipped']['nothing_owed'] = ($summary['skipped']['nothing_owed'] ?? 0) + 1;

            return;
        }

        // No currency override, exactly as GatewayWebhookController::stripeIntent
        // passes none: the module charges in the currency the shop sells in.
        // The alternative — billing an old invoice in its own source_currency —
        // is the better answer and is deliberately not taken here, because the
        // browser path would still do the other thing and a card path that
        // disagreed with the Pay Now button about the currency is worse than
        // one that shares its limitation. Whatever is recorded on the attempt
        // row has to be what the module actually sends, because it is half of
        // what makes a crash replay provable.
        $currency = shop_currency_code();

        if ($dryRun) {
            $summary['would_charge'][] = ['invoice' => $invoice->id, 'method' => $method->id, 'amount' => $amount, 'currency' => $currency];
            $this->presented++;

            return;
        }

        $claim = InvoiceChargeAttempt::claim($invoice, $method, $amount, $currency);

        if (! $claim->mayProceed()) {
            if ($claim === ChargeClaim::Parked) {
                // An attempt was sent for this invoice and its outcome was
                // never established. The model has already written the row and
                // logged it; this is what makes it reach a person.
                $summary['needs_review']++;
                $summary['review_invoices'][] = (int) $invoice->id;

                return;
            }

            $summary[match ($claim) {
                ChargeClaim::Held => 'held',
                ChargeClaim::NotDue => 'not_due',
                default => 'closed',
            }]++;

            return;
        }

        $row = InvoiceChargeAttempt::forInvoice($invoice);

        if ($row === null) {
            // The row was taken and is already gone, which means the invoice
            // was deleted underneath this run. Nothing to charge, nothing to
            // record it against.
            return;
        }

        // The registry's key, lower-cased, and not the raw column. It is what
        // the payment will be recorded under, and the webhook path records the
        // same payment under the literal route name ("stripe",
        // GatewayWebhookController::stripe). PaymentService dedupes on
        // (gateway, transaction id) as strings, so a card row spelled 'Stripe'
        // would make the two paths look like two different payments and the
        // invoice would be credited twice. ModuleRegistry::key() already
        // lower-cases for the same reason.
        $gatewayKey = strtolower((string) $method->gateway_name);
        $module = $gateways[$gatewayKey];

        $this->pace();

        // WHETHER THIS IS A REPEAT IS OURS TO KNOW AND THE MODULE'S TO BE TOLD.
        //
        // claim() has four ways of saying Taken — a first attempt, a retry that
        // came due, a card the customer replaced, and a replay of a charge
        // whose outcome was never heard — and only the last of them means "the
        // gateway already has this request". The module cannot see the
        // difference: it is handed one HTTP exchange and no history. Left
        // untold, it answers the question it can see. A rate limit says "the
        // API method never ran" and on a first send that is the truth and the
        // end of it; on a replay it is a remark about THIS post and says
        // nothing about the charge that went out fifteen minutes ago, which is
        // the only thing in question. Believed as an ordinary retryable
        // decline it scheduled a fresh charge three days out — past the
        // twenty-four hours the gateway holds the key, so a second real debit
        // on top of a first one nobody may assume failed.
        //
        // So the fact goes down rather than the module's reasoning coming up.
        // The alternative was to have every module label every answer with
        // whether it came from the gateway's saved record, and then refuse the
        // ones that did not here. That is a larger promise asked of every
        // gateway (including ones with no idempotency layer to describe), it
        // puts a second opinion about indeterminacy outside the one file that
        // is meant to hold it, and it fails the same way if a module forgets.
        // This way the caller states one fact it already owns and the module
        // keeps deciding, alone, what its own answers mean.
        // AND THE NAME THE GATEWAY KNOWS THIS CHARGE BY, which is the other
        // half of the same promise. Saying "this is a repeat" is worth nothing
        // unless the repeat goes out under the string the first request went
        // out under, and a module cannot know that string: it is stateless
        // across calls, so it can only work the key out again from whatever it
        // has to hand. StripeModule did exactly that, hashing the figures under
        // config('app.key') — and an operator who rotates APP_KEY (Laravel
        // documents it, config/app.php supports APP_PREVIOUS_KEYS, and
        // App\Casts\EncryptedValue survives it anyway) changed the key without
        // changing anything this end could see. The row swore the replay was
        // provably the same request; the request went out under a key Stripe
        // had never seen; Stripe had no saved result to answer from, so it
        // charged the card again.
        //
        // The row is the memory, so the row holds the name. It is written with
        // the claim, before the POST, and read back here unchanged for every
        // replay of it — which is what makes it incapable of drifting, whatever
        // moves underneath: the application key, the derivation, a corrected
        // amount, an input somebody adds next year. restart() mints a new one,
        // because a new attempt is a new operation and must not be answered out
        // of the old one's saved result.
        $result = $module->chargeStoredMethod($invoice, $method, $amount, [
            'replay' => $row->repeatsARequestAlreadySent(),
            'idempotency_key' => $row->idempotency_key,
        ]);

        $status = (string) ($result['status'] ?? 'failed');
        $transactionId = $result['transaction_id'] ?? null;
        $message = $result['message'] ?? null;
        $declineCode = $result['decline_code'] ?? null;

        if ($status === 'succeeded') {
            $this->settle($invoice, $row, $method, $gatewayKey, $result, $amount, $summary);

            return;
        }

        if ($status === 'requires_action') {
            // The money is still there and the card is fine; the bank wants its
            // owner. Nothing is credited, the invoice is untouched, and the
            // intent id is kept because nothing else in the schema keeps it —
            // no transaction row exists for a payment that has not happened.
            $row->recordActionRequired(is_string($transactionId) ? $transactionId : null, $message, $declineCode);
            $summary['action_required']++;
            $this->refusedCards[(int) $method->id] = true;

            Log::info('AutoCharge: the cardholder has to authenticate this payment', [
                'invoice' => $invoice->id,
                'method' => $method->id,
                'intent' => $transactionId,
            ]);

            // Said plainly and said once. Nothing about this is a decline, and
            // a customer told their card failed when their bank only wanted a
            // word with them cancels a subscription that was never in trouble.
            $this->tell($invoice, new AutoChargeActionRequiredMail($invoice, $method, $amount));

            return;
        }

        // A CHARGE WHOSE OUTCOME WAS NEVER HEARD IS NOT A FAILURE, and it must
        // not be written down as one.
        //
        // The module says outcome_unknown when the request was sent and no
        // answer came back — a dropped connection mid-POST, or a payment the
        // gateway has taken but not finished. The money may be gone. Recording
        // a failure here said the opposite, and the schedule that follows a
        // failure made it worse than a wrong word: recordFailure sends the row
        // to nextAttemptMoment, which deliberately lands the retry at least a
        // day later so that an ordinary decline is retried as a NEW request
        // rather than answered out of the gateway's saved copy. Applied to this
        // case that is precisely backwards. Stripe keeps an idempotency key
        // "at least 24 hours" and "we generate a new request if a key is reused
        // after the original is pruned" (https://docs.stripe.com/api/
        // idempotent_requests, fetched 2026-09-17), so the retry two mornings
        // later was a second, genuine charge for money that had very possibly
        // already left the card — with nothing in between that ever asked the
        // gateway what became of the first one.
        //
        // So the row is left exactly as the claim made it: in flight, with this
        // worker's claimed_at on it. That is the one state in this system that
        // means "a charge was sent and we do not know what happened", and the
        // whole rescue machinery is already built for it. Fifteen minutes from
        // now the lease is cold, the sweep picks the row up, and claim() asks
        // the only question that can be answered safely — would repeating this
        // be the same request at the gateway? Inside the window and with the
        // invoice, card, amount and currency unchanged, it is: the repeat comes
        // back out of the gateway's record of the first one, the card is never
        // asked twice, and we finally learn the answer. Past the deadline, past
        // InvoiceChargeAttempt::MAX_REPLAYS, or with any of the four changed,
        // claim() parks it for a person and no card is touched.
        //
        // AND THE DEADLINE ARRIVES, which is not a thing to take on trust. It
        // is measured from the row's first_sent_at, which the replay does not
        // write; measured from claimed_at, which it does, the sweep refreshed
        // the clock it was being judged by and this branch fed the same charge
        // to the gateway 289 times over three days without ever handing it to
        // anybody. Either way, now, it reaches a resting place — which is more
        // than "try again on Thursday" ever offered.
        //
        // NOTHING IS SAID TO THE CUSTOMER. Every other branch here knows
        // whether their money moved; this one does not, and telling somebody
        // their payment failed when their statement says otherwise is worse
        // than telling them nothing until it is known.
        if (($result['outcome_unknown'] ?? false) === true) {
            $summary['outcome_unknown']++;
            $summary['unknown_invoices'][] = (int) $invoice->id;

            // The same card is not presented again this run. It has just been
            // sent a charge that may have succeeded, and the customer's next
            // invoice must not walk into a second one while the first is
            // unresolved.
            $this->refusedCards[(int) $method->id] = true;

            Log::error('AutoCharge: a charge was sent and no answer came back — the attempt is left in flight for the rescue sweep to replay inside the gateway\'s idempotency window', [
                'invoice' => $invoice->id,
                'method' => $method->id,
                'amount' => $amount,
                'message' => $message,
            ]);

            return;
        }

        // Everything else is a failure, including a module that answers with
        // something the contract does not define. retryable decides whether
        // this is worth another morning; the row decides when, and stops at the
        // operator's cap.
        $row->recordFailure($message, $declineCode, (bool) ($result['retryable'] ?? false));
        $summary['failed']++;
        $this->refusedCards[(int) $method->id] = true;

        Log::warning('AutoCharge: card refused', [
            'invoice' => $invoice->id,
            'method' => $method->id,
            'decline_code' => $declineCode,
            'retryable' => (bool) ($result['retryable'] ?? false),
            'attempts' => $row->attempts,
        ]);

        $this->tellAboutFailure($invoice, $method, $row, $amount);
    }

    /**
     * Tell the customer their card did not pay, when there is something new to
     * say.
     *
     * TWICE AT MOST PER INVOICE, AND NEVER BOTH AT ONCE. The first refusal is
     * worth an email because there is still time to do something about it; the
     * last one is worth an email because nothing else will happen and the
     * invoice is now theirs to pay. The attempt in the middle says nothing the
     * first one did not, and the operator's own reminder chain is already
     * writing to this customer at 08:00 on seven separate days
     * (PaymentReminderCommand::STAGES). Six emails about one invoice in a week
     * is how a billing system teaches somebody to filter it.
     *
     * A refusal that both fails and finishes — a lost card on the first attempt
     * — is one email, not two: the exhausted branch is asked first and it says
     * everything the other one would have.
     *
     * The gateway's own words are deliberately left out of the message; see
     * AutoChargeFailedMail. What does travel is whether the issuer has ended
     * the card, read back from the row rather than from memory so that a module
     * which marks it in the database without touching the instance it was
     * handed is still believed.
     */
    private function tellAboutFailure(Invoice $invoice, PaymentMethod $method, InvoiceChargeAttempt $row, float $amount): void
    {
        $gaveUp = $row->state === ChargeAttemptState::Exhausted;

        if (! $gaveUp && (int) $row->attempts > 1) {
            return;
        }

        $this->tell($invoice, new AutoChargeFailedMail(
            $invoice,
            $method,
            $amount,
            $gaveUp ? null : $row->next_attempt_at,
            ($method->fresh()?->status ?? $method->status) !== PaymentMethod::STATUS_ACTIVE,
        ));
    }

    /**
     * Put a message to the customer on the queue.
     *
     * Wrapped exactly the way SendNotificationListener wraps every other
     * customer email in PNLCS: a mail server that is down, a template that
     * throws, an address that will not parse — none of them may stop the run,
     * because the next invoice in the list still has a card waiting to be
     * charged and an email is not what this command is for.
     *
     * billingEmail() rather than email, because that is the address the
     * invoice, the reminder and the receipt already go to; a customer with a
     * separate accounts address must not get half their billing mail at the
     * other one.
     */
    private function tell(Invoice $invoice, Mailable $mail): void
    {
        try {
            $address = $invoice->client?->billingEmail();

            if ($address) {
                Mail::to($address)->queue($mail);
            }
        } catch (\Throwable $e) {
            Log::error('AutoCharge: the customer could not be told what happened to their payment', [
                'invoice' => $invoice->id,
                'mail' => class_basename($mail),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * The money moved. Write it down.
     *
     * WRITE THE ID, THEN CREDIT, THEN CLOSE THE ROW, and each step in that order
     * survives the process being killed after it. Killed after the id is
     * written: rescue() credits the money from the row. Killed after the credit
     * but before the close: the row is in_flight over a paid invoice, which
     * rescue() closes without asking the card anything. Done the other way
     * round — closing first — a crash would leave a row marked succeeded on an
     * invoice nobody had credited, and claim() would answer Closed for ever:
     * money taken, invoice unpaid, and nothing left that would ever fix it.
     *
     * Nothing writes a Transaction by hand. PaymentService is the single door —
     * partial payments, overpayment to credit, AddFunds, affiliate commission
     * and the InvoicePaid event that drives renewal and provisioning all hang
     * off it — and its refusal of a (gateway, transaction id) pair it already
     * holds is the idempotency this relies on rather than inventing its own.
     *
     * A KNOWN AND DELIBERATE LIMIT: if an issuer takes less than was asked for,
     * what it took is credited honestly and the attempt is closed as succeeded,
     * which means the shortfall is never collected by card on its own. The
     * invoice is left partially_paid and travels the ordinary chain from there
     * — reminders, late fee, suspension — where a person sees it. Reopening the
     * row for the remainder would mean a state that is "succeeded but charge it
     * again", and the one rule this whole table exists to keep is that a
     * succeeded invoice is never presented to a card a second time. A Stripe
     * PaymentIntent returns amount_received equal to amount on success, so this
     * is a guard for some future module rather than a case in play today.
     */
    private function settle(Invoice $invoice, InvoiceChargeAttempt $row, PaymentMethod $method, string $gatewayKey, array $result, float $requested, array &$summary): void
    {
        $transactionId = $result['transaction_id'] ?? null;

        if (! is_string($transactionId) || $transactionId === '') {
            // A success with nothing to name it by cannot be credited safely:
            // PaymentService skips its duplicate check when there is no
            // transaction id, so a replay would credit the same money twice and
            // the books would say we received more than we did. StripeModule
            // always returns the intent id; this is the guard for the next
            // module that implements the interface.
            $row->recordFailure(
                'The gateway reported a payment with no transaction id, so it could not be recorded. Check the gateway before charging this invoice again.',
                null,
                false,
            );
            $summary['failed']++;

            // No email. Every other failure here means the money did not move;
            // this one means it may well have, and telling a customer "we could
            // not take payment" when their card has just been charged is worse
            // than telling them nothing. It is a fault at this end and it goes
            // to the operator's log, where somebody can look at the gateway.
            //
            // The card is registered as refused all the same, so the same
            // customer's next invoice does not walk into a second charge this
            // run cannot record either.
            $this->refusedCards[(int) $method->id] = true;

            Log::error('AutoCharge: gateway reported success without a transaction id', [
                'invoice' => $invoice->id,
                'method' => $method->id,
            ]);

            return;
        }

        // What the gateway says it took, read back through its own conversion,
        // in preference to what we asked for. They differ when an issuer
        // partially authorises, and crediting the request rather than the
        // receipt is how an invoice gets marked paid over money that never
        // arrived.
        $taken = isset($result['amount']) ? round((float) $result['amount'], 2) : $requested;

        // WRITE DOWN THAT THE MONEY MOVED BEFORE TRYING TO CREDIT IT.
        //
        // Between the gateway answering and the credit being recorded there is
        // a database transaction that takes lockForUpdate on the invoice, and
        // on a busy install at 06:45 that is exactly where a deadlock or a
        // lock-wait timeout lands. Without this line a throw there left the
        // card charged, the invoice unpaid, no transaction row anywhere, and
        // nothing in PNLCS that knew the money had gone: the customer got a
        // statement line for a payment we had no record of, then a late fee and
        // a suspension for the bill they had already paid. Only a Stripe
        // webhook could have rescued it, and nothing in this feature requires
        // the merchant to have subscribed to payment_intent.succeeded.
        //
        // With it, the attempt row carries the gateway's own id for the money
        // before anything else can fail, and rescue() credits it on the next
        // sweep from our own data — no gateway call, no guessing. The row stays
        // in_flight on purpose: in_flight with a transaction id is the one
        // state that means "the gateway has answered and we owe the ledger an
        // entry", and stuck() is already the query that finds it.
        $row->recordCharged($transactionId, $taken);

        try {
            $applied = $this->payments->applyPayment($invoice, $gatewayKey, $transactionId, $taken);
        } catch (\Throwable $e) {
            // Not rethrown, and not swallowed either. The money is safe — it is
            // written down, the sweep will credit it, and the same (gateway,
            // transaction id) pair means the webhook crediting it first simply
            // makes ours a duplicate. What must not happen is this throwing
            // past the loop and abandoning everybody else's invoice.
            $summary['taken_not_recorded']++;
            $this->refusedCards[(int) $method->id] = true;

            Log::error('AutoCharge: a card was charged and the payment could not be recorded — it is written on the attempt row and will be credited by the next rescue sweep', [
                'invoice' => $invoice->id,
                'method' => $method->id,
                'amount' => $taken,
                'transaction' => $transactionId,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $row->recordSuccess($transactionId, $taken);

        $summary['charged']++;

        if (! ($applied['duplicate'] ?? false)) {
            $summary['collected'] += $taken;
        }

        Log::info('AutoCharge: stored card charged', [
            'invoice' => $invoice->id,
            'method' => $method->id,
            'amount' => $taken,
            'transaction' => $transactionId,
            'duplicate' => (bool) ($applied['duplicate'] ?? false),
            'status' => $applied['status'] ?? null,
        ]);
    }

    /**
     * Finish what a killed run left behind.
     *
     * THIS IS WHERE CRASH RECOVERY ACTUALLY HAPPENS, and it is a separate,
     * frequent sweep for a reason that is arithmetic rather than taste. A
     * replay is only safe while Stripe still holds the idempotency key — "at
     * least 24 hours" (https://docs.stripe.com/api/idempotent_requests, fetched
     * 2026-09-16) — so InvoiceChargeAttempt::REPLAY_WINDOW_SECONDS is 23 hours.
     * Consecutive daily runs are 24 hours apart. A once-a-day charger therefore
     * meets its own wreckage an hour after the only thing that could rescue it
     * has expired, every time, and the recovery the whole in_flight design
     * exists for can never fire. `pnlcs:auto-charge --rescue` runs every
     * fifteen minutes (routes/console.php) and closes that gap.
     *
     * Five outcomes, and every row this touches reaches one of them:
     *
     *  1. THE INVOICE IS ALREADY PAID. The webhook got there first, or we were
     *     killed between crediting and closing the row. Closed without touching
     *     the card — unless whoever paid it did so through some other gateway,
     *     which is a question for a person (see closeAgainstTheLedger).
     *
     *  2. WE KNOW WHAT THE GATEWAY ANSWERED. settle() wrote the transaction id
     *     onto the row before crediting, so the money is recoverable from our
     *     own records: applyPayment it, with the same (gateway, transaction id)
     *     pair the webhook would use, and close.
     *
     *  3. THE INVOICE MAY NO LONGER BE CHARGED. Cancelled, the customer has
     *     opted out, the service was ended. A replay is not free in that
     *     situation: if the original request never reached Stripe, the replay
     *     IS the first charge, and it would be a charge on somebody who said
     *     no. Parked for a person instead.
     *
     *  4. THERE IS NO CARD TO REPLAY WITH. The customer removed it, the issuer
     *     ended it, or there are two and neither is the default. A replay is
     *     only provable against the card the attempt was made on, so nothing
     *     automatic can establish this outcome — now or in fifteen minutes.
     *     Parked for a person rather than skipped, which is what this branch
     *     used to do, silently and for ever.
     *
     *  5. OTHERWISE, THE ORDINARY PATH. collect() re-reads everything, claims,
     *     and claim() decides between a provable replay and needs_review.
     *
     *     THE REPLAY IS THE ONLY ONE OF THE FIVE THAT IS NOT AN ENDING, so it
     *     is bounded rather than trusted not to become one. It may happen at
     *     most InvoiceChargeAttempt::MAX_REPLAYS times, and only within
     *     REPLAY_WINDOW_SECONDS of the FIRST send — a deadline no replay can
     *     postpone, now that it is measured from a column the replay does not
     *     write. Whichever runs out first, the next sweep parks the row and
     *     fetches a person. So a row passes through here a handful of times at
     *     most and then reaches one of the other four.
     *
     *     Read off the lease instead, it passed through here for ever: measured
     *     over three simulated days of the real fifteen-minute schedule against
     *     a gateway that never answered, 289 presentations of one charge, the
     *     row still in_flight, nobody told, AutoCharge::maxAttempts counting it
     *     as one attempt — and every presentation past the twenty-fourth hour a
     *     second genuine charge, because Stripe had pruned the key the replay
     *     depended on.
     *
     * Oldest claim first, and every row reaches a resting place: the pass this
     * replaced skipped whatever it could not close, had no ordering and no
     * cursor, so five hundred unclosable rows at low ids would have starved it
     * for ever.
     *
     * @param  array<string, TokenizableGatewayInterface>  $gateways
     * @param  int  $limit  Cards this run may present, counted across both passes
     */
    private function rescue(array $gateways, int $limit, array &$summary): void
    {
        $stuck = InvoiceChargeAttempt::query()
            ->stuck()
            ->with('invoice.client')
            ->orderBy('claimed_at')
            ->limit(self::RESCUE_LIMIT)
            ->get();

        // Rows already parked for a person that nevertheless carry a
        // transaction id. That combination has exactly one cause — a charge
        // whose id we wrote down and whose credit never landed, parked before
        // this sweep reached it — and the money in it is recoverable without
        // asking anybody anything. It is the only thing done to a row that is
        // already with a person, and it moves it out of their queue rather
        // than into it.
        $recoverable = InvoiceChargeAttempt::query()
            ->where('state', ChargeAttemptState::NeedsReview)
            ->whereNotNull('last_transaction_id')
            ->with('invoice.client')
            ->orderBy('updated_at')
            ->limit(self::RESCUE_LIMIT)
            ->get();

        foreach ($stuck->concat($recoverable) as $row) {
            try {
                $this->rescueOne($row, $gateways, $limit, $summary);
            } catch (\Throwable $e) {
                $summary['errors']++;

                Log::error('AutoCharge: an abandoned attempt could not be finished, and the sweep moved on', [
                    'invoice' => $row->invoice_id,
                    'attempt' => $row->id,
                    'exception' => $e::class,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * One abandoned attempt. See rescue() for the four outcomes.
     *
     * @param  array<string, TokenizableGatewayInterface>  $gateways
     * @param  int  $limit  Cards this run may present, counted across both passes
     */
    private function rescueOne(InvoiceChargeAttempt $row, array $gateways, int $limit, array &$summary): void
    {
        // READ AGAIN, NOT OFF THE SWEEP'S SNAPSHOT. rescue() loads up to five
        // hundred rows with('invoice.client') in one go and this walks them at
        // the pace of whatever each one needs, so the last is acted on minutes
        // after that reading was taken — the same staleness the candidate loop
        // was faulted for, over the same columns. It matters more here than it
        // used to: the refusals below now include a customer's own renewal
        // switches, which are exactly the thing that changes while a sweep is
        // running. One point read on the primary key.
        $invoice = Invoice::with('client')->find($row->invoice_id);

        if ($invoice === null) {
            // The invoice was deleted; the row cascades with it. Nothing to
            // charge and nothing to record it against.
            return;
        }

        $this->handled[(int) $invoice->id] = true;

        $paid = strtolower((string) $invoice->status) === InvoiceStatus::Paid->value;
        $recordedId = is_string($row->last_transaction_id) && $row->last_transaction_id !== ''
            ? $row->last_transaction_id
            : null;

        if ($row->state === ChargeAttemptState::NeedsReview) {
            // Already with a person. The only thing worth doing automatically
            // is crediting money we know was taken; everything else about this
            // row is theirs to decide, and re-parking it every fifteen minutes
            // would be an alert storm rather than information.
            if ($recordedId !== null && ! $paid) {
                $this->creditWhatWasTaken($row, $invoice, $recordedId, $summary);
            }

            return;
        }

        if ($paid) {
            $this->closeAgainstTheLedger($row, $invoice, $summary);

            return;
        }

        if ($recordedId !== null) {
            $this->creditWhatWasTaken($row, $invoice, $recordedId, $summary);

            return;
        }

        if (($stop = $this->refusedNow($invoice)) !== null) {
            $this->park(
                $row,
                'A charge was sent, its outcome was never recorded, and this invoice may no longer be charged ('.$stop.'). '
                .'Check the gateway: if the money was taken it has to be refunded or credited by hand.',
                $summary,
            );

            return;
        }

        // THERE HAS TO BE A CARD TO REPLAY WITH, AND IF THERE IS NOT, THIS IS
        // A PERSON'S PROBLEM RATHER THAN A ROW TO STEP OVER.
        //
        // The branch below hands the row to collect(), which resolves a card
        // before it claims anything. When cardFor() finds none — the customer
        // removed the card through the panel, a declined charge left it
        // requires_update, a second card arrived and neither is the default —
        // collect() counted a skip and returned. The row stayed in flight, with
        // a claimed_at that only got colder, and this sweep found it again
        // fifteen minutes later and did the same nothing, for ever. Not
        // credited, not parked, not needs_review, no log line of any severity:
        // every one of the five surfaces built to fetch a person is reached
        // through needs_review, and this path never got there. Meanwhile the
        // invoice travelled the ordinary chain — reminded, fee'd, suspended —
        // for money that may already have been on the customer's statement. It
        // also sorted to the front of this sweep for ever, because it never
        // left stuck(), which is candidate starvation wearing a different hat.
        //
        // Parking is the honest answer and it is available. A replay is only
        // ever provable against the SAME card the abandoned attempt used, so a
        // run that cannot produce a card cannot establish this outcome by any
        // automatic means, today or in fifteen minutes' time. That is the
        // definition of "no safe automatic answer": refuse to charge, and fetch
        // somebody. The exit exists too — an operator who has checked the
        // gateway releases the review from the invoice screen and ordinary
        // collection resumes — so this is a stop, not a grave.
        //
        // It is parked once. A needs_review row is not re-announced by later
        // sweeps (rescue() only revisits parked rows that carry a transaction
        // id, to credit money already known to be taken), so a gateway switched
        // off for ten minutes costs one alert and one click, not a storm.
        //
        // Asked before the run limit for the same reason the branches above
        // are: this presents no card, and an operator's --limit is about cards.
        [$replayCard, $why] = $this->cardFor($invoice, $gateways);

        if ($replayCard === null) {
            $this->park(
                $row,
                'A charge was sent and its outcome was never recorded, and there is no longer a card this run can '
                .'repeat it with ('.$why.'), so the gateway cannot be asked what became of it. Check the gateway: if '
                .'the money was taken it has to be credited or refunded by hand.',
                $summary,
            );

            return;
        }

        // Only the last branch can present a card, so only the last branch is
        // capped: crediting money that was already taken and closing a row
        // against a payment somebody else recorded cost the gateway nothing,
        // and an operator's --limit is about cards, not bookkeeping.
        if ($this->presented >= $limit) {
            $summary['skipped']['over_run_limit'] = ($summary['skipped']['over_run_limit'] ?? 0) + 1;

            return;
        }

        $summary['considered']++;

        $this->collect($invoice, $gateways, false, $summary);
    }

    /**
     * The invoice is paid. Is it paid with the card this row was charging?
     *
     * The commonest two arrivals here are ours: the webhook delivered
     * payment_intent.succeeded and GatewayWebhookController credited it, or
     * settle() credited it and was killed before closing the row. Both were
     * paid through the same gateway as the card, and both are finished with —
     * closed here without touching the card, because a replay would be
     * harmless at Stripe and pointless everywhere else.
     *
     * WHAT IS NOT CLOSED IS AN INVOICE SOMEBODY ELSE PAID. A bank transfer an
     * admin approved, or a settlement out of account credit, says nothing
     * whatever about the charge that was in flight when the process died. The
     * old pass stamped whichever transaction was newest onto the row and
     * marked it succeeded, so a card charge that really had landed at the
     * gateway became invisible — the customer had paid twice and the one row
     * that would have said so now read as a clean card success. There is no
     * safe automatic answer to that: it is a person's question, and it is
     * asked rather than buried.
     */
    private function closeAgainstTheLedger(InvoiceChargeAttempt $row, Invoice $invoice, array &$summary): void
    {
        $settlement = Transaction::query()
            ->where('invoice_id', $invoice->id)
            ->where('client_id', $invoice->client_id)
            ->where('amount_in', '>', 0)
            ->whereNotNull('transaction_id')
            ->orderByDesc('id')
            ->first();

        $cardGateway = strtolower((string) (PaymentMethod::withTrashed()->find($row->payment_method_id)?->gateway_name ?? ''));
        $settledByTheSameGateway = $settlement !== null
            && $cardGateway !== ''
            && strtolower((string) $settlement->gateway) === $cardGateway;

        if (! $settledByTheSameGateway) {
            $this->park(
                $row,
                'A charge was sent and its outcome was never recorded, and this invoice was then settled '
                .($settlement === null ? 'without a gateway payment (account credit, or a payment with no reference)' : 'through '.$settlement->gateway)
                .'. Check the gateway: if the card was charged as well, the customer has paid twice.',
                $summary,
            );

            return;
        }

        $row->recordSuccess((string) $settlement->transaction_id);
        $summary['reconciled']++;

        Log::info('AutoCharge: an abandoned attempt was closed because the invoice is already paid', [
            'invoice' => $invoice->id,
            'transaction' => $settlement->transaction_id,
        ]);
    }

    /**
     * Credit a payment this system knows was taken.
     *
     * No gateway call: settle() wrote the id down before crediting precisely so
     * that this is answerable from our own records. PaymentService is still the
     * only door, and its refusal of a (gateway, transaction id) pair it already
     * holds is what keeps this and the webhook from crediting the same money
     * twice — whichever arrives first writes the Transaction, the other is told
     * it is a duplicate, and the row closes either way.
     */
    private function creditWhatWasTaken(InvoiceChargeAttempt $row, Invoice $invoice, string $transactionId, array &$summary): void
    {
        $method = PaymentMethod::withTrashed()->find($row->payment_method_id);
        $gatewayKey = strtolower((string) ($method?->gateway_name ?? ''));

        if ($gatewayKey === '') {
            // AND IT IS SAID ONCE, which is the half this branch was missing.
            // rescue() revisits a parked row on every sweep for as long as it
            // carries a transaction id, and parking one that is already parked
            // raises the error log, the run count and the operator's email
            // again each time — ninety-six times a day, for ever, about a row
            // nothing automatic can move. That is the alert storm rescue()'s
            // own needs_review branch says it is avoiding, reached through a
            // second door. The first parking has already fetched somebody and
            // the invoice is already in their review queue; a second one says
            // nothing new and teaches them to filter the first.
            if ($row->state === ChargeAttemptState::NeedsReview) {
                return;
            }

            // Without the gateway the payment cannot be recorded under the pair
            // the webhook would use, and inventing one would make the two
            // paths look like two payments. A person has to place it.
            $this->park(
                $row,
                'A charge was sent under transaction '.$transactionId.' and the card it was made with is gone, '
                .'so the payment could not be matched to a gateway. Record it by hand.',
                $summary,
            );

            return;
        }

        $amount = $row->amount !== null ? round((float) $row->amount, 2) : null;

        $applied = $this->payments->applyPayment($invoice, $gatewayKey, $transactionId, $amount);

        $row->recordSuccess($transactionId, $amount);
        $summary['recovered']++;

        if (! ($applied['duplicate'] ?? false)) {
            $summary['collected'] += (float) ($amount ?? 0);
        }

        Log::warning('AutoCharge: a payment that had been taken but never recorded was credited from the attempt row', [
            'invoice' => $invoice->id,
            'transaction' => $transactionId,
            'amount' => $amount,
            'duplicate' => (bool) ($applied['duplicate'] ?? false),
        ]);
    }

    /**
     * Stop, and fetch a person.
     *
     * The model writes the row and raises the error-level log — it is written
     * there so that no caller can park an invoice quietly — and this adds the
     * two things a service can add: the run's own count, which the command
     * prints on a line of its own rather than folding into "already finished
     * with", and the invoice number, which the email at the end of the run
     * names.
     */
    private function park(InvoiceChargeAttempt $row, string $message, array &$summary): void
    {
        $row->markNeedsReview($message);

        $summary['needs_review']++;
        $summary['review_invoices'][] = (int) $row->invoice_id;
    }

    /**
     * Is this invoice still one this run may charge, read a moment ago?
     *
     * Every clause here is also in the candidate SQL. It is asked twice on
     * purpose: the SQL keeps invoices that cannot be charged out of the run's
     * window altogether, and this catches the ones that changed their answer
     * after the window was taken. The second reading is the one that decides.
     *
     * @return string|null  null = chargeable; otherwise the reason it is not
     */
    private function refusedNow(Invoice $invoice): ?string
    {
        // THE OPERATOR'S STOP BUTTON, ASKED AGAIN. run() reads it once, before
        // anything else, and a run at the default limit then keeps going for up
        // to eight minutes and twenty seconds. An operator who switches the
        // feature off because something is going wrong watched it charge the
        // rest of the morning's cards anyway — three due invoices, the switch
        // thrown inside the first gateway call, three cards presented. One
        // indexed lookup on settings.setting per invoice, against a 500ms
        // pacing budget, is not a price worth arguing about for a stop button
        // that stops.
        if (! AutoCharge::enabled()) {
            return 'operator_switched_it_off_mid_run';
        }

        if (! in_array(strtolower((string) $invoice->status), self::COLLECTABLE_STATUSES, true)) {
            return 'invoice_no_longer_collectable';
        }

        $client = $invoice->client;

        if ($client === null) {
            return 'client_gone';
        }

        $status = $client->status instanceof ClientStatus
            ? $client->status
            : ClientStatus::tryFrom(strtolower((string) $client->status));

        if ($status !== ClientStatus::Active) {
            return 'client_no_longer_trading';
        }

        if (! $client->auto_charge) {
            return 'customer_has_opted_out';
        }

        if (($type = $this->lineTheCustomerEnded($invoice)) !== null) {
            // Named by line type so an operator reading the skipped counts can
            // tell a cancelled service from a domain whose renewal was switched
            // off, without opening the invoice.
            return match (strtolower($type)) {
                'hosting' => 'service_the_customer_ended',
                'domain' => 'domain_the_customer_ended',
                'addon' => 'addon_the_customer_ended',
                default => 'line_the_customer_ended',
            };
        }

        return null;
    }

    /**
     * Does this invoice bill for something the customer has said no to?
     *
     * THE GENERATOR IS THE AUTHORITY ON WHAT MAY BE BILLED, AND THIS HAS TO BE
     * AT LEAST AS STRICT AS THE GENERATOR. InvoiceGenerationService raises
     * renewals up to fourteen days before they are due
     * (config billing.invoice_days_before_due) and this collects them up to
     * three days before (AutoCharge::DEFAULT_DAYS_BEFORE), so there is a
     * fortnight in between during which the customer can change their mind —
     * and every way they have of doing so is a rule the generator applies when
     * it decides whether to raise the line at all. A charger that mirrors fewer
     * of those rules than the generator takes money for exactly the thing the
     * generator would have refused to bill for, and takes it from somebody who
     * went into the panel and said no.
     *
     * So this is written as a superset of the generator's refusals, line type
     * by line type. Where the generator selects the good rows, this refuses
     * everything outside them:
     *
     *   HOSTING (invoice_items.rel_id -> services.id)
     *     generator: status = 'active', auto_renew = true, and no unprocessed
     *                cancellation_request (InvoiceGenerationService, the
     *                $services query, each with its own comment).
     *     here:      status not in (active, suspended), OR auto_renew false, OR
     *                an unprocessed cancellation request.
     *     The single difference is 'suspended', which is deliberate and is
     *     argued at COLLECTABLE_SERVICE_STATUSES.
     *
     *   DOMAIN (invoice_items.rel_id -> domains.id)
     *     generator: status in (active, grace), and payment_method is null or
     *                not 'none' — 'A domain has no auto-renew column: the
     *                customer's switch flips payment_method to none'
     *                (Client\DomainController::toggleAutoRenew writes it).
     *     here:      the same, negated. A null is not a no, exactly as the
     *                generator says, so only the literal 'none' refuses.
     *
     *   ADDON (invoice_items.rel_id -> service_addons.id)
     *     generator: AddonService::dueQuery — the addon billable, its parent
     *                service not pending/terminated/cancelled/fraud, the parent
     *                auto_renew true, and no unprocessed cancellation request
     *                on the parent. The strictest of the three.
     *     here:      the addon itself ended, OR the parent service failing any
     *                of the hosting rules above.
     *
     * NOTHING WAS JOINED FOR DOMAIN OR ADDON LINES BEFORE THIS. The predicate
     * filtered invoice_items.type = 'Hosting', so a Domain line and an Addon
     * line matched no clause anywhere — in the candidate SQL or in the fresh
     * re-read — and a customer who turned a domain's renewal off, or cancelled
     * the service an addon hangs from, was charged for it the next morning. The
     * type filter was also the only thing standing between the old code and a
     * worse bug: it joined services.id = invoice_items.rel_id, and an Addon
     * line's rel_id is a service_addons id, so without the filter an unrelated
     * service's status would have decided the charge. Each type now joins its
     * own table inside its own branch.
     *
     * A LINE WHOSE REFERENT CANNOT BE FOUND DOES NOT REFUSE, and that is a
     * decision rather than an oversight. invoice_items.rel_id is not a foreign
     * key and is written by several paths that do not mean the same thing by it
     * (InvoiceService::createInvoice takes whatever the caller passes, and the
     * codebase's own factory writes 0). A row that names nothing is not a
     * customer saying no; it is no evidence either way, and refusing on it
     * would stop collecting invoices nobody ever objected to — a different
     * harm, with no consent question behind it. What this refuses is a referent
     * that exists and says no.
     *
     * ANY REFUSED LINE STOPS THE WHOLE INVOICE, not just its own line. An
     * invoice groups a client's renewals and the total is what the card is
     * asked for, so charging it while one line is for something the customer
     * ended takes money for that line too. Refusing the invoice leaves it to
     * the ordinary chain, where a person can credit the dead line and bill the
     * rest: where there is no safe automatic answer, do not take the money.
     *
     * Statuses rather than only cancellation requests, because the request is
     * deleted or processed while the service stays cancelled: a service that is
     * over is over however it got there. All comparisons are left to MySQL's
     * case-insensitive collation, as everywhere else that reads these columns.
     *
     * @return string|null  null = nothing on this invoice refuses; otherwise
     *                      the invoice_items.type of the line that does
     */
    private function lineTheCustomerEnded(Invoice $invoice): ?string
    {
        $line = $this->endedItems(
            DB::table('invoice_items')->where('invoice_items.invoice_id', $invoice->id)
        )->select('invoice_items.type')->first();

        return $line === null ? null : (string) $line->type;
    }

    /**
     * The conditions that make an invoice_items row one the customer said no to.
     *
     * ONE DEFINITION, TWO READERS, and it has to stay that way. The candidate
     * SQL uses it to keep such invoices out of the run's window altogether; the
     * per-invoice check above uses it to catch the ones whose answer changed
     * after the window was taken. Two copies of this would be two things to
     * edit, and the one that got forgotten would be the one that charges a
     * card.
     */
    private function endedItems($query)
    {
        return $query->where(fn ($line) => $line
            ->where(fn ($hosting) => $hosting
                ->where('invoice_items.type', 'Hosting')
                ->whereExists(fn ($s) => $this->serviceTheCustomerEnded($s
                    ->select(DB::raw(1))
                    ->from('services')
                    ->whereColumn('services.id', 'invoice_items.rel_id'))))
            ->orWhere(fn ($domain) => $domain
                ->where('invoice_items.type', 'Domain')
                ->whereExists(fn ($d) => $d
                    ->select(DB::raw(1))
                    ->from('domains')
                    ->whereColumn('domains.id', 'invoice_items.rel_id')
                    ->where(fn ($w) => $w
                        ->whereNotIn('domains.status', self::COLLECTABLE_DOMAIN_STATUSES)
                        // A null is not a no: the generator says so in as many
                        // words, and SQL would drop null rows out of a !=
                        // comparison and quietly stop billing them.
                        ->orWhere('domains.payment_method', 'none'))))
            ->orWhere(fn ($addon) => $addon
                ->where('invoice_items.type', 'Addon')
                ->whereExists(fn ($a) => $a
                    ->select(DB::raw(1))
                    ->from('service_addons')
                    ->whereColumn('service_addons.id', 'invoice_items.rel_id')
                    ->where(fn ($w) => $w
                        ->whereIn('service_addons.status', self::ENDED_ADDON_STATUSES)
                        // An extra on a service the customer has ended is part
                        // of what they ended — AddonService::dueQuery's own
                        // rule, and the strictest of the three.
                        ->orWhereExists(fn ($s) => $this->serviceTheCustomerEnded($s
                            ->select(DB::raw(1))
                            ->from('services')
                            ->whereColumn('services.id', 'service_addons.service_id')))))));
    }

    /**
     * The three ways a customer says no to a service, in one place.
     *
     * Read by the Hosting branch about the service a line bills, and by the
     * Addon branch about the service an extra hangs from, so the two can never
     * come to different conclusions about the same service.
     *
     * The query handed in is already correlated to a services row; this adds
     * the refusals to it.
     */
    private function serviceTheCustomerEnded($query)
    {
        return $query->where(fn ($w) => $w
            ->whereNotIn('services.status', self::COLLECTABLE_SERVICE_STATUSES)
            // The customer's own switch, Client\ServiceController::toggleAutoRenew.
            // The generator refuses to raise a line for it with the comment
            // 'The customer turned renewal off; billing them anyway is what got
            // the account suspended for an invoice they never wanted' — and the
            // toggle writes only the flag: no observer, listener or command
            // cancels an invoice that was already raised, so this is the only
            // thing between that click and the card.
            ->orWhere('services.auto_renew', false)
            ->orWhereExists(fn ($c) => $c
                ->select(DB::raw(1))
                ->from('cancellation_requests')
                ->whereColumn('cancellation_requests.service_id', 'services.id')
                ->whereNull('cancellation_requests.processed_at')));
    }

    /**
     * Retries whose date has not arrived, across the whole table.
     *
     * The candidate query no longer loads them — they cannot be charged today,
     * and while they sat in the window they were taking slots from invoices
     * that could — so this is what keeps them visible to the operator. One
     * COUNT on the (state, next_attempt_at) index the table already carries.
     */
    private function waitingForARetry(): int
    {
        return InvoiceChargeAttempt::query()
            ->where('state', ChargeAttemptState::Scheduled)
            ->whereNotNull('next_attempt_at')
            ->where('next_attempt_at', '>', now())
            ->count();
    }

    /**
     * Fetch a person, through the doors this codebase already uses for it.
     *
     * needs_review means money may have left a customer's card and this system
     * cannot say whether it did. That is not a log line: a log line nobody
     * reads is how the first review of this feature described the whole state.
     * So it goes out four ways, and each is aimed at a different moment —
     *
     *   the error log, at the instant the row is written (the model does that),
     *     which is the one an operator's aggregator picks up unattended;
     *   the command's own output, for whoever is running it by hand — NOT for
     *     cron, which is what a previous version of this comment claimed:
     *     Command::error() writes to stdout, and the scheduler sends both
     *     streams to /dev/null (Event::$output, CommandBuilder);
     *   an email to SystemEmailAddress, the same address and the same
     *     Mail::raw shape RegistrarBalanceCheckCommand uses for the other
     *     "somebody has to act" alert in this codebase;
     *   a notification event, so an installation with a Slack or webhook rule
     *     gets it where they actually watch;
     *
     * and the standing count is on the admin dashboard beside the pending
     * orders, linking to the invoices themselves, for the operator who was not
     * reading any of the above.
     *
     * ONLY NEW ONES. The count is of rows parked by this run, so an invoice
     * already waiting for somebody is not re-announced every fifteen minutes.
     */
    private function alertOperator(array $summary): void
    {
        $invoices = implode(', ', array_map(static fn ($id) => '#'.$id, $summary['review_invoices']));
        $subject = __('messages.auto_charge.needs_review_subject', ['count' => count($summary['review_invoices'])]);
        $body = __('messages.auto_charge.needs_review_body', ['invoices' => $invoices]);

        try {
            // SystemEmailAddress, and no alert address of its own. An
            // operator-facing setting that no screen can write is not a
            // setting, and this feature's four switches were kept to four
            // because each of them has an answer nobody else can pick.
            $to = (string) Setting::get('SystemEmailAddress');

            if ($to !== '') {
                Mail::raw($body, function ($message) use ($to, $subject) {
                    $message->to($to)->subject($subject);
                });
            }
        } catch (\Throwable $e) {
            Log::error('AutoCharge: the operator could not be emailed about the invoices that need a person', [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            app(NotificationService::class)->dispatch('payment.charge_needs_review', [
                'event_type' => 'payment.charge_needs_review',
                'subject' => $subject,
                'message' => $body,
                'invoices' => $summary['review_invoices'],
            ]);
        } catch (\Throwable) {
            // No rule configured for it yet; the email, the log, the command
            // output and the dashboard are all still there.
        }
    }

    /**
     * The invoices worth looking at, narrowed in SQL before anything is loaded.
     *
     * @param  array<string, TokenizableGatewayInterface>  $gateways
     * @param  int  $limit  Cards this run may present
     * @return Collection<int, Invoice>
     */
    private function candidates(array $gateways, int $limit): Collection
    {
        // How early a card may be charged. The generator raises renewals up to
        // fourteen days ahead (config billing.invoice_days_before_due), so
        // charging on sight would take the money a fortnight before the
        // customer expected it; this is the charger's own, later moment.
        $cutoff = now()->addDays(AutoCharge::daysBeforeDue())->endOfDay();

        return Invoice::query()
            ->with('client')
            ->whereIn('status', self::COLLECTABLE_STATUSES)
            ->whereNotNull('due_date')
            ->where('due_date', '<=', $cutoff)
            // A positive total is not a balance, but it is the part that can be
            // asked of SQL; balance() settles it per invoice a moment later.
            ->where('total', '>', 0)

            // An invoice that belongs to an order is not a renewal. Paying one
            // provisions an account — AutoAcceptOrderListener accepts the order
            // and OrderService builds the service — which is a great deal more
            // than collecting a bill, and the customer is in front of the
            // checkout for it anyway. RenewOnPaymentListener:32 already draws
            // the same line from the other side: an order-tied invoice is not
            // something it will advance a billing date for.
            // whereNotExists rather than whereNotIn: a NULL invoice_id inside a
            // NOT IN subquery makes the whole predicate unknown and silently
            // returns nothing at all.
            ->whereNotExists(fn ($q) => $q
                ->select(DB::raw(1))
                ->from('orders')
                ->whereColumn('orders.invoice_id', 'invoices.id'))

            // Add Funds is the customer topping up their own balance, one
            // deliberate act at a time. It is not a recurring bill, nobody
            // promised to pay it on a schedule, and an abandoned top-up must
            // not turn into a charge three days later. InvoiceService treats it
            // as a special case for the mirror-image reason: an Add Funds
            // invoice must not be settled out of the balance it exists to fill.
            ->whereDoesntHave('items', fn ($q) => $q->where('type', 'AddFunds'))

            // The customer has to be trading, AND they have to want this.
            //
            // An operator who marked an account inactive or closed has said
            // this account is not to be acted on, and an unattended charge is
            // the loudest possible way to act.
            //
            // clients.auto_charge is the customer's own answer and it is asked
            // here, in the same SQL, rather than anywhere further in. Whatever
            // else this class gets wrong, an invoice belonging to somebody who
            // has said stop is never loaded, never claimed, never counted and
            // never presented — there is no later branch that could let one
            // through. It defaults to true because every billing automation in
            // PNLCS already does (services.auto_renew, and
            // clients.override_auto_suspend from the other side), and because
            // the operator's master switch is off until somebody deliberately
            // turns it on.
            ->whereHas('client', fn ($q) => $q
                ->where('status', ClientStatus::Active->value)
                ->where('auto_charge', true))

            // And they have to have a card this installation could actually
            // present. Cheap to ask here, and it keeps a host with one stored
            // card and fifty thousand open invoices from loading all fifty
            // thousand. The per-invoice resolution below still decides WHICH
            // card; this only asks whether there is one.
            ->whereExists(fn ($q) => $q
                ->select(DB::raw(1))
                ->from('payment_methods')
                ->whereColumn('payment_methods.client_id', 'invoices.client_id')
                ->whereNull('payment_methods.deleted_at')
                ->where('payment_methods.status', PaymentMethod::STATUS_ACTIVE)
                ->whereIn('payment_methods.gateway_name', array_keys($gateways)))

            // AND IT MUST NOT BE BILLING ANYTHING THE CUSTOMER HAS SAID NO TO.
            // lineTheCustomerEnded() carries the reasoning; this is the same
            // predicate — the same method, not a copy of it — asked of the
            // whole window at once, so that such an invoice does not take a
            // slot either.
            ->whereNotExists(fn ($q) => $this->endedItems(
                $q->select(DB::raw(1))
                    ->from('invoice_items')
                    ->whereColumn('invoice_items.invoice_id', 'invoices.id')
            ))

            // AND THIS RUN HAS TO BE ABLE TO DO SOMETHING WITH IT.
            //
            // The query knew nothing about the attempt rows, and the window is
            // ordered by due date and capped at twice the run limit — so an
            // invoice that can never be charged again is both old and
            // permanent, sorts to the FRONT, and holds its slot every morning
            // for ever while the invoice that would have paid today never
            // enters the result set at all. Nothing counts it, because nothing
            // has seen it: the run reports "Considered 2, charged 0" and the
            // customer is suspended at 07:00 and fee'd at 07:30 for an invoice
            // the shop was configured to pay. Proven at --limit 1 with two
            // exhausted invoices ahead of one live one; the default cap of 1000
            // needs 2000 of them, which is years of a busy shop, and --limit is
            // exactly what a careful operator uses on the first morning.
            //
            // Excluded is only what this run cannot move:
            //   - succeeded and needs_review, which nothing automatic reopens;
            //   - exhausted, action_required, and a retry whose date has not
            //     arrived — but ONLY while the card they were decided against
            //     is the only card the customer has. Storing a different card
            //     reopens all three (takeOver() restarts on a changed card,
            //     ahead of the retry date), so an invoice whose client has some
            //     other active card stays in the window and gets its fresh go.
            // in_flight stays in: warm answers Held in one query, and cold is
            // the crash the rescue sweep exists for.
            ->whereNotExists(fn ($q) => $q
                ->select(DB::raw(1))
                ->from('invoice_charge_attempts as a')
                ->whereColumn('a.invoice_id', 'invoices.id')
                ->where(fn ($w) => $w
                    ->whereIn('a.state', [
                        ChargeAttemptState::Succeeded->value,
                        ChargeAttemptState::NeedsReview->value,
                    ])
                    ->orWhere(fn ($x) => $x
                        ->whereIn('a.state', [
                            ChargeAttemptState::Exhausted->value,
                            ChargeAttemptState::ActionRequired->value,
                            ChargeAttemptState::Scheduled->value,
                        ])
                        ->where(fn ($d) => $d
                            ->whereNull('a.next_attempt_at')
                            ->orWhere('a.next_attempt_at', '>', now()))
                        ->whereNotExists(fn ($c) => $c
                            ->select(DB::raw(1))
                            ->from('payment_methods as other')
                            ->whereColumn('other.client_id', 'invoices.client_id')
                            ->whereNull('other.deleted_at')
                            ->where('other.status', PaymentMethod::STATUS_ACTIVE)
                            ->whereIn('other.gateway_name', array_keys($gateways))
                            ->where(fn ($n) => $n
                                ->whereNull('a.payment_method_id')
                                ->orWhereColumn('other.id', '!=', 'a.payment_method_id'))))))

            // Oldest debt first: if a run hits its limit, the invoices that
            // have been owed longest are the ones that got collected.
            ->orderBy('due_date')
            ->orderBy('id')
            // More than the charge cap, because a good number of these will be
            // refused before a gateway is reached — a card that needs the
            // customer's attention, two cards and no default, nothing left
            // owing — and a run that fetched exactly its cap would collect less
            // than it was allowed to.
            ->limit($limit * 2)
            ->get();
    }

    /**
     * Which stored card pays this invoice.
     *
     * invoices.pay_method_id is the hook the schema has carried since
     * 2026_03_31_060004 and that nothing has ever read or written. It says
     * which stored method this invoice is to be paid with, and it is read here
     * as an instruction: when it names a card, that card is the only one tried
     * — no falling back to another one, because somebody chose this.
     *
     * invoices.payment_method - a different column, and deliberately not read.
     * It is the gateway box on the admin's invoice form
     * (InvoiceController::create, written at :391), and treating it as an
     * instruction was tried and taken out again: OrderService fills it on every
     * order invoice, FundsController on every top-up, the admin form pre-fills
     * it from clients.default_payment_method, and InvoiceFactory:29 puts
     * 'banktransfer' on every invoice in this codebase's own fixtures. A column
     * that is populated by default on most invoices cannot carry "do not charge
     * a card for this one" - respecting it would have silently stopped
     * collection wherever it happens to be set, which is a worse failure than
     * the one it prevents, and the customer's own consent (clients.auto_charge)
     * is the switch that means something here. Renewal invoices, which are what
     * this feature exists for, never carry it at all: InvoiceGenerationService
     * passes no payment_method to InvoiceService::createInvoice. An operator who
     * wants one invoice left alone needs a flag that says so, which is a product
     * decision rather than a reading of this column.
     *
     * It is NOT written here. A requirement that it be set first would collect
     * nothing at all, since no code path in PNLCS writes it; and stamping it on
     * the way past would turn a statement of intent into a record of history
     * and break the one thing the attempt row does for a customer whose card
     * has just been declined — a replacement card reopens the invoice with a
     * clean count, which it cannot do if the invoice is pinned to the dead one.
     * Writing it belongs to whatever gives an operator or a customer a way to
     * say "use this card", which is a screen, not a cron.
     *
     * @param  array<string, TokenizableGatewayInterface>  $gateways
     * @return array{0: PaymentMethod|null, 1: string}
     */
    private function cardFor(Invoice $invoice, array $gateways): array
    {
        if ($invoice->pay_method_id) {
            $named = PaymentMethod::query()->find($invoice->pay_method_id);

            if ($named === null || (int) $named->client_id !== (int) $invoice->client_id) {
                return [null, 'named_card_missing'];
            }

            if ($named->status !== PaymentMethod::STATUS_ACTIVE) {
                return [null, 'named_card_needs_the_customer'];
            }

            if (! isset($gateways[strtolower((string) $named->gateway_name)])) {
                return [null, 'named_card_gateway_unavailable'];
            }

            return [$named, ''];
        }

        // find() ignores a soft delete, so a removed card cannot arrive here;
        // the default query below is likewise limited to live rows. A card in
        // the bin is a card the customer told us to stop using, and deleting it
        // at this end does not detach it at the gateway — the token would very
        // probably still be accepted, which is exactly why this has to refuse
        // rather than leave it to the gateway to.
        $cards = PaymentMethod::query()
            ->where('client_id', $invoice->client_id)
            ->where('status', PaymentMethod::STATUS_ACTIVE)
            ->whereIn('gateway_name', array_keys($gateways))
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        if ($cards->isEmpty()) {
            return [null, 'no_chargeable_card'];
        }

        if ($cards->count() === 1) {
            return [$cards->first(), ''];
        }

        $default = $cards->firstWhere('is_default', true);

        if ($default !== null) {
            return [$default, ''];
        }

        // Several cards and nothing saying which. Charging the oldest, or the
        // newest, would be a guess made with somebody's money — and a customer
        // who is charged on a card they did not expect disputes it, which costs
        // far more than a collection that waited. One click on the client's
        // payment methods page fixes it, so it is reported rather than guessed.
        Log::warning('AutoCharge: the client has several stored cards and none is the default', [
            'invoice' => $invoice->id,
            'client' => $invoice->client_id,
            'cards' => $cards->pluck('id')->all(),
        ]);

        return [null, 'no_default_card'];
    }

    /**
     * The gateways that may be asked to charge a card unattended.
     *
     * usableGateways() is the installation's own answer to "is this gateway on
     * and does it have its keys", already used to decide what the cart offers.
     * A gateway an operator has switched off must not be quietly charging
     * cards every morning.
     *
     * @return array<string, TokenizableGatewayInterface>
     */
    private function chargeableGateways(): array
    {
        // ONE DEFINITION, TWO READERS. The client portal decides whether to
        // offer card storage from the same list, so that it can never offer to
        // store a card with a gateway this would not present it to, and this
        // can never present a card the portal would not have offered to store.
        return $this->modules->tokenisedGateways();
    }

    /**
     * Keep the run at two requests a second.
     *
     * Between requests, not before the first and not after the last, and only
     * counted when a request is actually made — an invoice refused before the
     * gateway is reached costs nothing and should not slow the run down. N
     * charges therefore cost N-1 waits, which is what the pacing test measures.
     *
     * Illuminate\Support\Sleep rather than usleep() so the wait is real in
     * production and assertable in a test; nothing about the pacing has to be
     * taken on trust.
     *
     * A queued job per invoice was the other candidate and is the wrong tool
     * here. There is no app/Jobs directory in this codebase — nothing has ever
     * been queued — and the queue connection is 'sync' in .env.example:51,
     * 'database' in config/queue.php:16 and 'redis' in this working copy, which
     * means the pacing would be a different thing on every installation. On
     * sync it would be nothing at all: SyncQueue::later() ignores the delay and
     * runs the job immediately (vendor/laravel/framework/src/Illuminate/Queue/
     * SyncQueue.php:251-254), so every charge would fire in one burst inside
     * the cron process — precisely what this exists to prevent, and silently.
     */
    private function pace(): void
    {
        if ($this->presented > 0) {
            Sleep::for(self::REQUEST_SPACING_MS)->milliseconds();
        }

        $this->presented++;
    }
}
