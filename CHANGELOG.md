# Changelog

All notable changes to PNLCS are documented here. Newest first.

## 1.4.0 — 2026-10-07

### Added

- **Import from WHMCS** (**Setup → Import WHMCS**, for staff with the
  `manage_settings` permission). Connect to an existing WHMCS database - it is
  only read, nothing in it is ever changed - and bring clients (with their
  custom fields), domains and services over through a field mapper: a preview
  of every record, fixed values, regex transforms, saved mappings, three modes
  (add new, add and update, update only) and a log of every record added,
  updated, skipped or rejected, with the reason.
  - Imported clients get a login. Nothing is mailed during an import: the
    customer chooses a password with "Forgot password" when they arrive, so the
    import can run before the move is announced.
  - An update never moves a domain or a service to another client, and never
    changes the product and server a live service runs on.
  - In every shipped language.

  **Thank you, [@hedon77](https://github.com/hedon77)**, for starting the
  importer and building it - and for everything else you keep contributing to
  PNLCS.

### Fixed

- **Updates in a hosting account.** Under a hosting account's cron (Panelica,
  cPanel and others) the system temporary directory is often not writable, and
  the check of a git installation stopped with "Unable to create temporary
  file". The updater no longer needs it.
- **A git installation's first update** no longer plans to remove `tests/`,
  `docs/`, `tools/` and the other paths a release package leaves out; they
  stay where they are.

### Updating from 1.3.0

- **Setup → Updates**, or `php artisan pnlcs:update`.
- Running PNLCS 1.3.0 inside a hosting account, and the check stops with
  "Unable to create temporary file"? Run this one update with a temporary
  directory of your own; 1.4.0 needs nothing of the kind afterwards:

  ```bash
  mkdir -p ~/tmp && TMPDIR=~/tmp php artisan pnlcs:update
  ```

- Docker: image `panelica/pnlcs-runtime:1.5` (`docker exec pnlcs /usr/local/bin/update.sh`).

### Database changes

- Three new tables: `whmcs_import_connections` (the WHMCS database password is
  encrypted with `APP_KEY`), `whmcs_import_profiles` and `whmcs_import_logs`.
  No existing table or row is changed.

### For theme and module authors

- Nothing changed for modules, themes or hooks.

### Views a theme may override that changed

- `resources/views/admin/layouts/app.blade.php` (the Import WHMCS menu entry).

## 1.3.0 — 2026-10-07

The first release since **1.2.0** (2026-07-10). Everything in this file from
here down to 1.2.0 ships in it: the dated sections below give the detail of
each piece of work; this entry is the whole picture.

### Upgrading from 1.2.0

- **Update now if you run 1.2.0 with the API switched on.** In 1.2.0 an API
  request that carried a valid identifier but no secret was accepted. This was
  fixed on 2026-07-11, and 1.3.0 is the first release with the fix.
- 1.2.0 has no updater, so this one update is made by hand, once: follow
  [Updating by hand](docs/install/updating.md#updating-by-hand-before-13)
  and check out `v1.3.0`. From 1.3.0 on, **Setup → Updates** (or
  `php artisan pnlcs:update`) does it, keeping your changes.
- Every one of the 138 new migrations adds; none edits or deletes an earlier
  one. The few that change existing data are listed under *Database changes*.
- Take a backup first ([Backups](docs/install/backups.md)).

### What is new since 1.2.0

**Updates and releases**
- Signed releases on a **stable** and a **beta** channel; installations are
  updated only from releases. The rules are in [RELEASING.md](RELEASING.md).
- **Setup → Updates** and `php artisan pnlcs:update`: an update checks the
  release's signature and compares it with your installation before it changes
  anything. Your themes, modules, hook files, `.env`, uploads and data are never
  touched; your edits to PNLCS's own files are merged or kept; a clash is shown
  to you, file by file, and edited right on the page or uploaded. The site is
  in maintenance for the seconds it takes, the database is snapshotted first,
  and any failure puts every file and the database back.
- **It heals itself.** An update cut off by a crash, a restart or a killed
  process is rolled back by the scheduler within a minute, with no one acting.
- A progress card shows each step as it happens; a bar at the bottom of the
  admin pages says when a release is available (hide it, or turn it off).

**Dialogs**
- One dialog system for the whole panel: every confirmation and notice is a
  styled, translated dialog. No browser `alert()` or `confirm()` is left.

**Security** (detail in *2026-07* and *2026-09-23* below)
- The API authentication bypass of 1.2.0 is closed; API secrets are stored as
  hashes and compared in constant time; credentials can be limited to IP
  addresses; gateway and registrar secrets and EPP codes are encrypted at rest.
- Broken access control on admin routes, an SSL client-area IDOR and payment
  forgery on Stripe and Razorpay are fixed; login throttling is per account.
- An audit of every API endpoint and of the MCP server: secrets no longer leave
  the API, every action answers to the right permission.
- Optional reCAPTCHA on sign-up, sign-in, password reset, the contact form and
  the ticket form; sign-in history and an optional mail on a new device;
  staff 2FA recovery codes work and the backup codes are shown once.

**Billing and payments**
- A redesigned tax model (VAT by country and the buyer's tax identity), a
  second billing currency, and customers choosing the currency prices and
  invoices are shown in.
- Collecting due invoices with a stored card (Stripe, iyzico, PayPal), safe to
  run twice; staff charge a stored card for one invoice on demand.
- The Stripe pay form is the Payment Element, with Apple Pay and Google Pay,
  in the customer's language.
- New gateways: **Tpay** and **iyzico**; proforma invoices; KSeF (Poland) and
  late fees; customer-group discounts, promotion rules, product requirements on
  codes, stock control and product addons that can be sold.
- Customers pay several invoices in one go, pay from their balance, renew early
  and change a service's billing cycle or options; staff add and remove credit.
- Domain renewal invoicing with the registrar's renewal call, prorated
  upgrades and downgrades, and many fixes to invoices, refunds, commissions,
  reports and reminders from the July audit.

**Domains**
- Customers manage nameservers (including glue records), WHOIS privacy and the
  WHOIS contact, restore a domain from redemption, renew several on one
  invoice, and give a domain to another client account; staff move domains
  between accounts and edit privacy, contact and glue on the admin page.
- A domain ordered with hosting is checked, priced and registered; a free
  domain with hosting; "set up on my hosting" from a domain's page.
- The **Openprovider** registrar module; DomainNameAPI fixes (bulk search,
  renewals, nameservers, privacy read-back, dialling codes); WHOIS for
  second-level names and `.tr`; events and hooks for registration, renewal,
  transfer and expiry; a registrar balance on the dashboard.

**Hosting with Panelica**
- Self-service tools in the client area: email, files (upload, drag and drop),
  databases, FTP, subdomains, cron jobs, DNS zone editing and backups, with a
  live dashboard of the account's usage.
- Managed resource plans, live usage graphs and one-click sign-in to the panel;
  **Live Servers** for staff.
- An **app catalogue** sold as one product, any app: customers pick and run
  containerised apps, served on their own domain, with a shell from the card.

**Proxmox VE**
- Selling VPS on Proxmox end to end: one VPS panel for customer and staff,
  order options, an image library and a step-by-step guide; customers give SSH
  keys when ordering or reinstalling.

**Store and client area**
- Guests shop and open the account at the payment step; the invoicing address
  is asked where the account is opened; email address confirmation (existing
  accounts are treated as confirmed).
- Sign in with **Google** and **GitHub**; the account owner invites and manages
  the account's users; contacts receive the emails they were added for.
- Marketing email consent with proof; personal data exported as one JSON file;
  the client area works on phones; country-aware billing identity fields.
- Order questions (a product's own custom fields), downloads limited to owners
  of particular products, private files with a download record, and a
  product's files on its service page.

**Tickets**
- Customers close and rate tickets; staff open tickets for customers, change
  status, priority, department and assignee from the ticket page, keep internal
  notes, use predefined replies, delete tickets, and inactive tickets close by
  themselves.

**Admin**
- Staff place an order for a customer; accept or cancel several orders at once;
  edit a service's record; to-do tasks for staff; mass mail recipients picked by
  product, server, status, group or domain.
- Search for invoices, services, domains, tickets, orders, menu pages and
  settings from the admin bar; how long each log is kept, set from the panel.
- **Setup → Modules** with an on/off switch for every module; fraud screening
  (MaxMind minFraud, FraudLabs Pro); phone verification through Twilio Verify
  or any SMS provider an addon installs; Telegram as a notification channel;
  configurable suspension grace and opt-in auto-termination.

**Extensibility**
- Addons bring their own service provider, menu entries, settings and
  `upgrade()`; modules are found by their manifest; new hooks (output hooks for
  the head, footer and service page; `EmailPreSend`, `InvoiceCancelled`,
  `ClientEdit`, `ClientDelete`, `ShoppingCartValidateCheckout`, `DailyCronJob`,
  `DownloadRequested`, `ServiceUnsuspended`, domain lifecycle hooks); a
  callback route for gateways without one of their own.

**API and MCP**
- All 171 API actions work and are documented; client SSO, invitations and
  per-login permissions; one error shape. `pnlcs-mcp`, a Model Context
  Protocol server, checked against the API reference.

**Languages and documentation**
- Complete German, Polish and Simplified Chinese; a full Turkish review; a
  language can be translated with AI from the panel.
- A full documentation site (installation, guides, developer, API, MCP,
  troubleshooting, FAQ) and a knowledge base that ships with the product.

### Database changes

All 138 migrations add tables, columns or rows. These also change existing data:

- `hash_api_credential_secrets`: API secrets are stored as SHA-256 digests.
  Clients keep sending their secret; it cannot be read back from the database.
- `encrypt_gateway_registrar_secrets`, `encrypt_epp_code_on_domains`: these
  values are encrypted with `APP_KEY`. Keep your `.env`.
- `rebuild_tax_rules`: `tax_rules.level` is replaced by `is_default`; the old
  catch-all rule becomes the default, so tax keeps applying.
- `extend_docker_apps_for_selling`: `docker_app_logos` is renamed
  `docker_apps`.
- `treat_existing_accounts_as_verified`: accounts that existed before email
  confirmation are marked confirmed, so no customer is locked out.
- `email_templates_multilingual`, `change_phone_prefix_length_to_4`,
  `add_google_login_to_users`: columns are widened or made nullable.

### For theme and module authors

- Modules and themes can state the PNLCS versions they work with
  (`"requires": {"pnlcs": ">=1.3 <2"}`); an update outside the range waits.
- New built-in themes, modules and hook files take the `pnlcs-` prefix.
- New hooks and addon capabilities: see *Extensibility* above and
  [Hooks](docs/developer/hooks.md).
- Confirmations use `pnConfirm(event, message)` / `pnDialog`; never the
  browser's `confirm()` (a test fails on it).

### Views a theme may override that changed

264 files under `resources/views` changed since 1.2.0. If your theme replaces
any of them, compare your copy: the updater lists, for your installation,
exactly the ones your active theme overrides.

### Updates in detail

#### Added

- **Releases on two channels.** PNLCS now ships as signed releases: stable, and
  beta about a week earlier. Installations are updated only from releases,
  never from the code under development. The rules every release follows are
  in [RELEASING.md](RELEASING.md).
- **Setup → Updates**, and `php artisan pnlcs:update`. An update downloads the
  release, checks its signature, and compares it with the installation before
  it changes anything:
  - your own themes, modules, hook files, `.env`, uploads and data are never
    touched;
  - your changes to PNLCS's own files are merged with the new version, or
    kept when the new version leaves the file alone;
  - when a change of yours and the new version clash, the update does not
    start: you choose, per file, the new version (yours is kept aside), yours,
    or a file you merged by hand;
  - the site is in maintenance for the few seconds the update takes; the
    database is snapshotted first, and if a migration, the new code or the
    health check after it fails, every file and the database are put back
    and the site comes back on its previous version;
  - an update cut off by a crash is rolled back with
    `php artisan pnlcs:update-rollback` (in Docker, by the container's next
    start), and nothing else runs until it is.
- A daily check tells administrators when a release is available
  (`update.available` in **Setup → Notification Channels**), and the results
  of updates are notified too (`update.completed`, `update.failed`).
- Modules and themes can state the PNLCS versions they work with
  (`"requires": {"pnlcs": ">=1.3 <2"}`); an update outside the range waits.
- A new permission, `manage_updates`, held by full administrators.

#### Docker image 1.5 (published on Docker Hub separately)

- The first start installs the newest signed release instead of cloning the
  development branch; dependencies and assets come built.
- `update.sh` and `AUTO_UPDATE=1` use the updater. Earlier images reset the
  code to the development branch and discarded changes made inside the
  container; recreate the container on 1.5 before updating.
- A code volume that holds an installation is never wiped, with or without a
  `.git` folder; an update cut off by a restart is rolled back on start.

#### For theme and module authors

- New built-in themes, modules and hook files take the `pnlcs-` prefix, so they
  never land on a name you use.

## 2026-09-25 — A domain ordered with hosting is checked, priced and registered

Reported in GitHub issue #48.

### Fixed

- **"Register a new domain" on a hosting order did nothing but leave a note.**
  The name was never checked, never priced, never put on the invoice and never
  sent to the registrar: the customer paid for the hosting alone and believed
  the domain came with it. The same was true of "Transfer". The domain now goes
  into the cart as a line of its own, at the price on the domain price list,
  and is registered (or transferred) when the invoice is paid, exactly like a
  domain bought from the domain search.
- The name is checked before anything goes into the cart: a registered name
  cannot be ordered as a new registration, a name the registry could not
  answer for is not assumed to be free, a name nobody has registered cannot be
  transferred, a transfer asks for the EPP code, and an extension the shop does
  not sell is refused without leaving the hosting in the cart on its own.
- The order summary on the configure page shows whether the name is available
  and what it costs, and adds it to the total.
- The cart names a domain line "Domain registration" or "Domain transfer" with
  its term, instead of "Product" and a dash.

## 2026-09-25 — The client area on phones

Reported in GitHub discussion #3, and measured in a browser at 320, 360, 375,
768, 1024 and 1280 pixels on every client page before and after.

### Fixed

- **On a phone the menu button was off the screen.** The language name, the
  user's name and the login and sign-up buttons pushed it past the right edge,
  so a visitor could not open the menu at all. On narrow screens these now
  shrink to icons or move into the menu, and a long company name is shortened.
- **A store with many product groups broke the desktop bar.** Every group was
  its own bar item; with a dozen groups the bar was 2,400 pixels wide on a
  1280-pixel screen. The groups are listed in the Services menu now, and a bar
  that still does not fit folds into the menu button.
- **Lists scrolled the whole page sideways** (services, domains, invoices,
  quotes, tickets, contacts, affiliates). A wide table now scrolls inside its
  own card.
- The contact, change password and new ticket pages keep their side column
  beside the form only when there is room for it; the knowledge base, the
  payment notification form, the order summary and several button rows no
  longer overflow narrow screens; the top bar of the home page and the guide
  pages' side menu fit a phone.

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

### Changed

- **Signing in to the hosting panel opens it in a new tab** from the service
  page and the containers tab, as it already did from the backups tab, so the
  client area stays open. From ENA Hosting.

### MCP server 1.0.7

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
