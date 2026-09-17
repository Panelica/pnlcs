<?php

namespace App\Contracts;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\PaymentMethod;

/**
 * Optional capability: a gateway that can vault a customer's payment method
 * and later charge it while the customer is not there.
 *
 * Kept separate from GatewayModuleInterface so that the modules which cannot
 * do this — bank transfer, the redirect-only gateways, anything a third party
 * has written against the existing contract — stay valid. isTokenised() on
 * GatewayModuleInterface only says a gateway keeps a handle to a card; it says
 * nothing about being able to charge that handle unattended, which is a
 * different promise and needs different error handling.
 */
interface TokenizableGatewayInterface
{
    /**
     * Begin storing a payment method for a client.
     *
     * Nothing is charged here. The gateway is asked to open a session the
     * browser can finish — for Stripe a SetupIntent — and to hand back the
     * customer record the stored method will hang off, so the same client
     * keeps one gateway customer rather than a new one per card.
     *
     * @return array{
     *     success: bool,
     *     message?: string,
     *     client_secret?: string|null,
     *     customer_id?: string|null,
     *     setup_intent_id?: string|null
     * }
     */
    public function beginVaulting(Client $client): array;

    /**
     * Finish storing a payment method, from the browser's word that it worked.
     *
     * beginVaulting() opens the session and the gateway's own webhook closes
     * it; this is the third door, and it exists because the first two do not
     * meet on every installation. A shop whose webhook endpoint has not been
     * registered with the gateway — or whose gateway is retrying a delivery
     * that has not arrived yet — would leave a customer looking at a card they
     * have just entered and a page that does not list it. The customer stores
     * it again, and again, and each attempt leaves another card on the
     * gateway's customer record.
     *
     * THE BROWSER IS NOT BELIEVED. What arrives from it is an id and nothing
     * else; the implementation must fetch that session from the gateway and
     * act on what the gateway says about it. A confirmation that the gateway
     * does not recognise, or that it says is unfinished, stores nothing — and
     * neither does one the gateway says belongs to a different client than the
     * one asking, which is why the client is passed rather than checked by the
     * caller afterwards: by then the card would already have been written.
     *
     * AND IT MUST WRITE WHAT THE WEBHOOK WRITES, by the same route. Two paths
     * that both create a stored method are two chances to create two rows for
     * one card, or to disagree about what a stored card looks like. The
     * implementation is expected to hand the fetched object to the same
     * handler the webhook feeds, so that whichever arrives second changes
     * nothing.
     *
     * @return array{
     *     success: bool,
     *     message?: string,
     *     client_id?: int|null,
     *     customer_id?: string|null
     * }
     */
    public function confirmVaulting(Client $client, string $sessionId): array;

    /**
     * Stop keeping a stored method at the gateway.
     *
     * Deleting the row at this end only stops PNLCS from using the token; the
     * gateway still holds the card against the customer. A customer who asks
     * for their card to be removed is asking for the second thing as well, and
     * this is the call that does it.
     *
     * Called from a scheduled sweep and never from the customer's own request,
     * so it may take as long as it takes. It must be safe to call twice: the
     * sweep retries until it is told the method is gone, and "the gateway has
     * never heard of it" is a success, not a failure — there is nothing left
     * to detach.
     *
     * retryable says whether the sweep should come back to this row. False
     * means the answer will not change, so the row is marked done and stops
     * being asked about; true leaves it outstanding.
     *
     * @return array{
     *     success: bool,
     *     message?: string,
     *     retryable?: bool
     * }
     */
    public function detachStoredMethod(PaymentMethod $method): array;

    /**
     * Charge a stored payment method for an invoice with the customer absent.
     *
     * Off-session charging has three meaningful outcomes and collapsing them
     * into success/failure loses the one that matters most. A card can be
     * declined for good (the invoice stays unpaid and retrying tomorrow
     * changes nothing), it can be declined for now (insufficient funds — worth
     * another attempt), or the bank can demand the cardholder authenticate,
     * which is not a failure at all: the money is still available, the
     * customer simply has to come back and finish 3-D Secure. Telling a
     * customer their card was declined when their bank only wanted a
     * confirmation is how a renewal turns into a cancellation.
     *
     * status is therefore the field to branch on:
     *   'succeeded'       — money taken; transaction_id settles the invoice
     *   'requires_action' — nothing taken yet; transaction_id names the intent
     *                       the customer must come back and confirm
     *   'failed'          — nothing taken; decline_code says why and retryable
     *                       says whether trying again is worth anything
     *
     * AND ONE FLAG THAT OVERRIDES ALL OF THEM. outcome_unknown is the contract's
     * name for "we do not know whether the card was charged", and a module owes
     * it on EVERY answer that leaves that unknown rather than on the one or two
     * a particular module happened to think of first. Three shapes produce it
     * and they are worth listing, because each was at some point handled as
     * though it were something else:
     *
     *   - no answer at all: a dropped connection or a read timeout, where the
     *     request may have reached the gateway and the gateway may have reached
     *     the payment network;
     *   - an answer that does not say what happened: an HTTP 5xx above all.
     *     Stripe's is the documentation to read on this — "You should treat the
     *     result of a `500` request as indeterminate ... if creating a charge
     *     returns a `500` error but we detect that the information has gone out
     *     to a payment network, we'll try to roll it forward"
     *     (https://docs.stripe.com/error-low-level, fetched 2026-09-17) — and
     *     any gateway's equivalent belongs here too;
     *   - an accepted payment that has not finished: 'processing' and its kin,
     *     where the money is neither taken nor refused yet.
     *
     * It is not a fourth status because a module that does not set it must not
     * accidentally mean it, and because every caller that reads ['success']
     * still gets false. A module MUST set it rather than reporting a retryable
     * failure whenever money may already have moved: a failure is a promise
     * that nothing was taken, and scheduling a fresh attempt on the strength of
     * it is how a customer is charged twice. Its own transaction_id is left out
     * on purpose unless the money is known to be taken — an id on an unfinished
     * payment invites a caller to credit an invoice against money that may
     * never arrive.
     *
     * retryable IS NOT THE PLACE FOR ANY OF THIS, and a module that is tempted
     * to write retryable => true for a gateway outage should read the caller
     * first. It does not mean "send this again now"; it means "raise a fresh
     * charge in AutoCharge::retryDays() days", which is deliberately beyond the
     * twenty-four hours a gateway keeps an idempotency key, so the repeat is a
     * new charge rather than a replay. That is correct for a decline the issuer
     * actually made, and it is a second debit for anything indeterminate.
     *
     * What the caller does with it is not retry it tomorrow. AutoChargeService
     * leaves the attempt in flight and lets the rescue sweep replay it inside
     * the gateway's own idempotency window, where the repeat is answered out of
     * the first request's saved result rather than charging again; when that
     * window closes with the answer still unknown, the invoice goes to a person
     * and no card is touched.
     *
     * success mirrors status === 'succeeded' so that a caller which only reads
     * ['success'], the way every other gateway call is read, can never mistake
     * an unfinished authentication for payment.
     *
     * AND ONE THING THE CALLER OWES THE MODULE, because no module can work it
     * out for itself. $params['replay'] === true says: you have already sent
     * this exact charge, under the same idempotency key, and nobody heard what
     * became of it — this POST is the repeat. A module is stateless across
     * calls by nature; the caller holds the history, and without it the module
     * is answering "did this request take money?" when the question is "did the
     * earlier one?". Those have different answers for every response a gateway
     * can produce before it consults its record of the first request: a rate
     * limit, an expired key, its own idempotency layer refusing this
     * presentation. A module that is told replay MUST answer outcome_unknown
     * for all of them, and MUST go on resolving the row exactly as before for
     * the answers that do come from that record — the saved result, a real
     * decline from the issuer — or crashed attempts that genuinely declined
     * would stop being collected. A module that ignores the key behaves as it
     * always has, which is why the key is the caller's promise rather than the
     * module's; AutoChargeService sends it on every call and its own tests
     * count requests at the gateway rather than POSTs, so the promise cannot be
     * dropped silently.
     *
     * AND THE OTHER HALF OF THAT PROMISE IS THE KEY ITSELF.
     * $params['idempotency_key'] is the name the caller has already sent this
     * charge under, and a module that is given one MUST send that string rather
     * than one of its own: saying "this is a repeat" is worth nothing if the
     * repeat arrives under a different name, since the gateway has no saved
     * result filed under it and executes the charge. A module that works the
     * key out for itself can only work it out from what it has in front of it,
     * and anything it reads may have moved since the first send — StripeModule
     * hashed the figures under config('app.key'), and an application key
     * rotated between a send and its replay was a second real debit. A caller
     * that supplies 'replay' => true and no key is asking for a charge that
     * cannot be proved to be a repeat; the module must refuse to send it and
     * answer outcome_unknown, which leaves the caller's row to the machinery
     * that already owns unknown outcomes. A caller with no memory of its own
     * supplies neither, and gets whatever the module has always done.
     *
     * @param  float  $amount  Amount to take, in the invoice's currency
     * @param  array  $params  ['replay' => bool, 'idempotency_key' => ?string]
     *                         above, plus gateway-specific extras, as capture()
     *                         takes them
     * @return array{
     *     success: bool,
     *     status: string,
     *     message?: string,
     *     transaction_id?: string|null,
     *     amount?: float,
     *     decline_code?: string|null,
     *     retryable?: bool,
     *     outcome_unknown?: bool
     * }
     */
    public function chargeStoredMethod(Invoice $invoice, PaymentMethod $method, float $amount, array $params = []): array;
}
