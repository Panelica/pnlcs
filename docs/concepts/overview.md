# How PNLCS Is Organized

If you've never used a billing panel, the vocabulary can be confusing. This
page explains the main ideas in plain English and how they fit together.

## The big picture

```
Client  ──places──▶  Order  ──generates──▶  Invoice  ──when paid──▶  Service (Active)
  │                                                                      │
  └── has contacts, tickets, domains                     runs on ──▶  Server (via a Module)
```

## The core objects

### Client

A **client** is a customer — a person or company that buys from you. Each client
has a login to the customer portal, a billing profile, and everything they own
(services, domains, invoices, tickets).

A client can also have **contacts** — additional people (e.g. a technical
contact and a billing contact) who can be given portal access.

### Product

A **product** is *what you sell* — a plan on your price list. "Starter Hosting
— $5/month" is a product. Products are grouped into **product groups**
(categories) that customers browse in the shop.

### Order

An **order** is a customer's *request to buy* one or more products. Placing an
order creates the order record and an invoice. An order starts **Pending** and
becomes **Active** once accepted (usually automatically, on payment).

### Invoice

An **invoice** is the *bill*. It lists what's owed and its status: *Unpaid*,
*Paid*, *Partially Paid*, *Overdue*, *Cancelled* or *Refunded*. Paying an
invoice is what drives the rest of the system into motion.

### Service

A **service** is the *live thing the customer owns* after they buy — the actual
hosting account, VPS or subscription. A service has its own status (*Active*,
*Suspended*, *Terminated*) and its own **billing cycle**: it renews and
generates new invoices on schedule, independently of the original order.

!!! tip "Order vs Service — the key distinction"
    The **order** is the one-time act of buying. The **service** is the ongoing
    thing that lives on afterward and keeps billing. One order creates a
    service; that service then renews for months or years.

### Server & Module

A **server** is a machine where hosting accounts are created. A **module** is
the integration that talks to it (Panelica, cPanel, Plesk, DirectAdmin,
HestiaCP, Proxmox, Vultr…). When a product is linked to a server + module,
PNLCS can create, suspend, unsuspend and terminate accounts automatically.

See [Servers & Modules](servers-and-modules.md).

### Gateway

A **payment gateway** is how money comes in — Stripe, PayPal, Authorize.Net,
bank transfer. See [Payment Gateways](../guides/payment-gateways.md).

### Registrar

A **registrar module** registers and renews **domains** (e.g. Enom, or Manual
if you handle domains yourself). See [Sell Domains](../guides/sell-domains.md).

## Admin vs Client — two sides

PNLCS has two front doors:

- **Admin panel** (`/admin`) — you and your staff manage everything here.
- **Client portal** (`/client`) — your customers shop, pay invoices, open
  tickets and manage their services here.

## Where to go next

- [Products, Services, Orders & Invoices](products-services-orders-invoices.md) — these four in depth
- [The Billing Lifecycle](billing-lifecycle.md) — how recurring billing, suspension and renewal work
- [Servers & Modules](servers-and-modules.md) — how provisioning happens
