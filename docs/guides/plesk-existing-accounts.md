# Plesk: Existing Accounts

If you already host customers on Plesk, you can bill them from PNLCS without recreating anything, by linking each service to its existing Plesk subscription.

## Linking a service

1. Connect the Plesk server under **Setup → Servers**.
2. On the client's page, add a service for a Plesk product and select the server.
3. In **Link to an existing account**, choose the subscription. The list shows the domain and its owner.

Linking only records the subscription and its owner in PNLCS. **Nothing on Plesk changes.** The service's username becomes the owner's login; its domain, if empty, becomes the subscription's name. A subscription already managed by another live service cannot be linked twice.

## Importing every subscription at once

**Setup → Servers → Sync accounts** lists every subscription on the server. Each row shows its domain, owner, service plan, creation date and status, and the service that bills it if there is one. Subscriptions without a service can be imported in batches of up to 100:

1. Tick the subscriptions to import. **Only accounts not linked** hides the ones already billed.
2. Check the client (by id: the field suggests names as you type), product, cycle, amount and next due date.
3. Click **Import selected**. Each subscription becomes a service of that client, linked as above. **Nothing on Plesk changes.**

Each row opens with a guess you can change:

| Field | Taken from |
|---|---|
| Client | The price list; else a domain or service already in PNLCS; else the owner's email |
| Product | The product whose Plesk plan matches the subscription's; the only Plesk product if there is one |
| Amount | The price list; else the product's price for the cycle |
| Next due date | The price list; else the next anniversary of the subscription's creation |
| Status | Active, or suspended if the subscription is suspended on Plesk |

### Price list

To fill many rows at once, upload a CSV with a header row and these columns: `domain`, `client_id` (or `client_email`), `amount`, `billing_cycle` (`Monthly`, `Quarterly`, `Semi-Annually`, `Annually`, `Biennially`, `Triennially`) and `next_due_date` (`YYYY-MM-DD`). Commas or semicolons both work, and any column can be left empty. The list is kept for your session; rows filled from it are marked.

```csv
domain,client_id,amount,billing_cycle,next_due_date
rossi.it,12,60.00,Annually,2027-03-15
```

!!! note
    Subscriptions that the Plesk administrator owns have no owner email, so the client can only come from the price list or from data already in PNLCS.

Importing needs the **edit clients** and **manage services** permissions; staff with **manage servers** alone can view the page.

## What the actions do

| Action | Effect on Plesk |
|---|---|
| Suspend, unsuspend | That subscription only; the customer's other subscriptions keep running |
| Terminate | **Deletes the subscription and its content.** The Plesk customer is deleted too once no subscriptions are left |
| Change password | Changes the **Plesk customer's** password, which applies to all their subscriptions |

!!! danger
    Terminate cannot be undone. On a service billed for an existing site, use it only when you really want the site removed from Plesk.

A service recorded before the subscription id was stored keeps the old behaviour: suspend and terminate act on the whole Plesk customer.

## Limits

Usage statistics are read per customer, not per subscription.
