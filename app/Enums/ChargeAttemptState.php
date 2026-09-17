<?php

namespace App\Enums;

/**
 * Where an invoice stands with the card that is supposed to pay it.
 *
 * Six states and not one of them is decoration: each answers the charger's
 * only question — may this invoice be charged right now, and if not, will it
 * ever be — differently, and two of them are the difference between a customer
 * who is chased and a customer who is left alone.
 *
 * The pair worth being careful about is ActionRequired and Exhausted. Both mean
 * "no unattended attempt will be made", and collapsing them would be the same
 * mistake GatewayEventClaim was written to undo. A card that needs its owner to
 * authenticate is a working card with money behind it; telling that customer
 * their card was declined, or quietly giving up on the renewal, loses a
 * subscription that was never in trouble.
 */
enum ChargeAttemptState: string
{
    /** A charger holds this invoice and the outcome is not known yet. */
    case InFlight = 'in_flight';

    /** The last attempt failed for a reason worth trying again; next_attempt_at says when. */
    case Scheduled = 'scheduled';

    /** The cardholder's bank wants them to authenticate. Nothing unattended can satisfy that. */
    case ActionRequired = 'action_required';

    /** No further automatic attempt: the card is finished, or the attempts are used up. */
    case Exhausted = 'exhausted';

    /** The money was taken. */
    case Succeeded = 'succeeded';

    /**
     * An attempt was sent, its outcome was never written down, and replaying it
     * could not be proven to be the same request rather than a second charge.
     * Only a person may clear this, after looking at the gateway.
     */
    case NeedsReview = 'needs_review';

    /**
     * Is the charger finished with this invoice unless something changes?
     *
     * "Unless something changes" is doing real work: a customer who stores a
     * different card reopens ActionRequired and Exhausted, because the verdict
     * belonged to the old card. Succeeded and NeedsReview are not reopened by
     * anything — the first because the invoice is paid and the second because a
     * charge whose fate is unknown stays unknown until a person says otherwise.
     */
    public function isFinished(): bool
    {
        return $this !== self::InFlight && $this !== self::Scheduled;
    }

    /**
     * May a new card give this invoice a fresh start?
     *
     * Succeeded must never be reopened: the invoice is paid, and a second
     * charge is the thing this whole table exists to prevent. NeedsReview must
     * never be reopened automatically either — the unresolved attempt is still
     * unresolved, and charging a new card on top of a charge that may have
     * gone through is how a customer pays twice. InFlight is not a verdict at
     * all, so there is nothing to reopen.
     */
    public function reopensForAnotherCard(): bool
    {
        return $this === self::Scheduled
            || $this === self::ActionRequired
            || $this === self::Exhausted;
    }
}
