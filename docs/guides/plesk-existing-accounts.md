# Plesk: Existing Accounts

If you already host customers on Plesk, you can bill them from PNLCS without recreating anything, by linking each service to its existing Plesk subscription.

## Linking a service

1. Connect the Plesk server under **Setup → Servers**.
2. On the client's page, add a service for a Plesk product and select the server.
3. In **Link to an existing account**, choose the subscription. The list shows the domain and its owner.

Linking only records the subscription and its owner in PNLCS. **Nothing on Plesk changes.** The service's username becomes the owner's login; its domain, if empty, becomes the subscription's name. A subscription already managed by another live service cannot be linked twice.

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
