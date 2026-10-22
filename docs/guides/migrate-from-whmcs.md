# Migrate from WHMCS

Moving from WHMCS to PNLCS? Because PNLCS deliberately mirrors WHMCS concepts,
the mental model carries straight over — clients, products, orders, invoices,
services, tickets and modules all mean the same thing.

PNLCS can read your clients, services and domains straight from the WHMCS
database (**Utilities → Import WHMCS**). Your catalogue, servers and settings
you set up once by hand; invoices, transactions and tickets are not imported.

## Recommended approach: run both, cut over gradually

Rather than a big-bang migration, run PNLCS alongside WHMCS and move customers
over as they renew. This avoids downtime and billing surprises.

### 1. Rebuild your catalog

Recreate your **product groups** and **products** in PNLCS with the same names
and prices. See [Your First Sale](../getting-started/your-first-sale.md). This
is quick and gives you a clean, correct price list.

### 2. Connect the same servers

Add your existing hosting **servers** to PNLCS
([Connect a Server](connect-a-server.md)). The accounts already exist on those
servers — PNLCS just needs to manage them going forward.

### 3. Recreate settings

- Payment [gateways](payment-gateways.md) (use the same accounts)
- [Tax rules](tax-rules.md)
- [Email](configure-email.md) and templates
- [Staff and roles](staff-and-roles.md)

### 4. Bring customers over

Import them (next section), or, for a handful of customers, create each
**client** in PNLCS by hand and add their existing
**services**: on the client, **Services → Add Service**, with the right product,
next due date and price. On a Panelica server, **Link to an existing account**
lists the accounts already on it; pick the customer's, and PNLCS manages that
account from then on (suspend, terminate, password). Leave it empty for a
billing-only record. New renewal invoices then bill from PNLCS.

Two common patterns:

- **On renewal** — as each customer's WHMCS renewal comes up, recreate them in
  PNLCS and let PNLCS bill the next cycle. Lowest risk.
- **All at once** — recreate everyone in a maintenance window, set due dates to
  match, and switch billing.

### 5. Point domains/links at PNLCS

Update your billing URL, client links and any signup forms to your PNLCS
install. Redirect the old WHMCS URL if you can.

## Import WHMCS

**Utilities → Import WHMCS** in the admin area (staff with the *manage
settings* permission) copies records from the WHMCS database into PNLCS.

1. **Connect.** Enter the WHMCS database's host, port, database name, user,
   password and table prefix (usually `tbl`), and press **Test connection**.
   PNLCS must be able to reach that MySQL server. The connection is read-only:
   PNLCS only reads, and the WHMCS database is never changed.
2. **Map the fields.** Pick the source table: `tblclients` for clients,
   `tblhosting` for services, `tbldomains` for domains. PNLCS suggests a PNLCS
   field for each WHMCS field; change, skip or set a constant value where you
   need to, and save the mapping to reuse it. For services, map each WHMCS
   product to a PNLCS product, or let it match by name.
3. **Preview, then import.** The preview shows the records as PNLCS will store
   them and lists any problems first. Choose **Add new**, **Add and update** or
   **Update only**, with a match key (by default the email for clients, the
   domain for services and domains), so running the import again updates
   instead of copying. Every run is logged with its counts, and with each
   skipped or failed record and the reason.

Import in this order: **clients, then services and domains**, because a service
or domain is attached to the PNLCS client with the same email as its WHMCS
owner. Recreate your products and add your servers first: a service is linked
to the PNLCS server with the same hostname (or name) as its WHMCS server.

What to know:

- **No email is sent.** Each imported client gets a login with a random
  password; customers set their own with **Forgot password** when you tell
  them about the move.
- **Hosting passwords stay on the servers.** WHMCS encrypts service passwords
  with its own key, so they are not imported; the accounts on your servers are
  not touched.
- **Not imported:** products, invoices, transactions, tickets and settings.
  Open balances are best settled in WHMCS, or entered as new invoices in PNLCS.

## Handy facts for planners

- **Data model parity:** WHMCS's clients/products/orders/invoices/services map
  1:1 to PNLCS, so exported CSVs are easy to re-enter or script against the API.
- **API:** the PNLCS API uses the WHMCS action names (`addclient`,
  `addorder`, `getclientsproducts`...), so migration scripts written for WHMCS
  carry over with a new address. See the [API reference](../api/index.md),
  and [Coming from WHMCS](../api/index.md#coming-from-whmcs) for the
  differences.
- **Passwords:** hosting account passwords live on the control panel, not in
  PNLCS — moving billing doesn't disturb live sites.

## Get help

Migrations are the kind of thing the community loves to compare notes on — ask
on the [forum](https://forum.panelica.com) or open an
[issue](https://github.com/Panelica/pnlcs/issues) with your scenario.
