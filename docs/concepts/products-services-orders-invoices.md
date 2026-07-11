# Products, Services, Orders & Invoices

These four objects are the heart of PNLCS. Understanding how they relate is the
single most useful thing you can learn.

## Product — what you sell

A **product** is an entry on your price list. It defines:

- A **name** and **group** (category)
- Which **server + module** it provisions on (or *Custom* / *None*)
- **Auto-setup** — when the account is created (on order / on payment / manual)
- **Pricing** per billing cycle (monthly, quarterly, annually, biennially…)
- Optional **configurable options**, **add-ons**, welcome email, and stock limits

A product is a *template*. Nothing is billed or provisioned until a customer
actually orders it.

## Order — the act of buying

When a customer checks out, PNLCS creates an **order**. The order:

- Records who bought, what, and at what price
- Generates an **invoice** for the total
- Creates a **pending service** for each hosting/VPS item and a **pending
  domain** for each domain item

Order statuses:

| Status | Meaning |
|--------|---------|
| **Pending** | Placed, waiting to be accepted (usually waiting for payment) |
| **Active** | Accepted — services have been activated/provisioned |
| **Cancelled** | Called off; related services cancelled |
| **Fraud** | Flagged as fraudulent; services suspended |

## Invoice — the bill

An **invoice** is generated for the order and later for each renewal. It carries
line items, tax, credit applied and a status:

| Status | Meaning |
|--------|---------|
| **Unpaid** | Awaiting payment |
| **Partially Paid** | Some money received, a balance remains |
| **Payment Pending** | A customer reported an offline (bank transfer) payment, awaiting your approval |
| **Paid** | Settled in full |
| **Overdue** | Past its due date, still unpaid |
| **Cancelled** | Voided |
| **Refunded** | Money returned |

**Paying an invoice is the trigger** for almost everything: it accepts the
order, activates and provisions the service, credits affiliates, and sends the
welcome email.

## Service — what the customer owns

Once an order is accepted, each hosting item becomes an **active service** — the
live account. A service has:

- Its own **status**: *Pending*, *Active*, *Suspended*, *Terminated*, *Cancelled*
- Its own **billing cycle** and **next due date**
- A link to the **server** it lives on and the **username** on that server

The service is what **renews**. As the next due date approaches, PNLCS
generates a renewal invoice; if it goes unpaid past your grace period, the
service is **suspended**, and eventually **terminated**.

## How they flow together

```
Product (your price list)
   │  customer orders
   ▼
Order (Pending) ──creates──▶ Invoice (Unpaid)
                                  │  customer pays
                                  ▼
Order (Active) ──activates──▶ Service (Active) ──provisioned on──▶ Server
                                  │
                                  │  each billing cycle
                                  ▼
                             Renewal Invoice ──unpaid too long──▶ Suspend ──▶ Terminate
```

## Common questions

**Why do I have both an "order" and a "service"?**
The order is the one-time purchase event. The service is the ongoing product
the customer owns, which keeps billing long after the order is history.

**Why is my service "Pending" and not "Active"?**
The order hasn't been accepted yet — usually because the invoice is still
unpaid, or the product's auto-setup is set to *manual*. See
[The Billing Lifecycle](billing-lifecycle.md).

**A customer paid but nothing was provisioned.**
Check the product has a **server + module** assigned and auto-setup is *on
payment*. See [Provisioning failed](../troubleshooting/common-issues.md#provisioning-did-not-happen).
