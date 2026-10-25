# Servers & Modules

Provisioning — automatically creating a hosting account when an order is paid —
is one of the most valuable things PNLCS does. This page explains how it works.

## Server

A **server** in PNLCS is a record describing a machine where customer accounts
live: its hostname, port, and API credentials. You add servers under
**Setup → Servers**.

## Module

A **module** is the code that knows how to talk to a particular control panel or
provider's API. PNLCS ships with these server modules:

| Module | What it provisions |
|--------|--------------------|
| **Panelica** | Accounts on a Panelica hosting panel |
| **cPanel** | WHM/cPanel accounts |
| **Plesk** | Plesk subscriptions |
| **DirectAdmin** | DirectAdmin users |
| **HestiaCP** | HestiaCP users |
| **Proxmox** | Proxmox VMs / LXC containers (VPS) |
| **Vultr** | Vultr cloud instances (VPS) |
| **Custom** | No automation — you create accounts by hand |

When you add a server you pick its **type** (which module handles it). A
**product** is then linked to a server, so PNLCS knows which module to call.

## Server groups

If you have several servers of the same type, put them in a **server group**.
A product can point at the group, and PNLCS picks a server from it — useful for
load spreading and failover.

## What the module does

For each service, the module can:

- **Create** the account (on order acceptance / payment)
- **Suspend** it (non-payment)
- **Unsuspend** it (payment received)
- **Terminate** it (cancellation)
- **Change password** and **change package** (upgrades/downgrades)
- **Report usage** (disk / bandwidth) back for overage billing

## Upgrades and downgrades

A customer moves a service to another package from its **Upgrade /
Downgrade** page. With the Panelica module, a package that gives less than the
account's plan does now can be chosen only when what the account has fits it:
apps, domains, email and FTP accounts, subdomains per domain and disk space,
counted the way Panelica counts them when something is created. A package that
does not fit is listed with what to remove first, and the move is refused,
through the API's `upgradeproduct` as well. When the account uses more memory
than a package allows, the customer is warned that their apps may be slowed
down or stopped there.

Panelica applies a plan's limits to what is created, not to what exists: a
smaller plan removes nothing, and the account's apps share the smaller plan's
CPU and memory. Staff can still change a service's product on its admin page.

## Reliable provisioning

PNLCS only marks a service **Active** *after* the module confirms the account
was created. If the module call fails (server down, bad credentials, quota
full), the service stays pending and the job is queued and **retried
automatically** — and you get an alert. Nothing silently shows "active" without
a real account behind it.

You can watch and retry failed jobs; see
[Provisioning failed](../troubleshooting/common-issues.md#provisioning-did-not-happen).

## Adding a server

Step-by-step: [Connect a Server](../guides/connect-a-server.md).

!!! tip "Which panel should I use?"
    PNLCS works with all the panels above. If you're still choosing,
    [Panelica](https://panelica.com) is a modern cPanel/Plesk alternative with
    built-in isolation (no CloudLinux) and universal migration.
