# Your First Sale

This walkthrough takes you through the **entire lifecycle** once: create a
product, place an order as a customer, pay it, and watch PNLCS provision the
hosting account automatically. Do this once and the whole system will make
sense.

!!! note "Prerequisites"
    - Email is configured ([guide](../guides/configure-email.md))
    - One payment gateway is enabled ([guide](../guides/payment-gateways.md))
    - A server module is connected, if you want real provisioning
      ([guide](../guides/connect-a-server.md))

## Step 1 — Create a product group

**Products → Product Groups → Create Group**

A group is just a category customers browse, e.g. **Shared Hosting**. Give it a
name and save.

## Step 2 — Create a product

**Products → Create Product**

- **Name**: e.g. "Starter Hosting"
- **Group**: the group you just made
- **Server / Module**: pick the server this plan provisions on (or leave as
  *Custom* if you'll set accounts up by hand)
- **Auto-setup**: choose when the account is created —
    - *On payment* — provision when the first invoice is paid (most common)
    - *On order* — provision immediately, before payment
    - *Manual* — you accept each order yourself
- **Pricing**: set a price for one or more billing cycles (monthly, annually…)

Save. Your product now appears in the customer shop.

!!! info "What is 'auto-setup'?"
    It decides *when* the hosting account is actually created on your server.
    Most hosts use **on payment** so nobody gets a live account without paying.
    See [The Billing Lifecycle](../concepts/billing-lifecycle.md).

## Step 3 — Place a test order as a customer

Open an **incognito/private browser window** so you're not logged in as admin.

1. Go to `https://your-domain/client/register` and create a test customer.
2. Browse the shop, pick your product, choose a billing cycle.
3. Enter a domain name for the account and continue to checkout.
4. An **order** and an **unpaid invoice** are created.

## Step 4 — Pay the invoice

Pay using your gateway's **test mode** (e.g. Stripe test card
`4242 4242 4242 4242`, any future expiry, any CVC).

The moment the payment is confirmed, PNLCS:

1. Marks the **invoice paid**
2. **Accepts the order**
3. **Provisions the account** on your server (if auto-setup is *on payment*)
4. Sends the customer a **welcome email** with their login details

## Step 5 — Verify everything worked

Back in the **admin panel**:

- **Billing → Invoices** — the invoice is *Paid*
- **Clients → your test client → Services** — the service is *Active*
- **Orders** — the order is *Active*
- If a server module is connected, the account exists on that server

Congratulations — that's a full sale, start to finish. Everything else in PNLCS
is a variation on this loop.

## Understand what just happened

- A **product** is what you sell.
- An **order** is a customer's request to buy it.
- An **invoice** is the bill for that order.
- A **service** is the live thing the customer now owns (the hosting account),
  which renews and bills on its own cycle.

Read [Products, Services, Orders & Invoices](../concepts/products-services-orders-invoices.md)
for the full picture.

## What's next

- [Sell Domains](../guides/sell-domains.md)
- [Connect more servers](../guides/connect-a-server.md)
- [Migrate your existing customers from WHMCS](../guides/migrate-from-whmcs.md)
- [Set up tax rules](../guides/tax-rules.md)
