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
     * success mirrors status === 'succeeded' so that a caller which only reads
     * ['success'], the way every other gateway call is read, can never mistake
     * an unfinished authentication for payment.
     *
     * @param  float  $amount  Amount to take, in the invoice's currency
     * @param  array  $params  Gateway-specific extras, as capture() takes them
     * @return array{
     *     success: bool,
     *     status: string,
     *     message?: string,
     *     transaction_id?: string|null,
     *     amount?: float,
     *     decline_code?: string|null,
     *     retryable?: bool
     * }
     */
    public function chargeStoredMethod(Invoice $invoice, PaymentMethod $method, float $amount, array $params = []): array;
}
