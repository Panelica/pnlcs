# Sell Domains

PNLCS sells domain registrations, transfers and renewals alongside hosting.
Selling domains is optional: if you only sell hosting, skip this page.

## 1. Set the prices

**Setup → Domain Pricing**

Add each extension you sell (`.com`, `.net`, `.org`...) with its **register**,
**transfer** and **renew** price. Customers can only order extensions that are
listed and enabled here.

## 2. Choose a registrar

**Setup → Domain Registrars**

A registrar module registers, transfers and renews names at the registry.
Open it, enter the credentials from your registrar account, and switch it on.

| Registrar | What it asks for |
|---|---|
| **Namecheap** | API username, API key, the whitelisted IP of your server, sandbox mode |
| **ResellerClub** | Reseller ID, API key, test mode |
| **OpenProvider** | Username, password, sandbox mode |
| **Enom** | Username (UID), password, test mode |
| **HRD** (Poland) | Login, API hash, API password, default nameserver group |
| **DomainNameAPI** (Turkey) | Reseller ID, live and test API keys, test mode, default nameservers |
| **Manual** | Nothing: you register names yourself at any registrar and record them in PNLCS |

**Manual** is a good start, or the answer when your registrar has no module
yet: billing works exactly the same, and the registration step is yours.

!!! warning "A paid domain is never marked registered without a registrar"
    If a domain's registrar cannot be loaded (switched off, or no longer
    installed), a paid order leaves the domain **pending** and you get a
    notification, rather than showing it active while nothing was registered.

## 3. How customers buy

- The **domain search** on the public site (`/client/domain-search`) checks
  availability and puts a name in the cart.
- During hosting checkout, customers can register or transfer a domain in the
  same order.
- A transfer asks for the EPP (auth) code from the current registrar.

### Free subdomains

A hosting order can also take a name under one of your own domains, such as
`yourname.example-sites.com`, instead of a domain of its own. This suits trial
plans and customers who have not chosen a domain yet.

- **Offer it:** in **Setup → General → Domains**, list the domains under
  **Free subdomain domains**, one per line. The order form then shows **Free
  subdomain from us**: the customer types the first part and picks the domain.
- **Point the domains at your server:** a wildcard DNS record
  (`*.example-sites.com`) pointing at the hosting server is the usual way.
- **Names nobody may take:** **Names nobody may take** lists the first parts
  kept back (`www`, `mail`, `admin`, your own hosts). Left empty, a built-in
  list is used.
- **Rules:**
  - only one label is accepted (`yourname`, not `a.yourname`);
  - a name another live service already has is refused, and is checked again
    when the order is placed;
  - the same rules apply when a customer types such a name as their own domain.

## What customers can do with their domains

In the client area, on each domain's page:

- see registration and expiry dates;
- **change the nameservers** (sent to the registrar);
- turn **auto-renew** on or off;
- turn the **registrar lock** on or off, and get the **EPP code** to move the
  domain away;
- **set the domain up on their hosting** (below).

### Set up on my hosting

A customer with an active hosting account can add a domain to it and point the
domain there in one step: **Set Up on My Hosting**, on the domain's page. With
several hosting accounts they choose which one.

PNLCS adds the domain to the account first, and only then changes its
nameservers to the ones entered on the account's **server** (**Setup →
Servers**), or else to the registrar's default nameservers. Pressing it again
changes nothing already done. It works for hosting on Panelica servers.

## Renewals and syncing

- Like services, domains renew on a cycle: PNLCS raises the renewal invoice
  ahead of expiry and renews at the registrar once it is paid. Reminders go out
  before expiry.
- Every night PNLCS asks the registrar for each domain's expiry, status and
  nameservers, and updates its records. A domain shown as active that the
  registrar does not know raises a notification, at most once a week.
- **Registrar balance watch** (**Setup → General Settings**) warns you when
  your prepaid balance at the registrar falls to a floor you set.
