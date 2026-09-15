<?php

namespace App\Models;

use App\Enums\GatewayEventClaim;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * One row per webhook a gateway has delivered, and how far that delivery got.
 *
 * Gateways retry until they get a 2xx, and they will redeliver an event that
 * was in fact handled but answered too slowly. Anything that moves money or
 * changes a subscription therefore has to be able to recognise a repeat before
 * it acts. The two unique keys on the table do the recognising.
 *
 * What the row means is the part worth being exact about. It means "a delivery
 * of this event took the work"; processed_at means "and finished it". A row
 * that only ever meant "seen" turns a crashed request into a permanently lost
 * event: the delivery that died left its claim behind, the gateway's retry saw
 * the claim and did nothing, and whatever that event was carrying — a payment,
 * most of all — was never recorded anywhere. The delivery a gateway retries
 * after a 500 is precisely the one that most needs to run again.
 *
 * So a claim is a lease, not a gravestone. It keeps two deliveries that arrive
 * together from doing the work twice, and it expires so that a delivery which
 * never came back cannot hold an event hostage. Stripe redelivers "for up to
 * three days with an exponential back off in live mode"
 * (https://docs.stripe.com/webhooks), which is what makes an expiring claim
 * self-healing rather than merely hopeful: a later retry is guaranteed to find
 * the lease cold and take over.
 */
class GatewayEvent extends Model
{
    /**
     * How long a delivery may hold an event before another one may take over.
     *
     * Chosen from our own side of the wire rather than the gateway's: no single
     * webhook request can still be alive after five minutes — PHP's execution
     * limit is a fraction of that — so a claim this old belongs to a request
     * that is gone. Erring long is the safe direction. A lease that outlives a
     * crash by a few minutes only delays the healing retry, because the gateway
     * goes on knocking for days; a lease shorter than a live request would let
     * two deliveries run side by side, and not being two is the entire job.
     */
    public const CLAIM_LEASE_SECONDS = 300;

    protected $fillable = [
        'gateway', 'event_id', 'event_type', 'object_id', 'claimed_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Take this event, unless it is already somebody else's.
     *
     * The insert IS the check. Asking first and writing afterwards leaves a gap
     * that two deliveries of the same event arriving together fit through
     * neatly — both read nothing, both go on to do the work — and a gateway
     * retrying on a slow response is exactly how those two arrive together. The
     * unique keys decide, and a duplicate-key error is an answer rather than a
     * fault.
     *
     * What that answer means depends on the row already there. One that carries
     * a processed_at is finished, and nothing more will ever be done about it.
     * One claimed a moment ago belongs to a delivery that is working on it right
     * now, and running alongside that delivery is the double credit this table
     * exists to prevent. One claimed long enough ago to be past the lease
     * belongs to a delivery that never came back, and this one takes over.
     *
     * The takeover is a conditional update rather than a read followed by a
     * write, so two retries racing to rescue the same abandoned event still
     * produce exactly one winner: the loser's update matches no row, because the
     * winner has already moved claimed_at out from under its WHERE clause.
     *
     * Which of the three it found is what comes back, because the two this
     * delivery may not act on want opposite answers given to the gateway. A
     * finished event is a repeat and should stop being sent; a held one is a
     * delivery still in flight, which may be the delivery that crashed, and
     * telling the gateway that one landed is how a payment gets lost. Collapsing
     * both into "no" was exactly that bug.
     *
     * @return GatewayEventClaim which of the three states this delivery met
     */
    public static function claim(
        string $gateway,
        string $eventId,
        string $eventType,
        ?string $objectId = null,
        int $leaseSeconds = self::CLAIM_LEASE_SECONDS,
    ): GatewayEventClaim {
        $now = now();

        try {
            static::create([
                'gateway' => $gateway,
                'event_id' => $eventId,
                'event_type' => $eventType,
                'object_id' => $objectId,
                'claimed_at' => $now,
            ]);

            return GatewayEventClaim::Taken;
        } catch (UniqueConstraintViolationException) {
            // Somebody has this event already. Which of the two unique keys
            // caught it settles nothing; the state of the row it caught on does.
            //
            // Only that one exception, and on purpose. Every other database
            // fault — the table not there yet because the migration has not run,
            // a connection lost, a deadlock — is left to escape, because the
            // only honest thing this method can say about an event it could not
            // write down is nothing. The caller turns an escaped throwable into
            // a 500 and the gateway comes back for days
            // (https://docs.stripe.com/webhooks: "for up to three days with an
            // exponential back off in live mode"), so the outage heals itself
            // the moment the database does. Treating a failed write as "nobody
            // else has this" is the shape of the hole this table exists to
            // close: two deliveries would both proceed.
        }

        $expired = $now->copy()->subSeconds($leaseSeconds);

        $taken = static::matching($gateway, $eventId, $eventType, $objectId)
            ->whereNull('processed_at')
            ->where(fn (Builder $lease) => $lease
                ->whereNull('claimed_at')
                ->orWhere('claimed_at', '<=', $expired))
            ->update(['claimed_at' => $now]);

        if ($taken > 0) {
            return GatewayEventClaim::Taken;
        }

        // The update matched nothing, which leaves two reasons and no way to
        // tell them apart without looking: the row is finished, or a live lease
        // holds it. Reading it now rather than inside the update is safe in both
        // directions. A finished row stays finished — nothing ever unsets
        // processed_at — and a row another rescuer took between the update and
        // this read is held, which is the answer being given anyway.
        //
        // Held is the default, and deliberately so. It is the reading that costs
        // a redelivery and the other is the reading that costs a payment, so
        // anything unexpected here — a row pruned out from under this request,
        // a collision on the object key whose row belongs to a delivery still
        // running — comes back as "come again" rather than as "all done".
        $finished = static::matching($gateway, $eventId, $eventType, $objectId)
            ->whereNotNull('processed_at')
            ->exists();

        return $finished ? GatewayEventClaim::Finished : GatewayEventClaim::Held;
    }

    /**
     * Stamp an event as finished.
     *
     * Addressed the same way it was claimed, because the two need not be the
     * same row by event id: a gateway that retries an event under a fresh id
     * collides on the object key instead, and the row this delivery took over
     * is the one carrying the first id.
     */
    public static function markProcessed(string $gateway, string $eventId, string $eventType = '', ?string $objectId = null): void
    {
        static::matching($gateway, $eventId, $eventType, $objectId)
            ->update(['processed_at' => now()]);
    }

    /**
     * The row (or rows, in theory) this delivery collides with.
     *
     * Mirrors the table's two unique keys: the same delivery arriving again, or
     * a second event of the same kind about the same object. The object arm is
     * only added when there is an object id to match on, because a NULL matches
     * nothing in a unique index and must not be allowed to match everything
     * here.
     */
    private static function matching(string $gateway, string $eventId, string $eventType, ?string $objectId): Builder
    {
        return static::query()
            ->where('gateway', $gateway)
            ->where(function (Builder $query) use ($eventId, $eventType, $objectId) {
                $query->where('event_id', $eventId);

                if ($objectId !== null && $eventType !== '') {
                    $query->orWhere(fn (Builder $sameObject) => $sameObject
                        ->where('object_id', $objectId)
                        ->where('event_type', $eventType));
                }
            });
    }
}
