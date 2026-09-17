<?php

namespace App\Enums;

/**
 * What a charger found when it tried to take an invoice for charging.
 *
 * Only one of these means "charge the card". The other three all mean "leave it
 * alone", and they are kept apart because they are not the same fact and an
 * operator asking "why was this invoice not charged this morning?" deserves the
 * real one. Held in particular is never to be reported as a refusal: it means a
 * charge for this invoice may be happening at this moment, which is the one
 * situation where doing nothing is the whole job.
 */
enum ChargeClaim: string
{
    /**
     * This worker holds the invoice: a first attempt, a retry that came due, a
     * card that changed, or a proven replay.
     *
     * Four things and one value, because the answer to "may I charge?" is the
     * same for all four. The one place the difference matters is what the
     * gateway is told about the request it is being handed, and that is read
     * from the row itself — InvoiceChargeAttempt::repeatsARequestAlreadySent()
     * — rather than carried here, so that the fact has one home and cannot
     * drift between two.
     */
    case Taken = 'taken';

    /** Another worker is inside the lease. Nothing to do, and nothing decided either. */
    case Held = 'held';

    /** There is an attempt row and its next attempt is still in the future. */
    case NotDue = 'not_due';

    /** Finished with: paid, waiting on the cardholder, given up on, or waiting on a person. */
    case Closed = 'closed';

    /**
     * An attempt was sent for this invoice, its outcome was never established,
     * and this worker is the one that has just parked it for a person.
     *
     * Closed would have been true of it and is what it used to answer, but it
     * is not the same fact and the difference is the whole of the operator's
     * side of this feature: Closed means somebody else has dealt with it, and
     * this means money may have moved and nobody knows. The charger counts and
     * announces this one rather than folding it in with the invoices that were
     * paid. Only the worker whose write landed is told Parked — a worker that
     * lost the race gets Closed, so one event produces one alert.
     */
    case Parked = 'parked';

    /**
     * May the worker holding this outcome charge the card?
     */
    public function mayProceed(): bool
    {
        return $this === self::Taken;
    }
}
