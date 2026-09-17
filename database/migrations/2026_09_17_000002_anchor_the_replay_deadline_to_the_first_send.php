<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two columns that let an abandoned charge stop being replayed.
 *
 * invoice_charge_attempts already had claimed_at, and it was made to do two
 * jobs that pull in opposite directions. As a LEASE it must be refreshed every
 * time a rescuer takes the row, because that refresh is what makes two
 * rescuers produce one winner. As the start of the REPLAY DEADLINE it must
 * never move, because the deadline is Stripe's idempotency retention and their
 * clock started when the first request was sent.
 *
 * It was refreshed, so the deadline moved with it. Every fifteen-minute sweep
 * (routes/console.php) replayed the charge and stamped claimed_at with the
 * time it did so, which left the row permanently fifteen minutes old and the
 * twenty-three-hour window permanently open. Simulated over three days of the
 * real schedule against a gateway that never answered: 289 presentations of
 * one charge, the row still in_flight, nothing ever handed to a person. Past
 * the twenty-fourth hour those presentations are not replays at all — Stripe
 * "generate[s] a new request if a key is reused after the original is pruned"
 * (https://docs.stripe.com/api/idempotent_requests, fetched 2026-09-17) and
 * StripeModule's key has no time component in it — so the customer is debited
 * twice for one invoice while the panel shows one payment.
 *
 * So the two jobs are given a column each.
 *
 *   first_sent_at  when the request now in flight FIRST went to the gateway.
 *                  Set with the claim, untouched by a replay, reset by
 *                  restart() because restart IS a new request. The deadline is
 *                  measured from here and nothing can postpone it.
 *
 *   replays        how many times that request has been handed back since.
 *                  Capped, so a gateway that answers nothing is asked a fixed
 *                  number of times and then a person is fetched, rather than
 *                  ninety-two times a day under a row that AutoCharge's
 *                  max-attempts setting still counts as one attempt.
 *
 * Neither replaces the other; InvoiceChargeAttempt::MAX_REPLAYS carries the
 * argument for why both are here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_charge_attempts', function (Blueprint $table) {
            // Nullable in the schema and never null in practice: the model
            // defaults it on insert. Nullable because this table may already
            // hold rows and a timestamp column cannot be added NOT NULL over
            // them without inventing a value in DDL; the backfill below is
            // where that value is chosen, in the open, with a reason.
            $table->timestamp('first_sent_at')->nullable()->after('claimed_at');

            // Small rather than tiny for no better reason than that attempts
            // beside it is small; the cap is four.
            $table->unsignedSmallInteger('replays')->default(0)->after('attempts');
        });

        // WHAT AN EXISTING IN-FLIGHT ROW'S DEADLINE SHOULD BE, and why it is
        // created_at rather than the two nearer-looking candidates.
        //
        // claimed_at is the obvious guess and is the one value that must not be
        // used. On a row that has been through the defect it is fifteen minutes
        // old however long the charge has really been outstanding, so it would
        // carry the bug across the migration and hand the row another
        // twenty-three hours of replays against a key pruned yesterday.
        //
        // NULL would be safe — a null first_sent_at refuses every replay — but
        // it parks every in-flight row on the next sweep, including the ones
        // claimed four minutes ago whose replay is provably safe and is the
        // whole point of the design. That is an alert storm and a pile of
        // manual gateway checks bought for nothing.
        //
        // created_at is the row's own floor: the attempt in flight cannot have
        // been sent before the row that records it existed. It is never later
        // than the true first send, so it can only ever make the deadline
        // arrive EARLIER than it should — which is the direction that costs an
        // invoice a human rather than a customer a second charge. It is too
        // early only for a row that has been restarted for a later dunning
        // attempt, and that row would be parked at worst; restart() writes the
        // real value from here on.
        DB::table('invoice_charge_attempts')->update(['first_sent_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('invoice_charge_attempts', function (Blueprint $table) {
            $table->dropColumn(['first_sent_at', 'replays']);
        });
    }
};
