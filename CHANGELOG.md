# Changelog

All notable changes to PNLCS are documented here. Newest first.

## 1.14.0 — 2026-10-29

A stable release, offered to every installation. It contains everything in
1.13.0. None of the changes below went out in a beta first. Thank you,
[@ertugrulkeyvan](https://github.com/ertugrulkeyvan) (#323, #330, #333),
[@hedon77](https://github.com/hedon77) (#328, #329, #331, #332),
[@pla0730484848-wq](https://github.com/pla0730484848-wq) (#321, #324, #325,
#326) and [@dualarrow](https://github.com/dualarrow) (#320, #334).

**The verification mail no longer signs anybody in.** Its link confirmed the
address *and* signed in whoever opened it, for 24 hours and as often as it was
opened. Customers forward that mail — they are told to look for "the code" and
the link is the only thing in it — so whoever received it was in the account
without a password. The mail now carries a **six-digit code** to type into the
page that asked for it, and the link only confirms the address: opened where
nobody is signed in, it confirms and sends the visitor to the sign-in page. If
you have customised the verification template, add `{verification_code}` to
it; a template without it still gets a working link (#333).

**A domain ordered with hosting kept the nameserver change from 1.13.0** — no
action needed, this is only a reminder that it landed there.

### Added

- **Additional IPv4 addresses, sold with a Proxmox VPS.** A new configurable
  option (*Additional IPv4 addresses*, `extra_ipv4`) on the product page, with
  a price per address; at most 16 per guest. The server's IPv4 pool hands them
  out under the same lock as the guest's own address, all or nothing, and each
  one gets a network card of its own (`net1`, `net2`, …) with no gateway, so
  the guest keeps a single default route. A customer can buy more later as an
  option upgrade: the addresses already held are kept, only the cards PNLCS
  added are removed when the number goes down, and a virtual machine is asked
  to restart so cloud-init reads them. The **Capacity** page gains an *IPv4
  pool* card showing every address with its use, service and client, and the
  admin VPS panel lists a guest's additional addresses with their card. Only
  offered where the product takes its address from the pool — a product on
  DHCP cannot sell an address the module would never give (#323, #321).
- **Ukrainian and Russian are complete.** Both languages now carry every
  group `lang/en` has: 1,543 texts that fell back to English in each of them
  are translated. Every placeholder, HTML tag and plural form was checked
  against the English (#326).
- **A language can be completed through Export and Import.** The export is
  built from the English key list, so it carries every text with an empty
  value where the language has none; before, a key the language did not have
  yet was simply absent and could not be filled in. **Export missing** gives
  only what is not counted as translated yet. A text kept the same as English
  on purpose can be ticked as such in the editor and then counts as
  translated, so a complete translation can reach 100%; an exported file
  carries those marks and import reads them back. Import now leaves out keys
  PNLCS does not have and names them instead of storing them silently
  (#330, #324).
- **A selectable invoice template.** Settings → General → Invoicing offers the
  built-in document or one you upload. The uploaded file is plain HTML whose
  `{placeholders}` — the invoice, the client, the company, the items table —
  are replaced when the PDF is made. It is data, not code: nothing in it is
  executed, and the vocabulary is listed on the settings page (#331).
- **The general settings page is split into tabs** — Company, Invoicing,
  Domains, Notifications, Security, Automation, SEO & tracking, Other —
  instead of twenty-two sections in one scroll. The form and its single Save
  button are unchanged. A link into a section opens the tab holding it, so the
  admin search still takes you straight to a setting, and the tab is recorded
  in the address so it can be shared (#332).
- **HRD joins the registrar balance watch.** The registrar now reports its
  partner balance in PLN, so it can be picked in Settings → Registrar balance
  and warn before renewals stop for want of funds (#329).

### Changed

- The active settings tab takes the installation's own colour
  (`--theme-primary`), so white-label branding applies to it.

### Fixed

- **The import mapper answered with a 500.** It passed its own problem list to
  the view as `$errors`, shadowing Laravel's error bag, and the admin layout's
  `$errors->any()` then failed on a plain array. The list is `$problems` now
  (#328).
- **Text in a custom invoice template is written as text.** A company called
  "Müller & Sohn", or a name holding a tag, went into the document as markup
  and could break the invoice around it. Every value is escaped; the three
  that are deliberately markup — the logo tag, the items table and the notes —
  are escaped as they are built, as is the KSeF number and QR tag.
- The admin VPS panel's list of additional addresses was written with text
  outside a translation call.
- German counts as fully translated again: the settings page's new *Domains*
  tab is written "Domains" in German too, as the Domain and Registrar labels
  above it already are, and it is listed as such.
- The addon renewal test expected a plain month step, which overflows a due
  date on the 31st into the month after next; a cycle is advanced without
  overflow, so the expectation now follows `BillingCycleHelper`.

### Security

- The verification link no longer signs anybody in (see above). The code is
  six digits from `random_int`, kept only as a SHA-256 hash together with a
  hash of the address it was sent to, compared with `hash_equals`, good for
  fifteen minutes, and used up by five wrong tries. Sending the mail again
  replaces it, a code sent to an address the login no longer has is refused,
  and the route sits in the signed-in group at ten tries a minute (#333).

### Database changes

- `dynamic_translations.same_as_english` (boolean, default false), migration
  `2026_10_28_000001_add_same_as_english_to_dynamic_translations` (#330).
- `2026_10_29_000001_email_verification_template_code` rewrites the
  verification template in every language **only** where the row still holds
  the text PNLCS shipped; a wording you wrote is left alone, and running it
  again changes nothing (#333).
- New settings: `InvoiceTemplate`, `InvoiceTemplateName` (#331); server
  setting `capacity_alert` keeps its meaning from 1.13.0.

### For theme and module authors

- `InvoicePdfService::merge()`, `placeholderVars()` and
  `customTemplatePath()`; the placeholder vocabulary is the keys of
  `placeholderVars()` (#331).
- `Ipv4Pool::holders()` and `Ipv4Pool::extras()`; `taken()` is built on them.
  `ProxmoxPlan::OPTION_KEYS` gains `extra_ipv4` and `ProxmoxPlan::MAX_EXTRA_IPV4`
  is 16. A service's additional addresses live in its module data as
  `pve_ipv4_extra`, with `pve_extra_nics` counting the cards PNLCS added (#323).
- `EmailVerificationController::CODE_MINUTES` and `CODE_ATTEMPTS`; the
  verification mail passes `{verification_code}` and `{code_minutes}` to a
  customised template; `EmailVerificationMail` takes the code as its last
  argument, so existing callers keep working (#333).
- `TranslationController::SAME_AS_ENGLISH` is the key an exported file uses
  for the texts kept the same as English (#330).
- `HrdRegistrar::getBalance()` returns the balance under `pln` (#329).

### Views a theme may override that changed

- `resources/views/admin/settings/general.blade.php` (tabs; the invoice
  template controls).
- `resources/views/client/auth/verify-email.blade.php` and
  `resources/views/emails/email-verification.blade.php` (the code).
- `resources/views/admin/config/languages/translations.blade.php` (Export
  missing, the *Same as English* tick and its marker).
- `resources/views/admin/config/server-capacity.blade.php` (the IPv4 pool
  card) and `resources/views/admin/products/partials/proxmox.blade.php`
  (the additional-addresses option).
- `resources/views/components/vps-panel.blade.php` (a guest's additional
  addresses).
- `resources/views/admin/whmcs-import/mapper.blade.php` (`$problems`).

## 1.13.0 — 2026-10-27

A stable release, offered to every installation. It contains everything in
1.12.0. None of the changes below went out in a beta first. Thank you,
[@ertugrulkeyvan](https://github.com/ertugrulkeyvan) (#294, #309 to #314,
#316 to #318) and [@pla0730484848-wq](https://github.com/pla0730484848-wq)
(#302, #303, #305 to #308, #315, #322).

**A domain ordered with hosting is now registered with the hosting's
nameservers.** Registration passed no nameservers before, so every registrar
module used its own (often the registrar's parking servers) and the site did
not open until the customer changed them. Now, in order: nameservers already
on the domain; those of the hosting server ordered with it (or, while the
server is not yet chosen, the ones every server of its group shares); Setup →
General Settings → **Default Nameservers**; and only then the module's own, as
before. Check your servers' nameservers and the defaults before updating
(#318).

**The reason typed when suspending a service is shown to the customer**, on
the service page and in the suspension email. Write it for the customer to
read (#309).

### Added

- **Prices that include tax, and tax switched off.** Setup → Tax
  Configuration has **Charge tax** (on by default; off adds no tax anywhere and
  keeps the rules) and **Prices include tax** (off by default; on, the price
  entered is what the customer pays and the tax is the part of it the rate
  accounts for, so 6.00 at 20% is 5.00 + 1.00). Each invoice keeps the mode it
  was issued with, so changing the settings later does not change an invoice
  already issued. The PDF, the client and admin invoice pages and KSeF all
  print the VAT rows from the same computation. With both switches at their
  defaults nothing changes (#312, #305).
- **Invoice each renewal separately.** Setup → General Settings → Invoices,
  off by default. On, each service (with its own addons and usage), each
  domain and each addon whose service is not renewing gets its own renewal
  invoice, so a customer can renew one server and let another lapse (#311,
  #302).
- **Proxmox capacity.** A **Capacity** page for each Proxmox server (next to
  Images) shows, per node, the CPU, memory and disk the guests have been given
  against what the node has, what PNLCS set up, what is used now, running and
  stopped guests, and this server's services by status. **Capacity alert (%)**
  on the server form (80 unless set, 0 = off): `pnlcs:proxmox-capacity` runs
  hourly and raises `server.capacity_high` once a day per node and resource
  when memory or disk given out passes it. CPU is shown but never alerts
  (#316, #303).
- **Search the admin list of services** by IP address, domain, username or
  service id. An IP address is matched whole against what the modules record
  (the Proxmox pool or reported address, Vultr's main IP, further addresses).
  The admin bar search uses the same rule, so it finds a server by its IP
  address too (#313, #308).
- **A suspension reason.** Suspend on the admin service page takes a reason
  (up to 255 characters). The customer reads it on the service page and in the
  suspension email; a billing suspension reads "Unpaid invoice"; with no
  reason the customer is asked to contact support. The email no longer calls
  every suspension an unpaid invoice (#309, #306).
- **Balance adjustments by staff** are listed under Billing → Transactions,
  with the date, client, credit added or deducted, amount, reason and the
  member of staff. They are kept apart from the transactions: no money moved,
  so they are not income (#310, #307).
- **A module installed through `pnlcs.json` may bring its own service
  provider** (`"provider"`), so a gateway's checkout pages, webhooks,
  migrations and scheduled tasks live in its folder and survive updates. A
  provider that fails is logged with the module's name and skipped (#317,
  #315).

### Changed

- **A paid order's servers are set up by a job** (`ProvisionOrderedService`).
  With a queue worker (`QUEUE_CONNECTION=database` and the scheduler) the
  checkout and the card payment's return answer at once. With the shipped
  `QUEUE_CONNECTION=sync` the setup still runs in the request, as before.
  Staff accepting an order by hand still wait and see the result. A second
  press of **Complete order** while the order is being placed no longer
  places it twice (#314).
- **Releasing an order held as fraud** makes its invoice payable again and
  switches back on the services the verdict suspended (only those). A part-paid
  invoice takes back the credit its cancellation returned and reopens part
  paid, so the payment is not counted twice; if that credit has been spent,
  the invoice stays cancelled for staff to settle (#294).

### Fixed

- **The client area named the Proxmox server a VPS runs on** in Server
  Information. A VPS now shows the customer the address of their own server;
  other services still show their server (#322).
- A **renewal test** expected a due date on the 31st to move into the month
  after next; the code correctly moves it to the last day of next month.

### Database changes

- `invoices.tax_mode` (nullable; null = tax added on top, as every existing
  invoice, `inclusive` or `none`), migration
  `2026_10_25_000002_add_tax_mode_to_invoices` (#312).
- New settings: `InvoiceRenewalsSeparately` (#311), `TaxEnabled`,
  `TaxInclusivePrices` (#312); server setting `capacity_alert` (#316).

### For theme and module authors

- `pnlcs.json` may name a `provider` class; `App\Support\GuardedProviders`
  registers and boots it, as it does an addon's (#317).
- `Invoice::lineTax()`, `Invoice::vatBreakdown()`, `Invoice::currentTaxMode()`
  and `TaxRule::enabled()`, `pricesIncludeTax()`, `taxShare()` and `taxOn()`
  (#312). A view that prints VAT rows should use `vatBreakdown()`.
- `Service::customerSuspensionReason()` (#309), `Service::matching()` (#313).
- `DomainHosting::serverNameservers()` and `DomainHosting::defaultNameservers()`
  (#318). Registration passes `ns1`…`ns5` and `nameservers` to the registrar
  module.
- Job `App\Jobs\ProvisionOrderedService` and `OrderService::provisionOrderedService()`
  (#314).
- Notification event `server.capacity_high`, command `pnlcs:proxmox-capacity`,
  route `admin.config.servers.capacity` (#316). `Credit::admin()` (#310).

### Views a theme may override that changed

- `resources/views/admin/config/servers.blade.php` (Capacity button, Capacity
  alert field) and the new `admin/config/server-capacity.blade.php`.
- `resources/views/admin/config/tax.blade.php` (the two tax switches).
- `resources/views/admin/config/transactions.blade.php` (balance adjustments).
- `resources/views/admin/invoices/create.blade.php`,
  `resources/views/admin/invoices/show.blade.php`,
  `resources/views/client/invoices/show.blade.php` and
  `resources/views/pdf/invoice.blade.php` (VAT rows from `vatBreakdown()`).
- `resources/views/admin/services/index.blade.php` (search, address under the
  domain) and `resources/views/admin/services/show.blade.php` (Suspend reason).
- `resources/views/admin/settings/general.blade.php` (Invoice each renewal
  separately).
- `resources/views/client/services/show.blade.php` (suspension notice; no
  Proxmox server name).
- `resources/views/emails/service-suspension.blade.php` (the reason).

## 1.12.0 — 2026-10-25

A stable release, offered to every installation. It contains everything in
1.11.0. None of the changes below went out in a beta first. Thank you,
[@ertugrulkeyvan](https://github.com/ertugrulkeyvan) (#288 to #293, #295 to
#300, #304) and [@pla0730484848-wq](https://github.com/pla0730484848-wq)
(#286, #287, #301, #305).

**Integrations using the API: a draft invoice now waits until it is
published.** `createinvoice` with `status=draft` used to mail the customer
the invoice, spend their credit balance on it and list it in their client
area. A draft now does none of that. `updateinvoice` with `status=unpaid`
publishes it: it is mailed and the balance applied, as for an invoice
created unpaid. If your integration creates drafts and never publishes them,
customers stop seeing them (#295).

**English text is read from `lang/en/*.php` only.** `lang/en.json` repeated
461 of those strings and, in English, took precedence over them, so an edit
to `lang/en/common.php` did not show in English. The update removes the file;
if you changed it yourself it is kept, and it keeps overriding. Two English
labels change: "Postcode" becomes "Postcode / ZIP" and "State" becomes
"State / Region" (#300).

### Added

- **Suspend only the services on the overdue invoice.** Setup → General
  Settings → Automation, off by default. Off, an invoice overdue past the
  grace period suspends every active service of the client, as before. On,
  only the services on that invoice are suspended: the service itself, its
  usage overage or one of its add-ons, so a late domain renewal no longer
  switches off the client's servers. Unsuspend on payment asks about the same
  debt, so a paid service comes back on while another invoice is still open
  (#291, #301).
- **A promotion can be limited to billing periods.** Both promotion forms have
  **Billing periods**; none ticked means every period, which is what every
  existing code keeps. A limited code is refused in the cart when no line is
  on one of its periods, and in a mixed basket and on renewals it comes off
  only the lines that are (#304).
- **Notification rules can be edited:** event, channel, recipient and the
  on/off switch, under the same checks as a new rule. They could only be
  added and deleted (#298).
- **An escalation rule's "Send notification on escalation"** raises the
  `ticket.escalated` event, which a notification rule can now pick. The
  switch was saved and never read (#296).
- **The customer's list of services shows each server's address** under the
  domain. The related-service pickers on the customer's and the admin's New
  Ticket pages, and the admin's ticket page, show it beside the domain. The
  address is what the module recorded (an address from the pool, the one the
  Proxmox guest last reported, Vultr's main IP); no server is asked (#289,
  #287).
- Menu entries for **Ticket Escalation Rules**, **Configurable Options** and
  **Mass Email**, which could be reached only by their address (#296).

### Changed

- **The admin top bar stays on one row** from 1280px up, with each count
  badge beside its icon. Between 1275px and 1599px the menu shows its labels
  without icons. The mobile menu is unchanged.

### Fixed

- **The VAT invoice for a paid proforma keeps the proforma's exchange rate
  and bulletin.** It was stamped with the rate of the day it was issued, so a
  customer billed in lira could get a legal invoice for a different lira
  amount from the proforma they had paid (#290).
- **Edit Client** has **Tax Exempt**, **Never suspend this client
  automatically** and **Group**. Billing reads all three, and none could be
  set once the client existed (#292, #305).
- **Client groups:** Add and Edit have **Never suspend this group's clients
  automatically** and **Never terminate this group's clients
  automatically**. Automatic suspension and termination read both, and no
  form set them (#293).
- **Network issues keep the server they affect.** The form has **Affected**,
  with your servers suggested, and customers see it on Network Status. There
  was no field, and the controller saved a key no column holds (#297).
- **Domain Pricing** has **Redemption Period (days, after grace)** for each
  extension. Domain sync and the renewal reminders count it, and it stayed 0
  because no form set it (#299).
- **Proxmox Test Connection** no longer says an existing vendor snippet is
  missing when the token has Datastore.AllocateSpace on the storage but not
  Datastore.Allocate: Proxmox lists snippets only to a token with Allocate.
  It now says the snippet could not be checked, and reports a problem only
  when AllocateSpace is missing too (#288, #286).

### Database changes

- `promotions.billing_cycles` (nullable; the periods, comma-separated),
  migration `2026_10_25_000001_add_billing_cycles_to_promotions` (#304).
- New setting: `AutoSuspendInvoicedOnly` (#291).

### For theme and module authors

- `lang/en.json` is gone; English strings come from `lang/en/*.php`. A JSON
  translation file that repeats a key of the group files fails the test
  suite (#300).
- `InvoiceService::publishDraft(Invoice $invoice)` issues a draft: it becomes
  unpaid, `InvoiceCreated` is raised and the client's balance is applied.
  `createInvoice()` holds all three back for a draft. The client area answers
  404 for a draft's page and PDF (#295).
- `Service::scopeOwingOverdue(int $graceDays)` and
  `SuspensionCommand::invoicedOnly()` (#291).
- `Promotion::billingCycles()`, `coversCycle()` and `cycleKey()` (#304).
- `Service::primaryIp()` gives the address the module recorded (#289).
- Notification event `ticket.escalated` (#296); route
  `admin.config.notification-rules.update` (PUT) (#298).
- `Client` makes `override_auto_suspend` fillable (#292).

### Views a theme may override that changed

- `resources/views/admin/clients/edit.blade.php` (Tax Exempt, Never suspend
  automatically, Group).
- `resources/views/admin/config/client-groups.blade.php` (the two
  exemptions).
- `resources/views/admin/config/domain-pricing.blade.php` (Redemption
  Period).
- `resources/views/admin/config/network-issues.blade.php` (Affected).
- `resources/views/admin/config/notifications.blade.php` (Edit Rule).
- `resources/views/admin/config/promotions.blade.php` (Billing periods).
- `resources/views/admin/layouts/app.blade.php` (three menu entries).
- `resources/views/admin/settings/general.blade.php` (Suspend only the
  services on the overdue invoice).
- `resources/views/admin/tickets/create.blade.php`,
  `resources/views/admin/tickets/show.blade.php`,
  `resources/views/client/tickets/create.blade.php` and
  `resources/views/client/services/index.blade.php` (the server's address).

## 1.11.0 — 2026-10-25

A stable release, offered to every installation. It contains everything in
1.10.0. None of the changes below went out in a beta first. Thank you,
[@ertugrulkeyvan](https://github.com/ertugrulkeyvan) (#279 to #285),
[@dualarrow](https://github.com/dualarrow) (#276) and
[@pla0730484848-wq](https://github.com/pla0730484848-wq) (#278).

**Automatic termination now ends only services suspended for non-payment.**
It used to take any service suspended long enough while the client owed some
overdue invoice, so a server staff suspended by hand (an abuse report, a
customer's request) was deleted with its data. It now takes only suspensions
automatic suspension made. A service suspended for non-payment with no reason
recorded is no longer terminated on its own either: end it by hand (#280).

### Added

- **Consolidated billing day.** Setup → General Settings → Invoices,
  **Consolidated billing day** (1–28, off by default): on that day each
  customer gets one invoice for everything renewing before the same day next
  month, plus the days set in **Raise renewal invoices (days before due)**,
  due after **Default payment due (days)**. Between billing days only what
  would otherwise be invoiced late is raised, such as a service ordered since.
  Off, nothing changes. If the billing day's run is missed (the server was
  down), that month's renewals are invoiced on their own days and the next
  billing day picks up from there (#285).
- **Promotions:** both promotion forms have **Start Date** and **Billing
  cycles discounted** (0 = no limit), and the Add form has **Applies to
  renewals as well**. Billing already read both; they could only be set in
  the database (#283).

### Changed

- **A customer cannot move onto a Panelica package that cannot hold what the
  account has.** The panel applies a plan's limits when something is created,
  not to what exists, so three apps stayed on a two-app package. A package
  that gives less than the account's plan does now is greyed out on the
  Upgrade / Downgrade page with what to remove first: apps, domains, email
  and FTP accounts, subdomains per domain and disk space, counted the way
  Panelica counts them. The move is refused through the API's
  `upgradeproduct` as well. Memory above a package is a warning, not a
  refusal. Staff can still change a service's product on its admin page
  (#276).
- **Rolling a VPS back to a snapshot asks for the server's name**, as a
  reinstall and a backup restore do, in the customer's panel and the admin's
  (#281).
- **A Panelica mailbox or database the panel refuses says why:** "limit
  reached" was shown for every refusal, including an API key without the
  scope. A limit keeps the translated message; anything else is shown as the
  panel worded it and logged (#282).

### Fixed

- **Proxmox with a token limited to a resource pool**, as Test Connection
  recommends: installing an image under Servers → Images, and creating a
  server from an ISO or a container, was refused with 403 on `/vms/<id>`.
  Proxmox checks tags on a new guest before it joins the pool, so with a pool
  set the tags are now written once the guest is in it (#278, #279).
- **Moving onto a product with managed resources** did not move the Panelica
  account onto that product's plan, while billing moved to the new product.
  The account now goes onto the product's own plan, as a new account does
  (#276).

### Security

- **The customer's VPS status named the Proxmox node** the server runs on,
  often the host's real name. It is now left out for customers; staff still
  see it (#284).

### Database changes

None. New setting: `ConsolidatedBillingDay` (#285).

### For theme and module authors

- A server module may implement
  `packageChangeProblems(Service $service, Product $newProduct): array`
  (`problems` and `warnings`, in words for the customer).
  `UpgradeService::packageFit()` asks it, and `requestProductChange()`
  refuses a package with problems (#276).
- `ServesVps::vpsShowsInfrastructure()` decides whether the status answer
  carries `node`; false unless a controller says otherwise (#284).
- `InvoiceGenerationService::billingWindow()` gives the last due date a run
  invoices and how far ahead that is (#285).
- API: `upgradeproduct` answers 422 when what the account has does not fit
  the new package, with what to remove in the message (#276).

### Views a theme may override that changed

- `resources/views/admin/config/promotions.blade.php` (Start Date, Billing
  cycles discounted, Applies to renewals as well).
- `resources/views/admin/settings/general.blade.php` (Consolidated billing
  day).
- `resources/views/client/services/upgrade.blade.php` (packages that do not
  fit, memory warnings).
- `resources/views/components/vps-panel.blade.php` (the server's name before
  a rollback).

## 1.10.0 — 2026-10-24

A stable release, offered to every installation. It contains everything in
1.9.0. None of the changes below went out in a beta first. Thank you,
[@ertugrulkeyvan](https://github.com/ertugrulkeyvan) (#249 to #273) and
[@pla0730484848-wq](https://github.com/pla0730484848-wq) (#274, #275).

**Check your Domain setting after updating.** New installs were seeded with
`hosting.panelica.com` as Setup → General Settings → **Domain**, and that is
the address the scheduler's mail links to (invoice reminders, suspension
notices, renewal invoices). New installs now leave it empty, so those links
follow the address given to the installer (#252). If yours still says
`hosting.panelica.com` and that is not your shop, correct or clear it.
Nothing is changed for you.

**Integrations using the API:** `openticket` and `updateticket` now refuse
the priority `critical` (422). No screen and no escalation rule knew it, and
a critical ticket fell to Low on its next save from the ticket page. Send
`high` instead (#257).

### Added

- **Pay an invoice with a stored card.** While automatic payment is on, an
  open invoice lists the customer's stored cards, each with a **Pay with Visa
  •••• 4242** button above the gateway forms. The card is charged at once,
  the way automatic payment charges it: one attempt at a time, recorded once,
  and a bank that asks the cardholder to confirm is answered on the invoice
  page. A customer who switched automatic payment off can still pay this way,
  as they are paying now, not agreeing to be charged later. Add Funds invoices
  and payments for several invoices are paid with the form. The Stripe form's
  "keep this card" box says a new card is kept beside the one already kept
  (#272).
- **A product can keep its customers on it.** Setup → Products/Services, a
  product's **Upgrade packages** card has **Customers can move to another
  package**, ticked by default. Unticked, the Upgrade / Downgrade page offers
  no other package, a change asked from the client area or through the API's
  `upgradeproduct` is refused, and the service page leaves out Upgrade /
  Downgrade unless the product has configurable options to raise. Staff can
  still change a service's product on its admin page, and the packages ticked
  in the list are kept for when moving is allowed again (#275).

### Changed

- **The Upgrade packages list is a box per package** across the card, under
  each product group, scrolling when it is long. It was a narrow multi-select
  that cut names off and needed Ctrl/Cmd to choose several (#274).
- **General Settings checks what it saves:** a word in the SMTP port, a
  negative grace period or late fee, a time zone PHP does not know, an e-mail
  field that is not an address, or a mail type, SMTP security or late fee
  type the code does not handle is refused with the reason at the top of the
  form, and nothing from that save is stored (#253).
- **General Settings → Mail Type has Server default (.env)**, selected while
  no mail type is stored: mail then goes through the mailer in `.env`. Choosing
  it hands a stored choice back to `.env` (#250).
- **Three General Settings fields are gone** because nothing read them:
  Admin Login URL Prefix (the admin area is always `/admin`), Client Area
  Template (the client area follows the active theme) and Enable Online Order
  Form (the store was open whatever it said). Stored values are left as they
  are (#251).
- **Currencies:** the edit dialog's Set as Default box, which saving ignored,
  is gone (use **Make default** in the list) (#261), and so is the Name
  column, which repeated the prefix (#262).
- **The Client Sources report** counts clients referred by an affiliate and
  the rest; it grouped them by staff's private notes (#264).
- **A knowledge base article** is always given a category; the form no
  longer offers Uncategorized, which could never be saved (#258).
- **Annual Income and Daily Performance** have a year field (and a month
  for Daily Performance); their period could be changed only through the
  address (#266).

### Fixed

- **Saving General Settings changed the shop's default language** to the
  first one in the list: the form did not see the language the languages
  screen stores. It is selected now, and choosing another one there changes
  it the way the languages screen does (#249).
- **Saving General Settings for any reason moved mail from `.env` to PHP
  Mail**, so an install sending through SMTP set in `.env` stopped sending
  (#250).
- **Making another currency the default** moved only the flag: the new
  default kept its old rate and every other rate stayed against the old one
  until the next rate update. Rates are now restated against the new default,
  and the TCMB bulletin details a restated lira rate no longer comes from are
  cleared (#260).
- **Tickets closed through the API** (`updateticket`, `mergeticket`) run the
  TicketClosed / TicketClose hooks, as tickets closed on screen do (#254).
- **Changing a VPS root password** runs the password hook, and the hook also
  runs under the name AfterModuleChangePassword (#255).
- **Ticket Departments** shows each department's ticket count; it showed 0
  (#256).
- **A notification rule can listen for a failed domain transfer**
  (`domain.transfer_failed`); the event was sent and nothing could subscribe
  to it (#259).
- **Reports:** Income by Product credited domain, addon and upgrade lines to
  an unrelated product whose service id matched (#263); Aging Invoices left
  out part-paid invoices and now shows what is still owed (#265).
- **A project created in the admin area** records who created it, which the
  Project Management addon lists as Assigned (#267).
- **`pnlcs:i18n-convert --dry-run`** no longer writes the translations table
  (#268).
- **`.env.example`** drops `MAIL_ENCRYPTION`, which nothing reads, and says
  what `MAIL_SCHEME` takes (#269). `CONTRIBUTING.md` lists every shipped
  module (#270), and the README points to the PNLCS Community (#273).

### Security

- **Staff signed in as a customer** (**Login as Client**) can no longer
  store a card, tick "keep this card", switch automatic payment on or off, or
  pay with the customer's stored card. Each of these is the cardholder's own
  consent, and it was recorded under the customer's name. Staff can still see
  the cards and pay an invoice with the gateway form; a stored card is charged
  on staff's initiative with **Charge card now** on the invoice in the admin
  area, which keeps to the customer's automatic-payment choice (#271).
- **The Client Sources report printed staff's private notes about clients**
  to anyone who can open reports (#264).

### Database changes

- New column `products.allow_upgrades` (boolean, default true): migration
  `2026_10_24_000001_add_allow_upgrades_to_products`. Every existing product
  allows moving, as before (#275).
- New installs seed the `Domain` setting empty (#252).
- Choosing Server default (.env) deletes the `MailType` setting (#250).
- Making another currency the default rewrites the other currencies' rates
  and empties `ExchangeRateSource`, `ExchangeRateKind`, `ExchangeRateDate` and
  `ExchangeRateBulletin` (#260).
- `AdminDir`, `ActiveClientAreaTemplate` and `OrderFormDisplayedOn` are no
  longer written; stored values stay (#251).

### For theme and module authors

- `App\Support\Impersonation::active()` says whether staff are signed in as
  the customer (#271).
- `AutoChargeService::payNowWith(Invoice, PaymentMethod)` charges a stored
  card the customer picked; route `client.invoices.pay-with-card` (POST)
  (#272).
- `UpgradeService::canMoveTo()` is false for every package when the
  product's `allow_upgrades` is off (#275).
- `AbstractReport::periodFilter()` returns `'year'`, `'month'` or `null`; it
  is not on `ReportModuleInterface`, and the report page asks for it only
  when a report has it (#266).
- Hooks: `AfterModuleChangePassword` runs beside `AfterModulePassword`, and
  both run for a VPS root password (#255); `TicketClosed` / `TicketClose` run
  for tickets closed through the API (#254).

### Views a theme may override that changed

- `resources/views/admin/config/currencies.blade.php` (no Set as Default
  box, no Name column).
- `resources/views/admin/config/knowledge-base.blade.php` (no
  Uncategorized).
- `resources/views/admin/products/edit.blade.php` (Upgrade packages).
- `resources/views/admin/reports/show.blade.php` (year and month fields).
- `resources/views/admin/settings/general.blade.php` (errors, Mail Type,
  System Settings).
- `resources/views/client/invoices/show.blade.php` (Pay with a stored card).
- `resources/views/client/payment-methods/index.blade.php` (staff signed in
  as the customer).
- `resources/views/client/services/show.blade.php` (Upgrade / Downgrade only
  when something can be offered).

## 1.9.0 — 2026-10-23

A stable release, offered to every installation. It contains everything in
1.8.0. None of the changes below went out in a beta first. Thank you,
[@pla0730484848-wq](https://github.com/pla0730484848-wq) (#246 to #248) and
[@dualarrow](https://github.com/dualarrow) (#245 and the free order report on
the forum).

### Added

- **Product groups can be edited, hidden and deleted.** A group could only be
  created (#246). Each group on Setup → Products/Services now has **Edit**
  (name, headline, tagline, **Hidden**: a hidden group is left out of the
  store, and its products can still be ordered through a direct link) and,
  when it holds no product, **Delete**. A group with products is refused,
  because deleting a group in the database takes its products with it; a
  group whose deleted products are still named by past services is refused
  too, so hide it instead.
- **A gateway that is switched on but missing its keys says so.** Checkout
  leaves such a gateway out; its card under Setup → Payment Gateways now
  says **Not offered** and names the fields to fill in.

### Changed

- **A refused form says why on every admin page.** Most admin pages never
  printed the reasons a form was refused, so a save that did not go through
  looked as if nothing had happened: a Proxmox product whose save was refused
  over one of its fields read as "the group cannot be changed" (#247). The
  admin layout now lists the reasons a page does not print itself.
- **The product buttons say what they make:** **New Product** (it makes every
  kind of product, a non-hosting service included, as Type Other with no
  server module) and **Add Invoice Item**, a ready-made line for invoices you
  create by hand; the section listing those is **Invoice Items** (#248).
- **"Open app" opens the app on the customer's domain** when the app is
  pointed at one, and shows the server address under it for a domain that
  does not reach the server yet; without a domain it says how to use one.
  The mailed connection details do the same (#245).
- **A free order asks for no payment.** A cart of free products at no charge
  shows no payment step; after the order the customer goes to their services
  with "There was nothing to pay" (and that it is set up once reviewed, when
  the product is set up by hand), not to a paid invoice saying "Please
  complete payment".
- **The client page's unpaid total** is labelled Unpaid Invoices and is red
  only when something is owed; at zero it read as an "UNPAID" status.

### Fixed

- **A product set to Free with its prices left as the form starts them** (-1,
  not sold, on every cycle) could not be ordered: the store said "Contact us"
  and checkout said no billing cycle was chosen. Such a product is now sold
  as Free, and the store and order page say Free. Free products with 0 on
  chosen cycles keep exactly those.
- **The apps page on a phone:** a long app name ran under its status badge,
  and the domain picker cut off its button.
- **The apps page in German and Spanish** read as machine translation:
  "Offene Schale" and "Als Wurzel" for Open shell and As root, "Häfen" for
  Ports, "Concha abierta", "Punto aquí." and category names with HTML
  entities in them. Rewritten.
- **The server edit form** names the password field by server type, as the
  add form does (a Panelica server's API Key read "New password").

### Database changes

None.

### For theme and module authors

- `ModuleRegistry::missingGatewaySettings($module, $values)` lists a
  gateway's required settings that hold no value; `usableGateways()` uses it.
- `ContainerAccessDetailsMail` takes an optional `serverUrl`.
- The admin layout prints validation errors the page did not print; an admin
  view that prints its own is unchanged.

### Views a theme may override that changed

- `resources/views/admin/clients/show.blade.php` (the unpaid total).
- `resources/views/admin/config/gateways.blade.php` (Not offered and the
  missing fields).
- `resources/views/admin/config/servers.blade.php` (the edit form's password
  label).
- `resources/views/admin/layouts/app.blade.php` (the reasons a form was
  refused).
- `resources/views/admin/products/create-group.blade.php` (also the edit
  form, with Hidden).
- `resources/views/admin/products/index.blade.php` (group Edit and Delete,
  the button names).
- `resources/views/client/cart/checkout.blade.php` (no payment step for a
  free order).
- `resources/views/client/cart/configure.blade.php` (Free on a free product).
- `resources/views/client/cart/store.blade.php` (Free on a free product).
- `resources/views/client/services/hosting/containers.blade.php` (Open app on
  the domain, the phone layout).
- `resources/views/emails/container-access-details.blade.php` (the domain and
  the server address).

## 1.8.0 — 2026-10-23

A stable release, offered to every installation. It contains everything in
1.7.0. None of the changes below went out in a beta first. Thank you,
[@ertugrulkeyvan](https://github.com/ertugrulkeyvan), for all of them
(#220 to #244).

**Check your product addons after updating.** The addon pricing form said
"leave a cycle at zero to not offer the addon on that cycle", but 0 has
always sold the addon free on that cycle. An addon set up by that hint may be
on sale for nothing on some cycles: set those cycles to -1 (#236). Nothing is
changed for you.

### Added

- **A held or cancelled order can be put back to Pending** from its page
  (**Set back to Pending**), as the API's `pendingorder` already could: an
  order held by fraud screening by mistake no longer needs the API. As with
  the API, services suspended and the invoice cancelled when it was held stay
  as they are (#230).
- **How early renewal invoices are raised is a setting:** Setup → General
  Settings → Invoices, *Raise renewal invoices (days before due)*, 14 by
  default as before. It was always 14, whatever was configured. Set below the
  automatic charging lead time (3 days by default), a stored card is charged
  as soon as the invoice is raised (#231).

### Changed

- **Addon pricing:** a cycle takes -1 to withdraw the addon from it, and the
  hint says what 0 and -1 mean (#236).
- **The server form names the credential field each module reads:** API Token
  (cPanel), API Key (Plesk) and Login Key (DirectAdmin) on the second field;
  the error for a missing one names that field (#235).
- **A ticket department's address** is described as the address customers
  write to, used to import their mail, which is what it is; new installs seed
  the default departments without the project's own addresses (#237).

### Fixed

- **Promotions:**
  - a fixed-amount code was taken off in the cart and never on the invoice
    (#220);
  - "new customers only" never applied to a first order, "once per customer"
    counted the order being placed as a use, and "existing customers only"
    let a first order through. A code that is refused is no longer kept on
    the order, where renewals would have applied it later (#221).
- **Invoices:**
  - the lines of a paid, cancelled or refunded invoice can no longer be
    changed on its page (#222);
  - **Mark Paid** proposes what is still owed, not the total, and **Credit**
    pays from the client's credit balance; it used to mark the invoice paid
    and leave the balance untouched (#229);
  - a quote turned into an invoice no longer puts the quote's admin notes on
    the customer's invoice (#223);
  - an invoice no longer names a TCMB bulletin when the lira rate came from
    elsewhere, and only lira invoices carry one (#224).
- **Fraud screening** sends the order's amount in the shop's currency; it was
  always sent as US dollars (#225).
- **Servers and provisioning:**
  - a product without a server group skips disabled servers and those at
    their account limit (#226), and the domain check while ordering skips a
    disabled server too;
  - **Test connection** knocks on each panel's own port (HestiaCP, aaPanel,
    CyberPanel fell back to 22), tests Vultr at its API (#227), and fails on
    DirectAdmin's login page (#241);
  - a product saved with no package uses the module's default, and an older
    plan key still applies (#232); a Panelica package change reads the
    package the product page sets (#233); a product page whose panel cannot
    list packages keeps the saved one (#234);
  - Proxmox applies the IPv6 setting to KVM guests (#242); Plesk sends its API
    key in the header the XML API reads (#243); HestiaCP removes the user when
    the domain cannot be added, instead of marking the service active, and
    retries a taken username (#244);
  - the client service page shows live usage for HestiaCP and aaPanel too
    (#228).
- **KSeF:**
  - the verification QR points at the environment set in the addon (#238);
  - the seller address comes from General Settings (#239);
  - a number is recorded, printed and announced only once KSeF accepted the
    invoice (#240).

### Database changes

None. New installs seed the default ticket departments without an email
address (#237). New setting: `InvoiceDaysBeforeDue` (#231).

### For theme and module authors

- `AbstractServerModule::planFrom(array $config, array $keys)` returns the
  first key with a non-blank value; `getRemotePackage()` uses it (#232).
- `Order::currencyCode()` is the currency an order's amount is in (#225).
- `Promotion::isValidFor()` takes an optional order id to leave out, and
  `InvoiceGenerationService::applyPromotion()` an optional `placingOrder`
  (#221).
- `ConfigController::defaultServerPort()` holds the default port of each
  server type (#227).

### Views a theme may override that changed

- `resources/views/admin/config/addons.blade.php` (-1 allowed, new hint).
- `resources/views/admin/config/servers.blade.php` (credential labels).
- `resources/views/admin/invoices/show.blade.php` (locked lines, the amount
  Mark Paid proposes).
- `resources/views/admin/orders/show.blade.php` (Set back to Pending).
- `resources/views/admin/products/edit.blade.php` (the saved package stays
  selectable).
- `resources/views/admin/settings/general.blade.php` (the renewal lead time).
- `resources/views/client/services/show.blade.php` (live usage for every
  module that reports it).

## 1.7.0 — 2026-10-23

A stable release, offered to every installation. It contains everything in
1.6.1. None of the changes below went out in a beta first.

### Added

- **Closing a service asks what happens to its account on the server.** The
  status buttons on a service's page change the record in PNLCS only; the
  account on the server is ended with **Module Actions → Terminate**. A
  service marked Terminated with the status button and then deleted left its
  account, domain and apps running on the server with nothing billing them,
  and the same domain could not be ordered again: the order stayed Pending
  and nobody was told (#212). Now marking a service Terminated or Cancelled,
  or deleting it, while its account may still be on the server asks first:
  **Terminate on the server** (the default) removes the account there and
  then closes the record; **Keep the account on the server** closes the
  record only and writes that to the activity log. If the server refuses,
  nothing changes and you see its reason. For Panelica servers PNLCS asks the
  panel whether the account is still there, so a service already marked
  Terminated by hand is covered too, and so is an active service that was
  imported or added without linking its account. See
  [Ending a service](docs/guides/connect-a-server.md#ending-a-service).
  Thank you, [@dualarrow](https://github.com/dualarrow), for the report and
  the screen recording that showed it.
- **A domain already set up on your servers is refused while the customer
  orders**, with a sentence asking them to contact you, instead of an order
  that gets stuck (#212). A server that does not answer does not stop the
  order.
- **A setup that did not go through is visible.** The staff service page
  shows the server's reason and the next automatic retry; staff are notified
  when a setup on order placement fails (until now only a log line said so);
  and the customer reads that the setup is in hand and there is no need to
  order again, instead of a bare "Pending" (#212).
- **Panelica Licenses shows your margin in your own currency without a
  dollar currency on the site.** Panelica prices in US dollars; a site
  selling in euros or lira had to add a USD currency, which also put dollars
  in the store's currency menu, to see suggested prices and its margin. With
  no USD currency the page now uses today's market rate, from the providers
  of the automatic currency update, checked once a day, and says which rate
  it used. A USD currency on the site still wins, and nothing is asked while
  automatic rate updates are off (#213). Thank you,
  [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).

### Changed

- **The status buttons on a service's page say what they do:** a line under
  them says they change the record only and points to Module Actions (#212).
- **The WHMCS migration guide describes Import WHMCS** (Utilities → Import
  WHMCS): what it imports (clients, services, domains), in which order, and
  what it leaves (products, invoices, transactions, tickets, settings). It
  still said PNLCS had no importer. See
  [Migrate from WHMCS](docs/guides/migrate-from-whmcs.md).

### Fixed

- **One stray character in a custom theme no longer breaks the styling of
  every page.** Only colours were checked when the theme was saved; a `;` or
  `}` in a font, size or shadow was printed as typed. Every token is now
  checked when it is saved, and a value saved before that is left out when
  printed. Values that work in CSS stay: `calc()`, font names in any script,
  and colour functions such as `oklch()`, `color-mix()` and `var()` (#215).
  Thank you, [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).
- **Alerts in the dark client area are readable:** their text kept the light
  theme's dark colour on a dark background.

### Security

- **The KSeF private key is no longer printed in the addon settings page.**
  It is stored encrypted, but the page put it back, decrypted, into its form.
  Now it shows only that a key is set; a blank save keeps it (#217).
- **The ticket spam filter checks the customer's own address.** A request
  carrying an extra `email` field had another address checked, so a blocked
  customer could still open tickets and the per-hour limit could be stepped
  around (#218).
- **Company Lookup answers only while the addon is on.** Its endpoint kept
  querying the registers with the operator's keys after the addon was
  switched off (#216).
- **A custom theme value can no longer end the `<style>` block it is printed
  in** (#215).

Thank you, [@ertugrulkeyvan](https://github.com/ertugrulkeyvan), for #213 and
#215 to #218.

### Database changes

None.

### For theme and module authors

- Two optional server-module methods, listed in
  [Writing a module](docs/developer/modules.md#optional-capabilities):
  `accountOnServer(Service): ?bool` (is the account still on the server;
  staff are asked before closing a record whose account is there or cannot
  be checked) and `domainHostedOn(Server, string $domain): ?bool` (refuse an
  order for a domain already on the server). The Panelica module has both.
- `App\Services\ServerAccount` and `App\Services\HostedDomainCheck` answer
  those questions for the core.
- `App\Services\ExchangeRateService` (`rates()`, `rate()`) gives market
  rates from the providers `pnlcs:currency-update` uses; the command now uses
  it.
- An addon configuration field can be `'secret' => true`: the settings page
  never shows its value, and a blank save keeps it.
- `ThemeService::isValidTokenValue()` decides whether a theme token value is
  one CSS value. A theme's `theme.json` value it refuses is left out of the
  page; every built-in theme passes.

### Views a theme may override that changed

- `resources/views/admin/config/addon-output.blade.php` (a secret field is
  shown empty).
- `resources/views/admin/panelica-licenses/index.blade.php` (the market rate
  note).
- `resources/views/admin/services/show.blade.php` (the question before a
  record is closed, the status buttons' hint, a setup that did not go
  through).
- `resources/views/client/layouts/app.blade.php` (dark alert text colours).
- `resources/views/client/services/show.blade.php` (the setup-in-hand note).

## 1.6.1 — 2026-10-21

A stable release with fixes, offered to every installation. It contains
everything in 1.6.0.

### Fixed

- **When staff change an account's email address, the customer signs in
  with the new one.** On the client's edit page (and with the API's
  `updateclient`) the client record changed, but the customer kept signing in
  with the old address and saw it on their profile, and no screen could
  change it (#210). Now the login that signed in with the old address moves
  to the new one: the new address is asked to confirm itself, and the old
  address is told that the change was made by staff. A login on another
  address (an invited user, a login imported from WHMCS) keeps it, and an
  address another login already signs in with is refused before anything is
  saved. See [Client logins](docs/guides/client-logins-and-permissions.md).
  Thank you again, [@dualarrow](https://github.com/dualarrow).
- **A password set by staff for an account with no login yet** opens the
  login on the address the account is saved with, not the one it had before
  the save.

### Security

- **The API's `updateuser`** changing a login's email address now asks the
  new address to confirm itself and tells the old address, as the other ways
  of changing it do. Before, it did neither.

### Database changes

None.

### For theme and module authors

- `App\Services\ClientLoginEmail` moves the login that signs in with an
  account's address when the address changes.
- `App\Mail\LoginEmailChangedMail` takes `byStaff` for a change made by
  staff.

### Views a theme may override that changed

- `resources/views/emails/login-email-changed.blade.php` (receives
  `$byStaff`).

## 1.6.0 — 2026-10-21

The stable release, offered to every installation. It contains everything in
1.5.1-beta.1, 1.6.0-beta.1, 1.6.0-beta.2 and 1.6.0-beta.3 (below), and the
changes listed here, which were made after 1.6.0-beta.3. There is no 1.5.1:
its fixes are in this release.

### Changed

- **The Panelica Licenses page is "Panelica" in the admin menu.** Not yet
  connected, the page explains how selling works and starts where somebody
  without a partner key starts: **Apply to become a partner** opens the
  partner application at Panelica; once approved, you create an API key in
  the partner portal and connect it under **Already a Panelica partner?**.
  See [Sell Panelica Licenses](docs/guides/sell-panelica-licenses.md).

### Fixed

- **A customer can change their email address on My Profile again.** Typing
  a new address never opened the current password field, and the field could
  not be typed into, so the change could not be saved; broken since 1.3.0
  (#210). Thank you, [@dualarrow](https://github.com/dualarrow), for the
  clear report.
- **Add funds** shows the amount in the shop's currency while the customer
  types; the conversion never ran since it was added in 1.3.0.

### Security

- **A refused new password no longer changes the sign-in address.** When a
  customer changed their email address and set a password they had used
  recently in the same save, the password was refused but the new address
  was kept, without asking the new address to be verified and without
  telling the old one. Nothing is saved now until every check has passed.

### Database changes

None besides those of the betas below.

### For theme and module authors

- A view that extends a layout keeps everything inside its sections. Markup
  written after the last `@endsection` is printed before the layout's
  `<!DOCTYPE html>`: the page opens in quirks mode and its scripts run before
  the page exists. A test now checks the core, theme and module views.

### Views a theme may override that changed

- `resources/views/client/account/profile.blade.php` (its scripts are in the
  `scripts` section). A theme with its own copy of this view should move the
  scripts after `@endsection` into `@section("scripts")`, or its customers
  cannot change their email address.
- `resources/views/client/funds/index.blade.php` (the same).
- `resources/views/admin/panelica-licenses/index.blade.php` (the partner
  application comes first when no partner account is connected).

## 1.6.0-beta.3 — 2026-10-21

A beta: offered only to installations on the **beta** channel (**Setup →
Updates → Update channel**). It contains everything in 1.6.0-beta.2.

### Added

- **Your own install link on the Panelica Licenses page.** A general install
  command at your own address (`curl -sSL https://<your site>/setup | bash`)
  to publish on your website: it runs your Panelica partner install link
  without showing it, and the server it installs is yours at Panelica. The
  link's code at Panelica can be changed on the same page; the next install
  uses the new code at once. See
  [Sell Panelica Licenses](docs/guides/sell-panelica-licenses.md).
- **Servers installed with your link** and **Your licenses at Panelica** on
  the Panelica Licenses page: every server Central saw installed with your
  link (whether it was tied to you, its license, the order it belongs to) and
  every license of your partner account, sold from this site or not, with the
  service here it belongs to.

### Changed

- **Sold from this site** is the new name of the Panelica Licenses page's list
  of the licenses sold here, beside the new list of every license of the
  partner account.

### Fixed

- **Check installs now** on the Panelica Licenses page no longer answers with
  a server error.
- **Switching the Panelica Licenses module off** under **Setup → Modules**
  now hides its menu entry and page, stops the install commands and the
  five-minute job. Before, the module kept working.

### Database changes

None.

### For theme and module authors

- `Modules\Servers\PanelicaLicense\PanelicaLicenseModule::enabled()` tells
  whether the module is switched on.

### Views a theme may override that changed

- `resources/views/admin/layouts/app.blade.php` (the Panelica Licenses menu
  entry is left out while the module is switched off).
- `resources/views/admin/panelica-licenses/index.blade.php` (receives `$link`,
  `$generalCommand`, `$installs`, `$centralLicenses`, `$serviceByLicense` and
  `$serviceByRef`).

## 1.6.0-beta.2 — 2026-10-21

A beta: offered only to installations on the **beta** channel (**Setup →
Updates → Update channel**). It contains everything in 1.6.0-beta.1. Most of
it came from [@ertugrulkeyvan](https://github.com/ertugrulkeyvan): thank you
for seventeen pull requests (#193 to #209).

### Added

- **Sell Panelica licenses.** A new **Panelica Licenses** page in the admin
  area's top menu, for staff who manage products. Connect your Panelica
  partner account with its API key, create a product for each Panelica plan in
  one click at your own price (your partner cost, Panelica's list price and
  your margin are shown beside it), and see your partner balance and every
  license sold. A customer who buys one gets an install command at your own
  address (`curl -sSL https://<your site>/setup/<reference> | bash`); once
  their new panel starts its trial, the license is bought from your partner
  balance by itself and shown on their service page. Each paid renewal buys
  the next period; suspending, unsuspending and terminating the service do the
  same to the license. Nothing is bought for an order nobody installs. See
  [Sell Panelica Licenses](docs/guides/sell-panelica-licenses.md).
- **Knowledge base and announcements in more than one language.** Articles,
  categories and announcements take a translation for every active language;
  visitors read their own language where one is written, the default text
  otherwise (#201, #203).
- **The knowledge base search box searches**, in the visitor's language
  (#202).
- **Retry registration** on the admin domain page, for a paid domain the
  registrar refused (#197).
- **"Keep this card"** when paying an invoice by card with Stripe while
  automatic payment is on, so the next invoice can be charged by itself
  (#209).

### Changed

- **Admin reports** read in the admin's language: titles, descriptions,
  columns and the CSV header (#208).
- **Statuses on the admin screens** are shown in the admin's language (#205).
- **The Panelica module's messages** to customers on the hosting tools are in
  their language (#199).
- **A domain order asks for the registrant's phone number** when the account
  has none, wherever the seller is: registries require one (#196).
- **Panel sign-in** uses the server's name when the panel gives its public IP
  and the name points at it, so the browser does not stop at a certificate
  warning (#200).
- **Spanish** has every new text of this release.

### Fixed

- **A fresh install's first save of the General settings** no longer turns off
  every email; the test email button says when an email was not sent (#194).
- **PHP mail under SELinux** on AlmaLinux and Rocky: the installer sets what
  sendmail needs (#195).
- **The Updates page** no longer offers the release already installed, and
  its beta badge can be read (#193).
- **A client's billing summary** counts their invoices; it showed 0 for
  everyone (#198).
- **Rate limits**: each limited route keeps its own count, so polling the VPS
  page no longer uses up the Reinstall button's (#204).
- **A terminated service hands its stock unit back** (#206); a service that
  had already ended does not end again (no second unit, no second email).

### Database changes

- New tables `kb_article_translations`, `kb_category_translations` and
  `announcement_translations` (additive; nothing existing changes).
- Settings `panelica_partner_api_key` (encrypted) and
  `panelica_partner_central` are written only when a partner account is
  connected.

### For theme and module authors

- **Server modules may offer more**, all optional (`docs/developer/modules.md`):
  `renew(Service)` for a paid period at a vendor (#207; a refusal is now also
  queued and retried); `clientAreaPanel()` / `adminAreaPanel()` for the
  module's own section on the service pages; `customFunctions()` buttons on
  the staff page and `clientFunctions()` buttons for the customer (run through
  the new route `client.services.module-function`); `servicePageHides()`;
  `isProvisioned()`; `emailMergeFields()`; and `needsServerRecord(): false`
  for a module that keeps its account elsewhere.
- New hook `AfterRegistrarRegistrationFailed` (`domain`, `domainid`, `error`)
  (#197). `ModuleActionFailed` can carry the action `renew`.
- The `throttle` middleware alias is `App\Http\Middleware\ThrottleRequests`,
  which counts per route; named limiters such as `throttle:api` are
  unchanged (#204).
- New public route `setup/{reference}` (32 letters and digits) for the
  license install command, and a scheduled command `pnlcs:panelica-licenses`
  every five minutes that does nothing until a partner account is connected.
- `App\Support\ContentTranslations` saves translations of operator-written
  content; `admin/partials/translation-fields` is its form fold.
- `report_text()`, `status_label()` helpers (#208, #205).

### Views a theme may override that changed

- `resources/views/client/services/show.blade.php` (receives `$modulePanel`
  and `$showServerDetails`; the server details panel is left out when the
  module hides it).
- `resources/views/client/kb/index.blade.php` (receives `$searchQuery`).
- `resources/views/client/partials/billing-address-fields.blade.php` (reads
  `$needsPhone` and `$showPhone`).
- New: `client/services/partials/panelica-license.blade.php`.

## 1.6.0-beta.1 — 2026-10-15

A beta: offered only to installations on the **beta** channel (**Setup →
Updates → Update channel**). It contains everything in 1.5.0 and in
1.5.1-beta.1. The next stable release is 1.6.0, with 1.5.1-beta.1's fixes
in it; there will be no 1.5.1.

### Added

- **One command on a fresh server.** On Ubuntu 22.04 or 24.04, Debian 12 or
  13, AlmaLinux 9 or Rocky Linux 9,
  `curl -fsSL https://raw.githubusercontent.com/Panelica/pnlcs/main/install.sh | sudo bash -s -- --domain billing.example.com`
  installs nginx, PHP 8.4 and MySQL or MariaDB, downloads the newest release
  and checks its signature, creates the database, and sets up nginx, the
  scheduler, the firewall and SELinux; then the install wizard asks for your
  administrator account. It stops before changing anything on a server that
  already has PNLCS. See [Install on your own server](docs/install/native.md#one-command).
- **Installing without a command line.** The release package, extracted on a
  web host and opened in the browser, starts the install wizard, which writes
  `.env` itself. A copy that is already set up is never touched. See
  [Inside a hosting-panel account](docs/install/hosting-panel.md).
- **The install wizard in five languages**: English, Turkish, German, Polish
  and Chinese. It opens in the browser's language when it is one of them,
  has a language switch at the top, and offers the language it was run in as
  the default on its last step. Thank you,
  [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).

### Changed

- **Install wizard, database step:** the settings are tried before they are
  saved, so a wrong password comes back on the form with the database's
  reason instead of being written to `.env`. The tables are then created on a
  page of their own with a progress bar, a few seconds at a time, so a web
  server's 60-second limit can no longer cut the step off. A step that was
  interrupted carries on where it stopped, and a database whose tables were
  only half made is no longer taken for a finished one.
- **The wizard's last page** shows the cron line the scheduler needs, with the
  installation's own path.
- **Client area, public pages and emails:** the last English texts a
  customer could read are translated: a stored card's last digits, the
  two-factor QR code's text, the logo's text (now the company's name), an
  announcement's date and time, the "Soon" badge on a service's tools, the
  CSR field's hint, the dark-mode button and a mass email's greeting. Thank
  you, [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).
- **My Account** says that only the languages active under **Setup →
  Languages** are offered there, with a link to that page.
- **Spanish** has every new text of this release.
- **The README's Docker quick start** uses image 1.5, with named volumes for
  the database and the installation.

### Fixed

- **An uploaded release package** answered the install wizard's first page
  with an error ("No application encryption key has been specified"): the
  package has no `.env`.

### Database changes

- None.

### For theme and module authors

- New view `install/migrate`, the progress page of the database step.
  `install/finish` receives `$path`: the installation's directory, or
  `/path/to/pnlcs` for anyone but the installer. `install/app` receives
  `$locales`, the languages offered as the default (code => name).
- The wizard's routes run the new `App\Http\Middleware\InstallLocale`, which
  sets the wizard's language; every other page keeps `SetLocale`.

### Views a theme may override that changed

- `resources/views/admin/settings/my-account.blade.php`.
- `resources/views/client/account/payment_methods.blade.php`,
  `client/announcements/show.blade.php`,
  `client/auth/enable-two-factor.blade.php`, `client/auth/login.blade.php`,
  `client/layouts/app.blade.php`, `client/services/show.blade.php`,
  `client/ssl/configure.blade.php`.
- `resources/views/emails/bulk-mass.blade.php`,
  `sections/navigation.blade.php`.
- `resources/views/install/admin.blade.php`, `install/app.blade.php`,
  `install/database.blade.php`, `install/finish.blade.php`,
  `install/layout.blade.php`, `install/requirements.blade.php`.

## 1.5.1-beta.1 — 2026-10-13

A beta: offered only to installations on the **beta** channel (**Setup →
Updates → Update channel**). It contains everything in 1.5.0.

### Fixed

- **Spanish showed about 98%** under **Setup → Languages** although it has
  every text: 110 texts were the same as in English. 70 of them are written
  that way in Spanish too (Total, General, Error, Manual, PayPal, Laravel)
  and are now listed as such, as for German, Polish, Turkish and Chinese;
  the other 40 were still English and are translated ("Weak", "Resolved",
  "Default", "Balance", "Qty" and others). Spanish shows 100%.
- **Spanish:** nine messages called a quote "Cita" (an appointment); they
  say "Cotización", as the menus and pages do.

## 1.5.0 — 2026-10-13

The stable release, offered to every installation. It contains everything in
1.5.0-beta.1 and 1.5.0-beta.2 (below), and the changes listed here, which
were made after 1.5.0-beta.2.

### Added

- **Charge in the invoice's billing currency** for Stripe, Mollie and PayPal,
  off by default (each gateway's settings under **Setup → Payment
  Gateways**). A customer billed in their own currency (**Setup →
  Currencies**) is charged the amount the invoice shows, at the invoice's own
  rate, instead of the shop currency converted by their bank. Thank you,
  [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).
- **Free subdomains for hosting orders** (**Setup → General → Domains**): a
  hosting order can take a name under one of the shop's own domains, such as
  `yourname.example-sites.com`, instead of a domain of its own. Reserved
  names (`www`, `mail`, `ns1`, `autodiscover`, `admin` and more, or your own
  list) and names another service already has are refused. The parent domains
  need a wildcard DNS record; see [Selling domains](docs/guides/sell-domains.md).
  Thank you, [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).
- **The shop's social media profiles** (**Setup → General → Search engines
  and sharing**): X, Facebook, Instagram, LinkedIn, YouTube, TikTok, Threads
  and Medium. The default home page footer links to them and the home page
  names them to search engines; nothing is added until a profile is set.
  Thank you, [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).
- **aaPanel server module** for Sub aaPanel accounts: create, suspend,
  unsuspend, terminate, password and package changes, disk and bandwidth
  usage, and **Login to panel**. It needs aaPanel Pro with Sub aaPanel
  installed; see [aaPanel hosting accounts](docs/modules/aapanel.md). The
  connection test, the package list and an unlicensed panel's refusal were
  checked on aaPanel 8.0.6 with Sub aaPanel 3.0.4; account changes have not
  yet been run against a licensed panel. Thank you,
  [@Alex9001](https://github.com/Alex9001).
- **Panelica mail certificate help:** when the certificate for a customer's
  mail host could not be issued, their email page lists the DNS records it
  needs and offers to try again. Thank you,
  [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).
- **Spanish** has every text of the language files, addressing the reader as
  "usted". Thank you, [@wmedia](https://github.com/wmedia).
- **German** client area and email texts, and all 32 email templates, from
  Dejan Wolf (WHOST, Austria), in the formal "Sie" the German translation
  uses.

### Changed

- **Email templates in a new language** start from the templates shipped for
  it (German and Polish) instead of copies of the English ones.
- **Panelica mail host:** mail apps are told `mail.<customer's domain>`, the
  name the panel's certificate is for, or the host name the panel gives the
  certificate; before, they were told a name built from the server's
  hostname. The first mailbox of a domain asks the panel for that
  certificate when it has none. Thank you,
  [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).
- **Panelica "Login to panel"** opens the panel by the server's host name
  when the panel runs behind NAT, instead of an internal address the browser
  cannot reach. Thank you, [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).
- **The language in the address:** links in the core views and the flavor
  and aurora themes carry the page's language, so no redirect is needed; the
  language switcher adds `?lang` only on the way back to the default
  language; a file at the root (`/sw.js`, `/manifest.json`, a verification
  file) keeps one address in every language. Thank you,
  [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).
- **Admin screens:** about 100 texts on 37 admin screens that showed English
  in every language are translated. Thank you,
  [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).
- **Updates:** when an update stops because the container runs an older
  `panelica/pnlcs-runtime` image, the page and `pnlcs:update` also say how
  Panelica replaces it (Docker Manager → **Update to** the new tag).

### Fixed

- **iyzico:** an invoice billed in lira is charged the lira amount the
  invoice shows, not today's conversion of it. Thank you,
  [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).
- **Hosting tabs:** when the hosting control panel does not answer, the
  customer gets a message instead of a "Server Error" page; after a form the
  panel may already have carried out, they are told to check before trying
  again. Thank you, [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).
- **Products:** a product or product group named like an existing one gets
  an address of its own (`vps-s-2`) and can be ordered; before, its order
  link opened the other product or gave a 404, and a second group with the
  same name could not be saved. Thank you,
  [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).
- **Products:** saving a product that has no module no longer gives it
  Panelica's default limits, so the price list no longer shows resources
  nobody chose. Thank you, [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).
- **Automation status:** the page lists the scheduled tasks (it showed none),
  and on the Docker image it says the scheduler already runs inside the
  container instead of asking for a host cron line. The cron note says
  "every minute", which is what the line does.
- **Updates:** a check or update request that the scheduler picked up but
  that never finished no longer leaves the page waiting for ever; it says the
  request was lost, also on a page that was kept open. Thank you,
  [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).
- **Search engines and sharing:** a page that writes its own sharing tags,
  such as a blog post, no longer gets a second set from the layout. Thank
  you, [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).

### Security

- **PayPal:** a capture is credited only in the currency the shop charges in,
  or the invoice's billing currency when that option is on. Before, a capture
  of 100 in a currency worth far less than the shop's (100 HUF against a
  100.00 USD invoice) could mark the invoice paid. Thank you,
  [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).
- **Two-factor setup** draws its QR code on the server. The setup address,
  which carries the shared secret, is no longer sent to an outside QR
  service. Thank you, [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).

### Database changes

- `2026_10_10_000001_ship_translated_email_templates`: a German or Polish
  email template that you have not made your own and that is still exactly
  the English text gets the shipped translation, its subject and its message
  each on their own. A field you wrote yourself stays.
- `2026_10_12_000001_give_products_with_a_shared_slug_their_own`: a product
  whose slug is empty or already used by an older product gets one of its own
  (`vps-s-2`, `vps-s-3`), so its order address changes; the oldest keeps its
  slug, and every other product is untouched.

### For theme and module authors

- `social_profiles()` returns the profiles that are set (network => address);
  the new partial `partials/social-links` draws them as icon links.
- A page that writes its own sharing tags sets `@section('seo_own', '1')`;
  the layout then prints neither `partials/seo-meta` nor
  `partials/locale-alternates` for it. [Themes](docs/developer/themes.md)
  lists the sections the layout reads.
- Write links as `url('/path')`, not as plain paths, so they keep the page's
  language when the language is in the address.
- The two-factor setup views receive `$qrImage`, an SVG data URI; `$qrUrl`
  is still passed, so an overriding view keeps working until it switches.
- New server module `aapanel` (`modules/Servers/AaPanel`).

### Views a theme may override that changed

- `resources/views/admin/affiliates/show.blade.php`,
  `admin/auth/enable-two-factor.blade.php`, `admin/calendar/index.blade.php`,
  `admin/clients/edit.blade.php`, `admin/clients/show.blade.php`,
  `admin/config/addon-output.blade.php`, `admin/config/automation.blade.php`,
  `admin/config/billable-items.blade.php`, `admin/config/bundles.blade.php`,
  `admin/config/custom-fields.blade.php`,
  `admin/config/domain-pricing.blade.php`, `admin/config/gateways.blade.php`,
  `admin/config/notifications.blade.php`, `admin/config/promotions.blade.php`,
  `admin/config/servers.blade.php`, `admin/config/ticket-escalation.blade.php`,
  `admin/config/ticket-spam.blade.php`, `admin/config/updates.blade.php`,
  `admin/domains/index.blade.php`, `admin/domains/show.blade.php`,
  `admin/invoices/create.blade.php`, `admin/invoices/show.blade.php`,
  `admin/layouts/app.blade.php`, `admin/logs/module.blade.php`,
  `admin/orders/show.blade.php`, `admin/products/create.blade.php`,
  `admin/products/create-group.blade.php`, `admin/products/edit.blade.php`,
  `admin/projects/create.blade.php`, `admin/projects/edit.blade.php`,
  `admin/projects/index.blade.php`, `admin/projects/show.blade.php`,
  `admin/quotes/create.blade.php`, `admin/quotes/edit.blade.php`,
  `admin/quotes/show.blade.php`, `admin/reports/show.blade.php`,
  `admin/settings/appearance.blade.php`, `admin/settings/general.blade.php`,
  `admin/ssl/show.blade.php`, `admin/whois.blade.php`.
- `resources/views/client/auth/enable-two-factor.blade.php`,
  `client/cart/configure.blade.php`, `client/layouts/app.blade.php`,
  `client/services/hosting/email.blade.php`,
  `client/services/show.blade.php`, `client/tickets/create.blade.php`.
- `resources/views/errors/403.blade.php`, `errors/404.blade.php`,
  `errors/500.blade.php`, `partials/locale-alternates.blade.php`,
  `partials/seo-meta.blade.php`, `sections/domain-search.blade.php`,
  `sections/footer.blade.php`, `sections/hosting-plans.blade.php`,
  `sections/navigation.blade.php`, `sections/styles.blade.php`,
  `sections/topbar.blade.php`, `sections/vps-plans.blade.php`.
- `themes/aurora/views/sections/hero.blade.php`,
  `themes/aurora/views/sections/navigation.blade.php`,
  `themes/flavor/views/client/layouts/app.blade.php`,
  `themes/flavor/views/sections/footer.blade.php`,
  `themes/flavor/views/sections/navigation.blade.php`.

## 1.5.0-beta.2 — 2026-10-10

A beta: offered only to installations on the **beta** channel (**Setup →
Updates → Update channel**). It contains everything in 1.5.0-beta.1. After
seven days without a regression the same commit becomes 1.5.0 for everyone.

### Added

- **Product mapping in the WHMCS importer.** The services mapper maps each
  WHMCS product to a PNLCS product; an unmapped one is still matched by name.
  Two services on the same domain (hosting and e-mail, a VPS and its
  maintenance) are imported as two services, and each client gets a note
  listing their WHMCS services, so the result can be checked against the
  source. A re-import updates the services 1.4.0 imported instead of copying
  them. Thank you, [@hedon77](https://github.com/hedon77).
- **The language in the address**, off by default (**Setup → Languages →
  Settings**). The default language keeps today's addresses; every other
  active language is served under its code (`/en/client/store`), so search
  engines can index every language and a shared link opens in the language it
  was copied in. Social sign-in and the e-mail confirmation link keep their
  own addresses. Thank you,
  [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).
- **Search engines and link previews** (**Setup → General → Search engines
  and sharing**): a description on every client-facing page, Open Graph and X
  card tags so a shared link shows a title, text and image, and a sitemap at
  `/sitemap.xml` that updates itself. `/robots.txt` can be edited there once
  the shipped `public/robots.txt` file is removed; while the file is there,
  the web server answers with it as before. Thank you,
  [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).
- **Tracking codes and a cookie consent bar** (**Setup → General → Tracking
  and cookie consent**): a Google Tag Manager container with Google Consent
  Mode v2, so ads and analytics wait for the visitor's consent, a consent bar
  where "necessary cookies only" is as easy as "accept", and code for the
  head and the end of every page. Nothing is added to a page until something
  is set. Thank you, [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).
- **A number format per currency** (**Setup → Currencies**): `1,234.56`,
  `1.234,56`, `1 234,56` or `1234.56`. Every currency keeps `1,234.56` until
  you choose otherwise; the cart's running totals follow the format too.
  Thank you, [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).
- **A promotion code in a link**: any page opened with `?promo=CODE` (or
  `?promocode=CODE`, as WHMCS links write it) remembers the code, and the cart
  applies it once it holds a product the code is for. Every promotion rule
  still applies, and a code the customer typed is never replaced. Thank you,
  [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).

### Changed

- **Translations.** The progress under **Setup → Languages** counts a text as
  translated only when it is no longer the English text, and **Translate
  missing** now also translates texts still in English (names that stay the
  same, such as Stripe, are not sent again). About 460 texts of the client
  hosting pages, the e-mail setup guide, the SSL page and the home page's apps
  section were missing in German, Polish and Chinese; they are translated,
  with a few German machine-translation mistakes corrected. English, Turkish,
  German, Polish and Chinese are complete; a text a community language does
  not have yet shows in English.
- **Domain availability** is checked through the registrar assigned to each
  domain extension, with the default registrar's bulk check kept for the
  rest. Thank you, [@terbora-core](https://github.com/terbora-core).
- **The client page** shows the client's information and billing identity in
  one panel. Thank you, [@hedon77](https://github.com/hedon77).

### Fixed

- **Invoices:** the "All" tab and the "All invoices" menu entry list paid and
  unpaid invoices; the list still opens on Unpaid. Thank you,
  [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).
- **Stripe:** the pay button names the currency ("Pay €12.00 by card"); the
  amount sent to Stripe is unchanged. Thank you,
  [@ertugrulkeyvan](https://github.com/ertugrulkeyvan).
- **Updates:** clicking **Update now** right after a check finished could be
  refused with "the updater is busy" - the page said "ready" while the
  updater was still cleaning up. The page now says so only once it is done.

### For developers

- Every language file is checked before it ships: it loads, keeps every
  placeholder the English text declares, and carries exactly the English
  markup. The community languages are reported, not enforced, so adding an
  English text no longer requires writing it in 25 languages.
  [Translations](docs/developer/translations.md) explains what is checked,
  texts that stay the same as English and how a language becomes complete;
  translators are credited in the README.

### Database changes

- `whmcs_import_profiles.product_mapping` (JSON, nullable) and
  `services.whmcs_product_name` (string, nullable): new columns.
- `2026_10_09_000001_drop_translation_rows_now_shipped_in_files`: the texts
  above used to be delivered as rows of `dynamic_translations`; they now ship
  in the language files, and a row that says exactly what the file says is
  removed (it would hide later corrections to the file). A text you changed
  yourself is different from the file and stays. What the pages show does
  not change.

### For theme and module authors

- New hook point `SitemapUrls`: return addresses (strings, or
  `['loc' => ..., 'lastmod' => ...]`) to add your public pages to the sitemap.
- Client layouts should print `ClientAreaHeadOutput` and
  `ClientAreaFooterOutput` (the tracking codes and the consent bar use them);
  the flavor theme now does.
- New view partials: `partials/seo-meta`, `partials/locale-alternates`,
  `partials/consent-bar`, and the `sitemap` view.

### Views a theme may override that changed

- `resources/views/admin/clients/show.blade.php`,
  `admin/config/currencies.blade.php`, `admin/config/languages/index.blade.php`,
  `admin/invoices/index.blade.php`, `admin/layouts/app.blade.php`,
  `admin/settings/general.blade.php`, `admin/whmcs-import/mapper.blade.php`.
- `resources/views/client/announcements/show.blade.php`,
  `client/auth/forgot-password.blade.php`, `client/auth/invite.blade.php`,
  `client/auth/login.blade.php`, `client/auth/register.blade.php`,
  `client/auth/reset-password.blade.php`, `client/auth/two-factor.blade.php`,
  `client/cart/configure.blade.php`, `client/kb/show.blade.php`,
  `client/layouts/app.blade.php`,
  `client/layouts/partials/language-selector.blade.php`,
  `client/unsubscribe.blade.php`.
- `resources/views/legal/layout.blade.php`, `sections/topbar.blade.php`,
  `welcome.blade.php`; `themes/flavor/views/client/layouts/app.blade.php`,
  `themes/flavor/views/welcome.blade.php`.

## 1.5.0-beta.1 — 2026-10-07

A beta: offered only to installations on the **beta** channel (**Setup →
Updates → Update channel**). After seven days without a regression the same
commit becomes 1.5.0 for everyone.

### Changed

- **The WHMCS import screens are redesigned.** A header that says what the
  importer promises (read only, preview before importing, every record
  logged) and the three steps; the connection form in groups and saved
  connections as cards; the import type as tabs; a count of the fields mapped;
  the preview, run and save buttons in a bar that stays in reach; the preview
  side by side; the log as coloured totals. Right to left and on phones, in
  every language.

### For developers

- The beta channel is covered end to end: tests for which channel offers
  which release, and an update lab scenario in which the stable channel does
  not see a beta, the beta channel updates to it, and then to the stable
  release made from the same commit.

### Database changes

- None.

### For theme and module authors

- Nothing changed for modules, themes or hooks.

### Views a theme may override that changed

- `resources/views/admin/whmcs-import/index.blade.php`,
  `mapper.blade.php` and `log.blade.php`; new: `_styles.blade.php` and
  `_steps.blade.php` in the same folder.

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
