# Sell Panelica Licenses

The `panelicalicense` server module sells **Panelica control panel licenses** with your Panelica partner account. Your customer pays you, installs Panelica with an install command of their own, and the license is bought and activated by itself. Nobody copies a license key.

## How it works

1. The customer orders a Panelica license product and pays.
2. Their service page shows an install command at **your** billing site's address:
   `curl -sSL https://billing.example.com/setup/<reference> | bash`.
   The reference belongs to this order and nobody else. The command is also available to the welcome email as `{service_install_command}`.
3. They run it as root on a fresh server. It runs Panelica's official installer, unchanged, through your partner install link, and tells Panelica Central which order the server belongs to.
4. In the new panel's setup wizard they start the free trial. Central writes that trial to your partner account.
5. Within five minutes PNLCS finds the server by the order's reference and turns the trial into the plan the customer paid for, charged to your partner balance at your partner price. The service page then shows the license key, the plan, the expiry date and the server.
6. Each paid renewal invoice buys the next period at Central.

You pay Panelica only for licenses that are actually installed: an order nobody installs costs you nothing.

## Requirements

- An **active Panelica partner account** with a **partner API key** (`rk_…`) and enough **balance** for the licenses you sell. Both are in your Panelica partner portal.
- Your PNLCS site reachable from your customers' servers over HTTPS: the install command is served from it.

## Configure PNLCS

Everything is on one page, **Panelica Licenses**: **Panelica** in the admin area's top menu (staff who may manage products see it).

Not a Panelica partner yet? The page explains how selling works and starts with **Apply to become a partner**, which opens the partner application at Panelica. Panelica reviews the application and contacts you; once you are approved, create an API key in your partner portal and come back to connect it.

1. **Connect.** Paste the partner API key (`rk_…`) from your Panelica partner portal and press **Connect**. PNLCS asks Central first and keeps the key, encrypted, only when the partner account is active.
2. **Create the products.** The page lists Panelica's plans with **your cost** (your partner price) and Panelica's list price beside each. Enter your monthly and/or annual price and press **Create product**: the product goes into a **Panelica Licenses** group in your store, sells that plan, and is set up as soon as the first payment is received. Prices in your other currencies follow their exchange rates. The page suggests Panelica's list price and shows your margin in your own currency: at the rate of your USD currency when the site has one, otherwise at today's market rate (the providers of the automatic currency update, checked once a day; not while automatic rate updates are off).
3. Panelica sells monthly and annual periods only, so the products are priced for those two. A service billed for any other period is refused when it is created.
4. Optional: add `{service_install_command}` to the product's welcome email.

The same page shows:

- your **partner balance** (with **Top up balance**, which opens your partner portal) and how many licenses are sold, waiting for installation or need attention;
- **Your install link** (see below);
- **Servers installed with your link**, as Central knows them: host name and address, operating system, whether Central tied the server to you (with Central's note when it could not), its license (trial or paid), when it was installed, and the order of this site it belongs to;
- **Your licenses at Panelica**: every license of your partner account, sold from this site or not, with its customer, plan, state, server and expiry date, and the service here it belongs to;
- **Sold from this site**: the latest services of these products, with their customer, product, state, key, server and expiry date.

**Check installs now** looks for installed servers at once instead of within five minutes. Under **Partner account settings** you can use another key or disconnect: licenses already sold keep working at Panelica, but none is bought or renewed from PNLCS until you connect again.

There is nothing to add under **Setup → Servers**: nothing runs on a machine of yours.

### Your install link

Besides the command each customer gets for their order, the page gives you a general install command at your own address, to publish on your website:

`curl -sSL https://billing.example.com/setup | bash`

It runs your partner install link at Panelica, unchanged, without showing it. A server installed with it is yours at Panelica (its trial goes to your partner account) but belongs to no order: it shows under **Servers installed with your link**, and you can sell it a plan from your partner portal or tie its license to a service with **Attach license**.

Next to it is the link's **code** at Panelica (the part after `/install/`). Change it there and press **Change code**: the next install, from the general command and from every customer's command, uses the new code at once. Older codes keep working until you disable them in your partner portal. A code Panelica refuses (already taken, reserved, not 4 to 20 lowercase letters, digits and hyphens) is not changed, and the page says why.

### Switching the module off

Under **Setup → Modules** the module can be switched off while no product sells licenses. Switched off, the **Panelica** menu entry and the page are gone, every install command stops with a message, and nothing is bought or read from Central. Switch it on again to get everything back as it was.

## What the customer sees

The service page has a **Panelica license** section instead of server details:

- **Waiting for installation**: three steps, their install command with a copy button, **Check now**, and **Get a new install link**, which replaces the command if they shared it by mistake (the old one stops working at once).
- **Licensed**: the license key, plan, status, expiry date, the server it runs on and its last check-in.

Problems that are yours to solve (a short balance, a server Central could not tie to the order) are never shown to the customer in Central's words; they see that the team has been told.

## What you see

The staff service page has a **Panelica license** section: the license and its Central ID, the customer's command, when it was used, the server Central saw, and anything that needs attention. Buttons:

- **Check now** looks for the server immediately.
- **Get a new install link** (before the license is bought).
- **Retry renewal**, while a paid period is still waiting to be bought.
- **Attach license** ties a license that already exists in your partner account to the service; from then on the service renews, suspends and ends it.

Create and Change password are not offered once the install link is out: Create could only hand out the same link again, and a license has no password.

## Supported operations

| PNLCS | At Panelica Central |
|---|---|
| Create | Nothing is bought; the install link is handed out. The partner account is checked first, so a key Central refuses is retried rather than giving the customer a dead command. |
| The customer's server shows up | The trial is turned into the paid plan (`convert`), once. |
| Renewal invoice paid | One period is bought (`renew`), keyed by the paid period so it is never bought twice. A refusal (for example a short balance) goes to the retry queue and is bought when it next succeeds. |
| Suspend | The license is suspended. |
| Unsuspend | The license is reactivated. A renewal paid while suspended lifts the suspension first, then renews. |
| Terminate | The license is suspended and never renewed again; the install link stops working. |
| Upgrade / downgrade | Before the license is bought: it is bought on the new plan. After: the plan is changed at Central (`change-plan`); where Central does not offer that call, the change is refused and nothing changes. |

## Things to know

- **Your balance.** Licenses are bought from your partner balance. When it is too low, the purchase or renewal waits and staff are told once. Top it up and use **Check now** or **Retry renewal**, or let the next run do it.
- **The license period starts when the server is installed**, not when the order is paid, so it always runs a little past the paid date: the customer never loses days and you never pay for a period nobody uses.
- **One order, one server.** If the same command is run on two servers, the first one gets the license; the other stays a trial and shows up in your partner portal.
- **Trial refused.** The panel's trial needs an email address that has not had a Panelica trial before. If Central refuses it, staff see Central's reason on the service and can **Attach license** to one created in the partner portal.
