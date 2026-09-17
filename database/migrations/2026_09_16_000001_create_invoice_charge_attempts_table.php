<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The arbitrator that makes charging an invoice safe to run twice.
 *
 * PNLCS already raises the renewal invoices — InvoiceGenerationService does it
 * from the services, addons and domains that are due, and routes/console.php
 * runs it every morning. What has never existed is anything that pays one of
 * them with a card the customer has already stored. That collection step is
 * the dangerous half: the generator writing a second invoice is an
 * embarrassment, a charger sending a second charge is somebody's money.
 *
 * This table is what stands between the two. Before a card is charged for an
 * invoice the charger has to take the invoice here, and taking it is an insert
 * that the unique key either allows or refuses. A second run — a cron that
 * overlapped, an operator running the command by hand while it is already
 * running, a queue worker restarted mid-flight — loses on the key rather than
 * on being slower.
 *
 * ONE ROW PER INVOICE, NOT ONE PER ATTEMPT, and that is forced rather than
 * chosen. An attempt ledger would be the nicer record, but then "only one
 * attempt may be in flight for this invoice" has to be expressed as a unique
 * index over the in-flight rows only, and MySQL has no partial index to hang
 * that condition on. Adding a discriminator column that is set while in flight
 * and NULL afterwards does not rescue it either: MySQL counts NULLs as
 * distinct in a unique index, so every finished attempt would be unique
 * against every other one and the constraint would stop constraining the
 * moment it was needed. invoice_id is NOT NULL and unique, which is a
 * constraint MySQL actually enforces. What is lost is per-attempt history;
 * what is kept is the count, the last outcome and the reason, which is what
 * the dunning policy and the operator read. The blow-by-blow stays in the
 * application log, where StripeModule already writes it.
 *
 * WHICH METHOD PAYS AN INVOICE IS NOT DECIDED HERE. invoices.pay_method_id has
 * been in the schema since 2026_03_31_060004 and read by nothing; it is the
 * column that says which stored method this invoice is to be paid with, and the
 * charger will read it. payment_method_id below is a different fact: the card
 * the attempts recorded on this row were actually made against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_charge_attempts', function (Blueprint $table) {
            $table->id();

            // The whole point of the table. Unique: one arbitrator per invoice,
            // for all time. Cascade because an invoice that no longer exists
            // cannot be collected and its collection history means nothing —
            // and invoices themselves cascade from clients, so this keeps a
            // deleted client from leaving rows pointing at nothing.
            $table->foreignId('invoice_id')->unique()->constrained()->cascadeOnDelete();

            // The card the counters below belong to. Read by the claim: a
            // customer who replaces a card that was declined deserves a fresh
            // attempt rather than inheriting the dead card's exhausted count,
            // and without this column there is no way to tell that the card
            // changed. Nullable only because a hard-deleted method must not
            // take the history with it — payment_methods soft-deletes, so in
            // normal life this stays filled.
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();

            // Where this invoice's collection stands: in_flight, scheduled,
            // action_required, exhausted, succeeded, needs_review. App\Enums\
            // ChargeAttemptState carries the meanings and the transitions.
            $table->string('state', 20);

            // How many separate requests this invoice has caused at the
            // gateway. The dunning cap counts it, and it is stamped when an
            // attempt is taken rather than when it comes back, so a crash
            // cannot make an issuer see more attempts than we counted. The
            // one exception is the replay of an attempt whose outcome was
            // lost, which is the same request at the gateway and so is not a
            // second one here either.
            $table->unsignedSmallInteger('attempts')->default(0);

            // What the attempt on this row was for. These two exist to answer
            // one question and it is the worst question in the file: an
            // attempt was sent, the process died before the answer was
            // written, and the money may or may not have moved. Stripe's
            // idempotency layer can answer it — replaying the identical
            // request returns the saved result of the first one rather than
            // charging again — but only while the key is still held, and only
            // if the parameters match: "You can remove keys from the system
            // automatically after they're at least 24 hours old. We generate a
            // new request if a key is reused after the original is pruned. The
            // idempotency layer compares incoming parameters to those of the
            // original request and errors if they're not the same to prevent
            // accidental misuse"
            // (https://docs.stripe.com/api/idempotent_requests, fetched
            // 2026-09-16). StripeModule derives that key from the invoice, the
            // method, the amount in minor units and the currency, so those are
            // exactly the four facts a rescue has to compare. Two of them are
            // the row's own invoice and method; these are the other two. With
            // them a rescue is provable; without them the only honest answer
            // is to stop and fetch a human, which is what needs_review is.
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency', 3)->nullable();

            // When the attempt in flight was taken. A lease, not a gravestone —
            // the same reasoning gateway_events is built on. A claim that is
            // still warm means another worker is inside the charge this
            // minute and nothing may touch it; a claim gone cold means the
            // worker that took it never came back, and then it is this column
            // that says whether the rescue above is still inside Stripe's
            // window.
            $table->timestamp('claimed_at')->nullable();

            // The earliest the charger may take this invoice again. NULL means
            // never, automatically: every state that is finished with — paid,
            // waiting on the cardholder, given up on, handed to a human —
            // leaves it NULL, so the due sweep excludes them on the column it
            // is already scanning rather than on a state list it might forget
            // to update.
            $table->timestamp('next_attempt_at')->nullable();

            // The gateway's id for the last attempt. On a success it ties this
            // row to the transaction PaymentService recorded. On a
            // requires_action it is the PaymentIntent the cardholder has to
            // come back and confirm, which nothing else in the schema keeps:
            // no transaction row is written for a payment that has not
            // happened, so without this column the customer cannot be sent
            // back to finish the authentication and a perfectly good renewal
            // dies as a decline.
            $table->string('last_transaction_id', 191)->nullable();

            // How the last refusal was classified (insufficient_funds,
            // do_not_honor, authentication_required...). Retryability is
            // already decided by the time it is written, so this is not what
            // the charger branches on; it is what the customer is told and
            // what the operator sorts by. "Your bank declined the payment" and
            // "your card has expired" are different letters and lead to
            // different actions.
            $table->string('last_decline_code', 64)->nullable();

            // The gateway's own words, truncated on write. Most failures that
            // matter to an operator carry no decline code at all — a gateway
            // that could not be reached, a secret key that is not configured,
            // a card that was removed at this end — and for those this column
            // is the only explanation anyone gets.
            $table->string('last_message', 500)->nullable();

            $table->timestamps();

            // The charger's due sweep: scheduled rows whose time has come.
            $table->index(['state', 'next_attempt_at']);
            // The rescue sweep, and the operator's list of attempts that never
            // came back: in-flight rows whose lease has gone cold. It cannot
            // ride on the index above, because an in-flight row's
            // next_attempt_at is NULL by definition.
            $table->index(['state', 'claimed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_charge_attempts');
    }
};
