# Changelog

All notable changes to PNLCS are documented here. Newest first.

## 2026-09-24 — API permissions, MCP server checked against the API

### Fixed

- **Two API actions answered to the wrong permission.** `resetpassword` (a
  customer login's password) was allowed with "manage settings" instead of
  "edit clients", and `createorupdatetld` (domain prices) with "manage domains"
  instead of "manage servers", the permission the admin area asks for the same
  change. Each has a test that fails on the old mapping.
- Four API methods that no route reached were removed.
- The order statuses in the API reference are written as PNLCS stores them:
  `pending`, `active`, `cancelled`, `fraud`.

### MCP server

- **Every tool is now checked against the API reference.** A new test reads
  `docs/api/openapi.json` and fails when a tool calls an action that does not
  exist, uses the wrong HTTP method, sends a parameter the action does not
  take, or leaves out one the action requires.
- `list_tickets` can filter by client, and lists the ticket statuses as
  PNLCS writes them.
- `list_invoices` names all nine invoice statuses; four were missing.

## 2026-09-24 — The complete documentation, staff 2FA recovery codes

### Documentation

The [documentation](https://docs.pnlcs.com) now covers PNLCS end to end:

- **Installation**: requirements, Docker, your own server (Ubuntu, Debian,
  AlmaLinux; every command tested on fresh servers), a hosting-panel account,
  updating, backups and a security checklist.
- **Guides** for the Modules screen, Live Servers, languages and translations,
  and client logins and permissions, alongside the updated existing ones.
- **Developer**: writing a module, hooks (five that were missing are listed
  now), themes, translations, contributing.
- **Troubleshooting** and an **FAQ**; every scheduled command, with its
  schedule.

Going through the product page by page corrected what the old pages said:
menu paths that no longer exist, a tax setting (prices with tax included) and
a per-product tax switch that PNLCS does not have, a two-factor requirement
per staff role that does not exist, staff being emailed their login (they are
not), the number of themes (16), and the queue worker advice.

### Fixed

- **Staff two-factor recovery codes did not work.** Turning 2FA on showed eight
  codes and stored none: the admins table had no column for them, so a member
  of staff who kept the codes and lost the phone was locked out. They are
  stored now, encrypted, and each works once.

## 2026-09-24 — Domain fixes from ENA Hosting, "set up on my hosting"

Found by **ENA Hosting** running PNLCS in production, fixed on their own
install and taken into PNLCS so every install has them. Thank you!

### Fixed

- **A paid domain could be marked active without being registered.** The
  DomainNameAPI registrar module shipped without being registered, so it could
  not be configured, and a domain naming a registrar that cannot be loaded was
  treated as having no registrar and marked active. Such a domain now stays
  pending and the operator is notified; a domain with no registrar at all
  (bought elsewhere, billed here) is still recorded as active.
- The daily domain sync warns (at most weekly) about an active domain its
  registrar does not know. DomainNameAPI now passes its own "not found" words
  through, so the warning can recognise them.
- **Customers could not change their nameservers**: the client area showed
  them read-only, though the route to change them existed. The domain page
  has the form now.
- **DomainNameAPI refused every nameserver change**: it sent POST where the
  API takes PUT, and read the empty success answer as a failure.
- **A seller in Turkey could not take an order**: checkout required a phone
  number and never asked for it. The billing address fields ask for it now.
- Turkish, German and Polish error messages name form fields in their own
  language instead of English.
- The registrar balance watch (DomainNameAPI by default) no longer warns an
  install that has not configured that registrar.

### New

- **Set up on my hosting**: from a domain's page, a customer adds the domain
  to one of their hosting accounts and points its nameservers there in one
  step (they choose the account when they have several). Nameservers come from
  the server the account is on, else the registrar's defaults. Works with any
  server module that implements `HostsAccountDomains`; Panelica does.

## 2026-09-24 — Complete API reference, MCP guide, API error shape

### Documentation

- **Every API action is documented**: all 171, with parameters (and which are
  required), response fields, errors, the permission it needs and a curl
  example that works as written. Plus an OpenAPI 3.1 file to import into
  Postman, Insomnia or Bruno. See the API section of the
  [documentation](https://docs.pnlcs.com/api/).
- The pages are generated from the route table and `config/api_docs.php`
  (`php artisan pnlcs:api-docs`); a test fails when a route has no entry, when
  a parameter documented as optional turns out to be required (or the
  reverse), when an example does not reach its endpoint, or when the pages on
  disk are stale.
- **MCP server guide** and a tool reference generated from the server's own
  tool list.
- The admin **API Documentation** screen described a single `/api/v1` address
  taking an `action` parameter (it answered 404), listed a GET-only action as
  POST (405), sent invoice lines in a shape the API ignored, named the
  `validatelogin` password parameter wrongly and left out required
  parameters of several actions. It now shows what really works, in all five
  languages, and links to the full reference. Twelve action descriptions that
  did not match the code were corrected, and 91 missing ones were added.

### Security

- `/api/v1/gethealthstatus` answered **without a credential**, giving anyone
  the PHP and framework versions, disk and memory figures, and the database
  error when the database was down. It needs a credential now; the public
  uptime probe is `/api/health`, which says only up or down.

### API

- **Every error now has `result: error`.** Validation failures (422), an
  unknown action (404), the wrong method (405, which now names the right one)
  and the rate limit (429, with `Retry-After`) came back in the framework's own
  shape, without the `result` field that WHMCS-compatible clients test. The
  validation `errors` list is kept alongside.
- **Credentials can be restricted to IP addresses** from the API Credentials
  screen (and with `allowed_ips` in `createoauthcredential` /
  `updateoauthcredential`). The API always enforced such a list, but nothing
  could set one. The screen can also edit a credential and switch it off
  without deleting it.

### MCP server 1.0.6

- `list_clients` said it listed the newest clients first; the API lists the
  oldest first. The description is corrected and `orderby` / `sorting` were
  added, so "newest first" can be asked for.

## 2026-09-23 — API & MCP security audit, German, modules, Live Servers

### ⚠️ Action required if you used pnlcs-mcp 1.0.4 or older

**Rotate the API credential you gave it.** Versions up to 1.0.4 sent the
identifier and secret in the query string of every read, so they were written
to your web server's access log, to any proxy in between and to request logs.
Create a new credential under **Setup → API Credentials**, put it in your MCP
client's configuration, update to `pnlcs-mcp` 1.0.5 (which sends credentials
only in the `X-API-Key` / `X-API-Secret` headers), and delete the old
credential.

### Security

- **Secrets no longer leave the API.** SSL certificate private keys
  (`getsslorders`, `getsslorder`), domain transfer (EPP) codes
  (`getclientsdomains`), the support mailbox password (`getsupportdepartments`,
  `gettickets`, `getticket`) and contact password hashes
  (`getclientsdetails`, `getcontacts`) were part of the responses. They are
  hidden at the model now, so no endpoint can return them.
- A **disabled** staff account's API credential and password stopped working
  only in the admin area; the API refuses them now.
- An account with **two-factor authentication** could reach the API with its
  password alone; it needs an API credential now.
- `createoauthcredential` created keys owned by the first administrator and
  needed only "manage settings"; keys now belong to the caller and need
  "manage staff", as on the staff screen.
- Projects, quotes, affiliates, products, promotions, registrars, module
  settings and mail endpoints answer to the same permissions as their screens.
- Reflected XSS on the client **reset-password** page (the token and email
  from the URL were printed unescaped) is fixed.
- The SSL provider password was printed into the SSL settings page and stored
  in plain text; it is never rendered now and is stored encrypted.

### API correctness

- `getclientsproducts`, `getclientsdomains` and `gettransactions` ignored
  `clientid` and returned **every customer's** records; they filter now.
- `deleteuserclient` deleted the login instead of removing it from one
  account; `geninvoices` ignored `clientid` and billed everyone (filters are
  refused now); `blockticketsender` blocked signups instead of tickets.
- Replies, notes, log entries and project messages are signed by the caller,
  not by whatever the request said.
- The parameter names the API reference documents (the WHMCS names) are the
  ones the API reads; the reference was corrected where it was wrong.
- Newly implemented: `sendemail`, `sendadminemail`, `resetpassword`,
  `activatemodule`, `deactivatemodule`, `getmoduleconfigurationparameters`,
  `updatemoduleconfiguration`, `triggernotificationevent` (new `api.custom`
  notification event), `starttasktimer`, `endtasktimer`, `addproduct`,
  `updatepaymethod`, `deletepaymethod`, `modulecustom`, `createssotoken`
  (one-time client-area sign-in links), `createclientinvite` (account
  invitations) and `getuserpermissions` / `updateuserpermissions`
  (per-login permissions, enforced in the client area; owners and existing
  logins keep full access).
- Still answering 501 on purpose: `addpaymethod` (cards are added by the
  customer at the gateway's form; card numbers never pass through PNLCS),
  `capturepayment`, `domainupdatewhoisinfo`, `domainrelease`,
  `encryptpassword`, `decryptpassword`.

### pnlcs-mcp 1.0.5

Credentials in headers only; client-scoped tools also send `userid` so older
installs filter too; ticket replies are filed as staff; the live test checks
the data, not only "success".

### German

A complete German translation, **contributed by Dirk Mehmke** — thank you!
Reviewed and merged with a handful of corrections.

### Admin

- **Setup → Modules**: every installed module with an on/off switch.
- Third-party modules are discovered from a `pnlcs.json` manifest
  (PR #47, thanks to @terbora-core); see "Writing your own module" in the
  README.
- **Live Servers** quick action: one-click sign-in to your Panelica servers.
- Fully translated languages can be chosen as the default language.

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
