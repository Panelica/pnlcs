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

- An **approved Panelica partner account** with a **partner API key** (`rk_…`) and enough **balance** for the licenses you sell. Both are in your Panelica partner portal.
- Your PNLCS site reachable from your customers' servers over HTTPS: the install command is served from it.

## Configure PNLCS

1. In **Setup → Servers**, add a server of type **Panelica Licenses**: hostname `www.panelica.com`, port `443`, **Max accounts** `0`. Paste the partner API key into **Partner API key**; it is stored encrypted. Username and Password are not used. **Test Connection** passes when the key is accepted and the partner account is active.
2. Create a product of type **Other**, module **Panelica Licenses**, and choose the **Package**: the list shows Panelica's plans with your price for a month and a year beside each, so you can set your selling price with the margin in view.
3. Price the product **monthly** and/or **annually** only. Panelica sells no other period; a service billed quarterly, for example, is refused when it is created.
4. Set **Auto setup** to *As soon as the first payment is received* (the default). With *As soon as the order is placed* the customer gets the install command before paying; the license is still bought only once the order's invoice is paid.
5. Optional: add `{service_install_command}` to the product's welcome email.

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
