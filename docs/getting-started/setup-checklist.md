# Setup Checklist

Work through these in order. Each one takes a few minutes, and together they
turn a fresh install into a system that can actually take orders and provision
hosting. The dashboard shows the same checklist and ticks items off as you
complete them.

!!! note
    You don't need every item to start — but **email** and at least **one
    payment gateway** are required before real customers can order.

## 1. General settings

**Settings → General**

- Company name, support email, logo and favicon
- Default language, currency and timezone
- Invoice due terms and tax display behavior

This is your business identity — it appears on invoices, emails and the
customer portal.

## 2. Email delivery *(required)*

**Settings → Email**

Set `MAIL_MAILER` to `smtp` and fill in your mail server details, then send a
**test email** from the same page.

Until this works, PNLCS writes emails to a log file instead of sending them, so
customers never receive invoices, order confirmations or ticket replies.

➡️ Full guide: [Configure Email](../guides/configure-email.md)

## 3. Payment gateways *(required)*

**Configuration → Gateways**

Enable at least one:

- **Stripe** — paste your API keys
- **PayPal** — client ID + secret
- **Bank Transfer** — enter the instructions shown to customers

➡️ Full guide: [Payment Gateways](../guides/payment-gateways.md)

## 4. Server modules *(if you sell hosting or VPS)*

**Configuration → Servers**

Add the server your accounts will be created on — Panelica, cPanel, Plesk,
DirectAdmin, HestiaCP, Proxmox or Vultr — and test the connection.

➡️ Full guide: [Connect a Server](../guides/connect-a-server.md)

## 5. Your first product

**Products**

Create a product group (e.g. "Shared Hosting"), then a product linked to your
server and a billing cycle and price.

➡️ Walkthrough: [Your First Sale](your-first-sale.md)

## 6. Domain pricing *(if you sell domains)*

**Configuration → Domain Pricing** — add TLDs and set register/transfer/renew
prices.

➡️ Full guide: [Sell Domains](../guides/sell-domains.md)

## 7. Tax rules *(if you charge tax/VAT)*

**Configuration → Tax** — add country/state tax rates and choose inclusive or
exclusive display.

➡️ Full guide: [Tax Rules](../guides/tax-rules.md)

## 8. Staff and roles *(optional)*

**Configuration → Admin Roles / Admins** — create roles with specific
permissions and invite team members.

➡️ Full guide: [Staff & Roles](../guides/staff-and-roles.md)

## 9. Require email verification *(recommended)*

**Settings → General → Security** — turn on **Require email verification** so
new signups confirm their address before ordering.

## 10. The cron runner *(required for automation)*

Invoices, reminders, suspensions and provisioning retries all run on a
schedule. Make sure the cron line is installed:

```
* * * * * cd /path/to/pnlcs && php artisan schedule:run >> /dev/null 2>&1
```

Docker installs handle this automatically.

➡️ What runs and when: [Scheduled Commands](../reference/scheduled-commands.md)

## Ready?

Once email and a gateway are set, walk through a real end-to-end sale:

➡️ [Your First Sale](your-first-sale.md)
