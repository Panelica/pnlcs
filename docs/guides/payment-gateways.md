# Payment Gateways

A **gateway** is how you get paid. You need at least one enabled before real
customers can order. Configure them under **Configuration → Gateways**.

## Stripe

The most fully tested gateway.

1. Create a [Stripe](https://stripe.com) account.
2. In the Stripe dashboard, copy your **Publishable key** and **Secret key**
   (use **test mode** keys first).
3. In PNLCS, open **Configuration → Gateways → Stripe**, paste both keys, and enable it.
4. **Webhook (recommended):** in Stripe, add a webhook endpoint pointing to
   `https://your-domain/gateway/stripe/webhook` so payments confirm reliably.

Test with card `4242 4242 4242 4242`, any future expiry, any CVC.

## PayPal

1. Create a PayPal app at the [PayPal Developer](https://developer.paypal.com) portal.
2. Copy the **Client ID** and **Secret** (sandbox first).
3. In **Configuration → Gateways → PayPal**, paste them, choose sandbox or live, enable.
4. **Webhook:** point a PayPal webhook at
   `https://your-domain/gateway/paypal/webhook`.

!!! note "Payments are verified"
    PNLCS re-checks every PayPal capture directly with PayPal before marking an
    invoice paid, so a forged callback can't create a free order.

## Authorize.Net

Enter your **API Login ID** and **Transaction Key** under
**Configuration → Gateways → Authorize.Net**.

## Bank Transfer

For manual/offline payment:

1. Enable **Bank Transfer** and enter the account details/instructions shown to
   customers at checkout.
2. When a customer pays, they can submit a **payment notification** (with an
   optional receipt) from their invoice.
3. You review it under **Billing → Payment Notifications** and approve it in one
   click — which marks the invoice paid and triggers provisioning.

## Testing before you go live

Always place a **test order** and pay it in the gateway's test/sandbox mode
before accepting real money. Confirm the invoice becomes *Paid* and (if you
sell hosting) the service is provisioned. Then switch to live keys.

## Refunds

Any paid invoice can be refunded — fully or partially — from the admin invoice
page. Gateway refunds go back through the original provider; bank-transfer
payments are refunded offline. See
[The Billing Lifecycle](../concepts/billing-lifecycle.md#refunds).
