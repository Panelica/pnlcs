# Migrate from WHMCS

Moving from WHMCS to PNLCS? Because PNLCS deliberately mirrors WHMCS concepts,
the mental model carries straight over — clients, products, orders, invoices,
services, tickets and modules all mean the same thing.

!!! note "There is no one-click importer yet"
    PNLCS does not ship an automated WHMCS database importer. This guide
    describes the practical, low-risk way to move. If you'd like to help build
    an importer, it's a welcome [contribution](https://github.com/Panelica/pnlcs/issues).

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

For each customer, create a **client** in PNLCS and add their existing
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
