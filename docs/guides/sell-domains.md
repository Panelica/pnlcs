# Sell Domains

PNLCS can sell domain registrations, transfers and renewals alongside hosting.

## 1. Set domain pricing

**Configuration → Domain Pricing**

Add each **TLD** you want to sell (`.com`, `.net`, `.org`, …) and set:

- **Register** price (per year)
- **Transfer** price
- **Renew** price

Customers can only order TLDs that have pricing set here.

## 2. Choose a registrar module

**Configuration → Registrars**

- **Enom** — automatic registration/renewal via the Enom API (enter your
  Enom credentials)
- **Manual** — you register domains yourself at your registrar and record them
  in PNLCS by hand

Pick **Manual** if you're just starting out or resell through a registrar that
isn't integrated yet — everything still bills correctly, you just do the
registration step manually.

## 3. How customers buy domains

- On the **public site**, a **domain search** lets visitors check availability
  and add a domain to their cart.
- During **hosting checkout**, customers can register or transfer a domain in
  the same order.

## Domain management for customers

From the client portal, customers can:

- View their domains, registration and expiry dates
- Update **nameservers**
- Toggle the **registrar lock**
- Retrieve the **EPP/auth code** (for transfers out)
- Toggle **auto-renew**

## Renewals

Like services, domains renew on a cycle. PNLCS generates a renewal invoice
ahead of the expiry date and (with auto-renew and a registrar module) can renew
automatically on payment.

!!! note
    Selling domains is optional. If you only sell hosting, you can skip this
    entire section.
