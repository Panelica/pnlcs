# The Billing Lifecycle

This page explains what PNLCS does *on its own*, on a schedule, without you
lifting a finger — how a service goes from active to renewed, or from unpaid to
suspended to terminated.

!!! important "This all depends on the cron runner"
    Every automated step below is driven by the scheduled task runner. If cron
    isn't running, none of this happens. See
    [Scheduled Commands](../reference/scheduled-commands.md).

## Recurring billing

Each active service has a **next due date**. Ahead of that date, PNLCS
automatically generates a **renewal invoice** and emails it to the customer.

Supported cycles: monthly, quarterly, semi-annually, annually, biennially,
triennially.

## Payment reminders

As the due date approaches and passes, the customer receives **reminder
emails** on a schedule you control. This nudges them to pay before anything is
interrupted.

## Overdue → Suspend → Terminate

If a renewal invoice isn't paid:

1. **Overdue** — past the due date, the invoice is marked overdue and reminders continue.
2. **Auto-suspend** — after a grace period you configure, the service is
   **suspended** on the server (the site goes offline, the account still exists).
3. **Auto-terminate** — after a further period, the service can be
   **terminated** (the account is deleted).

Paying the outstanding invoice at any point **automatically unsuspends** the
service.

## Late fees

You can add a **late fee** to invoices that pass their due date — a fixed
amount or a percentage.

## Auto-renew

Services and domains can **auto-renew**: PNLCS creates the renewal invoice and,
where a stored payment method exists, attempts payment automatically.

## Refunds

You can **refund** a paid invoice — fully or partially — through the original
gateway or offline for bank transfers. The invoice moves to *Refunded* (or back
to *Partially Paid*) and the money is returned via the gateway.

## What runs when

| Task | Typical schedule |
|------|------------------|
| Generate renewal invoices | Daily |
| Mark overdue invoices | Daily |
| Payment reminders | Daily |
| Apply late fees | Daily |
| Auto-suspend unpaid services | Daily |
| Unsuspend on payment | Every 30 minutes |
| Currency rate update | Daily |
| Database backup | Daily |
| Retry failed provisioning | Every 5 minutes |
| Import support mailboxes | Every 5 minutes |

Full list and command names: [Scheduled Commands](../reference/scheduled-commands.md).

## You're always in control

Every automated action can also be done **manually** from the admin panel —
create an invoice, mark it paid, suspend a service, issue a refund, accept an
order. Automation just handles the routine so you don't have to.
