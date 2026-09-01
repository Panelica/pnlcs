# Changelog

All notable changes to PNLCS are documented here. Newest first.

## 2026-09 — Tax model, extensible addons, Tpay & Polish-market billing

A round of billing and extensibility work, largely from community
contributions (thanks to [@hedon77](https://github.com/hedon77)), merged after
review. The features below are live; the wider Polish-localization series
(company lookup, KSeF e-invoicing, proforma flow) is still in review.

### Billing & tax

- **Redesigned tax model** (#14, `9cc5999`). VAT is matched by country and
  state with exactly one rate marked as the default — an exact country+state
  match wins, then the country default, then the global default. Invoice items
  carry their own VAT rate and label (per-line VAT), and a new invoicing
  **product catalog** (goods/services with a unit and rate) can seed invoice
  lines. The long-broken secondary tax (`tax2`), which was configurable but
  never actually charged, is removed; multi-rate jurisdictions use per-line
  rates instead. The migration promotes any existing catch-all rule to the
  default so taxation keeps applying after upgrade.

### Payments

- **Tpay (Poland) payment gateway** (#13, `f02f450`). Redirect-based Tpay Open
  API integration (BLIK, quick transfers, cards) with OAuth2 tokens, refunds,
  and webhook verification that requires both the JWS signature (RFC 7515, x5u
  certificate validated against the Tpay CA) and the md5 checksum.

### Extensibility

- **Generic addon settings framework** (#22, `3a06717`). Addons declare their
  own config fields and persist them in a per-addon settings store that is
  encrypted at rest, the same treatment gateway and registrar secrets get, and
  is managed from an addon settings screen.

### Security

- **Deleting a client removes its orphaned login accounts** (#16, `5c705ba`).
  A soft-deleted client previously left its `User` login able to sign in;
  logins that belong only to the deleted client are now removed, while accounts
  shared with another client are only detached.

### Admin experience

- **Formatted invoice number** shown in the admin invoice list (#24,
  `9be9e61`).

## 2026-07 — Security hardening, billing completeness & full Panelica integration

A large body of work focused on making PNLCS an enterprise-grade, self-hosted
WHMCS alternative: closing security gaps, completing the billing lifecycle,
achieving full parity with the Panelica control panel, and polishing the admin
and customer experience. Every change ships with automated tests; the suite is
green (769 passing).

### Security

- **API authentication bypass fixed** (`dae945a`). The API key branch only
  validated the secret when one was present, so a request with a valid
  identifier but no secret was authenticated. The secret is now mandatory and
  compared in constant time (`hash_equals`).
- **API secrets stored as SHA-256 hashes** (`0f805d1`, `d5f4ce9`). Credentials
  are no longer kept in plaintext; authentication hashes the presented secret
  and compares/looks up by digest. A migration hashes existing rows, so current
  clients keep working with their plaintext secret.
- **Per-credential API IP allowlist** (`55ccb52`). `ApiCredential.allowed_ips`
  is now enforced (plain IPs or CIDR, IPv4/IPv6); an empty list means no
  restriction.
- **Gateway & registrar secrets encrypted at rest** (`58c53f9`). Stripe/PayPal/
  Razorpay/Authorize.Net keys and registrar credentials are encrypted via a
  graceful cast that still reads legacy plaintext during the transition.
- **Password reset hardened** (`8d80074`). The reset token was written to the
  application log and no email was sent; it is now delivered by email and never
  logged. No user enumeration.
- **Admin broken access control fixed** (`d32bcab`). A block of state-changing
  admin routes (affiliate payouts, quote conversion, billable items, client
  groups, projects, system diagnostics) sat outside any permission group and is
  now guarded. The test harness's admin factory was corrected to a full-admin
  default, clearing ~150 permission-related test failures.
- **SSL client-area IDOR fixed** (`4a9c7a8`). The SSL controller authorised by
  user id instead of client id, exposing another client's SSL orders and
  private keys; now scoped by client id.
- **Payment forgery closed for Stripe and Razorpay** (`f083090`). Both confirm
  endpoints trusted browser-supplied ids; they now verify with the gateway and
  credit only the gateway-reported amount (PayPal was already fixed).
- **Login throttling tightened** (`dd598b6`). Per-account (email/username + IP)
  lockout after 5 failed attempts, plus a stricter coarse route limit.

### Billing

- **Domain renewal invoicing** (`1b9824b`). Registered domains are now billed on
  renewal; a payment advances the service by one cycle and the domain by its
  registration period (fixing a latent re-invoice bug).
- **Prorated upgrades / downgrades** (`1b9824b`). Product changes are prorated
  for the days left in the cycle; upgrades raise an invoice and apply the
  package change on payment, downgrades apply immediately.
- **Registrar renewal API call** (`277aabd`). Domain renewal now performs the
  real registrar `renew()` call, with a local date-advance fallback; fixes a
  Carbon date double-advance bug.
- **Staff role permission codes corrected** (`8817b5e`). Seeded example roles
  used non-canonical permission strings that 403'd real staff.

### Panelica control-panel integration (full parity)

- **Managed resource plans** (`21e4f5d`). A product can define its own resource
  limits and PanelicaModule builds/syncs a matching panel plan on provisioning,
  mirroring the Panelica WHMCS module exactly: CPU %, RAM, inode, IOPS, disk
  I/O, network, processes, disk, bandwidth, websites, subdomains, email,
  databases, FTP, cron, containers, SSH level, quota mode, ModSecurity, PHP
  limits, backups.
- **Resource limits UI on the product editor** (`878f978`). Set the full managed
  limit set from the product page, or reference an existing panel plan.
- **Panel plan dropdown** (`611931a`). The product editor loads the panel's
  plans into a dropdown, falling back to a text field when the panel is
  unreachable.
- **One-click control-panel SSO** (`c1b8c0e`). The service page offers a
  "Login to Control Panel" button that mints a one-time SSO url and redirects
  the customer into their panel; scoped by client id.
- **Live resource usage graphs** (`611931a`). The service page shows live disk,
  bandwidth and account counts pulled from the panel via a scoped usage
  endpoint; also fixes the usage-polling cron, which read non-existent keys and
  never populated disk limits.

### Admin & customer experience

- **Dashboard quick actions** (`3acb172`). Permission-gated shortcuts to create
  a product, add a server, add a client and create an invoice.
- **README**: prominent live-demo link (hosting.panelica.com) and an updated
  module compatibility table.

### Distribution

- **Docker runtime `panelica/pnlcs-runtime:1.4`** rebuilt on a fresh
  `php:8.4-fpm-alpine` base and published; the Panelica app template points at
  it. Application code is cloned from GitHub at runtime, so code updates reach
  installs via a fresh deploy or `docker exec <slug> /usr/local/bin/update.sh`.
