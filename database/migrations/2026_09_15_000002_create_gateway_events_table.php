<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every webhook a gateway has delivered, and how far that delivery got.
 *
 * Gateways retry. Stripe redelivers an event until it gets a 2xx, and it will
 * happily deliver the same event twice on a slow response, so anything that
 * takes money or changes a subscription has to be able to recognise a repeat.
 * PaymentService already refuses a transaction id it has seen, which covers a
 * payment arriving twice — but an event that is not a payment (a card updated,
 * a subscription cancelled, an invoice finalised) has nothing standing between
 * it and being applied again.
 *
 * Two unique keys, because a gateway repeats itself in two different ways:
 *
 *   (gateway, event_id)              the same delivery arriving again
 *   (gateway, object_id, event_type) a second event of the same kind about the
 *                                    same object — a retry given a fresh id,
 *                                    which the event id alone would not catch
 *
 * MySQL has no partial indexes, so the second key cannot be conditional. It
 * does not need to be: MySQL counts NULLs as distinct in a unique index, so
 * events that carry no object id never collide with each other, which is
 * exactly what should happen to them.
 *
 * The two timestamps are what keep a repeat and a rescue apart, and they are
 * not the same fact. claimed_at says a delivery took the work; processed_at
 * says it finished. A table that only recorded the first would swallow the one
 * delivery that most needs to run again — the retry that follows a request
 * which crashed halfway — so a claim is a lease that expires rather than a
 * permanent record of having been seen. GatewayEvent::claim() holds the rules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 50);
            // The gateway's id for this delivery (Stripe: evt_...).
            $table->string('event_id', 191);
            $table->string('event_type', 100);
            // What the event is about (Stripe: pi_..., sub_..., cus_...).
            $table->string('object_id', 191)->nullable();
            // When a delivery took this event. Past the lease, another delivery
            // may take over: the one that set this never came back.
            $table->timestamp('claimed_at')->nullable();
            // Filled when the handling finished. A row with this still null and
            // a cold claimed_at is work that was abandoned, which is worth
            // being able to find as much as it is worth being able to redo.
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'event_id']);
            $table->unique(['gateway', 'object_id', 'event_type']);
            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_events');
    }
};
