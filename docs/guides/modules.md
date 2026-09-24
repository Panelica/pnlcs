# Modules

PNLCS talks to the outside world through **modules**: server modules create
hosting accounts, payment gateways take money, registrars register domains, SSL
modules order certificates, and addons add features. They live in the
`modules/` folder.

## The Modules screen

**Setup → Modules** lists every installed module, grouped by type, each with:

- **Source**: *Built-in* (ships with PNLCS) or *Third-party* (added by you).
- **In use**: how many servers, products or records use it.
- **Switch on / Switch off**.
- **Configure**: opens the module type's own settings page.

Switching a module off stops it being **offered for new use**; whatever
already uses it keeps working:

| Type | Switched off means |
|---|---|
| Payment gateway | No longer offered at checkout |
| Registrar | No longer used by the domain search |
| Server | No longer offered when adding a server or a product |
| SSL | No longer offered for new certificate products |
| Addon | Deactivated |

A **server** or **SSL** module that a server or product still uses cannot be
switched off; the screen shows how many records use it.

## Where the settings are

Credentials and options stay on each type's own page:

| Type | Settings |
|---|---|
| Payment gateways | **Setup → Payment Gateways** ([guide](payment-gateways.md)) |
| Servers | **Setup → Servers**, per server ([guide](connect-a-server.md)) |
| Registrars | **Setup → Domain Registrars** ([guide](sell-domains.md)) |
| SSL | **Setup → SSL Modules** |
| Addons | the **Extensions** page in the settings sidebar |

## The built-in modules

| Type | Modules |
|---|---|
| Servers | Panelica, cPanel, Plesk, DirectAdmin, HestiaCP, Proxmox, Vultr, Custom |
| Payment gateways | Stripe, PayPal, Authorize.Net, Mollie, Razorpay, Tpay, iyzico, Bank Transfer |
| Registrars | Namecheap, ResellerClub, OpenProvider, Enom, HRD, DomainNameAPI, Manual |
| SSL | GoGetSSL |

## Adding a module

Drop the module's folder into `modules/` with its `pnlcs.json` manifest; it
appears on this screen, marked *Third-party*, on the next page load. Nothing
in PNLCS itself is edited, so updates leave it in place.

To write one: [Writing a module](../developer/modules.md).
