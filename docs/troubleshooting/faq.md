# FAQ

## How do I offer a free (zero-cost) plan?

Create the product and set **Payment Type → Free** (the dropdown offers
Recurring / One-time / Free). Do *not* put `0` in the price — that leaves the
product with no sellable price.

## How do I bring an existing customer/account into PNLCS?

Open the client → **Services** tab → **Add Service**. Leave the options empty
for a billing-only record; use **"Link to an existing account"** to attach the
account that already runs on the server (PNLCS then bills *and* manages it); or
tick **"Create the account on the server now"** to provision a brand-new one.

## How does PNLCS know which server account belongs to which service?

It stores the panel's internal **account ID** on the service (in `module_data`)
and every action — suspend, terminate, password — uses that ID. It does *not*
rely on usernames matching, so names can differ freely.

## How do I update to the latest version?

Docker: `docker exec pnlcs /usr/local/bin/update.sh`. Self-hosted or inside a
panel account: see [Updating](../install/updating.md). Your data is left untouched either
way.

## Can PNLCS import my WHMCS data?

There is no one-click importer. The API speaks WHMCS's action names, so
migration scripts carry over; see [Migrate from WHMCS](../guides/migrate-from-whmcs.md).

## Where are my backups?

`storage/app/backups/db/`, one gzip file a night, the last 7 kept. See
[Backups](../install/backups.md).

## Can customers pay automatically?

Yes, with Stripe, iyzico or PayPal: the customer stores a card at the gateway
and renewals are charged to it once **Automatic Payment** is switched on
(**Setup → General Settings**). See [Payment Gateways](../guides/payment-gateways.md#stored-cards-and-automatic-payment).

## Can I sell in several currencies?

Yes: add currencies under **Setup → Currencies**; exchange rates update every
night.

## Can an AI assistant work with PNLCS?

Yes, through the [MCP server](../mcp/index.md).

