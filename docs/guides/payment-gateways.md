# Payment Gateways

A **gateway** is how you get paid. At least one must be enabled before real
customers can order. Configure them under **Setup → Payment Gateways**: open a
gateway, fill in its keys, save and enable it.

!!! tip "Test before you take real money"
    Every card gateway below has a test or sandbox mode. Place a test order,
    pay it in test mode, and check that the invoice becomes *Paid* and, if you
    sell hosting, that the service is provisioned. Then switch to live keys.
    Checkout must run over HTTPS.

## What each gateway asks for

| Gateway | Fields | Webhook |
|---|---|---|
| **Stripe** | Publishable Key, Secret Key, Webhook Signing Secret | `/gateway/stripe/webhook` |
| **PayPal** | PayPal Email, Client ID, Client Secret, Sandbox Mode | `/gateway/paypal/webhook` |
| **Authorize.Net** | API Login ID, Transaction Key, Client Key (Accept.js), Test Mode | `/gateway/authorize/webhook` |
| **Mollie** | Mollie API Key, Test Mode | `/gateway/mollie/webhook` |
| **Razorpay** | Key ID, Key Secret, Webhook Secret, Test Mode | `/gateway/razorpay/webhook` |
| **Tpay** (Poland) | Open API Client ID, Open API Client Secret, Security Code, Sandbox Mode | `/gateway/tpay/webhook` |
| **iyzico** (Turkey) | API key, Secret key, Sandbox, Instalments offered, Payment group, Let the customer store their card | none: the result comes back with the customer from iyzico's page |
| **Bank Transfer** | Account holder, up to several banks (name, IBAN or account number, sort code, SWIFT/BIC), an additional note | none: you confirm payments by hand |

A gateway is offered at checkout only once every required field has a value
and it is enabled.

## Webhooks

Card gateways confirm a payment by calling PNLCS back. In the provider's
dashboard, set the webhook address to your PNLCS address followed by the path
in the table, for example:

```
https://example.com/gateway/stripe/webhook
```

For **Stripe**, copy the signing secret the dashboard shows for that endpoint
into **Webhook Signing Secret**. Without it every incoming webhook is refused,
because an unsigned one could claim any invoice was paid. PayPal payments are
also re-checked with PayPal directly before an invoice is marked paid.

## Stripe

1. In the [Stripe dashboard](https://dashboard.stripe.com), copy the
   **Publishable key** and **Secret key** (test mode first).
2. Add a webhook endpoint `https://example.com/gateway/stripe/webhook`, and copy
   its signing secret.
3. Paste all three into **Setup → Payment Gateways → Stripe** and enable it.

Test card: `4242 4242 4242 4242`, any future expiry, any CVC.

## PayPal

1. Create an app on the [PayPal Developer](https://developer.paypal.com) portal
   and copy its **Client ID** and **Secret** (sandbox first).
2. Fill them in under **Setup → Payment Gateways → PayPal**, choose sandbox or
   live, and enable it.
3. Point a PayPal webhook at `https://example.com/gateway/paypal/webhook`.

## Bank Transfer

1. Enable **Bank Transfer** and enter the bank details customers see at
   checkout.
2. After paying, a customer can send a **payment notification** from the
   invoice, with a receipt if they like.
3. You review it under **Billing → Payment Notifications** and approve it: the
   invoice is marked paid and whatever waits on it (provisioning, for example)
   runs.

## Stored cards and automatic payment

Stripe, iyzico and PayPal can store a customer's card at the gateway and
charge it for renewals. Settings are under **Setup → General Settings →
Automatic Payment**. Card numbers never pass through PNLCS: the customer
enters them on the gateway's own form.

## Refunds

A paid invoice can be refunded, fully or in part, from its page in the admin
area. Gateway payments go back through the gateway; bank transfers are
refunded outside PNLCS and recorded.
