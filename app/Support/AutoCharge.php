<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The operator's switches for charging stored cards, read from one place.
 *
 * Four settings, and the reason there are only four is that every one of them
 * has an answer nobody can pick for the operator. Everything else a dunning
 * policy could expose — which hour to run at, whether to email on the second
 * failure rather than the first — already has an owner elsewhere in PNLCS or a
 * right answer that does not vary by host.
 *
 * The defaults are here as constants rather than sprinkled through the callers
 * because the settings screen shows a default before anything is saved, and a
 * screen that offers 3 while the code assumes 5 is a support ticket waiting to
 * be written.
 */
class AutoCharge
{
    /** Off. A host who has never heard of this feature must never have it happen to them. */
    public const DEFAULT_ENABLED = '0';

    /** How many days before the due date a card may first be charged. */
    public const DEFAULT_DAYS_BEFORE = 3;

    /** How many times one invoice's card may be asked the question before we stop. */
    public const DEFAULT_MAX_ATTEMPTS = 3;

    /** Days between attempts. */
    public const DEFAULT_RETRY_DAYS = 3;

    /**
     * A retry sooner than this is not a retry.
     *
     * Stripe holds an idempotency key for at least 24 hours and answers a
     * repeat of the same request with the saved result of the first one:
     * "Subsequent requests with the same key return the same result, including
     * 500 errors" (https://docs.stripe.com/api/idempotent_requests, fetched
     * 2026-09-16).
     * StripeModule builds that key from the invoice, the method, the amount and
     * the currency and nothing else, so a second attempt at the same invoice
     * for the same money inside the window is handed back yesterday's decline
     * without the card ever being asked again. An operator who sets the
     * interval to zero would get a dunning cycle that burns all its attempts in
     * one morning against a cached answer, and would be told the card failed
     * three times when it was asked once.
     */
    public const MINIMUM_RETRY_DAYS = 1;

    /** Is the feature switched on at all? */
    public static function enabled(): bool
    {
        return (bool) (int) Setting::get('AutoChargeEnabled', self::DEFAULT_ENABLED);
    }

    /**
     * How early an invoice may be charged, in days before its due date.
     *
     * InvoiceGenerationService raises renewal invoices up to fourteen days
     * ahead. Charging one the morning it is raised takes the money a fortnight
     * before the customer expected it, so the charger needs a separate and
     * later moment of its own. Zero is meaningful and allowed: charge on the
     * due date and not before.
     */
    public static function daysBeforeDue(): int
    {
        return max(0, (int) Setting::get('AutoChargeDaysBefore', self::DEFAULT_DAYS_BEFORE));
    }

    /**
     * How many separate attempts one invoice's card may be put through.
     *
     * Not unlimited, and not the operator's business to set to zero: card
     * networks cap reattempts of a single charge and issuers read a card that
     * is asked repeatedly after a refusal as fraud, which drags down the
     * acceptance rate of the cards that would have worked.
     *
     * AN ATTEMPT IS NOT A REQUEST, and this used to say it was. An attempt
     * whose outcome never came back is replayed by the rescue sweep — the same
     * request, under the same idempotency key, answered out of the gateway's
     * record of the first one — up to InvoiceChargeAttempt::MAX_REPLAYS times
     * before the row is handed to a person, so one attempt can account for as
     * many as 1 + MAX_REPLAYS requests on the wire while the issuer is asked
     * once. That is not this setting being exceeded; it is the difference
     * between asking a card a question and asking the gateway what its answer
     * was. What this bounds is what the ISSUER sees, which is the thing that
     * costs a merchant its acceptance rate.
     */
    public static function maxAttempts(): int
    {
        return max(1, (int) Setting::get('AutoChargeMaxAttempts', self::DEFAULT_MAX_ATTEMPTS));
    }

    /** Days between attempts, floored at the point where a retry stops being one. */
    public static function retryDays(): int
    {
        return max(self::MINIMUM_RETRY_DAYS, (int) Setting::get('AutoChargeRetryDays', self::DEFAULT_RETRY_DAYS));
    }
}
